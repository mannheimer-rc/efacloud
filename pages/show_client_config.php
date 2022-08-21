<?php
/**
 * Page display file. Shows all logs of the application.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$cfg = $toolbox->config->get_cfg();
$reference_client_id = $cfg["reference_client"];
if (strlen($reference_client_id) == 0)
    $reference_client_id = "[kein Referenz-Client definiert, bitte Einstellungen prüfen.]";

include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
$efa_config->xml_to_csv();
$efa_config->load_efa_config();
$project_cfg_html = $efa_config->display_array($efa_config->project);
$types_cfg_html = $efa_config->display_array($efa_config->types);
$config_cfg_html = $efa_config->display_array($efa_config->config);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Efa-client-Konfiguration<sup class='eventitem' id='showhelptext_Stammdaten'>&#9432</sup> anzeigen</h3>
	<p>Hier wird die technische Konfiguration des efa-Clients mit der <b>efaCloudUserID
		"<?php echo $reference_client_id; ?>"</b> angezeigt, die efaCloud und efaWeb verwenden. Um efa sowohl mit als
		auch ohne efaCloud betreiben zu können, wird diese Konfiguration immer
		im efa-Client gepflegt, nie in der Cloud. Welcher efa-Client als
		Referenz verwendet wird, kann in den <a href='../forms/configparameter_aendern.php'>Einstellungen</a> angegeben werden.</p>
		<h4>Einstellungen für efaCloud</h4>
		<ul><li>Aktuelles Fahrtenbuch: <?php echo $efa_config->current_logbook; ?></li> 
		<li>Zeitraum: <?php echo date("d.m.Y", $efa_config->logbook_start_time) . " - " . date("d.m.Y", $efa_config->logbook_end_time); ?></li> 
		<li>Beginn des Sportjahres: <?php echo $efa_config->sports_year_start; ?></li></ul>
	<h4>Projekteinstellungen</h4>
	<?php echo $project_cfg_html; ?>
	<h4>Typ-Definitionen</h4>
	<?php echo $types_cfg_html; ?>
	<h4>Efa-Client Programmeinstellungen</h4>
	<?php echo $config_cfg_html; ?>
	<!-- END OF Content -->
</div>

<?php
end_script();
