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
        $this->logWarning("fillFields($data->path)");
        return true;
    }

    function fillDetails(&$data)
    {
        $this->logWarning("fillDetails($data->path)");
        return true;
    }

    function& get($filter, &$data)
    {
        $this->logWarning("get($filter, $data->path)");
        $fake_result = array();
        return $fake_result;
    }

    function insert(&$row, &$data)
    {
        $this->logWarning("insert(row, $data->path)");
        return true;
    }

    function update($filter, &$row, &$data)
    {
        $this->logWarning("update($filter, row, $data->path)");
        return true;
    }

    function delete($filter, &$data)
    {
        $this->logWarning("delete($filter, $data->path)");
        return true;
    }
}

return new TIP_Bucket;

?>
