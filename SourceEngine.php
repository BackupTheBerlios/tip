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
 * @package TIP
 */
class TIP_Source_Engine extends TIP_Type
{
    /**#@+ @access public */

    /**
     * Execute a buffer
     *
     * Parses and executes the commands specified in $buffer. Because of
     * $buffer can be a huge chunck of memory, it is passed by reference to
     * improve performances and avoid undesired copy overload.
     * The $context_message text is a context string to prepend in message
     * logging.
     *
     * This method MUST be overriden by all the types that inherits
     * TIP_Source_Engine.
     *
     * @param string     &$buffer          The buffer to run
     * @param TIP_Module &$module          The caller module
     * @param string      $context_message A context description
     * @return bool TRUE on success or FALSE on errors
     */
    function run(&$buffer, &$module, $context_message)
    {
        $this->logFatal('method TIP_Source_Engine::run() not implemented');
    }

    /**
     * Get a source engine
     *
     * Gets the singleton instance of a source engine using subsequential
     * TIP_Data_Engine::singleton() calls.
     *
     * A source engine is instantiated by includind its logic file found in the
     * 'sources' directory (relative to 'logic_root').
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
            $file = TIP::buildLogicPath('sources', $id) . '.php';
            $instance =& TIP_Source_Engine::singleton($id, $file);
        }
        return $instance;
    }

    /**#@-*/
}

?>
