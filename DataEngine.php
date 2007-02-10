<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Data_Engine definition file
 * @package TIP
 **/

/**
 * Base class for data engines
 *
 * Provides a common interface to access any data requested by TIP.
 *
 * @abstact
 * @package TIP
 **/
class TIP_Data_Engine extends TIP_Type
{
    /**#@+ @access public */

    /**
     * Get a data engine
     *
     * Gets the singleton instance of a data engine. The $data_engine,
     * if not yet registered, is defined by calling TIP_Type::factory().
     *
     * A data engine is instantiated by includind its logic file found in the
     * 'logic_data_root' directory (relative to 'logic_root').
     *
     * To improve consistency, the $data_engine name is always converted
     * lowercase. This means also the logic file name must be lowecase.
     *
     * @param string $data_engine The data engine name
     * @return TIP_Data_Engine A reference to a TIP_Data_Engine implementation
     * @static
     **/
    function& getInstance($data_engine)
    {
        $id = strtolower($data_engine);
        $instance =& TIP_Data_Engine::singleton($id);
        if (is_null($instance)) {
            $path = TIP::getOption('application', 'logic_data_root');
            $instance =& TIP_Data_Engine::singleton($id, TIP_Type::factory($id, $path));
        }

        return $instance;
    }

    /**
     * Prepare a name for a query or a filter
     *
     * Prepares an identifier to be inserted in a query or in a filter. The
     * TIP_Mysql engine, for example, backtickes the string.
     *
     * This method SHOULD be overriden by implementations of TIP_Data_Engine
     * that use client-server communications. This includes SQL based engines
     * that must avoid SQL injection.
     *
     * @param string $name The name to prepare
     * @return string The name prepared for the query
     **/
    function prepareName($name)
    {
        return $name;
    }

    /**
     * Prepares a value for a query or a filter
     *
     * Prepares a value to be inserted in a query or in a filter. The TIP_Mysql
     * engine, for example, escapes and quotes all the string values.
     *
     * This method SHOULD be overriden by implementations of TIP_Data_Engine
     * that use client-server communications. This includes SQL based engines
     * that must avoid SQL injection.
     *
     * @param mixed $value The value to prepare
     * @return string The value prepared for the query
     **/
    function prepareValue($value)
    {
        return (string) $value;
    }

    /**
     * Fill the fields
     *
     * Gets the data source structure and store the result in $data->fields.
     * Obviously, the implementation must fills the fields array a specific way:
     * read the TIP_Data documentation for further details.
     *
     * The field array can be filled by the engine at any time. This allows a
     * sort of performance gain if filled, for example, after a select query.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data $data The data context
     * @return bool TRUE on success or FALSE on errors
     **/
    function fillFields(&$data)
    {
        $this->logFatal('method TIP_Data_Engine::fillFields() not implemented');
    }

    /**
     * Fill the fields details
     *
     * Fills the detailed part of the fields array: read the TIP_Data
     * documentation for further details.
     *
     * The reason to split the fields filling procedure in two step is
     * performance. While the fillFields() operation is quite inexpensive and
     * often required, getting the field details is usually an relative
     * expensive operation and required only for automatic form generation.
     * If you do not need to split this operation, simply implement
     * fillFields() only and code fillDetails() as following:
     *
     * <code>
     * function fillDetails (&$data);
     * {
     *     return TRUE;
     * }
     * </code>
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data $data The data context
     * @return bool TRUE on success or FALSE on errors
     **/
    function fillDetails(&$data)
    {
        $this->logFatal('method TIP_Data_Engine::fillDetails() not implemented');
    }

    /**
     * Read data
     *
     * Gets the rows that satisfy the $filter conditions. The result returned
     * by this function must be homogeneus. This means for all the engines the
     * resulting array must be:
     *
     * <code>
     * $result = array (value of PrimaryKey1 => array (FieldId1 => value of FieldId1, ...),
     *                  value of PrimaryKey2 => array (FieldId1 => value of FieldId1, ...),
     *                  ...);
     * </code>
     *
     * The result must be an empty array if there's no matching rows.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param string    $filter The filter conditions
     * @param TIP_Data &$data   The data context
     * @return array|null A reference to an array of rows matching the
     *                    specified conditions or null on errors
     **/
    function& get($filter, &$data)
    {
        $this->logFatal('method TIP_Data_Engine::get() not implemented');
    }

    /**
     * Insert a new row
     *
     * Inserts a new row. The primary key of the new row is returned. If the
     * primary key is specified in $row and a row with this primary key yet
     * exists, this function must fail.
     *
     * Notice $row can be an empty array, in which case a new empty row must
     * be added without errors. In this case, the row must be filled with
     * its default values.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param array    &$row  The new row
     * @param TIP_Data &$data The data context
     * @return mixed|null The newly inserted primary key (usually an integer,
     *                    but can be any valid type) or null on errors
     **/
    function insert(&$row, &$data)
    {
        $this->logFatal('method TIP_Data_Engine::insert() not implemented');
    }

    /**
     * Update data
     *
     * Updates the rows that match the $filter conditions using the new $row
     * contents. To leave the fields untouched, simply do not specify these
     * fields in $row.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param string    $filter The filter conditions
     * @param array    &$row    The field=>value pairs to update
     * @param TIP_Data &$data   The data context
     * @return bool TRUE on success or FALSE on errors
     **/
    function update($filter, &$row, &$data)
    {
        $this->logFatal('method TIP_Data_Engine::update() not implemented');
    }

    /**
     * Delete data
     *
     * Removes the rows that match the $filter conditions.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param string    $filter The filter conditions
     * @return \c TRUE on success, \c FALSE otherwise.
     **/
    function delete ($filter, &$data)
    {
        $this->logFatal('method TIP_Data_Engine::delete() not implemented');
    }
}

?>
