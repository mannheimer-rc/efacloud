<?php
/**
 * A page to audit the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

include_once "../classes/efa_tables.php";
$efa_tables = new Efa_tables($toolbox, $socket);
include_once "../classes/efa_tools.php";
$efa_tools = new Efa_tools($efa_tables, $toolbox);

include_once '../classes/efa_db_layout.php';

// ===== Improve data base status, if requested
$improve = (isset($_GET["do_improve"])) ? $_GET["do_improve"] : "";
$do_improve = (strcmp($improve, "now") == 0);
$improvements = "";

$delete_corrupt = (isset($_GET["delete_corrupt"]) && strcasecmp($_GET["delete_corrupt"], "yes") == 0);
$mark_duplicates = (isset($_GET["mark_duplicates"]) && strcasecmp($_GET["mark_duplicates"], "yes") == 0);
$set_missing_defaults = (isset($_GET["set_missing_defaults"]) &&
         strcasecmp($_GET["set_missing_defaults"], "yes") == 0);

// maximum number of records which will be added an ecrid, if missing, in one go. Should never be hit.
$max_add_ecrids = 1000;
if ($do_improve) {
    $upgrade_success = $efa_tools->upgrade_efa_tables(true);
    $improvements = ($upgrade_success) ? "<b>Fertig</b><br>Das Tabellenlayout wurde angepasst. " : "<b>Fehler</b><br>Das Tabellenlayout konnte nicht angepasst werden. Details siehe '../log/efa_tools.log'. ";
    $added_ecrids = $efa_tools->add_ecrids($max_add_ecrids);
    $improvements .= (($added_ecrids > 0) ? $added_ecrids .
             " ecrids wurden hinzugefügt. (Der Schritt aktualisiert maximal " . $max_add_ecrids .
             " Datensätze und muss ggf. wiederholt werden.)<br>" : ".");
    $improvements .= "<br>";
    $db_layout_read = Efa_db_layout::compare_db_layout($socket, $efa_tables->db_layout_version_target);
    if ($db_layout_read == $efa_tables->db_layout_version_target) {
        $cfg_db = $toolbox->config->get_cfg_db();
        $cfg_db["db_layout_version"] = $db_layout_read;
        $cfg_db["db_up"] = Tfyh_toolbox::swap_lchars($cfg_db["db_up"]);
        $cfgStr = serialize($cfg_db);
        $cfgStrBase64 = base64_encode($cfgStr);
        $byte_cnt = file_put_contents("../config/settings_db", $cfgStrBase64);
        $improvements .= "Die Datenbank-Konfiguration wurde aktualisiert ($byte_cnt Bytes).<br>";
    }
    $improvements .= '<br>';
}
$optimization_needed = false;

// ===== Configuration check
$db_layout_read = Efa_db_layout::compare_db_layout($socket, $efa_tables->db_layout_version_target);
$db_layout_config = "<b>Ergebnis der Konfigurationsprüfung</b><ul>";
// compare the current version. $efa_tables still remembers the version before improvement
$layout_cfg_is_target = (intval($efa_tables->db_layout_version_target) ==
         intval($toolbox->config->get_cfg_db()["db_layout_version"]));
$layout_read_is_target = (is_numeric($db_layout_read) &&
         ($efa_tables->db_layout_version_target == $db_layout_read));
if ($layout_cfg_is_target && $layout_read_is_target) {
    $db_layout_config .= "<li>OK. " . "In Bestand und allen Konfigurationsparametern Layout Version " .
            $efa_tables->db_layout_version_target;
} else {
    $optimization_needed = true;
    $db_layout_config .= "<li>NICHT OK.</li><li>";
    $db_layout_config .= "In der Konfiguration hinterlegt: " . $efa_tables->db_layout_version . "</li><li>";
    $db_layout_config .= "Struktur der Datenbank entspricht: " . strval($db_layout_read) . "</li><li>";
    $db_layout_config .= "Standard für diese Programmversion: " . $efa_tables->db_layout_version_target;
}
$db_layout_config .= "</li></ul>";

// ===== Size check
$table_names = $socket->get_table_names();
$table_record_count_list = "<b>Größenprüfung: Tabellen und Datensätze</b><ul>";
$total_record_count = 0;
$total_table_count = 0;
foreach ($table_names as $tn) {
    $record_count = $socket->count_records($tn);
    $total_record_count += $record_count;
    $total_table_count ++;
    $table_record_count_list .= "<li>" . $tn . " [" . $record_count . "]</li>";
}
$table_record_count_list .= "<li>in Summe [" . $total_record_count . "] Datensätze in " . $total_table_count .
         " Tabellen.</li></ul>";

// ===== Layout implementation check
$efa_tools->change_log_path("../log/sys_db_audit.log");
$verification_result = "<b>Ergebis der Layoutprüfung</b><ul><li>";
$db_layout_verified = $efa_tools->update_database_layout(
        $_SESSION["User"][$toolbox->users->user_id_field_name], $efa_tables->db_layout_version_target, true);
if ($db_layout_verified) {
    $verification_result .= "OK. Das Layout stimmt mit dem Standard der Programmversion = Version " .
             $efa_tables->db_layout_version_target . " überein.";
} else {
    $optimization_needed = true;
    $verification_result .= "NICHT OK.</li><li>" . str_replace("\n", "</li><li>", 
            str_replace("Verification failed", "<b>Verification failed</b>", 
                    file_get_contents("../log/sys_db_audit.log")));
}
$efa_tools->change_log_path("");
$verification_result .= "</li></ul>";

// ===== Ecrid filling check
$total_no_ecrids_count = 0;
$no_ecrid_record_count_list = "<b>Datensätze ohne ecrid Identifizierung</b><ul>";
foreach ($table_names as $tn) {
    if (isset($efa_tools->ecrid_at[$tn]) && ($efa_tools->ecrid_at[$tn] == true)) {
        $records_wo_ecrid = $socket->find_records_sorted_matched($tn, ["ecrid" => ""
        ], $max_add_ecrids, "NULL", "", true);
        $no_ecrids_count = ($records_wo_ecrid === false) ? 0 : count($records_wo_ecrid);
        $colnames = $socket->get_column_names($tn);
        if (! in_array("ecrid", $colnames)) {
            $no_ecrids_count = $socket->count_records($tn);
        }
        $total_no_ecrids_count += $no_ecrids_count;
        if ($no_ecrids_count > 0)
            $no_ecrid_record_count_list .= "<li>" . $tn . " [" .
                     (($no_ecrids_count == $max_add_ecrids) ? strval($max_add_ecrids) . "+" : $no_ecrids_count) .
                     "]</li>";
    }
}
if ($total_no_ecrids_count > 0) {
    $optimization_needed = true;
    $no_ecrid_record_count_list .= "<li>NICHT OK</li></ul>";
} else
    $no_ecrid_record_count_list .= "<li>OK. Alle Datensätze enthalten die erforderliche ecrid-Identifizierung.</li></ul>";

// ===== data integrity auditing
include_once "../classes/efa_audit.php";
$efa_audit = new Efa_audit($efa_tables, $toolbox);
$data_integrity_result = $efa_audit->data_integrity_audit($delete_corrupt, $mark_duplicates, 
        $set_missing_defaults);
$data_integrity_result_list = "<b>Ergebnis der Datenintegritätsprüfung</b><ul>" . $data_integrity_result .
         "</ul>";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<!-- START OF content -->
<div class="w3-container">
	<h3>Audit für die Datenbank <?php echo $socket->get_db_name(); ?><sup
			class='eventitem' id='showhelptext_Audit'>&#9432</sup>
	</h3>
	<p>Hier das Ergebnis der Prüfung der Datenbank</p>
	<?php
echo $improvements;
echo $db_layout_config;
echo $verification_result;
echo $no_ecrid_record_count_list;
if ($optimization_needed)
    echo '<p><a href="?do_improve=now"><span class="formbutton">Jetzt korrigieren - Warten - dauert bis zu 5 Minuten!</span></a><br /><br /></p>';
echo $table_record_count_list;
echo $data_integrity_result_list;

?>
</div>
<?php
end_script();