<?php

/**
 * static class container file for a daily jobs routine. It may be triggered by whatever, checks whther it was
 * already run this day and if not, starts the sequence.
 */
class Tfyh_cron_jobs
{

    /**
     * run all daily jobs.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     * @param int $app_user_id
     *            the id of the invoking user.
     * @return true, if jobs were executed, false, if not (because they were already executed totday)
     */
    public static function run_daily_jobs (Tfyh_toolbox $toolbox, Tfyh_socket $socket, int $app_user_id)
    {
        
        // Check whether a day went by.
        $time_last_run = (file_exists("../log/cronjobs_last_day")) ? file_get_contents(
                "../log/cronjobs_last_day") : 0;
        $today = date("Y-m-d");
        if (strcmp($time_last_run, $today) == 0)
            return false;
            
        // TODO temporarily inserted, remove later (insert @ 22.3.22)
        $migrate = ["dones.txt" => "app_info.log","warns.txt" => "app_warnings.log",
                "fails.txt" => "app_errors.log","timestamps.log" => "sys_timestamps.log"
        ];
        foreach ($migrate as $src => $dest)
            rename("../log/" . $src, "../log/" . $dest);
        // TODO temporarily inserted, remove later (insert @ 22.3.22)
        
        $cronlog = "../log/sys_cronjobs.log";
        $cron_started = time();
        $last_step_ended = $cron_started;
        file_put_contents($cronlog, date("Y-m-d H:i:s") . " +0: Cronjobs started (time last run: $time_last_run)\n", FILE_APPEND);
        
        // remove obsolete files in log directory from previous program versions or debug runs
        $toolbox->logger->remove_obsolete();
        $toolbox->logger->rotate_logs();
        $toolbox->logger->collect_and_cleanse_init_login_error_log();
        file_put_contents("../log/app_init_login_error.csv", $toolbox->logger->get_activities_csv(14));
        file_put_contents($cronlog,
                date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Log rotation and analysis completed\n", FILE_APPEND);
        $last_step_ended = time();
        
        // refresh timer as first action, to avoid duplicate triggering by
        // different users.
        file_put_contents("../log/cronjobs_last_day", $today);
        $toolbox->logger->log(0, $app_user_id, "Starting daily jobs.");
        
        // run audit
        include_once "../classes/tfyh_audit.php";
        $audit = new Tfyh_audit($toolbox, $socket);
        file_put_contents($cronlog, 
                date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Audit completed\n", FILE_APPEND);
        $last_step_ended = time();
        
        // run a backup.
        if (isset($toolbox->config->settings_tfyh["config"]["backup"]) &&
                 (strcasecmp($toolbox->config->settings_tfyh["config"]["backup"], "on") == 0)) {
            include_once ("../classes/tfyh_backup_handler.php");
            $backup_handler = new Tfyh_backup_handler("../log/", $toolbox, $socket);
            $backup_handler->backup();
            $toolbox->logger->log(0, $app_user_id, "Backup completed.");
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Backup completed\n", 
                    FILE_APPEND);
            $last_step_ended = time();
        } else
            $toolbox->logger->log(0, $app_user_id, "Backup skipped by configuration.");
        
        // Cleansing the change log and activity logs first
        $socket->cleanse_change_log(100);
        $toolbox->logger->list_and_cleanse_entries(0, 100, true);
        $toolbox->logger->list_and_cleanse_entries(1, 100, true);
        $toolbox->logger->list_and_cleanse_entries(2, 100, true);
        file_put_contents($cronlog, 
                date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                         ": Change and app info|warnings|failures log entries cleansing completed\n", 
                        FILE_APPEND);
        $last_step_ended = time();
        
        // cleanse the page hit timestamps (inits, errors).
        $toolbox->logger->collect_and_cleanse_init_login_error_log();
        $toolbox->logger->log(0, $app_user_id, "Init, login and error statistics collected.");
        file_put_contents($cronlog, 
                date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                         ": Init, login and error statistics collected (last step).\n", FILE_APPEND);
        $last_step_ended = time();
        
        $toolbox->logger->log(0, $app_user_id, "Daily jobs completed.");
        return true;
    }
}
