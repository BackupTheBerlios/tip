<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Logs_View definition file
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
 * A logs view
 *
 * A special view to traverse the current logged rows.
 *
 * @package TIP
 */
class TIP_Logs_View extends TIP_View
{
    //{{{ Static methods

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) ||
            is_null($logger =& TIP_Application::getSharedModule('logger'))) {
            return false;
        }

        $options['rows'] =& $logger->getLogs();
        return true;
    }

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Logs_View instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ TIP_View implementation

    protected function fillRows()
    {
        return true;
    }

    //}}}
}
?>
