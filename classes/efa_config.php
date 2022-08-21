<?php

class Efa_config
{

    /**
     * The different efa-configurationparts: efa2project.
     */
    public $project = [];

    /**
     * The different efa-configurationparts: efa2types.
     */
    public $types = [];

    /**
     * The different efa-configurationparts: efa2configuration.
     */
    public $config = [];

    /**
     * Current logbook provided by the reference client
     */
    public $current_logbook;

    /**
     * Sports year start provided by the reference client
     */
    public $sports_year_start;

    /**
     * tine stamp for the beginnig of the current logbook as provided by the reference client
     */
    public $logbook_start_time;

    /**
     * tine stamp for the end of the current logbook as provided by the reference client
     */
    public $logbook_end_time;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * a local string builder for the recursive array display function.
     */
    private $str_builder;

    /**
     * a helper variable for the recursive array display function.
     */
    private $row_of_64_spaces = "                                                                ";

    /**
     * Construct the Util class. This parses the efa-configuration passed in the corresponding drectory
     * ../uploads/[efaCloudUserID] into csv files and .
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $this->load_efa_config();
    }

    /**
     * This parses the efa-configuration XML-files of the reference client as found in the directory
     * ../uploads/[efaCloudUserID] into csv files.
     */
    public function xml_to_csv ()
    {
        // get reference client information
        $cfg = $this->toolbox->config->get_cfg();
        $reference_client = intval($cfg["reference_client"]);
        if ($reference_client <= 0)
            return; // all configuration arrays will be empty
        
        $client_files = scandir("../uploads/" . $reference_client);
        include_once "../classes/tfyh_xml.php";
        $xml = new Tfyh_xml($this->toolbox);
        foreach ($client_files as $client_file) {
            if (strpos($client_file, "efa2project") !== false) {
                $xml->read_xml(file_get_contents("../uploads/" . $reference_client . "/" . $client_file), 
                        false);
                file_put_contents("../config/client_cfg/project.csv", $xml->get_csv("data", "record"));
                $project_cfg = $this->toolbox->read_csv_array("../config/client_cfg/project.csv");
            } elseif (strpos($client_file, "efa2types") !== false) {
                $xml->read_xml(file_get_contents("../uploads/" . $reference_client . "/" . $client_file), 
                        false);
                file_put_contents("../config/client_cfg/types.csv", $xml->get_csv("data", "record"));
                $types_cfg = $this->toolbox->read_csv_array("../config/client_cfg/types.csv");
            } elseif (strpos($client_file, "efa2config") !== false) {
                $xml->read_xml(file_get_contents("../uploads/" . $reference_client . "/" . $client_file), 
                        false);
                file_put_contents("../config/client_cfg/config.csv", $xml->get_csv("data", "record"));
                $config_cfg = $this->toolbox->read_csv_array("../config/client_cfg/config.csv");
            }
        }
    }

    /**
     * This loads the efa-configuration files into the respective associative arrays $this->project,
     * $this->types, $this->config.
     */
    public function load_efa_config ()
    {
        
        // load the project configuration
        $csv_rows = $this->toolbox->read_csv_array(
                (file_exists("../config/client_cfg/project.csv")) ? "../config/client_cfg/project.csv" : "../config/client_cfg_default/project.csv");
        foreach ($csv_rows as $csv_row) {
            if (! isset($this->project[$csv_row["Type"] . "s"]))
                $this->project[$csv_row["Type"] . "s"] = [];
            $record = [];
            foreach ($csv_row as $key => $value)
                if (strlen($value) > 0)
                    $record[$key] = $value;
            $this->project[$csv_row["Type"] . "s"][] = $record;
        }
        // load the types
        $csv_rows = $this->toolbox->read_csv_array(
                (file_exists("../config/client_cfg/types.csv")) ? "../config/client_cfg/types.csv" : "../config/client_cfg_default/types.csv");
        foreach ($csv_rows as $csv_row) {
            if (! isset($this->types[$csv_row["Category"]]))
                $this->types[$csv_row["Category"]] = [];
            $this->types[$csv_row["Category"]][intval($csv_row["Position"])] = [
                    "Type" => $csv_row["Type"],"Position" => $csv_row["Position"],"Value" => $csv_row["Value"]
            ];
        }
        foreach ($this->types as $category => $unsorted) {
            ksort($unsorted);
            $this->types[$category] = $unsorted;
        }
        
        // load the client configuration
        $csv_rows = $this->toolbox->read_csv_array(
                (file_exists("../config/client_cfg/config.csv")) ? "../config/client_cfg/config.csv" : "../config/client_cfg_default/config.csv");
        foreach ($csv_rows as $csv_row) {
            $this->config[$csv_row["Name"]] = (isset($csv_row["Value"])) ? $csv_row["Value"] : "";
        }
        
        // assign the direct variables
        $this->current_logbook = str_replace("JJJJ", date("Y"), 
                $this->toolbox->config->get_cfg()["current_logbook"]);
        if (isset($this->project["Boathouses"][0]["CurrentLogbookEfaBoathouse"]))
            $this->current_logbook = $this->project["Boathouses"][0]["CurrentLogbookEfaBoathouse"];
        $this->sports_year_start = false;
        foreach ($this->project["Logbooks"] as $logbook) {
            if (strcasecmp($logbook["Name"], $this->current_logbook) == 0) {
                // start of day
                $this->logbook_start_time = strtotime(
                        $this->toolbox->check_and_format_date($logbook["StartDate"]));
                // end of day
                $this->logbook_end_time = strtotime(
                        $this->toolbox->check_and_format_date($logbook["EndDate"])) + 22 * 3600;
                $this->sports_year_start = substr($logbook["StartDate"], 0, 6);
            }
        }
        if ($this->sports_year_start == false)
            $this->sports_year_start = $this->toolbox->config->get_cfg()["sports_year_start"];
    }

    /**
     * Recursive html display of an array using the &lt;ul&gt; list type.
     * 
     * @param array $a
     *            the array to display
     * @param int $level
     *            the recursion level. To start the recursion, use 0 or leave out.
     */
    public function display_array (array $a, int $level = 0)
    {
        if ($level == 0)
            $this->str_builder = "";
        $indent = substr($this->row_of_64_spaces, 0, $level * 2);
        $this->str_builder .= $indent . "<ul>\n";
        $indent .= " ";
        foreach ($a as $key => $value) {
            $this->str_builder .= $indent . "<li>";
            if (is_array($value)) {
                $this->str_builder .= $key . "\n";
                $this->display_array($value, $level + 1);
            } elseif (is_object($value))
                $this->str_builder .= "$key : [object]";
            else
                $this->str_builder .= "$key : $value";
            $this->str_builder .= "</li>\n";
        }
        $this->str_builder .= $indent . "</ul>\n";
        if ($level == 0)
            return $this->str_builder;
    }
}    