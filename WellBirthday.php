<?php
namespace Stanford\WellBirthday;

// Add trait
require_once "emLoggerTrait.php";
require_once "class.mail.php";

use ExternalModules\ExternalModules;
use REDCap;


class WellBirthday extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;


    /**
     * This is the cron task specified in the config.json
     */
    public function startCron() {
        $start_times = array("08:00");
        $delta_in_min = 90;

        $ready = $this->timeForCron(__FUNCTION__, $start_times, $delta_in_min);

        if ($ready) {
            // DO YOUR CRON TASK
            $this->emDebug("DoCron");

            $db_enabled = ExternalModules::getEnabledProjects($this->PREFIX);

            while ($proj = db_fetch_assoc($db_enabled)) {
                $pid = $proj['project_id'];
                $this->emDebug("Processing " . $pid);

                $birthday_accounts = REDCap::getData('array', null, array('id'
                                            ,'core_birthday_m'
                                            ,'core_birthday_d'
                                            ,'portal_firstname'
                                            ,'portal_lastname'
                                            ,'portal_email'
                                            ), null, null, false, true, false
                                            , '[core_birthday_m] = "' . Date("n") . '" and [core_birthday_d] = "' . Date("j") . '"'
                                            , true, true ); 

                foreach($birthday_accounts as $user){
                    $user               = array_shift($user);
                    $uid                = $user["id"];
                    $email              = $user["portal_email"];
                    $fname              = $user["portal_firstname"];
                    $lname              = $user["portal_lastname"];

                    
                    $hooks  = array(
                     "searchStrs" => array("#FIRSTNAME#"),
                     "subjectStrs" => array($fname)
                    );
                    emailReminder($fname, $uid, $hooks, $email,$module->getProjectSetting("email-body"),"WELL wishes you a happy birthday!");
                }
            }
        }
    }


    /**
     * Utility function for doing crons at a specified time
     * @param $cron_name        - name for logging
     * @param $start_times      - array of start times as in "08:00", "12:00"
     * @param $window_in_min    - number of minutes after start times to check if cron should be run (~3x cron freq)
     * @return bool             - returns true/false telling you if the cron should be done
     */
    public function timeForCron($cron_name, $start_times, $window_in_min) {
        // Get the current time (as a unix timestamp)
        $now_ts = time();

        // Name of key in external module settings for last-run timestamp
        $log_id = $cron_name . "_cron_last_run_ts";

        foreach ($start_times as $time_string) {
            // Convert our hour:minute value into a timestamp
            $dt = new \DateTime($time_string);
            $run_time_ts = $dt->getTimeStamp();

            // Calculate the number of minutes since the start_time
            $delta_min = ($now_ts-$run_time_ts) / 60;

            // Are we in the 'processing zone'?
            if ($delta_min >= 0 && $delta_min <= $window_in_min) {
                // Let's see if we have already run for this zone by storing that value in the em_settings table
                $last_cron_run_ts = $this->getSystemSetting($log_id);

                // If the start of this cron zone is less than our last $run_time_ts, then we should run the cron job
                if (empty($last_cron_run_ts) || $last_cron_run_ts < $run_time_ts) {

                    // Update our last_run timestamp
                    $this->setSystemSetting($log_id, $now_ts);

                    // Call our actual cronjob method
                    $this->emDebug("timeForCron TRUE");
                    return true;
                }
            }
        }
        $this->emDebug("timeForCron FALSE");
        return false;
    }

}

// Used by UserPie Email
function replaceDefaultHook($str) {
    global $default_hooks,$default_replace;
    return (str_replace($default_hooks,$default_replace,$str));
}

function emailReminder($fname,$uid,$hooks,$email,$email_template, $email_subject, $email_msg){
    $mail = new userPieMail();

    // Build the template - Optional, you can just use the sendMail function to message
    if(!is_null($email_template) && !$mail->newTemplateMsg($email_template,$hooks)) {
        print_r("error : building template");
     // logIt("Error building actition-reminder email template", "ERROR");
    } else {
     // Send the mail. Specify users email here and subject.
     // SendMail can have a third parementer for message if you do not wish to build a template.
     if(!is_null($email_msg) && !$mail->sendMail($email,$email_subject,$email_msg)) {
        print_r("error : sending email");
        // logIt("Error sending email: " . print_r($mail,true), "ERROR");
     } else {
        print_r("Email sent to $fname ($uid) @ $email");
        // Update email_act_sent_ts
     }
    }
}