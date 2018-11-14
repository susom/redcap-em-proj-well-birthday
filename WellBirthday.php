<?php
namespace Stanford\WellBirthday;

// Load trait
require_once "emLoggerTrait.php";

use ExternalModules\ExternalModules;
use REDCap;
use Message;


class WellBirthday extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    /**
     * This is the cron task specified in the config.json
     */
    public function startCron() {
        $this->emDebug("Cron Args",func_get_args());

        $start_times = array("10:00");
        $run_days    = array("mon","tue","wed","thu","fri","sat","sun");
        $cron_freq   = 300;

        $this->emDebug("Starting Cron : Check if its in the right time range");
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        echo "here is the useragent : $user_agent<br><br>";

        if ($this->timeForCron(__FUNCTION__, $start_times, $cron_freq, $run_days) || strpos($user_agent,"Chrome") > -1) {
            // DO YOUR CRON TASK
            $this->emDebug("DoCron");

            $db_enabled = ExternalModules::getEnabledProjects($this->PREFIX);

            echo "<pre>";

            $sent_emails = array();            
            while ($proj = db_fetch_assoc($db_enabled)) {
                $pid = $proj_id = $project_id = $proj['project_id'];
                $this->emDebug("Processing " . $pid);

                $birthday_accounts = REDCap::getData($pid,'array', null, array('id'
                                            ,'core_birthday_m'
                                            ,'core_birthday_d'
                                            ,'portal_firstname'
                                            ,'portal_lastname'
                                            ,'portal_email'
                                            ), null, null, false, true, false
                                            , '[core_birthday_m] = "' . Date("n") . '" and [core_birthday_d] = "' . Date("j") . '"'
                                            , true, true ); 
                $this->emDebug("Gathering (".count($birthday_accounts).") records that match criteria");
                
                print_r("Gathering (".count($birthday_accounts).") records that match criteria for project $pid<br><br>");

                $birthday_emails_msg    = array();
                foreach($birthday_accounts as $user){
                    $user               = array_shift($user);
                    $uid                = $user["id"];
                    $email              = $user["portal_email"];
                    $fname              = $user["portal_firstname"];
                    $lname              = $user["portal_lastname"];
                    
                    if(!in_array($email,$sent_emails)){
                        $sent_emails[] = $email;
                        $hooks  = array(
                         "searchStrs" => array("#FIRSTNAME#","\r","\n"),
                         "subjectStrs" => array($fname,"<br>","<br>")
                        );

                        $this->emDebug("Sending a birthday email to $fname");

                        $body = file_get_contents($this->getUrl("action.php",true,true) . "&pid=$pid&NOAUTH&email-body");

                        $email_msg = str_replace($hooks["searchStrs"],$hooks["subjectStrs"],$body);
                        emailReminder($fname, $email, $email_msg ,"WELL wishes you a happy birthday!");
                        $birthday_emails_msg[] = "Birthday email sent to $fname ($email)<br>";
                    }
                }

                $allemails = implode("\r",$birthday_emails_msg);
                emailReminder("Julia Gustafson", "julia.gustafson@stanford.edu", $allemails,  count($birthday_emails_msg). " daily birthday emails sent");
                emailReminder("Irvin Szeto", "irvins@stanford.edu", $allemails,  count($birthday_emails_msg). " daily birthday emails sent");

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
    public function timeForCron($cron_name, $start_times, $cron_freq, $run_days) {
        // Name of key in external module settings for last-run timestamp
        $cron_status_key = $cron_name . "_cron_last_run_ts";

        // Get the current time (as a unix timestamp)
        $now_ts = time();
        $day    = strtolower(Date("D"));
        
        $this->emDebug("The days is " . $day);

        if(array_search($day,$run_days) !== false){
            $this->emDebug("the correct day : " . $day);
            foreach ($start_times as $start_time) {
                // Convert our hour:minute value into a timestamp
                $dt = new \DateTime($start_time);
                $start_time_ts = $dt->getTimeStamp();

                // Calculate the number of minutes since the start_time
                $delta_min = ($now_ts-$start_time_ts) / 60;
                $cron_freq_min = $cron_freq/60;

                $this->emDebug("now : $now_ts , deltamin : $delta_min, cronfreqmin : $cron_freq_min" );
                // To reduce database overhead, we will only check to see if we should run if we are between 0-2x the cron frequency
                if ($delta_min >= 0 && $delta_min <= $cron_freq_min) {

                    // Let's see if we have already run this cron by looking up the last-run value
                    $last_cron_run_ts = $this->getSystemSetting($cron_status_key);

                    $this->emDebug("delta time OK, last run $last_cron_run_ts");

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
        }
        $this->emDebug("timeForCron FALSE");
        return false;
    }

}


function emailReminder($fname, $email, $email_msg, $subject){
    $msg = new Message();

    $msg->setTo($email);

    // From Email:
    $from_name  = "Stanford Medicine WELL for Life";
    $from_email = "wellforlife@stanford.edu";
    $msg->setFrom($from_email);
    $msg->setFromName($from_name);
    $msg->setSubject($subject);
    $msg->setBody($email_msg);

    $result = $msg->send();

    if ($result) {
        REDCap::logEvent("Birthday Email sent to $email");
    }
}