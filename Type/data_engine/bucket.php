<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Bucket definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
 */

/**
 * Bucket data engine
 *
 * Dummy data engine that simply does nothing.
 * Anyway, all the requested functions return succesful results and log a
 * warning message for debugging purpose.
 *
 * @package TIP
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

    public function query()
    {
        TIP::warning('query()');
        return null;
    }

    public function fillFields(&$data)
    {
        TIP::warning("fillFields($data)");
        return true;
    }

    public function &select(&$data, $filter, $fields)
    {
        TIP::warning("get($data, $filter, $fields)");
        $fake_result = array();
        return $fake_result;
    }

    public function insert(&$data, &$row)
    {
        TIP::warning("insert(row, $data)");
        return true;
    }

    public function update(&$data, $filter, &$row)
    {
        TIP::warning("update($data, $filter, row)");
        return true;
    }

    public function delete(&$data, $filter)
    {
        TIP::warning("delete($data, $filter)");
        return true;
    }

    public function dump($root)
    {
        TIP::warning("dump($root)");
        return true;
    }

    public function startTransaction()
    {
        TIP::warning('startTransaction()');
        return true;
    }

    public function endTransaction()
    {
        TIP::warning('endTransaction()');
        return true;
    }

    public function cancelTransaction()
    {
        TIP::warning('cancelTransaction()');
        return true;
    }

    //}}}
}
?>
