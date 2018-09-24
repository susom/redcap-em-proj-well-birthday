<?php
namespace Stanford\WellBirthday;

// Add trait
require_once "emLoggerTrait.php";

class WellBirthday extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;


    /**
     * This is the cron task specified in the config.json
     */
    public function startCron() {
        $start_times = array("08:00", "15:30");
        $delta_in_min = 3;

        $ready = $this->timeForCron(__FUNCTION__, $start_times, $delta_in_min);

        if ($ready) {
            // DO YOUR CRON TASK
            $this->emDebug("DoCron");
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