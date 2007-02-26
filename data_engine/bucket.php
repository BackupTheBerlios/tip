<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Bucket definition file
 *
 * @package TIP
 * @subpackage DataEngine
 */


/**
 * Bucket data engine
 *
 * Dummy data engine that simply does nothing.
 * Anyway, all the requested functions return succesful results and log a
 * warning message for debugging purpose.
 *
 * @final
 * @package TIP
 * @subpackage DataEngine
 *
 * @todo Must be implemented a function to show the row context, so you
 *       can see it in the logged warnings.
 */
class TIP_Bucket extends TIP_Data_Engine
{
    function prepareName($name)
    {
        return "prepareName($name)";
    }

    function prepareValue($value)
    {
        return "prepareValue($value)";
    }

    function fillFields(&$data)
    {
        TIP::warning("fillFields($data->_path)");
        return true;
    }

    function fillDetails(&$data)
    {
        TIP::warning("fillDetails($data->_path)");
        return true;
    }

    function& get($filter, &$data)
    {
        TIP::warning("get($filter, $data->_path)");
        $fake_result = array();
        return $fake_result;
    }

    function insert(&$row, &$data)
    {
        TIP::warning("insert(row, $data->_path)");
        return true;
    }

    function update($filter, &$row, &$data)
    {
        TIP::warning("update($filter, row, $data->_path)");
        return true;
    }

    function delete($filter, &$data)
    {
        TIP::warning("delete($filter, $data->_path)");
        return true;
    }
}

return 'TIP_Bucket';

?>
