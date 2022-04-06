<?php
/**
 * A data base bootstrap script to create the server side admin tables and the first admin user.
 * 
 * @author mgSoft
 */

// ===== THIS SHALL ONLY BE USED during application configuration, then access rights shall
// be changed to "no access" - even better: or the form deleted from the site.

// ===== initialize toolbox
include_once '../classes/tfyh_toolbox.php';
$toolbox = new Tfyh_toolbox();
// init parameters definition
$cfg = $toolbox->config->get_cfg();

// PRELIMINARY SECURITY CHECKS
// ===== throttle to prevent from machine attacks.
$toolbox->load_throttle("inits/", $toolbox->config->settings_tfyh["init"]["max_inits_per_hour"]);

// Create PHP-wrapper socket to data base
include_once '../classes/tfyh_socket.php';
$socket = new Tfyh_socket($toolbox);
$connected = $socket->open_socket();
if ($connected !== true)
    $toolbox->display_error("Datenbankverbindung fehlgeschlagen", $connected, "../install/setup_clear_db.pbp", 
            __FILE__);

$db_name = $socket->get_db_name();

// ===== define admin user default configuration
// set defaults
$cfg_default["ecadmin_vorname"] = "Alex";
$cfg_default["ecadmin_nachname"] = "Admin";
$cfg_default["ecadmin_mail"] = "alex.admin@efacloud.org";
$cfg_default["ecadmin_id"] = "1142";
$cfg_default["ecadmin_Name"] = "alexa";
$cfg_default["ecadmin_password"] = "123Test!";
$cfg_default["ecadmin_password_confirm"] = $cfg_default["ecadmin_password"];

// ===== Form texts for admin user configuration
$cfg_description["ecadmin_vorname"] = "Vorname des efacloud Server Admins";
$cfg_description["ecadmin_nachname"] = "Nachname des efacloud Server Admins";
$cfg_description["ecadmin_mail"] = "E-Mail Adresse des efacloud Server Admins";
$cfg_description["ecadmin_id"] = "userID des efacloud Server Admins (ganze Zahl)";
$cfg_description["ecadmin_Name"] = "admin Name des efacloud Server Admins (z.B. 'martin', 'admin' ist nicht zulässig!)";
$cfg_description["ecadmin_password"] = "Passwort des efacloud Server Admins, UNBEDINGT MERKEN";
$cfg_description["ecadmin_password_confirm"] = "Passwort des efacloud Server Admins wiederholen";

// ===== define field format in configuration form
$cfg_type["ecadmin_vorname"] = "text";
$cfg_type["ecadmin_nachname"] = "text";
$cfg_type["ecadmin_mail"] = "email";
$cfg_type["ecadmin_id"] = "text";
$cfg_type["ecadmin_Name"] = "text";
$cfg_type["ecadmin_password"] = "password";
$cfg_type["ecadmin_password_confirm"] = "password";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datenbank <?php echo $db_name; ?> löschen und neu aufsetzen</h3>
</div>
<div class="w3-container">

<?php
if ((isset($_GET['done']) && intval($_GET["done"]) == 1)) {
    
    foreach ($cfg_default as $key => $value)
        $cfg_to_use[$key] = $cfg_default[$key];
    
    // read entered values into $cfg_to_use array.
    foreach ($cfg_default as $key => $value) {
        $new_value = $_POST[$key];
        if (! is_null($new_value) && (strlen($new_value) > 0))
            $cfg_to_use[$key] = $_POST[$key];
    }
    // check password
    if (strcmp($cfg_to_use["ecadmin_password"], $cfg_to_use["ecadmin_password_confirm"]) != 0) {
        ?>
	<h4>Die Kennwörter stimmen nicht überein. Bitte korrigieren!</h4>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        exit();
    }
    if (strlen($toolbox->check_password($cfg_to_use["ecadmin_password"])) > 0) {
        ?>
	<h4>Das Kennwort genügt nicht den Sicherheitsregeln</h4>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        exit();
    }
    if (strcasecmp($cfg_to_use["ecadmin_Name"], "admin") == 0) {
        ?>
	<h4>Der admin-Name 'admin' ist unzulässig. Bitte verwende einen anderen admin Namen.</h4>
	<p>
		<a href='../install/setup_clear_db.php'>Neuer Versuch</a>
	</p>
<?php
        echo "</div>";
        exit();
    }
    
    // set session user to selected admin, in order to be able to manipulate the data base.
    $_SESSION["User"]["Vorname"] = $cfg_to_use["ecadmin_vorname"];
    $_SESSION["User"]["Nachname"] = $cfg_to_use["ecadmin_nachname"];
    $_SESSION["User"]["EMail"] = $cfg_to_use["ecadmin_mail"];
    $_SESSION["User"]["efaCloudUserID"] = $cfg_to_use["ecadmin_id"];
    $_SESSION["User"]["efaAdminName"] = $cfg_to_use["ecadmin_Name"];
    $_SESSION["User"]["Passwort_Hash"] = password_hash($cfg_to_use["ecadmin_password"], PASSWORD_DEFAULT);
    $_SESSION["User"]["Rolle"] = "admin";
    
    // ===== create data base
    include_once '../classes/efa_tables.php';
    $efa_tables = new Efa_tables($toolbox, $socket);
    include_once '../classes/efa_tools.php';
    $efa_tools = new Efa_tools($efa_tables, $toolbox);
    $result_bootstrap = $efa_tools->init_efa_data_base(true, true);
    
    echo "<p>" . $result_bootstrap . "</p>";
    // Display result and next steps
    ?>
	<h3>Fertig</h3>
	<p>
		Die Datenbank ist gelöscht und neu aufgesetzt. Die Einrichtung muss
		nun <a href='../install/setup_finish.php'>hier abgeschlossen</a>
		werden.
	</p>
<?php
} else {
    ?>
	<p>
		Hier bitte den Administrator der neu aufgesetzten Datenbank angeben.
		Das ist dann der einzige Nutzer der neuen Datenbank. Dieser Nutzer
		kann dann alle weiteren Verwaltungsvorgänge in der Anwendung
		durchführen.<br>
	</p>
	<form action="?done=1" method="post">
		<table>

    <?php
    // Display form fields depending on the installation mode.
    foreach ($cfg_default as $key => $value)
        echo '<tr><td>' . $key . ':<br>' . $cfg_description[$key] .
                 '&nbsp;</td><td><input class="forminput" type="' . $cfg_type[$key] .
                 '" size="35" maxlength="250" name="' . $key . '" value="' . $value . '"></td></tr>';
    ?>
    </table>
		<br> <input class="formbutton" type="submit"
			value="Datenbank neu aufsetzen">
	</form>
	<h2>Achtung: Dadurch werden alle bestehenden Daten in der Datenbank "<?php echo $db_name; ?>" gelöscht!</h2>
</div><?php
}