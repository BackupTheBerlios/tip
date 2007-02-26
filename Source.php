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
     * The source identifier
     *
     * The path which univoquely identify the source file.
     * This field is filled by TIP_Source during this class instantiation.
     *
     * @var string
     */
    var $_path = null;

    /**
     * The source/template engine
     * @var TIP_Source_Engine
     */
    var $_engine = null;

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


    /**
     * TIP_Source constructor
     *
     * Must not be called directly: use getInstace() instead.
     *
     * @param string  $path   The id of the source file
     * @param string &$engine A source/template engine instance
     */
    function TIP_Source($path, &$engine)
    {
        $this->TIP_Type();

        $this->_id = TIP_Source::_buildId($path, $engine);
        $this->_path = $path;
        $this->_engine =& $engine;
    }

    function _buildId(&$path, &$engine)
    {
        return $engine->getId() . ':/' . $path;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Get a TIP_Source instance
     *
     * Gets the previously defined $_path source object or instantiates
     * a new one and returns it.
     *
     * @param string            $path   The string identifying the source file
     * @param TIP_SourceEngine &$engine A source engine instance
     * @return TIP_Source A reference to the source instance
     * @static
     */
    function& getInstance($path, &$engine)
    {
        $id = TIP_Source::_buildId($path, $engine);
        $instance =& TIP_Source::singleton($id);
        if (is_null($instance)) {
            $instance =& new TIP_Source($path, $engine);
            TIP_Source::singleton($id, array($id => &$instance));
        }
        return $instance;
    }

    function getLine()
    {
        return $this->_engine->getLine($this);
    }

    /**
     * Execute the source file
     *
     * Parses and executes this source.
     *
     * @param TIP_Module &$module The caller module
     * @return bool true on success or false on errors
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
