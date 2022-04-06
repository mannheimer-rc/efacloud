<?php

/**
 * Container to hold the audit class. Shall be run by the cron jobs.
 */
class Tfyh_audit
{

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * Tfyh_socket to data base.
     */
    private $socket;

    /**
     * public Constructor. Constructing the Audit class will rn all standard audit tasks
     * 
     * @param Tfyh_toolbox $toolbox
     *            Common toolbox of application
     * @param Tfyh_socket $socket
     *            Common data base socket of application
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        // Header
        $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                 "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $audit_log = "Auditing '" . $toolbox->config->app_name . "' at '" . $actual_link .
                 "', version '" . file_get_contents("../public/version") . "'\n";
        
        // Check web server directory access settings
        $audit_log .= "Starting audit at: " . date("Y-m-d H:i:s") . "\n";
        $forbidden_dirs = explode(",", $toolbox->config->settings_tfyh["config"]["forbidden_dirs"]);
        $public_dirs = explode(",", $toolbox->config->settings_tfyh["config"]["public_dirs"]);
        $audit_warnings = "";
        
        // Lock access to forbidden directories
        $audit_log .= "Forbidden directories access check ...\n";
        $changed = 0;
        foreach ($forbidden_dirs as $forbidden_dir) {
            if (fileperms("../" . $forbidden_dir) != 0700) {
                $audit_log .= "    file permissons for " . $forbidden_dir . ": " .
                         $this->permissions_string(fileperms("../" . $forbidden_dir)) . ".\n";
                chmod("../" . $forbidden_dir, 0700);
            }
            $htaccess_filename = "../" . $forbidden_dir . "/.htaccess";
            if (! file_exists($htaccess_filename)) {
                $changed ++;
                file_put_contents($htaccess_filename, "deny for all");
                $audit_warnings = "    Missing " . $htaccess_filename . " added.\n";
            }
        }
        if ($changed == 0)
            $audit_log .= ".htaccess files ok.\n";
        else
            $audit_log .= $changed . " .htaccess files added.\n";
        
        // Open access to publicly available directories
        $audit_log .= "Publicly available directories access check ...\n";
        $changed = 0;
        foreach ($public_dirs as $public_dir) {
            if ((fileperms("../" . $public_dir) % 0755) != 0) {
                $audit_log .= "    file permissons for " . $public_dir . ": " .
                         $this->permissions_string(fileperms("../" . $public_dir)) . ".\n";
                chmod("../" . $public_dir, 0755);
            }
            $htaccess_filename = "../" . $public_dir . "/.htaccess";
            if (file_exists($htaccess_filename)) {
                $changed ++;
                unlink($htaccess_filename);
                $audit_warnings = "    Extra " . $htaccess_filename . " removed.\n";
            }
        }
        if ($changed == 0)
            $audit_log .= ".htaccess files ok.\n";
        else
            $audit_log .= $changed . " .htaccess files removed.\n";
        
        // reflect settings for support cases
        $audit_log .= "Framework configuration check ...\n";
        foreach ($toolbox->config->settings_tfyh as $module => $settings) {
            $audit_log .= $module . ":\n";
            foreach ($toolbox->config->settings_tfyh[$module] as $key => $value) {
                if (is_bool($toolbox->config->settings_tfyh[$module][$key]) ||
                         is_array($toolbox->config->settings_tfyh[$module][$key]))
                    $value = json_encode($value);
                $audit_log .= "    " . $key . " = " . $value . "\n";
            }
        }
        // Add configuration information for support cases
        $audit_log .= "Configuration:\n";
        $cfg = $toolbox->config->get_cfg();
        foreach ($cfg as $key => $value) {
            if ((strcasecmp($key, "db_up") == 0) || (strcasecmp($key, "db_user") == 0))
                $audit_log .= "    " . $key . " = " . strlen($value) . " characters long.\n";
            else
                $audit_log .= "    " . $key . " = " . json_encode($value) . "\n";
        }
        
        // check table sizes
        $audit_log .= "Table configuration check ...\n";
        $table_names = $socket->get_table_names();
        $table_record_count_list = "";
        $total_record_count = 0;
        $total_columns_count = 0;
        $total_table_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn);
            $columns = $socket->get_column_names($tn);
            $columns_count = ($columns === false) ? 0 : count($columns);
            $total_record_count += $record_count;
            $total_table_count ++;
            $total_columns_count += $columns_count;
            $history = "";
            if (isset($toolbox->config->settings_tfyh["history"][$tn])) {
                $history = ", hist:" . $toolbox->config->settings_tfyh["history"][$tn] . "." .
                         $toolbox->config->settings_tfyh["maxversions"][$tn];
                if (! in_array($toolbox->config->settings_tfyh["history"][$tn], $columns)) {
                    $warning_message = "    Missing history column `" .
                             $toolbox->config->settings_tfyh["history"][$tn] . "` in table `" . $tn .
                             "`. Data base insert and update statements will fail. Please fix configuration.\n";
                    $audit_log .= $warning_message;
                    $audit_warnings = $warning_message;
                }
            }
            $table_record_count_list .= "    " . $tn . " [" . $record_count . "*" . $columns_count . $history .
                     "], \n";
        }
        $table_record_count_list .= "in total [" . $total_record_count . "*" . $total_columns_count .
                 "] records * columns in " . $total_table_count . " tables.";
        $audit_log .= $table_record_count_list . "\n";
        
        // Check users and access rights
        $audit_log .= "Users and access rights check ... \n";
        $audit_log .= str_replace("Count of", "    Count of", 
                $toolbox->users->get_all_accesses($socket, true));
        
        // Check backup
        $audit_log .= "\nBackup check... \n";
        $backup_dir = "../log/backup";
        $backup_files = scandir($backup_dir);
        $backup_files_size = 0;
        $backup_files_count = 0;
        foreach ($backup_files as $backup_file) {
            if (strcasecmp(substr($backup_file, 0, 1), ".") != 0) {
                $backup_files_size += filesize($backup_dir . "/" . $backup_file);
                $backup_files_count ++;
            }
        }
        $audit_log .= "    " . $backup_files_count . " backup files with a total size of " .
                 (intval($backup_files_size / 1024 / 102) / 10) . " MByte\n";
        
        // Finish
        $audit_log .= "Audit completed.\n";
        file_put_contents("../log/app_audit.log", $audit_log);
        if (strlen($audit_warnings) > 0)
            file_put_contents("../log/audit.warnings", $audit_warnings);
        elseif (file_exists("../log/audit.warnings"))
            unlink("../log/audit.warnings");
    }

    /**
     * Provide a readable String for the file permissions, see:
     * https://www.php.net/manual/de/function.fileperms.php
     * 
     * @param int $perms            
     * @return string
     */
    private function permissions_string (int $perms)
    {
        switch ($perms & 0xF000) {
            case 0xC000: // Tfyh_socket
                $info = 's';
                break;
            case 0xA000: // Symbolischer Link
                $info = 'l';
                break;
            case 0x8000: // Regulär
                $info = 'r';
                break;
            case 0x6000: // Block special
                $info = 'b';
                break;
            case 0x4000: // Verzeichnis
                $info = 'd';
                break;
            case 0x2000: // Character special
                $info = 'c';
                break;
            case 0x1000: // FIFO pipe
                $info = 'p';
                break;
            default: // unbekannt
                $info = 'u';
        }
        
        // Besitzer
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
        
        // Gruppe
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
        
        // Andere
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
        
        return $info;
    }
}
