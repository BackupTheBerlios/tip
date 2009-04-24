<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Data definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
 */

/**
 * A generic data provider
 *
 * All the queries are "prepared" accordling to the data engine used.
 * This means the parameters are modified by calling preparedName() and
 * preparedValue() of the TIP_Data_Engine object.
 *
 * Prepending the at symbol (@) to a name means that name must be
 * passed-throught without modification (a raw name).
 *
 * @package  TIP
 */
class TIP_Data extends TIP_Type
{
    //{{{ Properties

    /**
     * The data path
     * @var string
     */
    protected $path = null;

    /**
     * The data engine
     * @var TIP_Data_Engine
     */
    protected $engine = null;

    /**
     * The primary key field
     *
     * Defaults to 'id' but can be changed passing a different 'primary_key'
     * option. The primary key univoquely identifies a single row of data.
     *
     * @var $string
     */
    protected $primary_key = 'id';

    /**
     * Fields to use in queries
     *
     * An array of field ids used by this object. If left null, all the fields
     * are assumed.
     * @var array
     */
    protected $fieldset = null;

    /**
     * Join definitions
     * @var array
     */
    protected $joins = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Check the options
     *
     * Overridable static method that checks $options for missing or invalid
     * values and eventually corrects its content.
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['path'])) {
            return false;
        }

        TIP::requiredOption($options, 'data_engine');

        if (is_string($options['data_engine'])) {
            $options['engine'] =& TIP_Type::singleton(array(
                'type' => array('data_engine', $options['data_engine'])
            ));
        } elseif (is_array($options['data_engine'])) {
            $options['engine'] =& TIP_Type::singleton($options['data_engine']);
        } else {
            $options['engine'] =& $options['data_engine'];
        }

        unset($options['data_engine']);

        if (!$options['engine'] instanceof TIP_Data_Engine) {
            return false;
        }

        // Forced overriding of the default id ('data')
        $options['id'] = $options['engine']->getProperty('id') . ':' . $options['path'];
        isset($options['fieldset']) && $options['id'] .= '(' . implode(',', $options['fieldset']) . ')';

        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Data instance.
     *
     * $options inherits the TIP_Type properties, and add the following:
     * - $options['path']:        the path to the data source or the table name
     * - $options['engine']:      the TIP_Data_Engine options to use
     * - $options['primary_key']: the primary key field
     * - $options['fieldset']:    fields to use in queries
     * - $options['join']:        join definitions
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Methods

    /**
     * Get the last id
     *
     * Returns the last id as returned by the last call to putRow().
     *
     * @return mixed The last id
     */
    public function getLastId()
    {
        return $this->_last_id;
    }

    /**
     * Get the reference of the $_fields internal property
     *
     * Mainly used by the data engine to fill the fields structure.
     *
     * @return array A reference to $_fields
     */
    public function &getFieldsRef()
    {
        return $this->_fields;
    }

    /**
     * Create a basic filter
     *
     * Creates a filter (in the proper engine format) to access the rows that
     * match the specified filter. If $condition is not specified, it defaults
     * to '=' (equal).
     *
     * @param  string $name      A field id
     * @param  mixed  $value     The reference value
     * @param  string $condition The condition to apply
     * @return string            The filter in the proper engine format
     */
    public function filter($name, $value, $condition = '=')
    {
        return $this->addFilter('WHERE', $name, $value, $condition);
    }

    /**
     * Create an appendable filter expression
     *
     * Creates a portion of a filter (in the proper engine format) that can
     * be added to the main filter (generated throught the filter() method).
     *
     * @param  string $connector A boolean expression
     * @param  string $name      A field id
     * @param  mixed  $value     The reference value
     * @param  string $condition The condition to apply
     * @return string            The filter portion in the proper engine format
     */
    public function addFilter($connector, $name, $value, $condition = '=')
    {
        // Special condition
        if (is_null($value) && strpos('is', strtolower($condition)) === false) {
            $condition = $condition == '=' ? 'IS' : 'IS NOT';
        }

        if ($name{0} == '@') {
            $name = substr($name, 1);
        } else {
            $name = $this->engine->preparedName($name);
            $path = $this->engine->preparedName($this->path);
            $name = $path . '.' . $name;
        }

        $value = is_array($value) ? reset($value) : $this->engine->preparedValue($value);
        return ' ' . $connector . ' '. $name . ' ' . $condition . ' ' . $value;
    }

    /**
     * Create a basic order clause
     *
     * Builds the order clause (in the proper engine format) to sort the rows
     * using the specified field. This clause can be appended to a main filter.
     *
     * @param  string $name       A field id
     * @param  bool   $descending true for descending order
     * @return string             The order clause
     */
    public function order($name, $descending = false)
    {
        if (empty($name)) {
            return '';
        }

        if ($name{0} == '@') {
            $name = substr($name, 1);
        } else {
            $name = $this->engine->preparedName($name);
        }

        $tail = $descending ? ' DESC' : '';
        return ' ORDER BY ' . $name . $tail;
    }

    /**
     * Create a limit clause
     *
     * Builds a limit clause (in the proper engine format).
     *
     * @param  int      $count  Maximum number of rows
     * @param  int|null $offset Starting row (starting from 0)
     * @return string           The limit clause
     */
    public function limit($count, $offset = null)
    {
        $limit = ' LIMIT ';
        isset($offset) && $limit .= (int) $offset . ',';
        $limit .= (int) $count;
        return $limit;
    }

    /**
     * Create a filter to access a single row
     *
     * Shortcut to create a filter (in the proper engine format) to access only
     * the first row with the primary key matching $value. Useful when the
     * primary key is unique to access records by id.
     *
     * @param  mixed  $id The primary key value
     * @return string     The requested filter in the proper engine format
     */
    public function rowFilter($id)
    {
        return $this->filter($this->primary_key, $id) . ' LIMIT 1';
    }

    /**
     * Get the fields structure
     *
     * Gets the field structure of this data context.
     *
     * @param    bool       $detailed Force a TIP_Data_Engine::fillDetails() call
     * @return   array|null           The field structure or null on errors
     */
    public function &getFields($detailed = true)
    {
        if (is_null($this->_fields) || $detailed && !$this->_detailed) {
            $this->_detailed = true;
            if ($this->engine->fillFields($this)) {
                if (is_array($this->_fields)) {
                    array_walk($this->_fields, array(&$this, '_mergeFieldInfo'));
                } else {
                    TIP::error('Populated fields are not an array (' . gettype($this->_fields) . ')');
                }
            } else {
                $id = $this->path;
                TIP::error("no way to get the table structure ($id)");
            }
        }

        return $this->_fields;
    }

    /**
     * Get a field type
     *
     * Gets the field type by updating the internal field structure and
     * searching for $id. If it is not found, a recursive search is
     * performed on the joined TIP_Data objects (if any).
     *
     * @param    mixed       $id A field identifier
     * @return   string|null     The requested field type or null on errors
     */
    public function getFieldType($id)
    {
        // Update the fields structure (no details needed)
        if (is_null($this->getFields(false))) {
            return null;
        }

        // Check if the field is found in the current structure
        if (array_key_exists($id, $this->_fields)) {
            return @$this->_fields[$id]['type'];
        }

        // Check if there are defined joins (the requested field could
        // be found in a joined table)
        if (!is_array($this->joins)) {
            return null;
        }

        // Recurse into the joined TIP_Data
        $options = array('type' => array('data'));
        foreach (array_keys($this->joins) as $path) {
            $options['path'] = $path;
            $data = TIP_Type::singleton($options);
            if (!is_null($type = $data->getFieldType($id))) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Read one row
     *
     * Retrieves the content of the row with the specified $id.
     *
     * @param  mixed      $id     The row id
     * @param  array|null $fields Fields to get or null for all
     * @return array|null         The row matching the specified id
     *                            or null on errors
     */
    public function &getRow($id, $fields = null)
    {
        isset($fields) || $fields = $this->fieldset;
        $rows =& $this->engine->select($this, $this->rowFilter($id), $fields);
        if (!@is_array($rows[$id])) {
            $fake_null = null;
            return $fake_null;
        }

        $this->_castRow($rows[$id]);
        return $rows[$id];
    }

    /**
     * Read more rows
     *
     * Gets the rows that satisfy the $filter conditions. The syntax of the
     * filter is data engine dependent: no assumptions are made by the TIP_Data
     * class. Whenever possible, use the filter() and rowFilter() to build a
     * proper filter.
     *
     * @param    string     $filter The filter conditions
     * @param    array|null $fields Fields to get or null for all
     * @return   array|null         The array of rows matching $filter
     *                              or null on errors
     */
    public function &getRows($filter, $fields = null)
    {
        isset($fields) || $fields = $this->fieldset;
        $rows =& $this->engine->select($this, $filter, $fields);
        if (is_array($rows)) {
            array_walk($rows, array(&$this, '_castRow'));
        }
        return $rows;
    }

    /**
     * Insert a new row
     *
     * Inserts a new row in the data source. If the insert() method returns an
     * autoincrement value, it will be used as the primary key of $row.
     *
     * If $row is an empty array, a new row with default values will be
     * inserted without errors.
     *
     * Also, this method is subject to the fieldset: if $fieldset is set,
     * only the fields present in this subset will be inserted.
     *
     * Instead, if the primary key is defined in $row, this function fails if
     * a row with the same primary key exists.
     *
     * @param  array &$row The row to insert
     * @return bool        true on success or false on errors
     */
    public function putRow(&$row)
    {
        if (!is_array($row)) {
            $type = gettype($row);
            TIP::error("unhandled row type ($type)");
            return false;
        }

        if (empty($row)) {
            $rows = array();
        } else {
            // Get the valid set
            if (!$this->_validate($row)) {
                return false;
            }
            $rows = array($row);
        }

        $result = $this->engine->insert($this, $rows);
        if (is_null($result)) {
            return false;
        }

        if (empty($row[$this->primary_key]) && $result) {
            // Set the primary key to the last autoincrement value, if any
            $row[$this->primary_key] = $result;
            $this->_last_id = $result;
        }

        return true;
    }

    /**
     * Insert more rows
     *
     * Multiple inserts rows in the data source.
     *
     * This method, as its single row counterpart, is subject to the fieldset
     * in the same way putRow() is.
     *
     * The primary keys can be defined, but if someone of them is yet present
     * in the data source, this function will fail.
     *
     * @param  array $rows An array of rows to insert
     * @return bool        true on success or false on errors
     */
    public function putRows($rows)
    {
        if (!is_array($rows)) {
            $type = gettype($rows);
            TIP::error("unhandled rows type ($type)");
            return false;
        }

        array_walk($rows, array(&$this, '_validate'));
        if (empty($rows) || empty($rows[0])) {
            TIP::error('no valid rows to insert');
            return false;
        }

        $result = $this->engine->insert($this, $rows);
        return !is_null($result);
    }

    /**
     * Update one row
     *
     * Updates $row. If $old_row is specified, the id of the row to update is
     * get from the primary key of $old_row. If it does not exists (or $old_row
     * is not used) the id will be the primary key of $row. If not found yet,
     * the function will fail because does not know the id to update.
     *
     * $old_row is used also as filter to remove matching field contents between
     * $row and $old_row. This is done to allow a check between the old and new
     * row content, trying to avoid the update operation.
     *
     * @param  array $row     The new row content
     * @param  array $old_row The old row content
     * @return bool           true on success or false on errors
     */
    public function updateRow($row, $old_row = null)
    {
        if (!is_array($row)) {
            $type = gettype($row);
            TIP::error("unhandled row type ($type)");
            return false;
        }

        if (@array_key_exists($this->primary_key, $old_row)) {
            $id = $old_row[$this->primary_key];
        } 
        
        if (@array_key_exists($this->primary_key, $row)) {
            $id = $row[$this->primary_key];
        }
       
        if (!isset($id)) {
            TIP::error('undefined row to update');
            return false;
        }

        // Get the valid set
        if (!$this->_validate($row)) {
            return false;
        }

        if (@is_array($old_row)) {
            // Keep only the items that differs between $row and $old_row
            $row = array_diff_assoc($row, $old_row);
        }

        if (empty($row)) {
            // No fields to update
            return true;
        }

        return $this->engine->update($this, $this->rowFilter($id), $row);
    }

    /**
     * Update more rows
     *
     * Updates the rows that match the $filter conditions accordling to the
     * $row array, which must be a collection of "fieldid => value" to change.
     * The syntax of $filter is data engine dependent: no assumptions are made
     * by the TIP_Data class. This also means the $filter parameter must be
     * $filter.
     *
     * This function must be used to update more than one row: this means
     * $row must not have the primary key defined (a primary key is unique).
     * To update only one row, use updateRow() instead.
     *
     * @param  string $filter The filter conditions
     * @param  array  $row    A row with the field => value pairs to update
     * @return bool           true on success or false on errors
     */
    public function updateRows($filter, $row)
    {
        if (!is_array($row)) {
            $type = gettype($row);
            TIP::error("unhandled row type ($type)");
            return false;
        }

        // Found a primary key: error
        if (array_key_exists($this->primary_key, $row)) {
            $key = $row[$this->primary_key];
            TIP::error("updateRows() impossible with a primary key defined ($key)");
            return false;
        }

        // Get the valid set
        if (!$this->_validate($row)) {
            return false;
        }

        return $this->engine->update($this, $filter, $row);
    }

    /**
     * Delete one row
     *
     * Deletes the specified row. $row could be the row id or the
     * row array to delete.
     *
     * @param  mixed $row The row to delete or the row id
     * @return bool       true on success or false on errors
     */
    public function deleteRow($row)
    {
        if (is_array($row)) {
            if (!array_key_exists($this->primary_key, $row)) {
                TIP::error('no primary key found to delete');
                return false;
            }
            $row = $row[$this->primary_key];
        }
        return $this->engine->delete($this, $this->rowFilter($row));
    }

    /**
     * Delete more rows
     *
     * Deletes the rows matching the $filter conditions. The syntax of $filter
     * is data engine dependent: no assumptions are made by the TIP_Data class.
     * This also means the $filter parameter must be prepared for the engine:
     * use the prepare... primitives provided by the data engine.
     *
     * Notice empty filter are rejected by the engine to avoid dropping the
     * whole content of a table.
     *
     * @param  string $filter The row filter
     * @return bool           true on success or false on errors
     */
    public function deleteRows($filter)
    {
        // Empty filter are by default not accepted
        if (empty($filter)) {
            return false;
        }

        return $this->engine->delete($this, $filter);
    }

    //}}}
    //{{{ Internal properties

    /**
     * Fields structure
     * @var array
     * @internal
     */
    private $_fields = null;
    
    /**
     * Has the $this->engine->fillFields() method been called?
     * @var bool
     * @internal
     */
    private $_detailed = false;

    /**
     * The last id as returned by the putRow() method, if any
     * @var mixed|null
     * @internal
     */
    private $_last_id = null;

    //}}}
    //{{{ Internal methods

    private function _castField(&$value, $key)
    {
        if (array_key_exists($key, $this->_fields)) {
            $field =& $this->_fields[$key];
            if ((!is_null($value) || !@$field['can_be_null']) && !settype($value, $field['type'])) {
                TIP::warning("invalid type for field['$key'] => $value ($field[type])");
            }
        }
    }

    /**
     * Cast a row content to the proper type
     *
     * Given a row, forces every field in the row that matches the field
     * structure in this context to be of the type specified by getFields().
     * The type forcing is done using settype().
     *
     * At least, the non-detailed part of the $_fields property MUST be filled
     * before calling this method.
     *
     * @param array &$row The row to cast
     */
    private function _castRow(&$row)
    {
        $this->getFields(false);
        array_walk($row, array(&$this, '_castField'));
    }

    private function _mergeFieldInfo(&$field)
    {
        if (isset($field['info'])) {
            $info = TIP::doubleExplode('|', '=', $field['info']);
            $field = array_merge($field, $info);
        }
    }

    private function _validate(&$row)
    {
        $this->getFields(false);

        // Keep only the keys that are fields
        $row = array_intersect_key($row, $this->_fields);

        if (isset($this->fieldset)) {
            // Apply the fieldset filter
            $row = array_intersect_key($row, array_flip($this->fieldset));
        }

        // Check for empty set
        if (empty($row)) {
            TIP::error('no valid field found');
            return false;
        }

        // Cast the set to the proper type
        $this->_castRow($row);
        return true;
    }

    //}}}
}
?>
