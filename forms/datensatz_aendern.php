<?php
/**
 * The form for user profile self service. Based on the Tfyh_form class, please read instructions their to
 * better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
// ===== page does not need an active session
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';

// === APPLICATION LOGIC ==============================================================
if (! isset($_SESSION["getps"][$fs_id]["table"]) || ! isset($_SESSION["getps"][$fs_id]["ecrid"]))
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file .
                     "' muss als Folgeseite von Datensatz finden aufgerufen werden.", __FILE__);
$tablename = $_SESSION["getps"][$fs_id]["table"];
$ecrid = $_SESSION["getps"][$fs_id]["ecrid"];
$app_user_id = $_SESSION["User"][$toolbox->users->user_id_field_name];
$record = $socket->find_record_matched($tablename, ["ecrid" => $ecrid
]);
if ($record === false)
    $toolbox->display_error("Nicht gefunden.", 
            "Der zu ändernde Datensatz mit der ecrid '$ecrid' konnte in der Tabelle" .
                     " $tablename nicht gefunden werden.", __FILE__);

// the form templates to use for data edit, depending on the chosen table
$form_templates = ["efa2autoincrement" => "dataedit_2","efa2boatdamages" => "dataedit_3",
        "efa2boatreservations" => "dataedit_4","efa2boats" => "dataedit_5","efa2boatstatus" => "dataedit_6",
        "efa2clubwork" => "dataedit_7","efa2crews" => "dataedit_8","efa2destinations" => "dataedit_9",
        "efa2fahrtenabzeichen" => "dataedit_10","efa2groups" => "dataedit_11","efa2logbook" => "dataedit_12",
        "efa2messages" => "dataedit_13","efa2persons" => "dataedit_14","efa2sessiongroups" => "dataedit_15",
        "efa2statistics" => "dataedit_16","efa2status" => "dataedit_17","efa2waters" => "dataedit_18"
];

// the lookup tables needed as in efaWeb to auto-fill the id/name fields
$lookups_needed = ["efa2autoincrement" => [],"efa2boatdamages" => ["efaWeb_boats","efaWeb_persons"
],"efa2boatreservations" => ["efaWeb_boats","efaWeb_persons"
],"efa2boats" => [],"efa2boatstatus" => ["efaWeb_boats"
],"efa2clubwork" => ["efaWeb_persons"
],"efa2crews" => ["efaWeb_boats","efaWeb_persons"
],"efa2destinations" => [],"efa2fahrtenabzeichen" => ["efaWeb_boats","efaWeb_persons"
],"efa2groups" => ["efaWeb_persons"
],"efa2logbook" => ["efaWeb_boats","efaWeb_persons","efaWeb_destinations","efaWeb_waters"
],"efa2messages" => ["efaWeb_persons"
],"efa2persons" => [],"efa2sessiongroups" => ["efaWeb_boats","efaWeb_persons"
],"efa2statistics" => [],"efa2status" => [],"efa2waters" => []
];

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";

// ======== start with form filled in last step: check of the entered values.
if ($done == 0) {
    // create form layout based on the table used for data edit.
    $form_layout = "../config/layouts/" . $form_templates[$tablename];
} else {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
    $entered_data = Efa_tables::fix_boolean_text($tablename, $entered_data);
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
    if (($todo == 1)) {
        // fix for boolean (checkbox) values: efa expects "true" or nothing instead of "on" or nothing
        include_once '../classes/efa_tables.php';
        $search_result = Efa_tables::fix_boolean_text($tablename, $record);
        $form_to_fill->preset_values($search_result);
    }
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>
		Einen Datensatz<sup class='eventitem' id='showhelptext_efaDaten'>&#9432</sup>
		ändern
	</h3>
	<p>Hier kannst Du den folgenden Datensatz <?php echo " <b>für die Tabelle " . $tablename . "</b>" ?> ändern. Bitte agiere mit Umsicht.</p>
</div>

<div class="w3-container">
<?php

echo $toolbox->form_errors_to_html($form_errors);
echo $form_to_fill->get_html(false);

// TODO hier ansetzen.
if ($todo == 1) { // step 1. No special texts for output
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>\n<script>";
    include_once "../classes/tfyh_list.php";
    $list_args = [ "{LastModified}" => "0" ];
    foreach ($lookups_needed[$tablename] as $listname) {
        $include_csv = new Tfyh_list("../config/lists/efaWeb", 0, $listname, $socket, $toolbox, $list_args);
        $csv_str = $include_csv->get_csv($_SESSION["User"]);
        echo "var " . $listname . " = `" . $csv_str . "`;\n";  
    }
    echo "</script>\n";
    echo '    <script src="../js/bLists.js"></script>' . "\n";
    echo '    <script src="../js/sFormHandler.js"></script>' . "\n";
} else {
    ?>
    <p>
		<b>Die Datenänderung ist <?php  echo (($form_errors) ? "nicht" : ""); ?> durchgeführt.</b>
	</p>
	<p>
		<?php
    echo (($form_errors) ? "" : "Folgende Änderungen wurden vorgenommen:<br />");
    ?>
             </p>
<?php
}
// TODO: hier die Listen der UUIDs / Namen als var einfügen.
?></div><?php
end_script();
