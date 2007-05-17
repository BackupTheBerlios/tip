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
 * @package TIP
 * @subpackage DataEngine
 *
 * @todo Must be implemented a function to show the row context, so you
 *       can see it in the logged warnings.
 */
class TIP_Bucket extends TIP_Data_Engine
{
    function __construct($id)
    {
        parent::__construct($id);
    }

    function fillFields(&$data)
    {
        TIP::warning("fillFields($data->_path)");
        return true;
    }

    function& select(&$data, $filter)
    {
        TIP::warning("get($filter, $data->_path)");
        $fake_result = array();
        return $fake_result;
    }

    function insert(&$data, &$row)
    {
        TIP::warning("insert(row, $data->_path)");
        return true;
    }

    function update(&$data, $filter, &$row)
    {
        TIP::warning("update($filter, row, $data->_path)");
        return true;
    }

    function delete(&$data, $filter)
    {
        TIP::warning("delete($filter, $data->_path)");
        return true;
    }
}
?>
