<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Data_Engine definition file
 * @package TIP
 */

/**
 * Base class for data engines
 *
 * Provides a common interface to access any data requested by TIP.
 *
 * @abstract
 * @package  TIP
 * @tutorial TIP/DataEngine/DataEngine.pkg
 */
class TIP_Data_Engine extends TIP_Type
{
    /**#@+ @access public */

    /**
     * Get a data engine
     *
     * Gets the singleton instance of a data engine using subsequential
     * TIP_Data_Engine::singleton() calls.
     *
     * A data engine is instantiated by includind its logic file found in the
     * 'data_engine' directory (relative to 'logic_root').
     *
     * To improve consistency, the data engine name is always converted
     * lowercase. This means also the logic file name must be lowecase.
     *
     * @param string $data_engine The data engine name
     * @return TIP_Data_Engine A reference to a TIP_Data_Engine implementation
     * @static
     */
    function& getInstance($data_engine)
    {
        $id = strtolower($data_engine);
        $instance =& TIP_Data_Engine::singleton($id);
        if (is_null($instance)) {
            $file = TIP::buildLogicPath('data_engine', $id) . '.php';
            $instance =& TIP_Data_Engine::singleton($id, $file);
        }
        return $instance;
    }

    /**
     * Prepare names for a query or a filter
     *
     * Prepares one or more identifiers to be inserted in a query or in a
     * filter. The TIP_Mysql engine, for example, backtickes the names.
     *
     * This method SHOULD be overriden by implementations of TIP_Data_Engine
     * that use client-server communications. This includes SQL based engines
     * that must avoid SQL injection.
     *
     * @param string|array $name The name/names to prepare
     * @return string|array $name prepared for the query
     */
    function prepareName($name)
    {
        return $name;
    }

    /**
     * Prepare values for a query or a filter
     *
     * Prepares one or more values to be inserted in a query or in a filter.
     * The TIP_Mysql engine, for example, escapes and quotes all the values.
     *
     * This method SHOULD be overriden by implementations of TIP_Data_Engine
     * that use client-server communications. This includes SQL based engines
     * that must avoid SQL injection.
     *
     * @param mixed|array $value The value/values to prepare
     * @return string|array $value prepared for the query
     */
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
     * @param TIP_Data &$data The data context
     * @return bool true on success or false on errors
     */
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
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data $data The data context
     * @return bool true on success or false on errors
     */
    function fillDetails(&$data)
    {
        $this->logFatal('method TIP_Data_Engine::fillDetails() not implemented');
    }

    /**
     * Read data
     *
     * Gets the rows that satisfy the $filter conditions. Only the fields
     * specified by $data->_fieldset must be read. If this fieldset is
     * null, all the fields are assumed.
     *
     * The result must be an empty array if there's no matching rows.
     * The type of the field values is not important: the resulting array will
     * be casted to the proper type in the TIP_Data object.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data &$data   The data context
     * @param string    $filter The filter conditions
     * @return array|null A reference to an array of rows matching the
     *                    specified conditions or null on errors
     */
    function& get(&$data, $filter)
    {
        $this->logFatal('method TIP_Data_Engine::get() not implemented');
    }

    /**
     * Insert new rows
     *
     * Inserts new rows. The autoincrement value of the last row is returned.
     * If the primary keys are specified in $rows and a row with any primary
     * key yet exists, this function must fail.
     *
     * The rows must be homogeneus: if the first row has five fields, all the
     * other rows must have the same five fields (obviously, with different
     * values).
     *
     * Notice $rows can be an empty array, in which case a new empty row must
     * be added without errors. In this case, the row must be filled with
     * its default values.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data &$data The data context
     * @param array    &$rows An array of rows
     * @return int|null The last autoincrement value or null on errors
     */
    function insert(&$data, &$rows)
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
     * The update method is subject to the fields subset: if
     * $data->_fields_subset is not null, only the fields present in this
     * subset will be updated.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data &$data   The data context
     * @param string    $filter The filter conditions
     * @param array    &$row    The field=>value pairs to update
     * @return bool true on success or false on errors
     */
    function update(&$data, $filter, &$row)
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
     * @param TIP_Data &$data   The data context
     * @param string    $filter The filter conditions
     * @return bool true on success or false on errors
     */
    function delete (&$data, $filter)
    {
        $this->logFatal('method TIP_Data_Engine::delete() not implemented');
    }

    /**#@-*/
}

?>
