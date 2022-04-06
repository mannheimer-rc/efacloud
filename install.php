<!DOCTYPE html>
<html>
<head>
<title>efaCloud - Installation</title>
<meta charset="UTF-8">
<style>
html, body, input, textarea {
	margin: 0;
	font: 1em "Noto Sans", sans-serif;
	background-color: #fff;
}

.efacloud-body {
	max-width: 1200px;
	margin: auto;
}

a {
	color: #171177; /*background may change */
}

a:hover {
	text-decoration: underline;
}

h1, h2, h3 {
	color: #171177; /* headline color */
}

input, p, textarea, blockquote, code, h4, h5, h6 {
	color: #0c0c0c; /* text color */
}

a:visited, a:hover {
	color: #12125b; /*background may change */
}
</style>
</head>

<body class='efacloud-body'>
	<h3 style='text-align: center'>
		<img
			src='data:image/png;base64,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'
			alt="efacloud Logo" style="width: 180px" />
	</h3>
	<h2 style='text-align: center'>
		<br>Efacloud-Server Installation.
	</h2>

<?php
if (! isset($_GET["version"])) {
    
    $versions_string = file_get_contents('https://efacloud.org/src/scanversions.php?own=none');
    $versions = explode("|", $versions_string);
    ?>
    <h3 style='text-align: center'>Option 1: Upgrade</h3>
	<p style='text-align: center'>Das Upgrade entpackt den Code und
		überschereibt dabei die vorhandenen Code-Dateien. Alle Bestandsdaten,
		wie zum Beispiel logs, uploads, backups usw. bleiben erhalten. Die
		Datenbank wird nicht modifiziert.</p>
	<p style='text-align: center'>     
    <?php
    foreach ($versions as $version)
        if (strlen($version) > 1)
            echo "<a href='?version=" . urlencode($version) . "&do=upgrade'><b>" . $version . "</b></a><br />";
    ?>
	<br />
	</p>
	<h3 style='text-align: center'>Option 2: Vollinstallation</h3>
	<p style='text-align: center'>Die Vollinstallation löscht auf dem
		Server alle Dateistrukturen, die von efaCloud verwendet werden, also
		auch zum Beispiel logs, uploads, backups. Anschließend wird der Code
		in die dazu neu angelegte Dateistruktur entpackt. Die
		Konfigurationsdateien für Datenbankzugriff und Anwendung werden
		zwischengespeichert und anschließend wieder hergestellt. Die Datenbank
		wird nicht modifiziert.</p>
	<p style='text-align: center'>    
    <?php
    foreach ($versions as $version)
        if (strlen($version) > 1) {
            echo "<a href='?version=" . urlencode($version) . "'><b>jetzt Version " . $version .
                     " installieren</a></b> --- ";
            echo "<a href='https://efacloud.org/src/" . $version .
                     "/release_notes.html' target='_blank'>Release Notes nachlesen</a><br />";
        }
    ?><br />
	</p>
	<p style='text-align: center'>Bitte beachten Sie: der Vorgang startet
		mit dem Klick auf den Link sofort. Dabei werden unter Umständen Daten
		gelöscht</p>
	
	
<?php
} else {
    
    $version_to_install = $_GET["version"];
    $mode_upgrade = strcasecmp($_GET["do"], "upgrade") == 0;
    $mode_install = strcasecmp($_GET["do"], "install") == 0;
    
    // Source Code path. Adapt with every new version.
    // ==============================================================================================
    $efacloud_src_path = "https://efacloud.org/src/" . $version_to_install . "/efacloud_server.zip";
    // ==============================================================================================
    
    // check loaded modules
    // ==============================================================================================
    $ref_config = ["calendar","Core","ctype","date","exif","fileinfo","filter","ftp","gettext","hash",
            "iconv","json","libxml","mbstring","mysqli","openssl","pcre","pdo_mysql","PDO","Phar","posix",
            "Reflection","session","sockets","SPL","standard","tokenizer","zip","zlib"
    ];
    $this_config = get_loaded_extensions();
    $missing = [];
    foreach ($ref_config as $rcfg) {
        $contained = false;
        foreach ($this_config as $tcfg) {
            $contained = $contained || (strcmp($tcfg, $rcfg) == 0);
        }
        if (! $contained)
            $missing[] = $rcfg;
    }
    echo "<p  style='text-align: center'>Installierte PHP-Module wurden geprüft.<br>";
    if (count($missing) > 0) {
        echo "Die folgenden Module fehlen auf dem Server im Vergleich zur Referenzinstallation:<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo "Es ist möglich, dass efaCloud auch ohne diese Module läuft, wurde aber nicht getestet.<br><br>";
    } else
        "Alle Module der Referenzinstallation sind vorhanden.<br><br>";
    
    // fetch program source
    // ==============================================================================================
    echo "Lade den Quellcode von: " . $efacloud_src_path . " ...<br>";
    file_put_contents("src.zip", file_get_contents($efacloud_src_path));
    echo " ... abgeschlossen. Dateigröße: " . filesize("src.zip") . ".<br><br>";
    if (filesize("src.zip") < 1000) {
        echo "</p><p style='text-align: center'>Die Größe des Quellcode-Archivs ist zu klein. Da hat " .
                 "etwas mit dem Download nicht geklappt. Deswegen bricht der Prozess hier ab.</p></body></html>";
        exit();
    }
    
    // read settings, will be used as cache in case of install
    echo "Sichere die vorhandene Konfiguration ...<br>";
    $settings_db = (file_exists("config/settings_db")) ? file_get_contents("config/settings_db") : false;
    $settings_app = (file_exists("config/settings_app")) ? file_get_contents("config/settings_app") : false;
    
    // Delete server side files.
    // ==============================================================================================
    if ($mode_install) {
        echo "Lösche Bestandsdateien vom Server ...<br>";

        function rrmdir ($dir)
        {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir . "/" . $object))
                            rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                        else
                            unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
                rmdir($dir);
            }
        }
        $dirs_to_delete = ["api","classes","config","forms","install","js","log","pages","resources",
                "uploads"
        ];
        foreach ($dirs_to_delete as $dir_to_delete) {
            if (is_dir($dir_to_delete)) {
                echo "Verzeichnis: " . $dir_to_delete . " ...<br>";
                rrmdir($dir_to_delete);
            }
        }
        echo "Bestandsdateien gelöscht ...<br>";
    }
    
    // Unpack source files
    // ==============================================================================================
    echo "Entpacke das Quellcode-Archiv ...<br>";
    $zip = new ZipArchive();
    $res = $zip->open('src.zip');
    if ($res === TRUE) {
        $zip->extractTo('.');
        $zip->close();
        echo "Aktualisiere Versionsangabe ...<br>";
        file_put_contents("public/version", $version_to_install);
        chmod("public/version", 0644);
        chmod("public/copyright", 0644);
        echo ' ... fertig. ... <br><br>';
    } else {
        echo "</p><p>Das Quellcode-Archiv konnte nicht entpackt werden. Da hat etwas mit dem Download " .
                 "nicht geklappt. Deswegen bricht der Prozess hier ab.</p></p></body></html>";
        exit();
    }
    unlink("src.zip");
    // restore settings, in case of upgrade
    if ($settings_db) {
        echo "Stelle die vorhandene db-Konfiguration wieder her ...<br>";
        file_put_contents("config/settings_db", $settings_db);
    }
    if ($settings_app) {
        echo "Stelle die vorhandene app-Konfiguration wieder her ...<br>";
        file_put_contents("config/settings_app", $settings_app);
    }
    
    // Set directories' access rights.
    // ==============================================================================================
    echo "Setze die Zugriffsberechtigung der angelegten Dateistruktur ...<br>";
    $restricted = ["classes","config","log","uploads"
    ];
    $open = ["api","forms","js","pages","resources","install"
    ];
    foreach ($restricted as $dirname)
        chmod($dirname, 0700);
    foreach ($open as $dirname)
        chmod($dirname, 0755);
    // .htaccess may still be in from previous installations. Remove it temporarily.
    unlink("install/.htaccess");
    echo ' ... fertig.<br></p>';
    ?>

<?php
    
    // Prepare data base connection setup, if needed
    // ==============================================================================================
    if (! $settings_db) {
        ?>
<p style='text-align: center'>
		Es wurden keine Einstellungen zur Datenbankverbindung vorgefunden.
		Diese müssen jetzt als nächstes konfiguriert werden:<br> <a
			href='install/setup_db_connection.php'><b>Datenbankverbindung
				konfigurieren</b></a>
	</p>
    <?php
    } else {
        
        // Read configuration. First try single file configuration
        include_once 'classes/toolbox.php'; // only for static access. Toolbox instance
                                            // creation will fail due to the install.php sitting in
                                            // the root directory instead of a subdirectory
        $settings_file_path = "config/settings";
        $cfg = [];
        if (file_exists($settings_file_path)) {
            $cfgStrBase64 = file_get_contents($settings_file_path);
            $cfg = ($cfgStrBase64) ? unserialize(base64_decode($cfgStrBase64)) : [];
            if ($cfg["db_up"])
                $cfg["db_up"] = Toolbox::swap_lchars($cfg["db_up"]);
        } else  // Configuration split into data base connection and application parameters
            if (file_exists($settings_file_path . "_db")) {
                // read data base connection configuration first
                $cfgStrBase64 = file_get_contents($settings_file_path . "_db");
                $cfg = ($cfgStrBase64) ? unserialize(base64_decode($cfgStrBase64)) : [];
                if ($cfg["db_up"])
                    $cfg["db_up"] = Toolbox::swap_lchars($cfg["db_up"]);
            }
        
        // test access
        $connect_success = false;
        if (count($cfg) > 0) {
            // do not connect, if connection is open.
            $mysqli = new mysqli($cfg["db_host"], $cfg["db_user"], $cfg["db_up"], $cfg["db_name"]);
            $connect_success = ! $mysqli->connect_error;
        }
        if ($connect_success) {
            ?>
<p style='text-align: center'>
		Die Einstellung zur Datenbankverbindung wurde erfolgreich getestet.
		Die Einrichtung kann daher nun <a href='install/setup_finish.php'>abgeschlossen</a>
		werden. <br />Alternativ gibt es jetzt die Möglichkeit zum <a
			href='install/setup_clear_db.php'>vollständigen Löschen und
			Neuaufsetzen der Datenbank</a>.
	</p>
    <?php
        } else {
            ?>
            <p style='text-align: center'>
		Mit den vorhandenen Einstellungen zur Datenbankverbindung konnte keine
		Verbindung hergestellt werden. Der Datenbankzugang muss daher jetzt
		als nächstes neu konfiguriert werden:<br> <a
			href='install/setup_db_connection.php'><b>Datenbankverbindung
				konfigurieren</b></a>
	</p>
        <?php
        }
    }
}
?>
	<p style='text-align: center'>&nbsp;</p>
	<p style='text-align: center'>
		<small>&copy; efacloud - nmichael.de</small>
	</p>
</body>
</html>
