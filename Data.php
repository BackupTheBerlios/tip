<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
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
 * The expression class
 *
 * A class representing a generic expression, used by the TIP_Query to build
 * conditional expressions.
 *
 * @todo Implementation using MDB2
 * @final
 * @package  TIP
 */
class TIP_Expr
{
    var $_lvalue = null;
    var $_operator = null;
    var $_rvalue = null;

    function TIP_Expr($lvalue, $operator, $rvalue)
    {
        $this->_name = $lvalue;
        $this->_operator = $operator;
        $this->_value = $rvalue;
    }
}

/**
 * A generic query interface
 *
 * @final
 * @package  TIP
 *
 * @todo Implementation using MDB2
 */
class TIP_Query
{
    /**#@+
     * This variable must be accessed only by the data engine implementation.
     * @access public
     * @internal
     */

    var $_where = null;
    var $_joins = null;
    var $_order = null;

    /**
     * Limits a select query to this rows count.
     * @var int
     */
    var $_limit = null;

    /**#@-*/


    /**
     * Set the where clause
     *
     * Sets the where conditions for this query, discarding the previous ones.
     * You should specify the conditions in a specific way: take a look to the
     * following examples to get an idea.
     *
     * A simple condition:
     * <code>
     * setWhere(new TIP_Expr('id', '=', 5));
     * </code>
     * 
     * A serie of conditions:
     * <code>
     * setWhere(new TIP_Expr('name', '<>', 'Emmanuele'),
     *   'and', new TIP_Expr('city', '<>', 'Rome'),
     *    'or', new TIP_Expr('date',  '>', '19430908));
     * </code>
     *
     * Sample of nested conditions:
     * <code>
     * setWhere(new TIP_Expr('age', '>', 18), 'and', array(new TIP_Expr('name', '<>', null),
     *                                               'or', new TIP_Expr('lastname', '<>', null)));
     * </code>
     */
    function setWhere()
    {
        $this->_where = func_get_args();
    }

    /**
     * Set the joins
     *
     * Joins $table, that is appends to the result of a select query the
     * $fieldset fields (of $table) of the rows that satisfy $condition.
     *
     * You can specify more than one join: simply follows to feed setJoins()
     * with $table, $master, $slave and $fieldset.
     *
     * Simple example:
     * <code>
     * setOrder('name', TIP_ASCENDING);
     * </code>
     *
     * Using more fields:
     * <code>
     * setOrder('lastname', TIP_ASCENDING, 'name', TIP_ASCENDING);
     * </code>
     *
     * Mixed order:
     * <code>
     * setOrder('title', TIP_ASCENDING, 'date', TIP_DESCENDING);
     * </code>
     *
     * @param string                       $field The field id
     * @param TIP_ASCENDING|TIP_DESCENDING $order The order to use
     */
    function setJoins($table, $master, $slave, $fieldset)
    {
        $args = func_get_args();
        if (count($args) % 4) {
            TIP::error('invalid setJoins call');
        } else {
            $this->_joins = array_chunk($args, 4);
        }
    }

    /**
     * Set the query order
     *
     * Defines the order the rows must be returned, discarding the old order.
     * You can specify more than one order: simply follows to feed setOrder()
     * with $field and $order pairs.
     *
     * Simple example:
     * <code>
     * setOrder('name', TIP_ASCENDING);
     * </code>
     *
     * Using more fields:
     * <code>
     * setOrder('lastname', TIP_ASCENDING, 'name', TIP_ASCENDING);
     * </code>
     *
     * Mixed order:
     * <code>
     * setOrder('title', TIP_ASCENDING, 'date', TIP_DESCENDING);
     * </code>
     *
     * @param string                       $field The field id
     * @param TIP_ASCENDING|TIP_DESCENDING $order The order to use
     */
    function setOrder($field, $order)
    {
        $args = func_get_args();
        if (count($args) % 2) {
            TIP::error('invalid setOrder call');
        } else {
            $this->_order = array_chunk($args, 2);
        }
    }

    /**
     * Set the limit clause
     *
     * Limits the result to the first $limit rows, discarding the previous
     * limit value.
     *
     * @param int $limit Number of rows
     */
    function setLimit($limit)
    {
        $this->_limit = $limit;
    }
}

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
     * Fields to use in queries
     *
     * An array of field ids used by this object.
     * @var array
     */
    var $_fieldset = null;

    var $_joins = null;

    /**
     * Subset flag
     *
     * It is false if $_fieldset is a superset of the fields of $_path
     * or true if it is a subset.
     * @var bool
     */
    var $_is_subset = false;

    /**
     * Fields structure
     * @var array
     */
    var $_fields = null;
    
    /**
     * Has the $this->_engine->fillDetails() function been called?
     * @var bool
     */
    var $_detailed = false;


    /**
     * TIP_Data constructor
     *
     * Must not be called directly: use getInstace() instead.
     *
     * @param array $options An array of options
     */
    function TIP_Data($options)
    {
        $this->TIP_Type();

        $this->_id = TIP_Data::_buildId($options);
        $this->_path = $options['path'];
        $this->_joins = $options['joins'];
        $this->_engine =& TIP_Data_Engine::getInstance($options['engine']);
        $this->_fieldset =& $options['fieldset'];
        $this->_is_subset = !in_array('*', $this->_fieldset[$this->_path]);
    }

    function _buildId($options)
    {
        $fields = implode(',', $options['fieldset'][$options['path']]);
        return $options['engine'] . ':/' . $options['path'] . ' ' . $fields;
    }

    function _castField(&$value, $key)
    {
        if (isset($value) && array_key_exists($key, $this->_fields)) {
            $field =& $this->_fields[$key];
            if ($value === '' && $field['can_be_null']) {
                $value = null;
            } elseif (!settype ($value, $field['type'])) {
                TIP::warning("invalid type for field['$key'] => $value ({$field['type']})");
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
        if (!empty($field['info'])) {
            $info = TIP::doubleExplode('|', '=', $field['info']);
            $field = array_merge($field, $info);
        }
    }

    function _validFields(&$row)
    {
        $this->getFields(false);

        // Keep only the keys that are fields
        $row = array_intersect_key($row, $this->_fields);

        if ($this->_is_subset) {
            // Apply the fieldset filter
            $row = array_intersect_key($row, array_flip($this->_fieldset[$this->_path]));
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


    /**#@+ @access public */

    /**
     * The primary key field
     *
     * Defaults to 'id' but can be changed with setPrimaryKey(). The primary
     * key univoquely identifies a single row of data.
     *
     * @var $string
     */
    var $primary_key = 'id';


    /**
     * Get a TIP_Data instance
     *
     * Gets the previously defined $_path table object or instantiates
     * a new one and returns it. In fieldset you can specify the list of
     * fields used by this object. If '*' is contained in this array,
     * all the fields of $path are included.
     *
     * @param array $options An array of options
     * @return TIP_Data A reference to the data instance
     * @static
     */
    function& getInstance($options)
    {
        $id = TIP_Data::_buildId($options);
        $instance =& TIP_Data::singleton($id);
        if (is_null($instance)) {
            $instance =& new TIP_Data($options);
            TIP_Data::singleton($id, array($id => &$instance));
        }
        return $instance;
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
        // Special condition
        if (is_null($value) && strpos('is', strtolower($condition)) === FALSE) {
            $condition = $condition == '=' ? 'IS' : 'IS NOT';
        }

        $name = $this->_engine->prepareName($name);
        if (count($this->_fieldset) > 1) {
            $path = $this->_engine->prepareName($this->_path);
            $name = $path . '.' . $name;
        }

        $value = $this->_engine->prepareValue($value);
        return 'WHERE ' . $name . ' ' . $condition . ' ' . $value;
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
        $name = $this->_engine->prepareName($name);
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
        return $this->filter($this->primary_key, $id) . ' LIMIT 1';
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
        if (is_null($this->_fields)) {
            $this->_engine->fillFields($this);
        }

        if ($detailed && ! $this->_detailed) {
            $this->_engine->fillDetails($this);
            array_walk($this->_fields, array(&$this, '_mergeFieldInfo'));
            $this->_detailed = true;
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
        $rows =& $this->_engine->get($this, $this->rowFilter($id));
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
        $rows =& $this->_engine->get($this, $filter);
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
     * Also, this method is subject to the fieldset: if $_is_subset is true,
     * only the fields present in the $_fieldset[$_path] array will be inserted.
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
            $rows = array($row);
            if (!$this->_validFields($rows[0])) {
                return false;
            }
        }

        $result = $this->_engine->insert($this, $rows);
        if (is_null($result)) {
            return false;
        }

        if (empty($row[$this->primary_key]) && $result) {
            // Set the primary key to the last autoincrement value, if any
            $row[$this->primary_key] = $result;
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

        array_walk($rows, array(&$this, '_validFields'));
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
     * @param array &$row     The new row content
     * @param array  $old_row The old row content
     */
    function updateRow($row, $old_row = null)
    {
        if (!is_array($row)) {
            $type = gettype($row);
            TIP::error("unhandled row type ($type)");
            return false;
        }

        if (@array_key_exists($this->primary_key, $old_row)) {
            $id = $old_row[$this->primary_key];
        } elseif (@array_key_exists($this->primary_key, $row)) {
            $id = $row[$this->primary_key];
        } else {
            TIP::error('undefined row to update');
            return false;
        }

        // Get the valid set
        if (!$this->_validFields($row)) {
            return false;
        }

        if (isset($old_row)) {
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
        if (array_key_exists($this->primary_key, $row)) {
            $key = $row[$this->primary_key];
            TIP::error("updateRows() impossible with a primary key defined ($key)");
            return false;
        }

        // Get the valid set
        if (!$this->_validFields($row)) {
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
