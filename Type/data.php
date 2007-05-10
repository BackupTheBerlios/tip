<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Data definition file
 * @package TIP
 */


/**
 * Ascending order, used by TIP_Query::setOrder()
 */
define('TIP_ASCENDING', false);

/**
 * Descending order, used by TIP_Query::setOrder()
 */
define('TIP_DESCENDING', true);


/**
 * A generic data provider
 *
 * @final
 * @package  TIP
 * @tutorial TIP/DataEngine/DataEngine.pkg#TIP_Data
 */
class TIP_Data extends TIP_Type
{
    /**#@+ @access private */

    /**
     * The data identifier
     *
     * The path (or name) which univoquely identify the data.
     * This field is filled by TIP_Data during this class instantiation.
     *
     * @var string
     */
    var $_path = null;

    /**
     * The data engine
     * @var TIP_Data_Engine
     */
    var $_engine = null;

    /**
     * The primary key field
     *
     * Defaults to 'id' but can be changed passing a different 'primary_key'
     * option. The primary key univoquely identifies a single row of data.
     *
     * @var $string
     */
    var $_primary_key = 'id';

    /**
     * The last id
     *
     * Contains the last id of the putRow() method, if any.
     */
    var $_last_id = null;

    /**
     * Fields of $_path to use in queries
     *
     * An array of field ids used by this object. If left null, all the fields
     * of $_path are assumed.
     * @var array
     */
    var $_fieldset = null;

    /**
     * Join definitions
     * @var array
     */
    var $_joins = null;

    /**
     * Fields structure
     * @var array
     */
    var $_fields = null;
    
    /**
     * Has the $this->_engine->fillFields() method been called?
     * @var bool
     */
    var $_detailed = false;


    function _castField(&$value, $key)
    {
        if (array_key_exists($key, $this->_fields)) {
            $field =& $this->_fields[$key];
            if ((!is_null($value) || !$field['can_be_null']) && !settype($value, $field['type'])) {
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
    function _castRow(&$row)
    {
        $this->getFields(false);
        array_walk($row, array(&$this, '_castField'));
    }

    function _mergeFieldInfo(&$field)
    {
        if (isset($field['info'])) {
            $info = TIP::doubleExplode('|', '=', $field['info']);
            $field = array_merge($field, $info);
        }
    }

    function _validate(&$row)
    {
        $this->getFields(false);

        // Keep only the keys that are fields
        $row = array_intersect_key($row, $this->_fields);

        if (isset($this->_fieldset)) {
            // Apply the fieldset filter
            $row = array_intersect_key($row, array_flip($this->_fieldset));
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

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Data instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function TIP_Data($id, $args)
    {
        $this->TIP_Type($id);

        $this->_path = $args['path'];
        $this->_joins = $args['joins'];
        $this->_engine =& $args['engine'];

        if (isset($args['primary_key'])) {
            $this->_primary_key =& $args['primary_key'];
        }

        if (isset($args['fieldset'])) {
            $this->_fieldset =& $args['fieldset'];
        }
    }

    /**
     * Build a TIP_Data identifier
     *
     * $args must be an array with at least the following items:
     * - $args['path']: the path/table to use
     * - $args['engine']: a reference to the data engine
     *
     * The following items are optionals:
     * - $args['fieldset']: an array of fields to select
     * - $args['joins']: an associative array of joins to other data
     * - $args['primary_key']: the field to use as primary key instead of the
     *                         default one (that is 'id')
     *
     * @param  array  $args The constructor arguments
     * @return string       The data identifier
     */
    function buildId($args)
    {
        $id = $args['engine']->getId() . ':' . $args['path'];
        if (isset($args['fieldset'])) {
            $id .= '(' . implode(',', $args['fieldset']) . ')';
        }
        return $id;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Get the path
     *
     * Returns the path of this data object.
     *
     * @return string The requested data path
     */
    function getPath()
    {
        return $this->_path;
    }

    /**
     * Get the primary key
     *
     * Returns the primary key of this data object.
     *
     * @return string The primary key field
     */
    function getPrimaryKey()
    {
        return $this->_primary_key;
    }

    /**
     * Get the last id
     *
     * Returns the last id as returned by the last call to putRow().
     *
     * @return mixed The last id
     */
    function getLastId()
    {
        return $this->_last_id;
    }

    /**
     * Create a basic filter
     *
     * Creates a filter (in the proper engine format) to access the rows that
     * match the specified filter. If $condition is not specified, it defaults
     * to '=' (equal).
     *
     * @param string $name      A field id
     * @param mixed  $value     The reference value
     * @param string $condition The condition to apply
     * @return string The requested filter in the proper engine format
     */
    function filter($name, $value, $condition = '=')
    {
        return $this->addFilter('WHERE', $name, $value, $condition);
    }

    function addFilter($connector, $name, $value, $condition = '=')
    {
        // Special condition
        if (is_null($value) && strpos('is', strtolower($condition)) === false) {
            $condition = $condition == '=' ? 'IS' : 'IS NOT';
        }

        $name = $this->_engine->_preparedName($name);
        $path = $this->_engine->_preparedName($this->_path);
        $name = $path . '.' . $name;

        $value = $this->_engine->_preparedValue($value);
        return ' ' . $connector . ' '. $name . ' ' . $condition . ' ' . $value;
    }

    /**
     * Create a basic order clause
     *
     * Builds the order clause (in the proper engine format) to sort the rows
     * using the specified field.
     *
     * @param string $name       A field id
     * @param bool   $descending true for descending order
     * @return string The requested order clause in the proper engine format
     */
    function order($name, $descending = false)
    {
        $name = $this->_engine->_preparedName($name);
        $tail = $descending ? ' DESC' : '';
        return 'ORDER BY ' . $name . $tail;
    }

    /**
     * Create a filter to access a single row
     *
     * Shortcut to create a filter (in the proper engine format) to access only
     * the first row with the primary key matching $value. Useful when the
     * primary key is unique to access records by id.
     *
     * @param mixed $id The primary key value
     * @return string The requested filter in the proper engine format
     */
    function rowFilter($id)
    {
        return $this->filter($this->_primary_key, $id) . ' LIMIT 1';
    }

    /**
     * Get the fields structure
     *
     * Gets the field structure of this data context.
     *
     * @param bool $detailed Force a TIP_Data_Engine::fillDetails() call
     * @return array|null The field structure or null on errors
     * @tutorial TIP/DataEngine/DataEngine.pkg#fields
     */
    function& getFields($detailed = true)
    {
        if (is_null($this->_fields) || $detailed && !$this->_detailed) {
            $this->_detailed = true;
            if ($this->_engine->fillFields($this)) {
                if (is_array($this->_fields)) {
                    array_walk($this->_fields, array(&$this, '_mergeFieldInfo'));
                } else {
                    TIP::error('Populated fields are not an array (' . gettype($this->_fields) . ')');
                }
            } else {
                TIP::error("no way to get the table structure ($data->_path)");
            }
        }

        return $this->_fields;
    }

    /**
     * Read one row
     *
     * Retrieves the content of the row with the specified $id.
     *
     * @param mixed $id The row id
     * @return array|null The row matching the specified id or null on errors
     */
    function& getRow($id)
    {
        $rows =& $this->_engine->select($this, $this->rowFilter($id));
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
     * @param string $filter The filter conditions
     * @return array|null The array of rows matching the specified filter or
     *                    null on errors
     * @tutorial TIP/DataEngine/DataEngine.pkg#rows
     */
    function& getRows($filter)
    {
        $rows =& $this->_engine->select($this, $filter);
        if (is_array($rows)) {
            array_walk($rows, array(&$this, '_castRow'));
        }
        return $rows;
    }


    /**#@+ @return bool true on success or false on errors */

    /**
     * Insert a new row
     *
     * Inserts a new row in the data source. If the insert() method returns an
     * autoincrement value, it will be used as the primary key of $row.
     *
     * If $row is an empty array, a new row with default values will be
     * inserted without errors.
     *
     * Also, this method is subject to the fieldset: if $_fieldset is set,
     * only the fields present in this subset will be inserted.
     *
     * Instead, if the primary key is defined in $row, this function fails if
     * a row with the same primary key exists.
     *
     * @param array &$row The row to insert
     */
    function putRow(&$row)
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

        $result = $this->_engine->insert($this, $rows);
        if (is_null($result)) {
            return false;
        }

        if (empty($row[$this->_primary_key]) && $result) {
            // Set the primary key to the last autoincrement value, if any
            $row[$this->_primary_key] = $result;
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
     * @param array $rows An array of rows to insert
     */
    function putRows($rows)
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

        $result = $this->_engine->insert($this, $rows);
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
     * @param array $row     The new row content
     * @param array $old_row The old row content
     */
    function updateRow($row, $old_row = null)
    {
        if (!is_array($row)) {
            $type = gettype($row);
            TIP::error("unhandled row type ($type)");
            return false;
        }

        if (@array_key_exists($this->_primary_key, $old_row)) {
            $id = $old_row[$this->_primary_key];
        } 
        
        if (@array_key_exists($this->_primary_key, $row)) {
            $id = $row[$this->_primary_key];
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

        return $this->_engine->update($this, $this->rowFilter($id), $row);
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
     * @param string $filter The filter conditions
     * @param array  $row    A row with the field => value pairs to update
     */
    function updateRows($filter, $row)
    {
        if (!is_array($row)) {
            $type = gettype($row);
            TIP::error("unhandled row type ($type)");
            return false;
        }

        // Found a primary key: error
        if (array_key_exists($this->_primary_key, $row)) {
            $key = $row[$this->_primary_key];
            TIP::error("updateRows() impossible with a primary key defined ($key)");
            return false;
        }

        // Get the valid set
        if (!$this->_validate($row)) {
            return false;
        }

        return $this->_engine->update($this, $filter, $row);
    }

    /**
     * Delete one row
     *
     * Deletes the row with the specified $id.
     *
     * @param mixed $id The row id
     */
    function deleteRow($id)
    {
        return $this->_engine->delete($this, $this->rowFilter($id));
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
     * @param string $filter The row filter
     */
    function deleteRows($filter)
    {
        // Empty filter are by default not accepted
        if (empty($filter)) {
            return false;
        }

        return $this->_engine->delete($this, $filter);
    }

    /**#@-*/

    /**#@-*/
}

?>