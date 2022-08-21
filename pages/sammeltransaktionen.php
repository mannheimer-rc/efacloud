<?php
/**
 * Page display file. A set of mass transactions available.
 *
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

if (isset($_GET["todo"]))
    $todo = $_GET["todo"];
else
    $todo = 0;
if (isset($_GET["cdelay"]))
    $cdelay = $_GET["cdelay"];
else
    $cdelay = 0;

// === PAGE OUTPUT ===================================================================

// ===== start page output, except for download of SEPA or LSB-file.
    if (($todo != 2) && ($todo != 3) && ($todo != 12) && ($todo != 11)) {
    echo file_get_contents('../config/snippets/page_01_start');
    echo $menu->get_menu();
    echo file_get_contents('../config/snippets/page_02_nav_to_body');
}

// kein Todo. Auflisten der Optionen
if ($todo == 0) {
    $collection_date_1 = date("Y-m-d", time() + 7 * 24 * 3600);
    $collection_date_2 = date("Y-m-d", time() + 12 * 24 * 3600);
    $collection_date_3 = date("Y-m-d", time() + 15 * 24 * 3600);
    ?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<p>Hier können Sammeltransaktionen ausgeführt werden</p>
</div>

<div class="w3-container">
	<p>Wenn Du diese Seite siehst, bst du dazu berechtigt. Führe die
		Transaktionen mit Bedacht aus. Sie können in der Regel nicht
		rückgängig gemacht werden.</p>
	<p>
		<b>Mögliche Transaktionen</b>
	</p>
	<p>
		<a href="?todo=1">Aufnahme nach Ruderkurs: Erwachsene</a>.<br />Die
		Transaktion wird in der Regel nach Abschluss des Ruderkurses
		durchgeführt. Sie kann etwas dauern (10 - 30 Sekunden).
	</p>
	<p>
		<a href="?todo=2">Aufnahme nach Ruderkurs: Jugend</a>.<br />Die
		Transaktion wird in der Regel nach Abschluss des Ruderkurses
		durchgeführt. Sie kann etwas dauern (10 - 30 Sekunden).
	</p>
	<p>
		Beitragserhebung. <b>SEPA</b> zum <a href="?todo=3&cdelay=7"><?php echo $collection_date_1; ?></a>
		oder <a href="?todo=3&cdelay=12"><?php echo $collection_date_2; ?></a>
		oder <a href="?todo=3&cdelay=15"><?php echo $collection_date_3; ?></a>
		oder <a href="?todo=12&cdelay=1"><b>Rechnungszahler</b></a> .<br />Erstellen einer
		SEPA Lastschriftdatei für die Abbuchung der Mitgliedsbeiträge
		einschließlich der Einmalbeträge oder einer
		Rechnungszahleraufstellung. Bitte nach Abbuchung nicht vergessen, die
		Einmalbeträge zu löschen!
	</p>
	<p>
		<a href="?todo=4">Löschen der Einmalbeträge</a>.<br />Die Transaktion
		wird in der Regel nach der Abrechnung durchgeführt. Diese Löschung hat
		keinen Prüfschritt. Sie wird bei Klick auf den Link sofort
		durchgeführt.
	</p>
	<p>
		<a href="?todo=5">Archivieren der Stammdaten der ausgetretener
			Mitglieder</a>.<br />Die Transaktion wird in der Regel einmal im
			Quartal im Rahmen der Mitgliederpflege ausgeführt. Archiviert werden
			Mitgliedsnummer, Vorname, Nachname, Eintritts- und Austrittsdatum der
			Mitglieder, deren Austrittsdatum vor <?php echo date("d.m.Y", time() + (3 * 24 * 3600)); ?> liegt. Die
			Mitgliederdatensätze werden aus der Mitgliederliste gelöscht.
		</p>
	<p>
		<a href="?todo=7">Löschen der Datensätze für nicht eingetretene
			Teilnehmer des Ruderkurses</a>.<br />Die Transaktion wird in der
		Regel einmal im Jahr nach der Jubelversammlung durchgeführt. Die
		Mitgliederdatensätze aller Ruderkursteilnehmer werden aus der
		Mitgliederliste gelöscht.
	</p>
	<p>
		<a href="?todo=9">Löschen der Daten aus dem Vereinsbetrieb</a>.<br />Die
		Transaktion wird in der Regel einmal im Jahr zu Jahresbeginn
		durchgeführt. Sie löscht alle Datensätze der Tabellen
		"Reservierungen", "Trainingsdoku" und "Mails" die älter als zwei Jahre
		sind, aus diesen Tabellen des Vereinsbetriebs. Diese Löschung hat
		keinen Prüfschritt. Sie wird bei Klick auf den Link sofort
		durchgeführt.
	</p>
	<p>
		<a href="?todo=10">Validierung der Daten der Mitgliederliste</a>.<br />Die
		Transaktion validiert alle Mitgliedsdaten auf grundsätzliche
		Vollständigkeit und Gültigkeit.
	</p>
	<p>
		<a href="?todo=11">Bestandserhebung für den Landessportbund</a>.<br />Die
		Die Transaktion summiert über Jahrgang und Geschlecht de Mitglieder
		zur Meldung per Datei-Upload an den Landessportbund www.lsb.nrw.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist die Ausgabe der Anmeldungen nach Ruderkurs
elseif (($todo == 1) || ($todo == 2)) {
    include_once "../classes/tfyh_list.php";
    include_once '../classes/pdf.php';
    $pdf = new PDF($toolbox, $socket, "Mitgliederliste");
    // list #1 = Ruderkurs Erwachsene, list #1 = Ruderkurs Jugend
    $list = new Tfyh_list("../config/lists/verwalten", $todo, "", $socket, $toolbox);
    $template = ($todo == 1) ? "Aufnahmeantrag_Erw_nachRuderkurs" : "Aufnahmeantrag_Jgd_nachRuderkurs";
    $titel = "Aufnahme in die BRG nach erfolgreich abgeschlossenem Ruderkurs";
    $rows = $list->get_rows();
    $all_ids = "";
    $file_paths = [];
    chdir("../pdfs");
    foreach ($rows as $row) {
        $mitgliedsnummer = $row[0];
        if ($mitgliedsnummer) {
            $computed_fields = [];
            $user_to_address = $socket->find_record("Mitgliederliste", "Mitgliedsnummer", 
                    $mitgliedsnummer);
            $id = $user_to_address["ID"];
            $name_plus = htmlspecialchars($user_to_address["Vorname"]) . " " .
                     htmlspecialchars($user_to_address["Nachname"]) . " (" . $mitgliedsnummer . "), ";
            $all_ids .= "<li>" . $name_plus . "</li>";
            if (strcasecmp($user_to_address["Geschlecht"], "m") == 0) {
                $computed_fields["Adressfeldanrede"] = "Herrn";
                $computed_fields["Briefanrede"] = "Lieber";
            } else {
                $computed_fields["Adressfeldanrede"] = "Frau";
                $computed_fields["Briefanrede"] = "Liebe";
            }
            $computed_fields["Kalenderjahr"] = date("Y");
            $computed_fields["Tagesdatum"] = date("d.m.Y");
            $file_path = $pdf->create_pdf($template, $titel, $id, $computed_fields);
            $file_paths[] = str_replace("../pdfs/", "", $file_path);
        }
    }
    if (count($file_paths) > 0) {
        $toolbox->logger->log_bulk_transaction($titel, $_SESSION["User"]["Mitgliedsnummer"], 
                "für " . count($file_paths) . " Teilnehmer in " . $titel);
        $toolbox->return_files_as_zip($file_paths, $template . ".zip", true);
    } else
        echo "Keine Ruderkursteilnehmer gefunden.";
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Aufnahme nach Ruderkurs</h4>
	<p>Die Anmeldeanträge wurden generiert für:</p>
	<ul><?php echo $all_ids; ?></ul>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist das Erstellen der SEPA Lastschriftdatei oder der Rechnungszahlerdatei
elseif (($todo == 3) || ($todo == 12)) {
    include_once '../classes/fees.php';
    $fees = new Fees($socket, $toolbox);
    $collection_date = date("Y-m-d", time() + $cdelay * 10 * 3600);
    $toolbox->logger->log_bulk_transaction("Erstellen der SEPA Lastschriftdatei", 
            $_SESSION["User"]["Mitgliedsnummer"], "");
    $invoiced = ($todo == 12);
    $errors_collecting = $fees->collect_fees($collection_date, $invoiced);
    // This will be reached on error only, because the code above returns a file, not a page.
    echo file_get_contents('../config/snippets/page_01_start');
    echo $menu->get_menu();
    echo file_get_contents('../config/snippets/page_02_nav_to_body');
    ?>
<div class="grid_12">
	<!-- This will be reached on error only, because the code above returns a file, not a page. -->
	<p><b>Es sind Fehler aufgetreten:</b><br><?php echo $errors_collecting; ?></p>
	<p><a href='../pages/sammeltransaktionen.php'>Zu den sammeltransaktionen.</a></p>
</div>
<?php
} // angefordert ist das Löschen der Einmalbeträge
elseif ($todo == 4) {
    $sql_cmd = "UPDATE `Mitgliederliste` SET `Einmalbetrag` = 0 WHERE 1;";
    $res = $socket->query($sql_cmd);
    if ($res) {
        $res_text = "Einmalbeträge wurden erfolgreich für alle Mitglieder auf 0 gesetzt.";
        $toolbox->logger->log_bulk_transaction("Löschen der Einmalbeträge", 
                $_SESSION["User"]["Mitgliedsnummer"], $res_text);
        // write change log entry. changed ID = 0 for all changed
        $sql_cmd = "INSERT INTO `Changelog` (`ID`, `Autor`, `Time`, `Tabelle`, `ChangedID`, `Beschreibung`) VALUES (NULL, '" .
                 $_SESSION["User"]['Mitgliedsnummer'] .
                 "', CURRENT_TIMESTAMP, 'Mitgliederliste', '0', 'Einmalbeiträge gelöscht.');";
        $tmpr = $socket->query($sql_cmd);
    } else
        $res_text = "Einmalbeträge konnten nicht gelöscht werden. Datenbankfehler.";
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Löschen der Einmalbeträge</h4>
	<p>Die Löschung wurde durchgeführt. <?php echo $res_text; ?></p>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist das Archivieren und Löschen der ausgetretenen Mitglieder. Schritt 1 prüfen.
elseif ($todo == 5) {
    include_once "../classes/tfyh_list.php";
    // list #7: Ausgetretene Mitglieder. Wenn sich die Reihenfolge der Listen in
    // ../config/lists/verwalten ändert, muss hier und unter todo == 5 auch die Ziffer angepasst
    // werden.
    $list = new Tfyh_list("../config/lists/verwalten", 7, "", $socket, $toolbox);
    $rows = $list->get_rows();
    $all_ids = "";
    $count = 0;
    foreach ($rows as $row) {
        $mitgliedsnummer = $row[0];
        if ($mitgliedsnummer) {
            $user_to_delete = $socket->find_record("Mitgliederliste", "Mitgliedsnummer", 
                    $mitgliedsnummer);
            $id = $user_to_delete["ID"];
            $austritt = $user_to_delete["Austritt"];
            $name_plus = "<b>" . htmlspecialchars($user_to_delete["Vorname"]) . " " .
                     htmlspecialchars($user_to_delete["Nachname"]) . "</b> (" . $mitgliedsnummer .
                     "), Ausgetreten zum: " . $austritt;
            $all_ids .= "<li>" . $name_plus . "</li>";
            $count ++;
        }
    }
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Archivieren und Löschen der ausgetretenen Mitglieder</h4>
	<h4>ACHTUNG: PlusMinus-Liste schon fertig? Die geht hinterher nicht
		mehr.</h4>
	<p>Für folgende Nutzer werden im nächsten Schritt Mitgliedsnummer,
		Vorname, Nachname, Eintritts- und Austrittsdatum archiviert und ihr
		Datensatz in der Mitgliederliste wird endgültig gelöscht:</p>
	<ol><?php echo $all_ids; ?></ol>
	<p>
		Nutzer <a href="?todo=6">endgültig löschen</a>
	</p>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>

<?php
} // angefordert ist das Archivieren und Löschen der ausgetretenen Mitglieder. Schritt 2 löschen
elseif ($todo == 6) {
    include_once "../classes/tfyh_list.php";
    // list #7: Ausgetretene Mitglieder. Wenn sich die Reihenfolge der Listen in
    // ../config/lists/verwalten ändert, muss hier auch die Ziffer angepasst werden.
    $list = new Tfyh_list("../config/lists/verwalten", 7, "", $socket, $toolbox);
    $rows = $list->get_rows();
    $all_ids = "";
    $count_succ = 0;
    $count_fail = 0;
    foreach ($rows as $row) {
        $mitgliedsnummer = $row[0];
        if ($mitgliedsnummer) {
            $user_to_update = $socket->find_record("Mitgliederliste", "Mitgliedsnummer", 
                    $mitgliedsnummer);
            $name_plus = "<b>" . htmlspecialchars($user_to_update["Vorname"]) . " " .
                     htmlspecialchars($user_to_update["Nachname"]) . "</b> (" . $mitgliedsnummer .
                     "), Ausgetreten zum: " . $user_to_update["Austritt"];
            $user_to_archive["Mitgliedsnummer"] = $user_to_update["Mitgliedsnummer"];
            $user_to_archive["Vorname"] = $user_to_update["Vorname"];
            $user_to_archive["Nachname"] = $user_to_update["Nachname"];
            $user_to_archive["Eintritt"] = $user_to_update["Eintritt"];
            $user_to_archive["Austritt"] = $user_to_update["Austritt"];
            $res = $socket->insert_into($_SESSION["User"]["Mitgliedsnummer"], "Mitgliederarchiv", 
                    $user_to_archive);
            if (is_numeric($res)) {
                $res = $socket->delete_record($_SESSION["User"]["Mitgliedsnummer"], 
                        "Mitgliederliste", $user_to_update["ID"]);
                $all_ids .= "<li>" . $name_plus . " gelöscht und archiviert.</li>";
                $count_succ ++;
            } else {
                $all_ids .= "<li>" . $name_plus . " Archivierung fehlgeschlagen. Grund: '" . $res .
                         "'. Nutzer wurde nicht gelöscht.</li>";
                $count_fail ++;
            }
        }
    }
    $toolbox->logger->log_bulk_transaction("Archivieren und Löschen der ausgetretenen Mitglieder", 
            $_SESSION["User"]["Mitgliedsnummer"], 
            $count_succ . " archiviert, " . $count_fail . " Fehler.");
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Archivieren und Löschen der ausgetretenen Mitglieder</h4>
	<p>Für folgende Nutzer wurden die Stammdaten Mitgliedsnummer, Vorname,
		Nachname, Eintritts- und Austrittsdatum archiviert und der
		Mitgliedsdatensatz endgültig gelöscht:</p>
	<ol><?php echo $all_ids; ?></ol>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist das Löschen der nicht eingetretenen Ruderkursteilnehmer. Schritt 1 prüfen.
elseif ($todo == 7) {
    include_once "../classes/tfyh_list.php";
    // list #1: Ruderkurs Erwachsene, list #2: Ruderkurs Jugendliche. Wenn sich die Reihenfolge der
    // Listen in
    // ../config/lists/verwalten ändert, muss hier und unter todo == 7 auch die Ziffer angepasst
    // werden.
    $list = new Tfyh_list("../config/lists/verwalten", 1, "", $socket, $toolbox);
    $rows_1 = $list->get_rows();
    $list = new Tfyh_list("../config/lists/verwalten", 2, "", $socket, $toolbox);
    $rows_2 = $list->get_rows();
    $rows = [];
    foreach ($rows_1 as $row)
        $rows[] = $row;
    foreach ($rows_2 as $row)
        $rows[] = $row;
    $all_ids = "";
    foreach ($rows as $row) {
        $mitgliedsnummer = $row[0];
        if ($mitgliedsnummer) {
            $user_to_delete = $socket->find_record("Mitgliederliste", "Mitgliedsnummer", 
                    $mitgliedsnummer);
            $id = $user_to_delete["ID"];
            $name_plus = "<b>" . htmlspecialchars($user_to_delete["Vorname"]) . " " .
                     htmlspecialchars($user_to_delete["Nachname"]) . "</b> (" . $mitgliedsnummer .
                     "), Plausi-Check: eingetreten: " . $user_to_delete["Eintritt"] .
                     ", Beitragsart: " . $user_to_delete["Beitragsart"] . ", Geburtsdatum: " .
                     $user_to_delete["Geburtsdatum"];
            $all_ids .= "<li>" . $name_plus . "</li>";
        }
    }
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Archivieren und Löschen der ausgetretenen Mitglieder</h4>
	<p>Für folgende Nutzer werden im nächsten Schritt endgültig gelöscht:</p>
	<ol><?php echo $all_ids; ?></ol>
	<p>
		Nutzer <a href="?todo=8">endgültig löschen</a>
	</p>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist das Löschen der nicht eingetretenen Ruderkursteilnehmer. Schritt 2 löschen
elseif ($todo == 8) {
    include_once "../classes/tfyh_list.php";
    // list #1: Ruderkurs Erwachsene, list #2: Ruderkurs Jugendliche. Wenn sich die Reihenfolge der
    // Listen in
    // ../config/lists/verwalten ändert, muss hier und unter todo == 7 auch die Ziffer angepasst
    // werden.
    $list = new Tfyh_list("../config/lists/verwalten", 1, "", $socket, $toolbox);
    $rows_1 = $list->get_rows();
    $list = new Tfyh_list("../config/lists/verwalten", 2, "", $socket, $toolbox);
    $rows_2 = $list->get_rows();
    $rows = [];
    foreach ($rows_1 as $row)
        $rows[] = $row;
    foreach ($rows_2 as $row)
        $rows[] = $row;
    $all_ids = "";
    $count_succ = 0;
    $count_fail = 0;
    foreach ($rows as $row) {
        $mitgliedsnummer = $row[0];
        if ($mitgliedsnummer) {
            $user_to_update = $socket->find_record("Mitgliederliste", "Mitgliedsnummer", 
                    $mitgliedsnummer);
            $name_plus = "<b>" . htmlspecialchars($user_to_delete["Vorname"]) . " " .
                     htmlspecialchars($user_to_delete["Nachname"]) . "</b> (" . $mitgliedsnummer .
                     ")";
            $res = $socket->delete_record($_SESSION["User"]["Mitgliedsnummer"], "Mitgliederliste", 
                    $user_to_update["ID"]);
            if ($res !== false) {
                $all_ids .= "<li>" . $name_plus . " gelöscht.</li>";
                $count_succ ++;
            } else {
                $all_ids .= "<li>" . $name_plus .
                         " Löschung fehlgeschlagen. Nutzer nicht gefunden.</li>";
                $count_fail ++;
            }
        }
    }
    $toolbox->logger->log_bulk_transaction("Löschen der nicht eingetretenen Ruderkursteilnehmer", 
            $_SESSION["User"]["Mitgliedsnummer"], 
            $count_succ . " gelöscht, " . $count_fail . " Fehler.");
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Löschen der nicht eingetretenen Ruderkursteilnehmer</h4>
	<p>Für folgende Nutzer wurden die Stammdaten endgültig gelöscht:</p>
	<ol><?php echo $all_ids; ?></ol>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist das Löschen der Daten aus dem Vereinsbetrieb, die älter als 2 Jahre sind
elseif ($todo == 9) {
    $delete_older_than = time() - (2 * 365 * 24 * 3600);
    $age_limit = date("Y-m-d H:i:s", $delete_older_than);
    $res_mails = $socket->query("DELETE FROM `Mails` WHERE `versendetAm` < '" . $age_limit . "'");
    $res_trainingsdoku = $socket->query(
            "DELETE FROM `Trainingsdoku` WHERE `TagDerLeistung` < '" . $age_limit . "'");
    $res_reservierungen = $socket->query(
            "DELETE FROM `Reservierungen` WHERE `VerwendungEndeTag` < '" . $age_limit . "'");
    $info_res = "";
    if (! $res_mails)
        $info_res .= "Fehler bei Löschung der Mails: " . $res_mails . ".<br>";
    if (! $res_trainingsdoku)
        $info_res .= "Fehler bei Löschung der Trainingsdoku: " . $res_trainingsdoku . ".<br>";
    if (! $res_reservierungen)
        $info_res .= "Fehler bei Löschung der Reservierungen: " . $res_reservierungen . ".<br>";
    $toolbox->logger->log_bulk_transaction("Löschen der Daten aus dem Vereinsbetrieb", 
            $_SESSION["User"]["Mitgliedsnummer"], str_replace(".<br>", " / ", $info_res));
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Löschen der Daten aus dem Vereinsbetrieb, die älter als 2 Jahre
		sind</h4>
	<p>
		Die Datensätze der Tabellen "Reservierungen", "Trainingsdoku" und
		"Mails" die älter als zwei Jahre sind, wurden aus diesen Tabellen des
		Vereinsbetriebs gelöscht.<br><?php echo $info_res; ?></p>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist die Validierung der Daten der Mitgliederliste
elseif ($todo == 10) {
    $cnt = 0;
    $all_users = $socket->find_records("Mitgliederliste", "", "", 1000);
    $res_check_all = "";
    foreach ($all_users as $user_to_check) {
        $res_check = $toolbox->users->check_user_profile($user_to_check);
        if ($res_check)
            $res_check_all .= "Prüfung des Users <a href='/forms/nutzer_aendern.php?id=" .
                     $user_to_check["ID"] . "'>#" . $user_to_check["Mitgliedsnummer"] . "</a> (ID:" .
                     $user_to_check["ID"] . ") mit folgende(n) Fehler(n): " . $res_check . "<br>";
        $cnt ++;
    }
    if (! $res_check_all)
        $res_check_all = "Keine Fehler gefunden.";
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<h4>Validierung der Daten der Mitgliederliste</h4>
	<p>
		<?php echo $cnt; ?> Datensätze der Tabelle "Mitgliederliste" wurden validiert mit folgendem Ergebnis:
		<br><?php echo $res_check_all; ?></p>
	<p>
		<a href="?todo=0">hier</a> geht es zurück zur Übersicht der
		Sammeltransaktionen.
	</p>
	<p><?php echo $toolbox->logger->list_and_cleanse_bulk_txs(400, true); ?></p>
</div>
<?php
} // angefordert ist die Bestandserhebung für den Landessportbund.
elseif ($todo == 11) {
    $cnt = 0;
    include_once "../classes/tfyh_list.php";
    $list = new Tfyh_list("../config/lists/verwalten", 32, "", $socket, $toolbox);
    $all_users = $list->get_rows();
    $lsb_table = [];
    foreach ($all_users as $unnamed_user_to_add) {
        $user_to_add = $list->get_named_row($unnamed_user_to_add);
        $year = intval(substr($user_to_add["Geburtsdatum"], 0, 4));
        $gender = strtolower(substr($user_to_add["Geschlecht"], 0, 1));
        $row = $year . ";" . $gender;
        if (isset($lsb_table[$row]))
            $lsb_table[$row] ++;
        else
            $lsb_table[$row] = 1;
    }
    $lsb_table_csv = "";
    foreach ($lsb_table as $key => $value)
        $lsb_table_csv .= "3400;" . $key . ";" . $value . "\n";
    $toolbox->logger->log_bulk_transaction("Bestandserhebung für den Landessportbund", 
            $_SESSION["User"]["Mitgliedsnummer"], "");
    $toolbox->return_string_as_zip($lsb_table_csv, "BonnerRudergesellschaft_MeldungLSB.csv");
    ?>
<div class="w3-container">
	<h3>Sammeltransaktionen</h3>
	<!-- This will never be reached, because the code above returns a file, not a page. -->
</div>
<?php
}
end_script();
