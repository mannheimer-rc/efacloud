<?php

/**
 * class file for the efaCloud table auditing
 */
class Efa_archive
{

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * The efa tables function set.
     */
    private $efa_tables;

    /**
     * The afCloudUserID of the archiving user.
     */
    private $app_user_id;

    /**
     * The prefix identifying an archived record.
     */
    public static $archive_id_prefix = "archiveID:";

    /**
     * the maximum age in days corresponding to 2147483648 seconds to avoid an overflow error (~68 years)
     */
    public static $max_age_days = 24855;

    /**
     * The archive settings. ITS SEQUENCE MUST BE THE SAME AS FOR THE LISTS IN '../config/lists/efaArchive'.
     * This is also the complete list of tables for which records will be archived at all.
     */
    public $archive_settings = [
            "efa2boatdamages" => ["ListParam" => "DamageAgeDays","MinAgeDays" => 24855,
                    "Origin" => "delete","archiveID_at" => "Notes"
            ],
            "efa2boatreservations" => ["ListParam" => "ReservationAgeDays","MinAgeDays" => 24855,
                    "Origin" => "delete","archiveID_at" => "Reason"
            ],
            "efa2clubwork" => ["ListParam" => "ClubworkAgeDays","MinAgeDays" => 24855,
                    "Origin" => "delete","archiveID_at" => "Description"
            ],
            "efa2logbook" => ["ListParam" => "SessionAgeDays","MinAgeDays" => 24855,"Origin" => "delete",
                    "archiveID_at" => "Comments"
            ],
            "efa2messages" => ["ListParam" => "MessageAgeDays","MinAgeDays" => 24855,"Origin" => "delete",
                    "archiveID_at" => "Subject"
            ],
            "efa2persons" => ["ListParam" => "PersonsAgeDays","MinAgeDays" => 24855,"Origin" => "update",
                    "archiveID_at" => "LastName"
            ]
    ];

    /**
     * Maximm number of records to be archived for a table in one go, for performance reasons
     */
    private $max_count_archived = 250;

    /**
     * The list of tables in which records which are marked as deleted shall be finally purge. Note: they must
     * be kept to inform all clients of their deletion. Once they are purged, a client will no more be
     * notified of this deletion.
     */
    private $tables_to_purge_deleted = ["efa2autoincrement","efa2boatdamages","efa2boatreservations",
            "efa2boats","efa2boatstatus","efa2clubwork","efa2crews","efa2destinations","efa2fahrtenabzeichen",
            "efa2groups","efa2logbook","efa2messages","efa2persons","efa2sessiongroups","efa2statistics",
            "efa2status","efa2waters"
    ];

    /**
     * public Constructor.
     * 
     * @param int $appUserID
     *            the ID of the application user of the user who performs the statement. For change logging.
     */
    public function __construct (Efa_tables $efa_tables, Tfyh_toolbox $toolbox, int $appUserID)
    {
        $this->efa_tables = $efa_tables;
        $this->socket = $efa_tables->socket;
        $this->toolbox = $toolbox;
        $this->app_user_id = $appUserID;
        // read age parameters configuration
        include_once "../classes/tfyh_list.php";
        $id = 1;
        $cfg = $toolbox->config->get_cfg();
        foreach ($this->archive_settings as $for_table => $archive_setting) {
            // the
            $archive_min_age_days = (! isset($cfg[$archive_setting["ListParam"]])) ? self::$max_age_days : ((intval(
                    $cfg[$archive_setting["ListParam"]]) > 180) ? intval($cfg[$archive_setting["ListParam"]]) : 180);
            $this->archive_settings[$for_table]["MinAgeDays"] = $archive_min_age_days;
            $archive_list = new Tfyh_list("../config/lists/efaArchive", $id, "", $efa_tables->socket, $toolbox);
            if (strcmp($archive_list->get_table_name(), $for_table) != 0) {
                echo "Die Reihenfolge der archive-Listen in ../config/lists/efaArchive muss den Tabellen in den Efa_archive::\$archive_settings entsprechen. " .
                         "Fehler bei: $for_table. ABBRUCH.";
                exit();
            }
            $id ++;
        }
    }

    /**
     * Convert the efa-validity value (millis, forever = Long.MAX_VALUE) into a 32 bit integer (seconds,
     * forever = Imteger.MAX_VALUE)
     * 
     * @param String $validity
     *            the efa-validity value
     * @return number the resulting 32 bit integer
     */
    private function value32_validity (String $validity)
    {
        if (strlen($validity) > $this->efa_tables->forever_len_gt)
            return $this->efa_tables->forever32; // 32 bit maximum number
        if (strlen($validity) > 3)
            return intval(substr($validity, 0, strlen($validity) - 3));
        return 0;
    }

    /**
     * Copy a record as json encoded String except its history to the archive. This will not change the record
     * itself.
     * 
     * @param String $tablename
     *            the name of the table to be used.
     * @param array $record
     *            a named array with key = column name and value = values to be inserted. Values must be PHP
     *            native encoded Strings. Enclosed quotes "'" will be appropriately escaped for the SQL
     *            command.
     * @return the ID (integer) of the copy in the archive table, or an error String, if the insterion failed.
     */
    private function copy_to_archive (String $tablename, array $record)
    {
        // remove record history
        if (isset($record["ecrhis"]) || is_null($record["ecrhis"]))
            unset($record["ecrhis"]);
        $archive_entry = json_encode($record);
        // limit size to 64k
        $cut_len = 65535 - 4096;
        while (strlen($archive_entry) > 65535) {
            foreach ($record as $key => $value)
                if (strlen($value) > $cut_len)
                    $record[$key] = substr(strval($record[$key]), 0, $cut_len);
            $archive_entry = json_encode($record);
            $cut_len = $cut_len - 4096;
        }
        $archive_record = ["Time" => date("Y-m-d H:i:s"),"Table" => $tablename,"Record" => $archive_entry
        ];
        // === test code
        // file_put_contents("../log/tmp", date("Y-m-d H:i:s") . " - " . $tablename . ": Would copy to
        // archive: " . $archive_entry . "\n", FILE_APPEND);
        // $archive_ID = 999999;
        // === test code
        $archive_ID = $this->socket->insert_into($this->app_user_id, "efaCloudArchived", $archive_record);
        return $archive_ID;
    }

    /**
     * Move a record to the archive based on the provided list element. The record is retreived, than copied
     * to the archive table, then emptied and marked as deleted or updated, depending on whether a minimum
     * record stub shall be kept for referential integrity and finally written to the data base. Any error
     * will be logged.
     * 
     * @param array $named_row_to_move
     *            the named list row indicaion the record that shall be archived. Must contain the ecrid for
     *            reference.
     * @param String $table_name
     *            the table it belongs to
     */
    private function move_to_archive (array $named_row_to_move, String $table_name)
    {
        if (! isset($named_row_to_move["ecrid"]) || (strlen($named_row_to_move["ecrid"]) < 5)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to archive Id: " . $named_row_to_move[0] . " for " . $table_name .
                             ". No ecrid available.");
            return false;
        }
        $ecrid = $named_row_to_move["ecrid"];
        $modification = $this->archive_settings["$table_name"]["Origin"];
        
        // retrieve the full record
        $full_record_to_move = $this->socket->find_record($table_name, "ecrid", $ecrid);
        if ($full_record_to_move === false) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to archive Id: " . $named_row_to_move[0] . " for " . $table_name .
                             ". No matching record found for ecrid " . $ecrid);
            return false;
        }
        
        // copy the record to the archive.
        $archive_id = $this->copy_to_archive($table_name, $full_record_to_move);
        if (! is_numeric($archive_id)) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to copy Id: " . $named_row_to_move[0] . " for " . $table_name .
                             " to archive. Reason: ") . $archive_id;
            return false;
        }
        
        // create the stub
        $nominal_stub = $this->create_archive_stub($table_name, $archive_id, $full_record_to_move, time(), 
                $full_record_to_move["ChangeCount"]);
        if (in_array("ecrhis", Efa_tables::$server_gen_fields[$table_name]))
            $nominal_stub["ecrhis"] = "REMOVE!";
        // === test code
        // file_put_contents("../log/tmp",
        // $table_name . ": Would update record with: " . json_encode($emptied_record) . "\n",
        // FILE_APPEND);
        // $update_result = "";
        // === test code
        $update_result = $this->socket->update_record_matched($this->app_user_id, $table_name, 
                ["ecrid" => $ecrid
                ], $nominal_stub);
        if (strlen($update_result) > 0) {
            $this->toolbox->logger->log(2, $this->app_user_id, 
                    "Failed to empty or delete record after archiving: " . $named_row_to_move . " for " .
                             $table_name . ". Error: " . $update_result);
            return false;
        }
        return true;
    }

    /**
     * Get all records archived for the object which is archived in $archive_record.
     * 
     * @param array $archive_record
     *            the archive record as stored in the efaCloudArchived table, containing the record to decode
     *            in the $archive_record["Record"] field
     * @return array of archive records which belong to the archived object, sorted youngest first.
     *         Associative with $key = InvalidFrom in seconds. If this record is the only one, returns an
     *         array with just one element. If the record does not belong to a cersionized table, false is
     *         returned.
     */
    public function get_all_archived_versions (array $archive_record)
    {
        if (! in_array($archive_record["Table"], $this->efa_tables->is_versionized))
            return false;
        $archived_record = $this->decode_record($archive_record);
        if (! isset($archived_record["Id"]))
            return false;
        $id = $archived_record["Id"];
        $id_entry = '"Id":"' . $id . '"';
        $list_args = ["{IdEntry}" => $id_entry
        ];
        include_once "../classes/tfyh_list.php";
        $object_list = new Tfyh_list("../config/lists/efaArchive", 9, "Datensätze zu Objekt", $this->socket, 
                $this->toolbox, $list_args);
        $object_rows = $object_list->get_rows();
        $object_records = [];
        foreach ($object_rows as $object_row) {
            $object_archive_record = $object_list->get_named_row($object_row);
            $object_archived_record = $this->decode_record($object_archive_record);
            $invalidFrom32 = $this->efa_tables->value_validity32($object_archived_record["InvalidFrom"]);
            $object_records[$invalidFrom32] = $object_archive_record;
        }
        ksort($object_records);
        return $object_records;
    }

    /**
     * Decode the json encoded archived record
     * 
     * @param array $archive_record
     *            the archive record as stored in the efaCloudArchived table, containing the record to decode
     *            in the $archive_record["Record"] field
     * @return array the "Record" decoded to an associate array, from the json String.
     */
    public function decode_record (array $archive_record)
    {
        // see
        // https://stackoverflow.com/questions/24312715/json-encode-returns-null-json-last-error-msg-gives-control-character-error-po
        $ctrl_replaced = preg_replace('/[[:cntrl:]]/', '', $archive_record["Record"]);
        return json_decode($ctrl_replaced, true);
        $archive_record;
    }

    /**
     * Create an archive reference record to be used in the origin table.
     * 
     * @param String $tablename
     *            the name of the table the record belongs to
     * @param int $archive_id
     *            the ID of the archive record to link to
     * @param array $full_record
     *            the full record to create the stub from
     * @param int $last_modified_secs
     *            time for LastModified timestamp, in seconds; "000" will be added.
     * @param String $change_count
     *            the change count to be used in the stub
     */
    private function create_archive_stub (String $tablename, int $archive_id, array $full_record, 
            int $last_modified_secs, String $change_count)
    {
        $is_efa2persons = strcmp($tablename, "efa2persons") == 0;
        $is_efa2logbook = strcmp($tablename, "efa2logbook") == 0;
        $last_modification = $this->archive_settings[$tablename]["Origin"];
        // if so, continue by creating the nominal stub
        $nominal_stub = $this->efa_tables->clear_record_for_delete($tablename, $full_record);
        // add the archive ID to provide a link to the archived record
        $archive_id_reference = self::$archive_id_prefix . $archive_id;
        $nominal_stub[$this->archive_settings[$tablename]["archiveID_at"]] = $archive_id_reference;
        // add the virtual fields like in the cronjob routine to avoid ping-pong between
        // cronjob virtual field generation in stubs and stub autocorrection.
        if ($is_efa2logbook)
            $nominal_stub["AllCrewIds"] = $this->efa_tables->create_AllCrewIds_field($nominal_stub);
        if ($is_efa2persons)
            $nominal_stub["FirstLastName"] = $nominal_stub["FirstName"] . " " . $nominal_stub["LastName"];
        $last_modification = $this->archive_settings[$tablename]["Origin"];
        $nominal_stub = $this->efa_tables->register_modification($nominal_stub, $last_modified_secs, 
                $change_count, $last_modification);
        if (in_array("ecrhis", Efa_tables::$server_gen_fields[$tablename]))
            $nominal_stub["ecrhis"] = "";
        return $nominal_stub;
    }

    /**
     * Look through all archive records for the table $tablename, check whether a referencing stub is needed
     * within the table itself and fix it, if needed. To fix a stub a nominally correct stub is build based on
     * the archived record. It is inserted, if it is missing. If a stub is there, the nominal and the
     * currently available stub are compared and the stub replaced, if it is different. In order to force
     * synchronisation the stub will always get the current time as LastModified timestamp.
     * 
     * @param String $tablename
     *            the table to check and fix the stubs for
     * @return number the count of fixed stubs
     */
    public function autocorrect_archive_stubs (String $tablename)
    {
        // prepare activity
        $start_row = 0;
        $chunk_size = 100;
        $checked = 0;
        $corrected = 0;
        $failed = 0;
        $skipped = 0;
        $dublets = 0;
        $ecrids_handled = [];
        $modification = $this->archive_settings[$tablename]["Origin"];
        $is_delete_stub = strcmp($modification, "delete") == 0;
        $min_age_secs = $this->archive_settings[$tablename]["MinAgeDays"] * 86400;
        $now = time();
        do {
            // Check all existing archived records
            $archive_records = $this->socket->find_records_sorted_matched("efaCloudArchived", 
                    ["Table" => $tablename
                    ], $chunk_size, "=", "ID", true, $start_row);
            if ($archive_records !== false)
                foreach ($archive_records as $archive_record) {
                    $checked ++;
                    $archive_id = $archive_record["ID"];
                    $archived_at = strtotime($archive_record["Time"]);
                    $archived_record = $this->decode_record($archive_record);
                    // check whether this ecrid is a dublet in the archive
                    if (isset($ecrids_handled[$archived_record["ecrid"]])) {
                        // echo "<br>dublet found for ecrid '" . $archived_record["ecrid"] . "'.
                        // Previous:<br>";
                        // var_dump($ecrids_handled[$archived_record["ecrid"]]);
                        // echo "<br>Dublet:<br>";
                        // var_dump($archived_record);
                        if ($this->efa_tables->values_are_equal($ecrids_handled[$archived_record["ecrid"]], 
                                $archived_record, false)) {
                            $delete_result = $this->socket->delete_record($this->app_user_id, 
                                    "efaCloudArchived", $archive_id);
                            // echo "<br>dublet removal ID $archive_id: $delete_result<hr>";
                        } else {
                            // echo "<br>Different, thus kept.<hr>";
                        }
                        $dublets ++;
                        // check whether a stub is required and can be created.
                    } elseif (isset($archived_record["ecrid"]) && ! isset(
                            $ecrids_handled[$archived_record["ecrid"]]) &&
                             (! $is_delete_stub || ($now - $archived_at <= $min_age_secs))) {
                        $ecrids_handled[$archived_record["ecrid"]] = $archived_record;
                        // if so, continue by creating the nominal stub
                        $nominal_stub = $this->create_archive_stub($tablename, $archive_id, $archived_record, 
                                $now, $archived_record["ChangeCount"]);
                        // check the current stub
                        $current_stub = $this->socket->find_record($tablename, "ecrid", 
                                $archived_record["ecrid"]);
                        if ($current_stub === false) {
                            // current stub is missing, insert the nominal stub
                            // echo $archive_id_reference . "<br>nominal stub<br>";
                            // var_dump($nominal_stub);
                            // add the Change Management fields
                            $insert_result = $this->socket->insert_into($this->app_user_id, $tablename, 
                                    $nominal_stub);
                            // echo "<br>insert_result: $insert_result <hr>";
                            if (is_numeric($insert_result))
                                $corrected ++;
                            else
                                $failed ++;
                        } else {
                            // current stub is existing, check for correctness.
                            // add the Change Management fields, using the current stubs change count
                            $change_count_current = $current_stub["ChangeCount"];
                            $current_stub = $this->efa_tables->register_modification($current_stub, $now, 
                                    $change_count_current, $modification);
                            // adapt the nominal_stub ChangeCount field for equality checks
                            $nominal_stub = $this->efa_tables->register_modification($nominal_stub, $now, 
                                    $change_count_current, $modification);
                            // echo $archive_id_reference . "<br>nominal stub<br>";
                            // var_dump($nominal_stub);
                            // echo "<br>current stub<br>";
                            // var_dump($current_stub);
                            // compare the current with the nominal stub
                            if (! $this->efa_tables->values_are_equal($nominal_stub, $current_stub, false)) {
                                if (in_array("ecrhis", Efa_tables::$server_gen_fields[$tablename]))
                                    $nominal_stub["ecrhis"] = "REMOVE!";
                                $change_result = $this->socket->update_record_matched($this->app_user_id, 
                                        $tablename, 
                                        ["ecrid" => $nominal_stub["ecrid"]
                                        ], $nominal_stub);
                                if (strlen($change_result) == 0)
                                    $corrected ++;
                                else
                                    $failed ++;
                            }
                            // else echo "<br>no change.<hr>";
                        }
                    } else {
                        // echo "<br>skipped.<hr>";
                        $skipped ++;
                    }
                }
            $start_row += $chunk_size;
        } while (($archive_records !== false) && (count($archive_records) > 0) &&
                 ($corrected < $this->max_count_archived));
        
        if ($corrected == 0)
            return "";
        $result = "Überprüft: $checked, korrigiert: $corrected, Korrektur gescheitert: $failed," .
                 " Dubletten in Archiv: $dublets, keine Referenz erforderlich: $skipped.";
        return $result;
    }

    /**
     * Get the time in seconds which reflect the content's age for a non versionized record. For the logbook
     * this ist the sessions start date, for a person the InvalidFrom timestamp of the youngest version.
     * 
     * @param String $tablename
     *            the name of the table the record belongs to
     * @param array $record
     *            the record to determine the age for. The fields used are: efa2boatdamages.ReportDate,
     *            efa2boatreservations.DateFrom, efa2clubwork, efa2logbook, efa2messages.Date, all other:
     *            LastModified.
     * @return int as time in seconds of the "birth" of the record
     */
    public function time_of_non_versionized_record (String $tablename, array $record)
    {
        if (strcmp($tablename, "efa2boatdamages") == 0) {
            $creation_date = $this->toolbox->check_and_format_date($record["ReportDate"]);
        } elseif (strcmp($tablename, "efa2boatreservations") == 0) {
            $creation_date = $this->toolbox->check_and_format_date($record["DateFrom"]);
        } elseif ((strcmp($tablename, "efa2clubwork") == 0) || (strcmp($tablename, "efa2logbook") == 0) ||
                 (strcmp($tablename, "efa2messages") == 0)) {
            $creation_date = $this->toolbox->check_and_format_date($record["Date"]);
        }
        // Fallback: use LastModified timestamp.
        if ($creation_date === false)
            return $this->value32_validity($record["LastModified"]);
        // set 1970-01-01 as value for null Dates or invalid dates
        if (strlen($creation_date) == 0)
            $creation_date = "1970-01-01";
        $time_of_creation_date = strtotime($creation_date);
        if ($time_of_creation_date !== false)
            return $time_of_creation_date;
    }

    /**
     * Move versionized objects to the archive. This moves all records of all due objects (currently just
     * person objects) to the archive. The object is due, if the most recent version has come to the maximum
     * age. After being copied to the archive, the records in the originating table are replaced by reference
     * stubs.
     * 
     * @param Tfyh_list $versionized_list
     *            The list of all records to be checked for archiving.
     */
    private function versionized_to_archive (Tfyh_list $versionized_list)
    {
        $last_uuid = "";
        $table_name = $versionized_list->get_table_name();
        
        $pos_field_for_uuid = $versionized_list->get_field_index("Id");
        $pos_field_for_invalidFrom = $versionized_list->get_field_index("InvalidFrom");
        // abort on inconsistency of programmed application configuration
        if (($pos_field_for_uuid === false) || ($pos_field_for_invalidFrom === false)) {
            echo "Efa_archive::versionized_to_archive hit \$pos_field_for_uuid === false for table $table_name. Aborting.";
            exit();
        }
        $pos_of_archive_id_in_list = $versionized_list->get_field_index(
                $this->archive_settings[$table_name]["archiveID_at"]);
        // abort on inconsistency of programmed application configuration
        if ($pos_of_archive_id_in_list === false) {
            echo "Efa_archive::versionized_to_archive hit \$pos_of_archive_id_in_list === false for table $table_name. Aborting.";
            exit();
        }
        
        $versionized_rows = $versionized_list->get_rows();
        $count_archived = 0;
        $count_failed = 0;
        $min_age_secs = $this->archive_settings[$table_name]["MinAgeDays"] * 86400;
        
        $this_id_to_archive = false; // default, the value will be set with the first not archived row.
        foreach ($versionized_rows as $versionized_row) {
            // the first record of an object is kept, thus needs special treatment.
            $first_record_of_id = strcmp($versionized_row[$pos_field_for_uuid], $last_uuid) != 0;
            $invalidFromSecs = (is_null($versionized_row[$pos_field_for_invalidFrom])) ? 0 : $this->efa_tables->value_validity32(
                    $versionized_row[$pos_field_for_invalidFrom]);
            // check whether this object was already archived.
            $is_archived = $first_record_of_id && (strpos($versionized_row[$pos_of_archive_id_in_list], 
                    Efa_archive::$archive_id_prefix) !== false);
            
            // check whether this object shall be archived.
            $this_id_to_archive = ($first_record_of_id && ! $is_archived) ? ((time() - $invalidFromSecs) >
                     $min_age_secs) : $this_id_to_archive;
            if ($this_id_to_archive && ($count_archived < $this->max_count_archived)) {
                $named_row = $versionized_list->get_named_row($versionized_row);
                $success = $this->move_to_archive($named_row, $table_name);
                if ($success)
                    $count_archived ++;
                else
                    $count_failed ++;
            }
            $last_uuid = $versionized_row[0];
        }
        if (($count_archived + $count_failed) >= 0)
            return $table_name . ": " . $count_archived . "/" . strval($count_archived + $count_failed) . ", ";
        else
            return "";
    }

    /**
     * Move non-versionized records to the archive. The record is due, if it has reached the maximum age.
     * After being copied to the archive, the record is emptied and the 'LastModification' field set to
     * 'delete'. THERE IS CURRENTLY NO CHECK FOR REFERENTIAL INTEGRITY, because the non-versionized tables
     * selected for archiving do not require such a check.
     * 
     * @param Tfyh_list $simple_list
     *            The list of the records to be archived.
     * @param String $parameter_name
     *            the name of the configuration parameter holding the maximum age.
     */
    private function non_versionized_to_archive (Tfyh_list $simple_list)
    {
        $table_name = $simple_list->get_table_name();
        // simple lists are already filtered for records to archive
        $simple_rows = $simple_list->get_rows();
        $count_archived = 0;
        $count_failed = 0;
        $min_age_secs = $this->archive_settings[$table_name]["MinAgeDays"] * 86400;
        foreach ($simple_rows as $simple_row) {
            $named_row = $simple_list->get_named_row($simple_row);
            // THERE IS CURRENTLY NO CHECK FOR REFERENTIAL INTEGRITY, NEED CAN ARISE IF TABLES ARE ADDED.
            $time_of_record = $this->time_of_non_versionized_record($table_name, $named_row);
            if (((time() - $time_of_record) > $min_age_secs) && ($count_archived < $this->max_count_archived)) {
                $success = $this->move_to_archive($named_row, $table_name);
                if ($success)
                    $count_archived ++;
                else
                    $count_failed ++;
            }
        }
        if (($count_archived + $count_failed) >= 0)
            return $table_name . ": " . $count_archived . "/" . strval($count_archived + $count_failed) . ", ";
        else
            return "";
    }

    /**
     * Restore an arcived record from the archive back to the table. Updates the table record, increases
     * ChangeCount and updates LastModified timestamp by one second, if the stub is still existing, to trigger
     * client synchronisation. Deletes the archived record after successful resore.
     * 
     * @param int $archive_id
     *            the ID of the archive record
     * @param String $tablename
     *            the name of the table into which the record shall be resotred
     * @param array $archived_record
     *            the archived record, already json_decoded.
     */
    private function restore_from_archive (int $archive_id, String $tablename, array $archived_record)
    {
        $stub = $this->socket->find_record($tablename, "ecrid", $archived_record["ecrid"]);
        $restored = false;
        if ($stub === false) {
            $result = $this->socket->insert_into($this->app_user_id, $tablename, $archived_record);
            if (is_numeric($result))
                $restored = true;
        } else {
            $archived_record = $this->efa_tables->register_modification($archived_record, time(), 
                    $stub["ChangeCount"], "update");
            $result = $this->socket->update_record_matched($this->app_user_id, $tablename, 
                    ["ecrid"
                    ], $archived_record);
            if (strlen($result) == 0)
                $restored = true;
        }
        if ($restored)
            $result = $this->socket->delete_record($this->app_user_id, "efaCloudArchived", $archive_id);
        return $result;
    }

    /**
     * Restore all records of a table which were archived less than $archived_less_than_days_ago.
     */
    public function restore_form_archive (String $tablename, int $archived_less_than_days_ago)
    {
        $restore_list_args = ["{ArchivedLessThanDaysAgo}" => $archived_less_than_days_ago,
                "{Table}" => $tablename
        ];
        $restore_list = new Tfyh_list("../config/lists/efaArchive", count($this->archive_settings) + 1, "", 
                $this->efa_tables->socket, $this->toolbox, $restore_list_args);
        $restore_rows = $restore_list->get_rows();
        $successes = 0;
        $failed = 0;
        foreach ($restore_rows as $restore_row) {
            $archive_record = $restore_list->get_named_row($restore_row);
            $archive_id = $archive_record["ID"];
            $archived_at = strtotime($archive_record["Time"]);
            $archived_record = $this->decode_record($archive_record);
            $stub = $this->socket->find_record_matched($tablename, 
                    ["ecrid" => $archived_record["ecrid"]
                    ]);
            if ($stub !== false)
                // if a stub is existing, use its ChangeCount to trigger synchronisation
                $restore_record = $this->efa_tables->register_modification($archived_record, time(), 
                        $stub["ChangeCount"], "update");
            else
                $restore_record = $this->efa_tables->register_modification($archived_record, time(), 
                        $archived_record["ChangeCount"], "update");
            $update_result = $this->socket->update_record_matched($this->app_user_id, $tablename, 
                    ["ecrid" => $archived_record["ecrid"]
                    ], $restore_record);
            if (strlen($update_result) == 0) {
                $successes ++;
                $this->socket->delete_record_matched($this->app_user_id, "efaCloudArchived", 
                        ["ID" => $archive_id
                        ]);
            } else
                $failed ++;
        }
        return "Die Wiederherstellung für Datensätze aus $tablename, die vor weniger als $archived_less_than_days_ago " .
                 "Tagen archiviert wurden, ist abgeschlossen: $successes mal erfolgreich, $failed nicht erfolgreich.";
    }

    /**
     * Move all due records to the archive
     */
    public function records_to_archive ()
    {
        $id = 1;
        $info = "";
        
        // define the list to use
        include_once '../classes/tfyh_list.php';
        foreach ($this->archive_settings as $for_table => $archive_setting) {
            // The archiving trigger can never be less than 180 days.
            $list_args = ["{" . $archive_setting["ListParam"] . "}" => $archive_setting["MinAgeDays"]
            ]; // These arguments are not needed for the versionized list, but do no harm.
            $archive_target_list = new Tfyh_list("../config/lists/efaArchive", $id, "", $this->socket, 
                    $this->toolbox, $list_args);
            // The table name is needed to ditinguish the handling
            $table_name = $archive_target_list->get_table_name();
            if (in_array($table_name, $this->efa_tables->is_versionized)) {
                $info .= $this->versionized_to_archive($archive_target_list, $archive_setting);
            } else {
                $info .= $this->non_versionized_to_archive($archive_target_list);
            }
            $id ++;
        }
        if (strlen($info) == 0)
            $info = "no records to be archived.";
        else
            $info = substr($info, 0, strlen($info) - 2);
        return $info;
    }

    /**
     * Purge all deleted records of all tables, if too old.
     */
    public function purge_outdated_deleted ()
    {
        $cfg = $this->toolbox->config->get_cfg();
        // Default is 100 years = never.
        $purgeDeletedAgeDays = (isset($cfg["PurgeDeletedAgeDays"]) && (strlen($cfg["PurgeDeletedAgeDays"]) > 0)) ? intval(
                $cfg["PurgeDeletedAgeDays"]) : 36500;
        $info = "";
        if ($purgeDeletedAgeDays > 0)
            foreach ($this->tables_to_purge_deleted as $tablename) {
                $deleted_cnt = $this->socket->count_records($tablename, 
                        ["LastModification" => "delete"
                        ], "=");
                $sql_cmd = "DELETE FROM `" . $tablename .
                         "` WHERE (`LastModification` = 'delete') AND (`LastModified` < ((UNIX_TIMESTAMP() - " .
                         $purgeDeletedAgeDays . " * 86400) * 1000))";
                // === test code
                // file_put_contents("../log/tmp", $tablename . ": Would execute purge: " . $sql_cmd . "\n",
                // FILE_APPEND);
                // === test code
                $this->socket->query($sql_cmd);
                $affected_rows = $this->socket->affected_rows();
                if (($affected_rows > 0) || ($deleted_cnt > 0))
                    $info .= $tablename . ": " . $affected_rows . "/" . $deleted_cnt . ", ";
            }
        if (strlen($info) == 0)
            $info = "no deleted records were found";
        else
            $info = substr($info, 0, strlen($info) - 2);
        return $info;
    }
}