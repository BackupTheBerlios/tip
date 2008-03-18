<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Data_View definition file
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
 * A data view
 *
 * Management of the data views mainly used by the TIP_Content.
 * This class provides a way to bind summuries values to the view (such as
 * totals or subtotals) and some callbacks to customize the view.
 * Specifically, the 'on_row' callback is used to add some custom fields
 * (also called calculated fields) to every row while the 'on_view' callback
 * can be used to fill the 'summary' values with every kind of aggregate
 * function you desire.
 *
 * @package TIP
 */
class TIP_Data_View extends TIP_View
{
    //{{{ Static methods

    static protected function checkOptions(&$options)
    {
        // Check for requested options
        if (!parent::checkOptions($options) || !isset($options['data'])) {
            return false;
        }

        $options['id'] = $options['data']->getProperty('id');
        isset($options['fields']) && $options['id'] .= '(' . implode(',', $options['fields']) . ')';
        isset($options['filter']) && $options['id'] .= ' ' . $options['filter'];

        return true;
    }

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_View instance.
     *
     * $options could be an array with the following items:
     * - $options['data']:    the reference to a TIP_Data object (requested)
     * - $options['filter']:  the filter conditions
     * - $options['fields']:  set of fields to get
     * - $options['summary']: additional summary fields
     * - $options['on_row']:  row callback
     * - $options['on_view']: view callback
     *
     * @param array  $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ TIP_View implementation

    protected function fillRows()
    {
        $this->rows =& $this->data->getRows($this->filter, $this->fields);
        return is_array($this->rows);
    }

    //}}}
}
?>
