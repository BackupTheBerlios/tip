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
    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Modules_View instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function __construct($id, $args)
    {
        // Remove the 'data' item: initialization of data source not needed
        unset($args['data']);

        parent::__construct($id, $args);
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
    function buildId()
    {
        return '__MODULES__';
    }

    /**
     * Get the installed modules
     *
     * Fills the $rows property with all the installed modules.
     *
     * @return bool true on success or false on errors
     */
    function fillRows()
    {
        $register =& TIP_Module::singleton();

        if ($handle = opendir(TIP::buildLogicPath('module'))) {
            while (($file = readdir($handle)) !== false) {
                if (strcasecmp(substr($file, -4), '.php') == 0) {
                    $module = strtolower(substr($file, 0, -4));
                    $this->rows[$module] = array(
                        'id'     => $module,
                        'in_use' => array_key_exists($module, $register)
                    );
                }
            }
            closedir($handle);
        }

        return true;
    }

    /**#@-*/
}
?>
