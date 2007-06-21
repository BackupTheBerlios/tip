<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Array_View definition file
 *
 * @package TIP
 */


/**
 * An array view
 *
 * A special view to traverse a defined array or rows.
 *
 * @package TIP
 */
class TIP_Array_View extends TIP_View
{
    //{{{ Static methods

    static protected function checkOptions(&$options)
    {
        return parent::checkOptions($options) && isset($options['rows']);
    }

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Array_View instance.
     *
     * $options could be an array with the following items:
     * - $options['id']:      the instance identifier (requested)
     * - $options['rows']:    the requested array (requested)
     * - $options['summary']: additional summary fields
     * - $options['on_row']:  row callback
     * - $options['on_view']: view callback
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
