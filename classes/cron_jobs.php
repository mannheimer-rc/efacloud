<?php

/**
 * static class container file for a daily jobs routine. It may be triggered by whatever, checks whther it was
 * already run this day and if not, starts the sequence.
 */
include_once '../classes/tfyh_cron_jobs.php';

class Cron_jobs extends Tfyh_cron_jobs
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
     */
    public static function run_daily_jobs (Tfyh_toolbox $toolbox, Tfyh_socket $socket, int $app_user_id)
    {
        $cron_started = time();
        $daily_run = Tfyh_cron_jobs::run_daily_jobs($toolbox, $socket, $app_user_id);
        
        // add application specific cron jobs here.
        // The sequence is an implicit priority, in case one of the jobs fails.
        if ($daily_run) {
            
            $cronlog = "../log/sys_cronjobs.log";
            file_put_contents($cronlog, date("Y-m-d H:i:s") . " +0: specific efaCloud cronjobs started.\n", 
                    FILE_APPEND);
            $last_step_ended = time();
            
            // Run the configured cron jobs as personal logbook or monitoring report.
            self::run_configured_jobs($toolbox, $socket, $app_user_id);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Configured jobs completed.\n", 
                    FILE_APPEND);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . ": Cron jobs done. Total cron jobs duration = " .
                             (time() - $cron_started) . ".\n", FILE_APPEND);
            
            // add missing ecrids (just all. There must not be many left in April 2022.)
            include_once "../classes/efa_tables.php";
            $efa_tables = new Efa_tables($toolbox, $socket);
            include_once '../classes/efa_tools.php';
            $efa_tools = new Efa_tools($efa_tables, $toolbox);
            $added_ecrids = $efa_tools->add_ecrids(10000);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Added " . $added_ecrids .
                             " missing efaCloud record Ids.\n", FILE_APPEND);
            $last_step_ended = time();
            
            // Add missing values in helper data fields which are build of multiple direct fields.
            $efa_tools->add_AllCrewIds($app_user_id);
            $efa_tools->add_FirstLastName($app_user_id);
            
            // create usage statistics
            include_once "../classes/tfyh_statistics.php";
            $tfyh_statistics = new Tfyh_statistics();
            file_put_contents("../log/efacloud_server_statistics.csv", 
                    $tfyh_statistics->pivot_timestamps(86400, 14));
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Statistics created.\n", 
                    FILE_APPEND);
            $last_step_ended = time();
            
            // run archive and delete procedures
            include_once "../classes/efa_archive.php";
            $efa_archive = new Efa_archive($efa_tables, $toolbox, $app_user_id);
            $archive_info = $efa_archive->records_to_archive();
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Archived: " . $archive_info .
                             ".\n", FILE_APPEND);
            $last_step_ended = time();
            $purge_info = $efa_archive->purge_outdated_deleted();
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Purged: " . $purge_info .
                             ".\n", FILE_APPEND);
            $last_step_ended = time();
            
            // manage deleted records (TODO: this may be temporary, introduced April 2022).
            $efa_tools->cleanse_deleted($app_user_id);
            $efa_tools->remove_old_cleansed_records(30);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) . ": Cleansing completed.\n", 
                    FILE_APPEND);
            $last_step_ended = time();

            // audit tables
            include_once "../classes/efa_audit.php";
            $efa_audit = new Efa_audit($efa_tables, $toolbox);
            $audit_report = $efa_audit->data_integrity_audit(false, false, false);
            file_put_contents("../log/app_db_audit.html", $audit_report);
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                             ": Database integrity audit completed.\n", FILE_APPEND);
            $last_step_ended = time();
        }
    }

    /**
     * Returns true, if a task is due today based on the $task_day specification: starts with a letter (D =
     * Daily, W = Weekly, M = Monthly), continues with a number (for D : 1 always, for W : 1 = Monday, 2 =
     * Tuesday asf., for M : day of month, 31 is the same as ultimo).
     * 
     * @param String $task_day            
     */
    private static function due_today (String $task_day)
    {
        $period = substr($task_day, 0, 1);
        $day = intval(substr($task_day, 1));
        // daily run
        if (strcasecmp($period, "D") == 0)
            return true;
        // weekly run
        if ((strcasecmp($period, "W") == 0) && ($day == intval(date("w"))))
            return true;
        // monthly run, any day
        if ((strcasecmp($period, "M") == 0) && ($day == intval(date("j"))))
            return true;
        // monthly run, ultimo. 86400 seconds are 1 day
        if ((strcasecmp($period, "M") == 0) && ($day == 31) && (intval(date("d", time() + 86400)) == 1))
            return true;
    }

    /**
     * Jobs can be configured to be run together with the cron jobs trigger, which should be called on a daily
     * basis. A job consists of a scheduled day (see Tfyh_tasks->due_today for details) and a task type. Task
     * types are: persLogbook = send a personal logbook extract to all valid efa2persons who have an Email
     * address provided.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     */
    private static function run_configured_jobs (Tfyh_toolbox $toolbox, Tfyh_socket $socket, int $app_user_id)
    {
        // get the job list
        $cfg = $toolbox->config->get_cfg();
        if (! isset($cfg["configured_jobs"]) || (strlen($cfg["configured_jobs"]) < 4))
            return;
        
        // decode the jobs
        $configured_jobs = explode("\n", $cfg["configured_jobs"]);
        
        $cronlog = "../log/sys_cronjobs.log";
        $cron_started = time();
        $last_step_ended = $cron_started;
        file_put_contents($cronlog, date("Y-m-d H:i:s") . " +0: configured efaCloud cronjobs started.\n", 
                FILE_APPEND);
        
        // run the jobs
        foreach ($configured_jobs as $configured_job) {
            $configured_job_parts = explode(" ", trim($configured_job));
            file_put_contents($cronlog, 
                    date("Y-m-d H:i:s") . " +0: checking " . trim($configured_job) . ".\n", FILE_APPEND);
            $due_today = Cron_jobs::due_today($configured_job_parts[0]);
            $type = $configured_job_parts[1];
            if ($due_today) {
                
                if (strcasecmp($type, "persLogbook") == 0) {
                    include_once '../classes/efa_dataedit.php';
                    $efa_dataedit = new Efa_dataedit($toolbox, $socket);
                    include_once '../classes/efa_logbook.php';
                    $efa_logbook = new Efa_logbook($toolbox, $socket, $efa_dataedit);
                    $mails_sent = $efa_logbook->send_logbooks();
                    $toolbox->logger->log(0, $app_user_id, 
                            "Persönliches Fahrtenbuch gesendet an " . $mails_sent . " Personen.");
                    file_put_contents($cronlog, 
                            date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                     ": Personal logbook sent to " . $mails_sent . " recipients.\n", 
                                    FILE_APPEND);
                    $last_step_ended = time();
                } elseif (strcasecmp($type, "monitoring") == 0) {
                    include_once '../classes/efa_tables.php';
                    $efa_tables = new Efa_tables($toolbox, $socket);
                    include_once '../classes/efa_tools.php';
                    $efa_tools = new Efa_tools($efa_tables, $toolbox);
                    $app_status_summary = $efa_tools->create_app_status_summary($toolbox, $socket);
                    $statistics_filename = "../log/efacloud_server_statistics.csv";
                    
                    // create report as zip of log files.
                    $monitoring_report = "../log/" . $toolbox->logger->zip_logs();
                    $admins = $socket->find_records("efaCloudUsers", "Rolle", "admin", 30);
                    include_once '../classes/tfyh_mail_handler.php';
                    $cfg = $toolbox->config->get_cfg();
                    $mail_handler = new Tfyh_mail_handler($cfg);
                    $mails_sent = 0;
                    foreach ($admins as $admin) {
                        $mailfrom = $mail_handler->system_mail_sender;
                        $mailto = $admin["EMail"];
                        $mailsubject = "[" . $cfg["acronym"] . "] Regelbericht efaCloud Überwachung";
                        $mailbody = "<html><body>" . $app_status_summary . $cfg["mail_subscript"] .
                                 $cfg["mail_footer"];
                        $success = $mail_handler->send_mail($mailfrom, $mailfrom, $mailto, "", "", 
                                $mailsubject, $mailbody, $statistics_filename, $monitoring_report);
                        if ($success)
                            $mails_sent ++;
                    }
                    
                    include_once '../classes/tfyh_logger.php';
                    $toolbox->logger->log(0, $app_user_id, 
                            "Regelbericht efaCloud Überwachung gesendet an " . $mails_sent . " Personen.");
                    unlink($monitoring_report);
                    file_put_contents($cronlog, 
                            date("Y-m-d H:i:s") . " +" . (time() - $last_step_ended) .
                                     ": Monitoring report sent to " . $mails_sent . " recipients.\n", 
                                    FILE_APPEND);
                    $last_step_ended = time();
                }
            }
        }
    }
}
