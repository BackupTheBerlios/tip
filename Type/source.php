<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * @package TIP
 */

/**
 * A generic source template
 *
 * @package  TIP
 * @tutorial TIP/SourceEngine/SourceEngine.pkg#TIP_Source
 */
class TIP_Source extends TIP_Type
{
    //{{{ Internal properties

    /**
     * The content of the source file
     * @var string|false
     * @internal
     */
    public $_buffer = null;

    /**
     * A custom property to be used by the source engine
     * @var mixed
     * @internal
     */
    public $_implementation = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Data instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Methods

    /**
     * Execute the source file
     *
     * Parses and executes this source.
     *
     * @param  TIP_Module &$module The caller module
     * @return bool                true on success or false on errors
     */
    public function run(&$module)
    {
        if (is_null($this->_buffer)) {
            $this->_buffer = file_get_contents($this->id, false);
            if ($this->_buffer === false) {
                TIP::error("error in reading file ($this->id)");
            }
        }
        return $this->_buffer !== false && $module->getProperty('engine')->run($this, $module);
    }

    //}}}
}
?>
