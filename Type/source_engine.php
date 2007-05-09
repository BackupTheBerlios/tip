<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Source_Engine definition file
 * @package TIP
 */

/**
 * Base class for source engines
 *
 * Provides a common interface to run source files.
 *
 * @abstract
 * @package  TIP
 * @tutorial TIP/SourceEngine/SourceEngine.pkg
 */
class TIP_Source_Engine extends TIP_Type
{
    /**#@+ @access protected */

    function TIP_Source_Engine($id)
    {
        $this->TIP_Type($id);
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Execute a source/template file
     *
     * Parses and executes the source.
     *
     * This method MUST be overriden by all the types that inherits
     * TIP_Source_Engine.
     *
     * @param TIP_Source &$source The source instance
     * @param TIP_Module &$module The caller module
     * @return bool TRUE on success or FALSE on errors
     */
    function run(&$source, &$module)
    {
        $this->logFatal('method TIP_Source_Engine::run() not implemented');
    }

    /**#@-*/
}
?>
