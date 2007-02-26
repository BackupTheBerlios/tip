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
    /**#@+ @access public */

    /**
     * Get a source engine
     *
     * Gets the singleton instance of a source engine using subsequential
     * TIP_Source_Engine::singleton() calls.
     *
     * A source engine is instantiated by includind its logic file found in the
     * 'source_engine' directory (relative to 'logic_root').
     *
     * To improve consistency, the $source_engine name is always converted
     * lowercase. This means also the logic file name must be lowecase.
     *
     * @param string $source_engine The source engine name
     * @return TIP_Source_Engine A reference to a TIP_Source_Engine implementation
     * @static
     */
    function& getInstance($source_engine)
    {
        $id = strtolower($source_engine);
        $instance =& TIP_Source_Engine::singleton($id);
        if (is_null($instance)) {
            $file = TIP::buildLogicPath('source_engine', $id) . '.php';
            $instance =& TIP_Source_Engine::singleton($id, $file);
        }
        return $instance;
    }

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

    /**
     * Get the current line
     *
     * Gets the number of the current line, for debug purposes.
     *
     * @param TIP_Source &$source The source instance
     * @return int|null The current line number or null if not implemented
     */
    function getLine(&$source)
    {
        return null;
    }

    /**#@-*/
}

?>
