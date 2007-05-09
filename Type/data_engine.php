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
    /**#@+ @access protected */

    function TIP_Data_Engine($id)
    {
        $this->TIP_Type($id);
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Fill the fields structure
     *
     * Fills the $_fields property of the TIP_Data object: read the TIP_Data
     * documentation for further details.
     *
     * This method MUST be overriden by all the types that inherits TIP_Data_Engine.
     *
     * @param TIP_Data $data The data context
     * @return bool true on success or false on errors
     */
    function fillFields(&$data)
    {
        $this->logFatal('method TIP_Data_Engine::fillFields() not implemented');
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
    function& select(&$data, $filter)
    {
        $this->logFatal('method TIP_Data_Engine::select() not implemented');
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
