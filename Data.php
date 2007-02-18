<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */

/**
 * A generic data provider
 *
 * In TIP, all the data are based on a primary key access, that is there must
 * be, for every source data, a field that identify a row (record). The primary
 * key field is named by default 'id', but can be easely changed to any other
 * valid field id.
 *
 * This class is a context used to represent a source of data. This can be a
 * database table, an XML file or whatever can provides rows of data.
 *
 * @package TIP
 * @final
 */
class TIP_Data extends TIP_Type
{
    /**#@+ @access private */

    /**
     * The data engine
     *
     * This must be a reference to an implementation of TIP_Data_Engine. It
     * provides the needed interface to access the data.
     * This field is filled by the TIP_Data during this class instantiation.
     *
     * @var TIP_Data_Engine
     */
    var $_engine = null;

    /**
     * Has the $this->_engine->fillDetails() function been called?
     *
     * @var bool
     */
    var $_detailed = false;


    function TIP_Data($path, $engine)
    {
        $this->TIP_Type();

        $this->path = $path;
        $this->_engine =& TIP_Data_Engine::getInstance($engine);
    }

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Fields structure
     *
     * Contains the description of all the fields of this table. It can be
     * filled by the engine at any time. This allows a sort of performance
     * gain if filled, for example, after a select query.
     *
     * @var array
     */
    var $fields = null;
    
    /**#@-*/


    /**#@+ @access public */

    /**
     * The data identifier
     *
     * The path (or name) which univoquely identify the data.
     * This field is filled by the TIP_Data during this class instantiation.
     *
     * @var string
     */
    var $path = null;

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
     * Gets the previously defined $path table object or instantiates
     * a new one and returns it.
     *
     * @param string $path   The path (or name) identifying the dataset
     * @param string $engine The data engine name to use
     * @return TIP_Data A reference to the table instance
     * @static
     */
    function& getInstance($path, $engine)
    {
        $id = $path . '.' . $engine;
        $instance =& TIP_Data::singleton($id);
        if (is_null($instance)) {
            $instance =& TIP_Data::singleton($id, new TIP_Data($path, $engine));
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
        $value = $this->_engine->prepareValue($value);
        return 'WHERE ' . $name . $condition . $value;
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
     * Gets the field structure of this data context. This array will strictly
     * have the following format:
     *
     * <code>
     * $fields = array (
     *   'fieldid1' => array (
     *
     *      // Filled by TIP_Data_Engine::fillFields()
     *		'id'          => 'fieldid1',
     *		'type'        => a valid settype() type string,
     *		'subtype'     => 'date', 'time', 'datetime', 'enum', 'set', 'unsigned' or null,
     *		'length'      => an integer specifing the max length, or 0 if not used,
     *		'can_be_null' => true or false,
     * 
     *      // Filled by TIP_Data_Engine::fillDetails()
     *      'default'     => default value,
     *      'automatic    => true if the field is set by the server,
     *		'choices'     => array of valid values for 'set' or 'enum' subtypes,
     *		'info'        => a string of custom informations
     *		...),
     *
     *   ...);
     * </code>
     *
     * @param bool $detailed Force a TIP_Data_Engine::fillDetails() call
     * @return array|null The field structure or null on errors
     */
    function& getFields($detailed = true)
    {
        if (is_null($this->fields)) {
            $this->_engine->fillFields($this);
        }

        if ($detailed && ! $this->_detailed) {
            $this->_engine->fillDetails($this);
            $this->_detailed = true;
        }

        return $this->fields;
    }

    /**
     * Cast a row content to the proper type
     *
     * Given a row, forces every field in the row that matches the field
     * structure in this context to be of the type specified by getFields().
     * The type forcing is done using settype().
     *
     * @param array &$row The row to cast
     */
    function forceFieldType(&$row)
    {
        $fields =& $this->getFields(false);

        foreach ($fields as $id => $field) {
            if (! is_null(@$row[$id])) {
                if ($row[$id] === '' && $field['can_be_null']) {
                    $row[$id] = null;
                } else {
                    settype ($row[$id], $field['type']);
                }
            }
        }
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
        return $this->_engine->get($this, $this->rowFilter($id));
    }

    /**
     * Read more rows
     *
     * Gets the rows that satisfy the $filter conditions. The syntax of the
     * filter is data engine dependent: no assumptions are made by the TIP_Data
     * class. This also means the $filter parameter must be prepared for the
     * engine: use the prepare... primitives provided by the data engine.
     *
     * Of course, the result can be an empty array if there's no matching rows
     * that satisfy $filter.
     *
     * @param string $filter The filter conditions
     * @return array|null The array of rows matching the specified filter or
     *                    null on errors
     */
    function& getRows($filter)
    {
        return $this->_engine->get($this, $filter);
    }


    /**#@+ @return bool true on success or false on errors */

    /**
     * Insert a new row
     *
     * Inserts a new row in the data source. If the primary key is not
     * found in $row, will be defined after the insert operation so after this
     * call $row['id'] (or whatever have you choosed as primary key) will
     * contain the value of the recently added row.
     * Instead, if the primary key is defined in $row, this function fails if
     * a row with the same primary key exists.
     *
     * @param array &$row The row to insert
     */
    function putRow(&$row)
    {
        // Remove the primary key from row, if present
        if (array_key_exists($this->primary_key, $row)) {
            unset($row[$this->primary_key]);
        }

        $id = $this->_engine->insert($this, $row);
        if (is_null($id)) {
            return false;
        }

        // Add the recently added primary key to row
        $fields =& $this->getFields(false);
        settype($id, $fields[$this->primary_key]['type']);
        $row[$this->primary_key] = $id;
        return true;
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
    function updateRow(&$row, $old_row = null)
    {
        if (@array_key_exists($this->primary_key, $old_row)) {
            $id = $old_row[$this->primary_key];
        } elseif (@array_key_exists($this->primary_key, $row)) {
            $id = $row[$this->primary_key];
        } else {
            $this->logError('Undefined row to update');
            return false;
        }

        // Keep only the field keys differents from $old_row (if specified)
        $keys = array_keys($this->getFields(false));
        $set = empty($old_row) ? $row : array_diff_assoc($row, $old_row);
        $set = array_flip(array_intersect(array_flip($set), $keys));
        if (empty($set)) {
            // No fields to update
            return true;
        }

        return $this->_engine->update($this, $this->rowFilter($id), $set);
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
     * @param array &$row    A row with the field => value pairs to update
     */
    function updateRows($filter, &$row)
    {
        // Found a primary key: error
        if (array_key_exists($this->primary_key, $row)) {
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
