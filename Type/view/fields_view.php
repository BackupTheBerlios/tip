<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

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
    /**
     * Constructor
     *
     * Initializes a TIP_Fields_View instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    protected function __construct($id, $args)
    {
        parent::__construct($id, $args);
    }

    protected function postConstructor()
    {
        $this->_rows =& $this->_data->getFields(true);
        $this->onPopulated();
    }
}
?>
