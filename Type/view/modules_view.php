<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Modules_View definition file
 *
 * @package TIP
 */


/**
 * A modules view
 *
 * A special view to traverse the configured modules.
 *
 * @package TIP
 */
class TIP_Modules_View extends TIP_View
{
    /**
     * Constructor
     *
     * Initializes a TIP_Modules_View instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    protected function __construct($id, $args)
    {
        // Remove the 'data' item: initialization of data source not needed
        unset($args['data']);

        parent::__construct($id, $args);
    }

    protected function postConstructor()
    {
        $this->_rows = $GLOBALS['cfg'];
        $this->onPopulated();
    }

    /**
     * Build a TIP_Modules_View identifier
     *
     * $args can have all the items specified in TIP_View::buildId(), but the
     * 'filter' and 'data' arguments are not used.
     *
     * The returned identifier is constant because the modules view is only one.
     *
     * @return '__MODULES__' The data identifier
     */
    protected function buildId()
    {
        return '__MODULES__';
    }
}
?>
