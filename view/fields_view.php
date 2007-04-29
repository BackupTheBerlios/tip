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
    /**#@+ @access protected */

    /**
     * Get the field list
     *
     * Fills the $rows property with the field structure as specified by
     * TIP_Data::getFields().
     *
     * @return bool true on success or false on errors
     */
    function fillRows()
    {
        $this->rows =& $this->data->getFields(true);
        if (is_null($this->rows)) {
            $this->rows = false;
            return false;
        }
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Fields_View instance. $id must contain the identifier
     * of a TIP_Data instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function TIP_Fields_View($id, $args)
    {
        $this->TIP_View($id, $args);
    }

    /**
     * Build a TIP_Fields_View identifier
     *
     * $args are the same as specified in TIP_View::buildId().
     * The 'filter' argument is not used.
     *
     * @param  array  $args The constructor arguments
     * @return string       The data identifier
     */
    function buildId($args)
    {
        return $args['data']->getId();
    }

    /**#@-*/
}
?>
