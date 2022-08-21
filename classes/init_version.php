<?php
/**
 * snippet to execute after upgrade to this version.
 */
// ===== Execute basic data base configuration checks
include_once "../classes/efa_tables.php";
$efa_tables = new Efa_tables($toolbox, $socket);
include_once "../classes/efa_tools.php";
$efa_tools = new Efa_tools($efa_tables, $toolbox);
include_once '../classes/efa_db_layout.php';

$db_audit_needed = "";
$db_layout_read = Efa_db_layout::compare_db_layout($socket, $efa_tables->db_layout_version_target);
if (intval($efa_tables->db_layout_version_target) !=
         intval($toolbox->config->get_cfg_db()["db_layout_version"]))
    $db_audit_needed .= "In der Konfiguration ist eine falsche Version für das Datenbank-Layout hinterlegt. ";
if (is_numeric($db_layout_read) && ($efa_tables->db_layout_version_target == $db_layout_read))
    $db_audit_needed .= "Das Datenbank-Layout ist nicht auf dem aktuellen Stand. ";
if ($efa_tools->update_database_layout($_SESSION["User"][$toolbox->users->user_id_field_name], 
        $efa_tables->db_layout_version_target, true))
    $db_audit_needed .= "Die Auditierung meldet Abweichung in Details des Datenbank-Layouts. ";

// ===== Ecrid filling check
$total_no_ecrids_count = 0;
$table_names = $socket->get_table_names();
foreach ($table_names as $tn) {
    if (isset($efa_tools->ecrid_at[$tn]) && ($efa_tools->ecrid_at[$tn] == true)) {
        $records_wo_ecrid = $socket->find_records_sorted_matched($tn, ["ecrid" => ""
        ], 10, "NULL", "", true);
        $no_ecrids_count = ($records_wo_ecrid === false) ? 0 : count($records_wo_ecrid);
        $colnames = $socket->get_column_names($tn);
        if (! in_array("ecrid", $colnames))
            $no_ecrids_count = $socket->count_records($tn);
        $total_no_ecrids_count += $no_ecrids_count;
    }
}
$db_audit_needed .= "Es wurden Datensätze ohne ecrid-Wert gefunden. ";

// ===== Reflect upgrade result
echo "<p><b>Vielen Dank für die Aktualisierung!</b><br>";
if (strlen($db_audit_needed) > 0) {
    echo "Bei der Überprüfung der Datenbank wurde festgestellt, dass sie nicht komplett für diese Version vorbereitet ist.<br>";
    echo $db_audit_needed . "<br>";
    echo "<b>Bitte führe jetzt erst ein Datenbank-Audit durch und die dort empfohlenen Korrekturen.</b><br><br>";
    echo "<a href='../pages/db_audit.php'><input type='submit' class='formbutton' value='Audit starten'></a></p>";
} else {
    echo "Die Version " . file_get_contents("../public/version") . " ist nun betriebsbereit.";
    echo "<br>Diese Seite nicht neu laden, sondern als nächstes:<br><br>";
    echo "<a href='../pages/home.php'><input type='submit' class='formbutton' value='Loslegen'></a></p>";
}