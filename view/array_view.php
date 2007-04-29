<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

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
    /**#@+ @access private */

    var $_stored_rows = null;

    /**#@-*/

    
    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Array_View instance. $id is an arbitrary string
     * identifying this view.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function TIP_Array_View($id, $args)
    {
        // Remove the 'data' item (if present): data initialization not needed
        unset($args['data']);

        $this->TIP_View($id, $args);

        $this->_stored_rows =& $args['rows'];
    }

    /**
     * Build a TIP_Array_View identifier
     *
     * $args can have all the items specified in TIP_View::buildId(), but the
     * 'filter' and 'data' arguments are not used.
     *
     * Furthermore, the following items can be specified:
     * - $args['id']: the instance identifier (must be present)
     * - $args['rows']: a reference to the array of rows to use as content
     *
     * @param  array  $args The constructor arguments
     * @return string       The data identifier
     */
    function buildId($args)
    {
        return $args['id'];
    }

    function fillRows()
    {
        $this->rows =& $this->_stored_rows;
        return true;
    }

    /**#@-*/
}
?>
