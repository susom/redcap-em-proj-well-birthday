<?php
namespace Stanford\WellBirthday;

// Load trait
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
        $cron_freq = 3600;

        $this->emDebug("startCron", func_get_args());

        if ($this->timeForCron(__FUNCTION__, $start_times, $cron_freq)) {
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
     * @param $cron_name        - name for recording last run timestamp
     * @param $start_times      - array of start times as in "08:00", "12:00"
     * @param $cron_freq        - cron_frequency in seconds
     * @return bool             - returns true/false telling you if the cron should be done
     */
    public function timeForCron($cron_name, $start_times, $cron_freq) {
        // Name of key in external module settings for last-run timestamp
        $cron_status_key = $cron_name . "_cron_last_run_ts";

        // Get the current time (as a unix timestamp)
        $now_ts = time();

        foreach ($start_times as $start_time) {
            // Convert our hour:minute value into a timestamp
            $dt = new \DateTime($start_time);
            $start_time_ts = $dt->getTimeStamp();

            // Calculate the number of minutes since the start_time
            $delta_min = ($now_ts-$start_time_ts) / 60;
            $cron_freq_min = $cron_freq/60;

            // To reduce database overhead, we will only check to see if we should run if we are between 0-2x the cron frequency
            if ($delta_min >= 0 && $delta_min <= $cron_freq_min) {

                // Let's see if we have already run this cron by looking up the last-run value
                $last_cron_run_ts = $this->getSystemSetting($cron_status_key);

                // If the start of this cron zone is less than our last $start_time_ts, then we should run the cron job
                if (empty($last_cron_run_ts) || $last_cron_run_ts < $start_time_ts) {

                    // Update our last_run timestamp
                    $this->setSystemSetting($cron_status_key, $now_ts);

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