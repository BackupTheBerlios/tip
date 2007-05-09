<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */

/**
 * A generic source template
 *
 * @final
 * @package  TIP
 * @tutorial TIP/SourceEngine/SourceEngine.pkg#TIP_Source
 */
class TIP_Source extends TIP_Type
{
    /**#@+ @access private */

    /**
     * The absolute path to the source file
     * @var string
     */
    var $_path = null;

    /**
     * The content of the source file
     * @var string|false
     */
    var $_buffer = null;

    /**
     * A custom property to be used by the source engine
     * @var mixed
     */
    var $_implementation = null;

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Source instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function TIP_Source($id, $args)
    {
        $this->TIP_Type($id);

        $this->_path = $args['path'];
        $this->_engine =& $args['engine'];
    }

    /**
     * Build a TIP_Source identifier
     *
     * $args must be an array with at least the following items:
     * - $args['path']: the absolute path to the source file
     * - $args['engine']: a reference to the source engine
     *
     * @param  array  $args The constructor arguments
     * @return string       The source identifier
     */
    function buildId($args)
    {
        return $args['engine']->getId() . ':' . $args['path'];
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Execute the source file
     *
     * Parses and executes this source.
     *
     * @param  TIP_Module &$module The caller module
     * @return bool                true on success or false on errors
     */
    function run(&$module)
    {
        if (is_null($this->_buffer)) {
            $this->_buffer = file_get_contents($this->_path, false);
            if ($this->_buffer === false) {
                TIP::error("error in reading file ($this->_path)");
            }
        }
        return $this->_buffer !== false && $this->_engine->run($this, $module);
    }

    /**#@-*/
}
?>
