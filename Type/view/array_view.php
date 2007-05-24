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
    /**
     * Constructor
     *
     * Initializes a TIP_Array_View instance. $id is an arbitrary string
     * identifying this view.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    protected function __construct($id, $args)
    {
        // Remove 'id', yet passed as $id
        unset($args['id']);
        parent::__construct($id, $args);
    }

    protected function postConstructor()
    {
        // Rows yet defined in the constructor as 'rows' argument
        $this->onPopulated();
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
}
?>
