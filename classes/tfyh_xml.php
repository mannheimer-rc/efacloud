<?php

/**
 * class file for simple XML reading without library support.
 */
class Tfyh_xml
{

    /**
     * Collection of all tag ids found
     */
    public $tag_ids = [];

    /**
     * Single xml root tag which is not part of the tree, but the header
     */
    public $xmlroot = [];

    /**
     * Collection of all tags in a tree structure
     */
    public $xml_tree = null;

    /**
     * XML string to parse.
     */
    private $xml = "";

    /**
     * parser position.
     */
    private $l = 0;

    /**
     * currently open tag in parser.
     */
    private $ctag = [];

    /**
     * currently open tag in parser.
     */
    private $toolbox;

    /**
     * public empty Constructor.
     */
    public function __construct (Tfyh_toolbox $toolbox = null)
    {
        // echo "Tfyh_xml::__construct().<br>";
        include_once "../classes/tfyh_xml_tag.php";
        $this->toolbox = $toolbox;
    }

    /**
     * Read an XML tag within an eFa file and its following text. No self closing tags allowed.
     * 
     * @param String $xml
     *            XML String to parse
     * @param int $offset
     *            psotion to start with
     * @return mixed if no tag was found: false. Else the tag including its text following
     */
    private function read_next_tag ()
    {
        // find tag itself
        $pos_lt = strpos($this->xml, "<", $this->l);
        $pos_gt = strpos($this->xml, ">", $pos_lt + 1);
        
        // none to come, return false
        if (($pos_lt === false) || ($pos_gt === false))
            return false;
        // get tag id and attributes (no attribute parsing)
        $id_attr = substr($this->xml, $pos_lt + 1, $pos_gt - $pos_lt - 1);
        $id = explode(" ", $id_attr, 2)[0];
        $attr = (strpos($id_attr, " ") === false) ? "" : explode(" ", $id_attr, 2)[1];
        // get the following text and classify the tag as open or close
        $pos_lt = strpos($this->xml, "<", $pos_gt + 1);
        if ($pos_lt === false) {
            $txt = "";
            $this->l = strlen($this->xml);
        } else {
            $txt = substr($this->xml, $pos_gt + 1, $pos_lt - $pos_gt - 1);
            $this->l = $pos_lt;
        }
        // cleanse the text and put it to the correct position
        $txt = trim(str_replace("  ", " ", str_replace("  ", " ", str_replace("\n", " ", $txt))));
        $tag = new Tfyh_xml_tag();
        $tag->id = $id;
        $tag->attr = $attr;
        if (strpos($id, "/", 0) === 0) {
            $tag->txt_c = $txt;
            $tag->is_close = true;
        } else {
            $tag->txt_o = $txt;
            $tag->is_close = false;
        }
        // add the id to the flat ids list.
        if (! $tag->is_close)
            if (! isset($this->tag_ids[$id]))
                $this->tag_ids[$id] = 1;
            else
                $this->tag_ids[$id] ++;
        return $tag;
    }

    /**
     * Read an XML string into $this->xml_tree. Encoding must be UTF-8. READ PER 1,000 TAGS READ.
     * 
     * @param String $xml
     *            the String to be parsed
     * @param String $echo
     *            echo dots on each 5000 characters progress and a "<br>" at the end
     * @return Tfyh_xml_tag the root tag of the structure read.
     */
    public function read_xml (String $xml, bool $echo = false)
    {
        if ($echo)
            echo " parsing ";
        $this->tag_ids = [];
        $this->l = 0;
        $this->xml = $xml;
        // read first tag. skip it, if it is the xml definition
        $this->xmlroot = $this->read_next_tag();
        if (strcasecmp($this->xmlroot->id, "?xml") == 0)
            $this->xml_tree = $this->read_next_tag();
        else
            $this->xml_tree = $this->xmlroot;
        
        // read tree recursivly from root.
        $this->ctag = $this->xml_tree;
        $i = 0;
        flush();
        ob_flush();
        do {
            // read the tag
            $tag = $this->read_next_tag();
            if ($tag !== false) {
                // there was a tag, handle it.
                if ($tag->is_close) {
                    // hand over to parent on closing
                    $this->ctag->txt_c = $tag->txt_c;
                    $this->ctag = $this->ctag->parent;
                } else {
                    // add child on opening
                    $this->ctag->children[] = $tag;
                    // add parent to new tag
                    $tag->parent = $this->ctag;
                    // change current context to the new tag
                    $this->ctag = $tag;
                }
                // provide some progress output.
                $i ++;
                if (($i % 5000) == 0) {
                    if ($echo)
                        echo ".";
                    flush();
                    ob_flush();
                }
            }
        } while ($tag !== false);
        if ($echo)
            echo "<br>";
        return $this->xml_tree;
    }

    /**
     * Look for all children of this $branch_tag
     * 
     * @param Tfyh_xml_tag $branch_tag
     *            the branhch tag to check
     * @param String $tag_id_to_find
     *            the ID to find
     */
    private function find_first_tag_in_branch (Tfyh_xml_tag $branch_tag, String $tag_id_to_find)
    {
        foreach ($branch_tag->children as $child_tag) {
            $child_found = null;
            if (strcasecmp($child_tag->id, $tag_id_to_find) == 0)
                $child_found = $child_tag;
            elseif (count($child_tag->children) > 0)
                $child_found = $this->find_first_tag_in_branch($child_tag, $tag_id_to_find);
            if (! is_null($child_found)) {
                return $child_found;
            }
        }
    }

    /**
     * Find the tag with the given $table_root_tag_id closest to the root and create a table with all records.
     * For it to work the $table_root_tag must only contain $table_records_tags and they must not have more
     * than one level of subtags.
     * 
     * @param String $table_root_tag_id
     *            the id of the tables root tag, e.g. 'data'
     * @param String $table_records_tag_id
     *            the id of each record within the table, e.g. 'record'
     */
    public function get_csv (String $table_root_tag_id, String $table_records_tag_id)
    {
        // parse table
        $table_root = $this->find_first_tag_in_branch($this->xml_tree, $table_root_tag_id);
        $fieldnames = [];
        $records = [];
        foreach ($table_root->children as $record_xml) {
            $record_array = [];
            foreach ($record_xml->children as $field) {
                if (! isset($fieldnames[$field->id]))
                    $fieldnames[$field->id] = true;
                $record_array[$field->id] = $field->txt_o;
            }
            $records[] = $record_array;
        }
        // output of csv header
        $csv = "";
        foreach ($fieldnames as $fieldname => $exists) {
            $csv .= $fieldname . ";";
        }
        if (strlen($csv) == 0)
            return "empty table";
        $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
        // output of csv data
        foreach ($records as $record) {
            foreach ($fieldnames as $fieldname => $exists) {
                if (isset($record[$fieldname]))
                    $csv .= $this->toolbox->encode_entry_csv($record[$fieldname]);
                $csv .= ";";
            }
            $csv = substr($csv, 0, strlen($csv) - 1) . "\n";
        }
        return $csv;
    }

    /**
     * Write an xml-String based on the provided root tag. No <?xml ...> header tag encluded.
     * 
     * @param Tfyh_xml_tag $xml_tag
     *            the root tag to get the branch for
     * @param String $indent
     *            the indentation for the branch, e. g. " ".
     * @return an array of String with xml-lines. This is used instead of a full String for speed puroses.
     */
    public function xml_lines (Tfyh_xml_tag $xml_tag, String $indent)
    {
        // write open tag including attributes in first line
        $open_tag = $indent . "<" . $xml_tag->id;
        if (strlen($xml_tag->attr) > 0)
            $open_tag .= " " . $xml_tag->attr;
        $xml_lines = [];
        
        // leaf tags (i.e. without children) are put to a single line and the text
        // following the close tag is added in a second line, if existing.
        if (count($xml_tag->children) == 0) {
            $xml_lines[] = $open_tag . ">" . $xml_tag->txt_o . "</" . $xml_tag->id . ">";
            if (strlen($xml_tag->txt_c) > 0)
                $xml_lines[] = $indent . $xml_tag->txt_c;
            return $xml_lines;
        }
        
        // branch tags, first line is tag id.
        $xml_lines[] = $open_tag . ">";
        // write text following the open tag in the second line, if existing
        if (strlen($xml_tag->txt_o) > 0)
            $xml_lines[] = $indent . "  " . $xml_tag->txt_o;
        // add all children, recursively
        foreach ($xml_tag->children as $child_tag) {
            $child_lines = $this->xml_lines($child_tag, "  " . $indent);
            $xml_lines = array_merge($xml_lines, $child_lines);
        }
        // write close tag in second or third line
        $xml_lines[] = $indent . "</" . $xml_tag->id . ">";
        // write text following the close tag in the third/fourth line, if existing
        if (strlen($xml_tag->txt_c) > 0)
            $xml_lines[] = $indent . $xml_tag->txt_c;
        return $xml_lines;
    }
}

?>