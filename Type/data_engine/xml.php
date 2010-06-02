<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_XML definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2009 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.3.3
 */

/**
 * The XML data engine
 *
 * Interface to an XML file. It uses the SimpleXML object to access the
 * underlying XML file. The default options are arranged to parse
 * standard atom 1 feeds, although the "fields_xpath" property should be
 * anyway explicitely configured.
 *
 * The "path" property of TIP_Data should specify a file path relative to
 * the data root directory or an absolute URI beginning with "http://".
 *
 * The XML data engine provides a basic SQL filtering, allowing the
 * following query syntax:
 *
 * <code>
 * [WHERE field {=|<>|>|<} value] [LIMIT length[,offset]]
 * </code>
 *
 * so the following tags are all valids:
 *
 * <code>
 * {forSelect(LIMIT 20,40)}...{}
 * {forSelect(WHERE rating > 123}...{}
 * {forSelect(WHERE id=abcde LIMIT 1)}...{}
 * {forSelect(WHERE title <> 0)}...{}
 * </code>
 *
 * while the ones are not valid (this can change in the future):
 *
 * <code>
 * {forSelect(ORDER BY id)}...{}
 * {forSelect(WHERE title LIKE abc}...{}
 * {forSelect(WHERE rating IS NULL)}...{}
 * {forSelect(WHERE raters < > 4)}...{}
 * </code>
 *
 * @package TIP
 */
class TIP_XML extends TIP_Data_Engine
{
    //{{{ Properties

    /**
     * The base XPath to use while filtering rows (defaults '/feed')
     * @var string
     */
    protected $base_xpath = '/feed';

    /**
     * The row XPath, relative to the $base_xpath: it defaults to 'entry'
     * @var string
     */
    protected $row_xpath = 'entry';

    /**
     * An associative array of 'fieldid' => 'XPath', relative to $row_path
     * @var string
     */
    protected $fields_xpath = array();

    /**
     * The field id of the parent: leave it null for plain models
     * @var string
     */
    protected $parent_field = null;

    //}}}
    //{{{ Costructor/destructor

    /**
     * Ensures the required 'fields_xpath' option is defined
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) ||
            !@is_array($options['fields_xpath'])) {
            return false;
        }

        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_XML instance.
     *
     * $options inherits the TIP_Type properties, and add the following:
     * - $options['base_xpath']:    the base xpath, that is the container
     *                              of the rows (default is '/feed')
     * - $options['row_xpath']:     the row xpath, relative to the
     *                              base_xpath (defaults is 'entry')
     * - $options['fields_xpath']:  an associative array of 'id' => 'xpath',
     *                              where at least one field must be defined
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ TIP_Data_Engine implementation

    public function preparedName($name, $domain = null)
    {
        return is_array($name) ? implode(',', $name) : $name;
    }

    public function preparedValue($value)
    {
        return $value;
    }

    public function query()
    {
        // Not implemented
        assert(false);
        return null;
    }

    public function fillFields(&$data)
    {
        $fields =& $data->getFieldsRef();

        // Consider all fields as string
        foreach (array_keys($this->fields_xpath) as $id) {
            $fields[$id] = array(
                'type'   => 'string',
                'widget' => null,
                'length' => 0
            );
        }

        $primary_key = $data->getProperty('primary_key');
        if (!array_key_exists($primary_key, $fields)) {
            // Primary key not explicitly defined in the XML:
            // will be used an autogenerated sequence
            $fields[$primary_key] = array(
                'type'      => 'int',
                'widget'    => null,
                'length'    => 0,
                'automatic' => true
            );
        }

        if (isset($this->parent_field) &&
            !array_key_exists($this->parent_field, $fields)) {
            // The parent field is defined but need to be
            // automatically calculated: this means the model
            // is non-linear (trees and hierarchies) and the
            // parent field must be set by checking row recursion
            $fields[$this->parent_field] = array(
                'type'   => $fields[$primary_key]['type'],
                'widget' => null,
                'length' => $fields[$primary_key]['length']
            );
        }

        return true;
    }

    public function &select(&$data, $filter, $fields)
    {
        // Always work on a copy of the cached rows
        $rows = $this->_getRows($data, $fields);
        if (empty($rows)) {
            return $rows;
        }

        if (!empty($filter)) {
            // Provide basic SQL filtering
            if (!preg_match('"^\s*(?:WHERE\s+(\S+?)\s*(=|<>|>|<)\s*(\S+))?\s*(?:LIMIT\s*(\d+)(?:\s*,\s*(\d+))?)?\s*$"i', $filter, $matches)) {
                // Unrecognized query: raise a warning and returns an empty set
                TIP::warning("query not recognized ($filter)");
                $rows = array();
                return $rows;
            }

            $field    = @$matches[1];
            $operator = @$matches[2];
            $value    = @$matches[3];
            $length   = @$matches[4];
            $offset   = @$matches[5];

            // Apply the WHERE clause
            if (!empty($field)) {
                // Change the assignment to the (expected) comparison operator
                $operator == '=' && $operator = '==';

                // Array filtering
                $callback = create_function('$row', "return \$row['$field']$operator'$value';");
                $rows = array_filter($rows, $callback);
            }

            // Apply the LIMIT clause
            if (!empty($length)) {
                // array reduction (the LIMIT clause)
                isset($offset) || $offset = 0;
                $rows = array_slice($rows, $offset, $length);
            }
        }

        return $rows;
    }

    public function insert(&$data, &$rows)
    {
        // Not implemented
        assert(false);
        return null;
    }

    public function update(&$data, $filter, &$row)
    {
        // Not implemented
        assert(false);
        return null;
    }

    public function delete(&$data, $filter)
    {
        // Not implemented
        assert(false);
        return null;
    }

    public function dump($root)
    {
        // Not implemented
        assert(false);
        return false;
    }

    protected function transaction($action)
    {
        return true;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The cached rows returned by SimpleXML parsing
     * @var array
     * @internal
     **/
    private $_rows = array();

    /**
     * The current TIP_Data object
     * @var TIP_Data
     * @internal
     **/
    private $_data = null;

    /**
     * The fields to retrieve
     * @var array
     * @internal
     **/
    private $_fields = null;

    //}}}
    //{{{ Internal methods

    private function &_getRows(&$data, $fields)
    {
        $path = $data->getProperty('path');

        if (!array_key_exists($path, $this->_rows)) {
            if (strncmp($path, 'http://', 7) == 0) {
                $uri = $path;

                if (function_exists('curl_init')) {
                    // CURL extension available: this should be the
                    // first attempt because the dumb 'open_basedir'
                    // directive can fuck up file_get_contents()
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $uri);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    $xml_data = curl_exec($curl);
                    curl_close($curl);
                } else if (in_array('http', stream_get_wrappers())) {
                    // http wrapper present
                    $xml_data = file_get_contents($uri);
                } else {
                    // No viable way to use the http protocol
                    $xml_data = false;
                }
            } else {
                $uri = TIP::buildDataPath($data->getProperty('path'));
                $xml_data = file_get_contents($uri);
            }

            $xml_tree = false;
            if (is_string($xml_data)) {
                // Work-around to let SimpleXML be happy with the fucking
                // default namespace
                $xml_data = str_replace(' xmlns=', ' fakens=', $xml_data);
                $xml_tree = @simplexml_load_string($xml_data);
            }

            if ($xml_tree) {
                // Takes only the first element matching "base_xpath"
                $xml = reset($xml_tree->xpath($this->base_xpath));

                $this->_data =& $data;
                if (empty($fields)) {
                    $this->_fields = array_keys($this->fields_xpath);
                } else {
                    $this->_fields = $fields;
                }
                $nodes = $xml->xpath($this->row_xpath);
                $rows = $this->_nodesToRows($nodes);
                unset($nodes, $this->_fields, $this->_data);
            } else {
                $rows = array();
                TIP::error("failed to load XML file ($uri)");
            }

            $this->_rows[$path] = $rows;
        }

        return $this->_rows[$path];
    }

    private function &_nodesToRows($nodes, $parent = null)
    {
        static $autoincrement = 0;

        // Stop recursion
        $rows = null;
        if (empty($nodes)) {
            return $rows;
        }

        if (is_null($parent)) {
            $autoincrement = 1;
        }

        $primary_key = $this->_data->getProperty('primary_key');
        $rows = array();

        while (list(, $node) = each($nodes)) {
            unset($row);
            $row = $this->_nodeToRow($node);
            if (!is_array($row)) {
                // Invalid row: silently skip it
                continue;
            }

            if (!array_key_exists($primary_key, $row)) {
                // The primary key must be autogenerated
                $row[$primary_key] = $autoincrement;
                ++ $autoincrement;
            }

            $id = $row[$primary_key];
            $rows[$id] =& $row;

            if (!isset($this->parent_field)) {
                // No recursion needed
                continue;
            }

            if (!array_key_exists($this->parent_field, $row)) {
                // Parent field not explicitely set by the model
                $rows[$id][$this->parent_field] = $parent;
            }

            $subrows =& $this->_nodesToRows($node->xpath($this->row_xpath), $id);
            if (isset($subrows)) {
                $rows = array_merge($rows, $subrows);
                unset($subrows);
            }
        }

        return $rows;
    }

    /**
     * Convert an XML node to a row
     *
     * Given a node, try to convert it to a row, that is an associative
     * array of fieldid => values, by using the XPaths defined in the
     * "fields_xpath" option.
     *
     * @param SimpleXMLElement $node  The node
     * @return array                  The node converted to a plain row,
     *                                or null on errors
     * @internal
     **/
    private function &_nodeToRow(&$node)
    {
        $row = array();

        foreach ($this->_fields as $field_id) {
            $field_xpath = $this->fields_xpath[$field_id];
            $xml = $node->xpath($field_xpath);
            if (!is_array($xml) || empty($xml)) {
                $row[$field_id] = '';
            } else {
                // Get only the first matching xpath
                $row[$field_id] = (string) reset($xml);
            }
        }

        return $row;
    }

    //}}}
}
?>
