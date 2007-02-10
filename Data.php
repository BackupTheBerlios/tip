<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Data definition file
 * @package TIP
 **/

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
 * @final
 **/
class TIP_Data extends TIP_Type
{
    /**#@+ @access private */

    /**
     * Has the $engine->fillDetails() function been called?
     *
     * @var bool
     **/
    var $_detailed = false;


    function TIP_Data($path, $engine)
    {
        $this->TIP_Type();

        $this->path = $path;
        $this->engine =& TIP_Data_Engine::getInstance($engine);
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
     **/
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
     **/
    var $path = null;

    /**
     * The data engine
     *
     * This must be a reference to an implementation of TIP_Data_Engine. It
     * provides the needed interface to access the data.
     * This field is filled by the TIP_Data during this class instantiation.
     *
     * @var TIP_Data_Engine
     **/
    var $engine = null;

    /**
     * The primary key field
     *
     * Defaults to 'id' but can be changed with setPrimaryKey(). The primary
     * key univoquely identifies a single row of data.
     *
     * @var $string
     **/
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
     **/
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
     * @param string $name      The field id
     * @param mixed  $value     The reference value
     * @param string $condition The condition to apply
     * @return string The requested filter in the proper engine format
     **/
    function filter($name, $value, $condition = '=')
    {
        // Special condition
        if (is_null($value) && stripos('is',$condition) === FALSE) {
            $condition = $condition == '=' ? 'IS' : 'IS NOT';
        }

        return 'WHERE ' .
            $this->engine->prepareName($name) .
            $condition .
            $this->engine->prepareValue($value);
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
     **/
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
     *		'choices'     => the number of choices for 'set' or 'enum' subtypes,
     *		'choice1'     => the first choice,
     *		'choice2'     => the second choice,
     *		...),
     *
     *   ...);
     * </code>
     *
     * @param bool $detailed Force a TIP_Data_Engine::fillDetails() call
     * @return array|null The field structure or null on errors
     **/
    function& getFields($detailed = true)
    {
        if (is_null($this->fields)) {
            $this->engine->fillFields($this);
        }

        if ($detailed && ! $this->_detailed) {
            $this->engine->fillDetails($this);
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
     * @param array $row The row to cast
     **/
    function forceFieldType(&$row)
    {
        $fields =& $this->getFields(false);

        foreach ($fields as $id => $field) {
            if (array_key_exists($id, $row)) {
                if ($row[$id] == '' && $field['can_be_null']) {
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
     **/
    function getRow($id)
    {
        return $this->engine->get($this->rowFilter($id), $this);
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
     **/
    function& getRows($filter)
    {
        return $this->engine->get($filter, $this);
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
     **/
    function putRow(&$row)
    {
        // Remove the primary key from row, if present
        if (array_key_exists($this->primary_key, $row)) {
            unset($row[$this->primary_key]);
        }

        $id = $this->engine->insert($row, $this);
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
     * Updates the row matching the primary key of $old_row with the $new_row
     * content. Only the fields that are presents in $new_row and that differs
     * from $old_row are updated. Obviously, if $old_row and $new_row are
     * equals no update operations are performed.
     *
     * This function is quite different from the other because require an array
     * instead of a filter string or a row id. This is done to allow a check
     * between the old and new row content, trying to avoid the update operation.
     *
     * @param array &$old_row The old row
     * @param array &$new_row The new row
     **/
    function updateRow(&$old_row, &$new_row)
    {
        $old_id = @$old_row[$this->primary_key];

        // No primary key found: error
        if (is_null($old_id)) {
            $this->logWarning('Undefined row to update');
            return false;
        }

        $fields = $this->getFields(false);
        $delta_row = false;
        foreach (array_keys ($fields) as $id) {
            if (@$old_row[$id] != @$new_row[$id])
                $delta_row[$id] = $new_row[$id];
        }

        if (! $delta_row) {
            return true;
        }

        $filter = $this->rowFilter($old_id);
        return $this->engine->update($filter, $delta_row, $this);
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
     **/
    function updateRows($filter, &$row)
    {
        // Found a primary key: error
        if (array_key_exists($this->primary_key, $row)) {
            return false;
        }

        return $this->update($filter, $row, $this);
    }

    /**
     * Delete one row
     *
     * Deletes the row with the specified $id.
     *
     * @param mixed $id The row id
     **/
    function deleteRow($id)
    {
        return $this->engine->delete($this->rowFilter($id), $this);
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
     **/
    function deleteRows($filter)
    {
        // Empty filter are by default not accepted
        if (empty($filter)) {
            return false;
        }

        return $this->engine->delete($filter, $this);
    }

    /**#@-*/

    /**#@-*/
}

?>
