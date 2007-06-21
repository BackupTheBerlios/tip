<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Logs_View definition file
 *
 * @package TIP
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
