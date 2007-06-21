<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Fields_View definition file
 *
 * @package TIP
 */

/**
 * A fields view
 *
 * A special view to traverse the field structure.
 *
 * @package TIP
 */
class TIP_Fields_View extends TIP_View
{
    //{{{ Static methods

    static protected function checkOptions(&$options)
    {
        // Check for requested options
        if (!parent::checkOptions($options) || !isset($options['data'])) {
            return false;
        }

        $options['id'] = (string) $options['data'];
        return true;
    }

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Array_View instance.
     *
     * $options could be an array with the following items:
     * - $options['data']:    the reference to a TIP_Data object (requested)
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
    //{{{ Callbacks

    protected function fillRows()
    {
        $this->rows =& $this->data->getFields(true);
        return is_array($this->rows);
    }

    //}}}
}
?>
