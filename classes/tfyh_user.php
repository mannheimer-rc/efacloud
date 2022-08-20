<?php

/**
 * A utility class to hold the user profile management functions which do not depend on the application.
 */
class Tfyh_user
{

    /**
     * Application specific configuration
     */
    protected $action_links;

    public $user_table_name;

    // a numeric ID
    public $user_id_field_name;

    // an alphanumeric ID, but no valid e-mail address
    public $user_account_field_name;

    // a valid e-mail address
    public $user_mail_field_name;

    public $user_archive_table_name;

    public $user_firstname_field_name;

    public $user_lastname_field_name;

    public $use_subscriptions;

    public $use_workflows;

    public $use_concessions;

    public $useradmin_role;

    public $anonymous_role;

    public $self_registered_role;

    public $owner_id_fields;

    /**
     * The common toolbox.
     */
    protected $toolbox;

    /**
     * roles may include other roles. Expansion provides the role plus the respective included roles
     * in an array. The role_hierarchy is read from the file "../config/access/role_hierarchy" which
     * must contain per role a line "role=role1,role2,...".
     */
    public $role_hierarchy;

    /**
     * Is true for those roles for which those, who get it, shall be listed on role control.
     */
    public $is_priviledged_role;

    /**
     * Construct the Users class. This reads the configuration, initilizes the logger and the
     * navigation menu, asf.
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $roles = file_get_contents("../config/access/role_hierarchy");
        $roles_list = explode("\n", $roles);
        foreach ($roles_list as $role_def) {
            if (strlen($role_def) > 0) {
                $nvp = explode("=", trim($role_def));
                $main_role = trim($nvp[0]);
                $is_priviledged_role = (substr($main_role, 0, 1) == "*");
                if ($is_priviledged_role)
                    $main_role = substr($main_role, 1);
                $this->is_priviledged_role[$main_role] = $is_priviledged_role;
                $included_roles = explode(",", trim($nvp[1]));
                $this->role_hierarchy[$main_role] = $included_roles;
            }
        }
        $settings_tfyh = $toolbox->config->settings_tfyh;
        
        // user data configuration
        $this->action_links = $settings_tfyh["users"]["action_links"];
        $this->user_table_name = $settings_tfyh["users"]["user_table_name"];
        $this->user_id_field_name = $settings_tfyh["users"]["user_id_field_name"];
        $this->user_account_field_name = (isset($settings_tfyh["users"]["user_account_field_name"])) ? $settings_tfyh["users"]["user_account_field_name"] : "account";
        $this->user_mail_field_name = (isset($settings_tfyh["users"]["user_mail_field_name"])) ? $settings_tfyh["users"]["user_mail_field_name"] : "EMail";
        $this->user_archive_table_name = $settings_tfyh["users"]["user_archive_table_name"];
        $this->user_firstname_field_name = $settings_tfyh["users"]["user_firstname_field_name"];
        $this->user_lastname_field_name = $settings_tfyh["users"]["user_lastname_field_name"];
        
        // user role management
        if (! isset($settings_tfyh["users"]["useradmin_role"]) ||
                 ! isset($settings_tfyh["users"]["anonymous_role"]) ||
                 ! isset($settings_tfyh["users"]["use_subscriptions"]) ||
                 ! isset($settings_tfyh["users"]["use_workflows"]) ||
                 ! isset($settings_tfyh["users"]["use_concessions"]) ||
                 ! isset($settings_tfyh["users"]["ownerid_fields"])) {
            echo "Error in settings_tfyh: useradmin_role, anonymous_role, self_registered_role, use_subscriptions, use_workflows, use_concessions, or ownerid_fields not defined.";
            exit();
        }
        if (! isset($settings_tfyh["users"]["self_registered_role"]))
            $settings_tfyh["users"]["self_registered_role"] = $settings_tfyh["users"]["anonymous_role"];
        
        // useradmin and anonymous role definition.
        // Table field name: "Rolle" for the user role.
        $this->useradmin_role = $settings_tfyh["users"]["useradmin_role"];
        $this->anonymous_role = $settings_tfyh["users"]["anonymous_role"];
        $this->self_registered_role = $settings_tfyh["users"]["self_registered_role"];
        $owner_id_fields = explode(",", $settings_tfyh["users"]["ownerid_fields"]);
        foreach ($owner_id_fields as $owner_id_field) {
            if (strlen(trim($owner_id_field)) > 0) {
                $nvp = explode(".", trim($owner_id_field));
                if (count($nvp) == 2)
                    $this->ownerid_field[$nvp[0]] = $nvp[1];
            }
        }
        
        // user preferences and permissions
        $this->use_subscriptions = $settings_tfyh["users"]["use_subscriptions"]; // Table field
                                                                                 // name:
                                                                                 // Subskriptionen
        $this->use_workflows = $settings_tfyh["users"]["use_workflows"]; // Table field name:
                                                                         // Workflows
        $this->use_concessions = $settings_tfyh["users"]["use_concessions"]; // Table field name:
                                                                                 // Concessions
    }

    /*
     * ======================== Access Control ==============================
     */
    /**
     * Check whether an item is hidden on the menu, i. e. it is not shown, but can be accessed. This
     * is declared by a preceding "." prior to the permission of the item..
     *
     * @param String $permission
     *            the permission of the menu or list item which shall be checked.
     * @return true, if the item is hidden
     */
    public function is_hidden_item ($permission)
    {
        return (strcasecmp(".", substr($permission, 0, 1)) == 0);
    }

    /**
     * Check for workflows, concessions and subscriptions whether they are allowed for the current
     * user.
     *
     * @param array $permissions_of_item_array
     *            permissions of this menu item, split into an array
     * @param int $services
     *            the allowed services for the user as integer value representing 32 flags
     * @param String $identifier
     *            the identifier String of the servives type: @ - wokflows, $ - concessions, # -
     *            subscriptions
     * @return boolean true, if th service is allowed, false, if not.
     */
    private function is_permitted_service (array $permissions_of_item_array, int $services, 
            String $identifier)
    {
        $services_allowed = 0;
        foreach ($permissions_of_item_array as $permissions_of_item_element)
            if (strpos($permissions_of_item_element, $identifier) !== false)
                $services_allowed = $services_allowed |
                         intval(substr($permissions_of_item_element, 1));
        if (($services & $services_allowed) > 0)
            return true;
        return false;
    }

    /**
     * Check whether a role shall get access to the given item. The role will be expanded according
     * to the hierarchy and all included roles are as well checked, except it is preceded by a '!'.
     * If the permission String is preceded by a "." the menu will not be shown, but accessible -
     * same for all accessing roles.
     *
     * @param String $permission
     *            the permission String of the menu item or list which shall be accessed.
     * @param array $user
     *            The user for which the check shall be performed. Default is the $_SESSION["User"],
     *            but for API-Access such user is not set.
     * @return true, if access shall be granted
     */
    public function is_allowed_item (String $permission, array $user = null)
    {
        if (is_null($user)) {
            if (isset($_SESSION) & isset($_SESSION["User"]))
                $user = $_SESSION["User"];
            else // This happens on access errors
                $user["Rolle"] = $this->anonymous_role;
        }
        $accessing_role = (isset($user) && isset($user["Rolle"])) ? $user["Rolle"] : $this->anonymous_role;
        $subscriptions = ($this->use_subscriptions && isset($user) && isset($user["Subskriptionen"])) ? $user["Subskriptionen"] : 0;
        $workflows = ($this->use_workflows && isset($user) && isset($user["Workflows"])) ? $user["Workflows"] : 0;
        $concessions = ($this->use_concessions && isset($user) && isset($user["Concessions"])) ? $user["Concessions"] : 0;
        // else it must match one of the role in the hierarchy.
        $roles_of_hierarchy = $this->role_hierarchy[$accessing_role];
        $permissions_of_item = ($this->is_hidden_item($permission)) ? substr($permission, 1) . "," : $permission .
                 ",";
        $permitted = false;
        foreach ($roles_of_hierarchy as $r) {
            // find the role of the role hierarchy in the permissions String
            // add a comma to both, becasue the String is comma separated
            if (strpos($permissions_of_item, $r . ",") !== false)
                $permitted = true;
        }
        // or meet the permitted subscriptions.
        if (! $permitted)
            $permissions_of_item_array = explode(",", $permissions_of_item);
        if (! $permitted && ($subscriptions > 0) && (strpos($permissions_of_item, '#') !== false))
            $permitted = $this->is_permitted_service($permissions_of_item_array, $subscriptions, 
                    '#');
        // or meet the permitted workflows.
        if (! $permitted && ($workflows > 0) && (strpos($permissions_of_item, '@') !== false))
            $permitted = $this->is_permitted_service($permissions_of_item_array, $workflows, '@');
        // or meet the permitted concessions.
        if (! $permitted && ($concessions > 0) && (strpos($permissions_of_item, '$') !== false))
            $permitted = $this->is_permitted_service($permissions_of_item_array, $concessions, '$');
        return $permitted;
    }

    /**
     *
     * @param Tfyh_socket $socket
     *            the common data base access socket
     * @param bool $for_audit_log
     *            set true to return only the counts for the audit log
     * @return String an HTML formatted overview on granted accesses for plausibility checking.
     */
    public function get_all_accesses (Tfyh_socket $socket, bool $for_audit_log = false)
    {
        $html_str = "<h4>Rollen</h4>";
        $audit_log_str = "Count of privileged roles: ";
        foreach ($this->is_priviledged_role as $_role => $_is_priviledged) {
            if ($_is_priviledged) {
                $html_str .= "<h5>$_role</h5><p>";
                $audit_log_str .= $_role . " - ";
                $count_role_users = 0;
                $all_priviledged = $socket->find_records($this->user_table_name, "Rolle", $_role, 
                        1000);
                if ($all_priviledged != false)
                    foreach ($all_priviledged as $priviledged) {
                        $html_str .= "&nbsp;&nbsp;#<a href='../forms/nutzer_aendern.php?id=" .
                                 $priviledged["ID"] . "'>" . $priviledged[$this->user_id_field_name] .
                                 "</a>: " .
                                 ((isset($priviledged["Titel"])) ? $priviledged["Titel"] : "") . " " .
                                 $priviledged[$this->user_firstname_field_name] . " " .
                                 $priviledged[$this->user_lastname_field_name] . ".<br>";
                        $count_role_users ++;
                    }
                if (! $all_priviledged)
                    $html_str .= "&nbsp;&nbsp;Niemand<br>";
                $audit_log_str .= $count_role_users . "; ";
            }
        }
        
        $audit_log_str .= "\nCount of non-privileged roles: ";
        foreach ($this->is_priviledged_role as $_role => $_is_priviledged) {
            if (! $_is_priviledged) {
                $html_str .= "<h5>$_role</h5><p>";
                $all_non_priviledged = $socket->find_records($this->user_table_name, "Rolle", 
                        $_role, 5000);
                if (! $all_non_priviledged)
                    $html_str .= "&nbsp;&nbsp;Niemand<br>";
                else
                    $html_str .= "&nbsp;&nbsp;In Summe " . count($all_non_priviledged) .
                             " Nutzer.<br>";
                $audit_log_str .= $_role . " - " .
                         (($all_non_priviledged) ? strval(count($all_non_priviledged)) : "0") . "; ";
            }
        }
        $audit_log_str .= "\n";
        $html_str .= "</p><p>";
        
        $services_text = "";
        if ($this->use_workflows)
            $services_text .= $this->get_service_users_listed("workflows", "Workflows", false, 
                    $socket, $for_audit_log) . "\n";
        if ($this->use_concessions)
            $services_text .= $this->get_service_users_listed("concessions", "Concessions", false, 
                    $socket, $for_audit_log) . "\n";
        if ($this->use_subscriptions)
            $services_text .= $this->get_service_users_listed("subscriptions", "Subskriptionen", 
                    true, $socket, $for_audit_log) . "\n";
        
        if ($for_audit_log)
            return $audit_log_str . $services_text;
        else
            return $html_str . str_replace("\n", "</p><p>", $services_text) . "</p>";
    }

    /**
     * Provide a list of users for all services existing
     *
     * @param String $type
     *            either "subscriptions" or "workflows", i. e. the sevices file name in
     *            /config/access.
     * @param String $field_name
     *            either "Subskriptionen" or "Workflows", i. e. the field name in the user record
     * @param bool $count_only
     *            set true to get the count of service users instead of the named list
     * @param Tfyh_socket $socket
     *            the data base socket to retrieve data
     * @param bool $for_audit_log
     *            set true to return only the counts for the audit log
     * @return string
     */
    private function get_service_users_listed (String $type, String $field_name, bool $count_only, 
            Tfyh_socket $socket, bool $for_audit_log)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/$type");
        $services_list = (count($services_set) > 0) ? "<h4>$field_name</h4>" : "";
        $audit_log = $field_name . ": ";
        $no_users_at = "";
        
        foreach ($services_set as $service) {
            $titel = ((strcasecmp("workflows", $type) == 0) ? "@" : ((strcasecmp("concessions", 
                    $type) == 0) ? "$" : "#")) . $service["Flag"] . ": " . $service["Titel"];
            $service_users = $socket->find_records_sorted($this->user_table_name, $field_name, 
                    $service["Flag"], 5000, "&", $this->user_firstname_field_name, true);
            $count_of_service_users = ($service_users) ? count($service_users) : 0;
            if ($count_of_service_users == 0)
                $no_users_at .= $titel . ", ";
            else {
                $services_list .= "<h5>$titel</h5><p>";
                $services_list .= "In Summe " . $count_of_service_users . " Nutzer.<br>";
                $audit_log .= $titel . " - " . $count_of_service_users . "; ";
                if (! $count_only && is_array($service_users))
                    foreach ($service_users as $service_user)
                        $services_list .= "<a href='../forms/" . strtolower($field_name) .
                                 "_aendern.php?id=" . $service_user["ID"] . "'>#" .
                                 $service_user[$this->user_id_field_name] . "</a>: " .
                                 ((isset($service_user["Titel"])) ? $service_user["Titel"] : "") .
                                 " " . $service_user[$this->user_firstname_field_name] . " " .
                                 $service_user[$this->user_lastname_field_name] . ".<br>";
                $services_list .= "</p>";
            }
        }
        
        if (strlen($no_users_at) > 0) {
            $services_list .= "<h5>Keine Nutzer für</h5><p>" . $no_users_at . "</p>";
            $audit_log .= "\n: ohne Nutzer: " . $no_users_at;
        }
        return ($for_audit_log) ? $audit_log : $services_list;
    }

    /**
     * Provide a list of service titles for subscriptions, workflows and concessions which the user
     * is granted. In case of subscriptions a change link is added.
     *
     * @param String $type
     *            either "subscriptions", "workflows" or "concessions", i. e. the sevices file name
     *            in /config/access.
     * @param String $key
     *            either "Subskriptionen", "Workflows" or "Concessions", i. e. the field name in the
     *            user record
     * @param String $value
     *            the value of the respective field in the user record
     * @return string list of service titles for subscriptions and workflows
     */
    public function get_user_services (String $type, String $key, String $value)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/$type");
        $services_list = "";
        foreach ($services_set as $service)
            if ((intval($value) & intval($service["Flag"])) > 0)
                $services_list .= $service["Titel"] . ", ";
        $change_link = (strcasecmp($type, "subscriptions") == 0) ? "<br><a href='../forms/subskriptionen_aendern.php'> &gt; ändern</a>" : "";
        return "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . $services_list .
                 $change_link . "</td></tr>\n";
    }

    /*
     * ======================== Generic user property management ==============================
     */
    /**
     * Provide a list of attributes for which the user is registered
     *
     * @param int $user_id
     *            Mitgliedsnummer of user.
     * @param String $attribute
     *            either "Funktionen", "Ehrungen" or "Spinde", i. e. the table name of the attribute
     *            table
     * @param String $period_definition
     *            the definition of the time stamp relations, e.g. "am", "seit", "von - bis" of the
     *            respective field in the user record
     * @param int $attr_at
     *            the position of the attribute name within the table row
     * @param int $start_at
     *            the position of the period start within the table row
     * @param int $end_at
     *            the position of the period end within the table row
     * @param bool $short
     *            set true to get a simple string instead of table rows. Default: false
     * @return string an html formatted attributes table
     */
    public function get_user_attributes (int $user_id, Tfyh_socket $socket, String $attribute, 
            String $period_definition, int $attr_at, int $start_at, int $end_at, bool $short = false)
    {
        $html_str = "<tr><td><b>$attribute</b>&nbsp;&nbsp;&nbsp;</td><td>$period_definition:</td></tr>\n";
        $html_short = "<tr><td><b>$attribute</b></td><td>\n";
        $sql_cmd = "SELECT * FROM `$attribute` WHERE `" . $this->user_id_field_name . "`='" .
                 $user_id . "'";
        $res = $socket->query($sql_cmd);
        $r = 0;
        if ($res !== false)
            do {
                $r ++;
                $row = $res->fetch_row();
                if (! is_null($row)) {
                    $html_str .= "<tr><td>&nbsp;&nbsp;&nbsp;" . htmlspecialchars($row[$attr_at]) .
                             "</td><td>" . $row[$start_at];
                    $html_short .= "&nbsp;" . htmlspecialchars($row[$attr_at]);
                    $end_string = (!is_null($row[$end_at]) && (strpos($row[$end_at], "0000-00-00") === false)) ? $row[$end_at] : "heute";
                    if (strpos($period_definition, "-") != false) {
                        $html_str .= " - " . $end_string;
                        $html_short .= ":&nbsp;" . $row[$start_at] . " - " . $end_string;
                    } else 
                        $html_short .= "&nbsp;$period_definition: " . $row[$start_at];
                        
                    $html_str .= "</td></tr>\n";
                    $html_short .= " / ";
                } elseif ($r === 1) {
                    // now rows at all. Return empty String
                    $html_str = "";
                    $html_short = "";
                }
            } while ($row);
        else {
            // now rows at all. Return empty String
            $html_str = "";
            $html_short = "";
        }
        if (strlen($html_short) > 2) $html_short = substr($html_short, 0, strlen($html_short) - 2);
        return ($short) ? $html_short : $html_str;
    }

    /**
     * Check within Mitgliederliste and Mitgliederarchiv whether a first name and name already exist
     * to avoid name duplicates.
     *
     * @param array $new_user
     *            the new user to check. must contain at least a valid
     *            $new_user[$this->user_lastname_field_name] and
     *            $new_user[$this->user_firstname_field_name]
     * @param Tfyh_socket $socket
     *            The socket to the data base.
     * @return string[] false, if no new user was found. Else an array with the last match carrying
     *         the "Status", $this->user_firstname_field_name, $this->user_lastname_field_name,
     *         $this->user_id_field_name.
     */
    public function check_new_user_name_for_duplicates (array $new_user, Tfyh_socket $socket)
    {
        // check users for identical name. Information will be provided to clarify offline, whether
        // a user has returned or a duplicate name exists. Get those with the same first name.
        $previous_user = [];
        
        if (strlen($this->user_archive_table_name) > 0) {
            $archived_users = $socket->find_records($this->user_archive_table_name, 
                    $this->user_firstname_field_name, $new_user[$this->user_firstname_field_name], 
                    100);
            // check for equality of last name.
            foreach ($archived_users as $archived_user) {
                if (strcasecmp($new_user[$this->user_lastname_field_name], 
                        $archived_user[$this->user_lastname_field_name]) == 0) {
                    $previous_user["Status"] = "archiviert";
                    $previous_user[$this->user_firstname_field_name] = $archived_user[$this->user_firstname_field_name];
                    $previous_user[$this->user_lastname_field_name] = $archived_user[$this->user_lastname_field_name];
                    $previous_user[$this->user_id_field_name] = $archived_user[$this->user_id_field_name];
                }
            }
        }
        // now repeat for all active users.
        $active_users = $socket->find_records($this->user_table_name, 
                $this->user_firstname_field_name, $new_user[$this->user_firstname_field_name], 100);
        // check for equality of last name.
        foreach ($active_users as $active_user) {
            if (strcasecmp($new_user[$this->user_lastname_field_name], 
                    $active_user[$this->user_lastname_field_name]) == 0) {
                $previous_user["Status"] = "aktiv";
                $previous_user[$this->user_firstname_field_name] = $active_user[$this->user_firstname_field_name];
                $previous_user[$this->user_lastname_field_name] = $active_user[$this->user_lastname_field_name];
                $previous_user[$this->user_id_field_name] = $active_user[$this->user_id_field_name];
            }
        }
        return (count($previous_user) == 0) ? false : $previous_user;
    }

    /**
     * Return the respective link set for allowed actions of a verified user regarding the user to
     * modify.
     *
     * @param int $user_id
     *            the ID of the user for which the action shallt be taken
     * @return an HTML formatted String with the links to the actions allowed
     */
    public function get_action_links (int $user_id)
    {
        $action_links_html = "";
        $a = 0;
        foreach ($this->action_links as $action_link) {
            $parts = explode(":", $action_link);
            if ($this->is_allowed_item($parts[0]))
                $action_links_html .= str_replace("{#ID}", $user_id, $parts[1]);
        }
        return $action_links_html;
    }

    /**
     * Get an empty user for this application
     */
    public function get_empty_user ()
    {
        $user = array();
        $user[$this->user_id_field_name] = - 1;
        $user["ID"] = 0;
        $user["Rolle"] = $this->anonymous_role;
        if ($this->use_subscriptions)
            $user["Subskriptionen"] = 0;
        if ($this->use_workflows)
            $user["Workflows"] = 0;
        if ($this->use_concessions)
            $user["Concessions"] = 0;
        return $user;
    }
}