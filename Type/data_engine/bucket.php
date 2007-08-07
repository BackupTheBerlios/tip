<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

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
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Bucket instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ TIP_Data_Engine implementation

    function fillFields(&$data)
    {
        TIP::warning("fillFields($data)");
        return true;
    }

    function& select(&$data, $filter, $fields)
    {
        TIP::warning("get($data, $filter, $fields)");
        $fake_result = array();
        return $fake_result;
    }

    function insert(&$data, &$row)
    {
        TIP::warning("insert(row, $data)");
        return true;
    }

    function update(&$data, $filter, &$row)
    {
        TIP::warning("update($data, $filter, row)");
        return true;
    }

    function delete(&$data, $filter)
    {
        TIP::warning("delete($data, $filter)");
        return true;
    }

    public function dump($root)
    {
        TIP::warning("dump($root)");
        return true;
    }

    //}}}
}
?>
