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
 * @package  TIP
 * @tutorial TIP/SourceEngine/SourceEngine.pkg
 */
abstract class TIP_Source_Engine extends TIP_Type
{
    /**
     * Source engine constructor
     *
     * Chains up the parent constructor.
     * You must redefine the constructor as public to be able to use it.
     *
     * @param string $id The derived instance identifier
     */
    function __construct($id)
    {
        parent::__construct($id);
    }

    /**
     * Execute a source/template file
     *
     * Parses and executes the specified source.
     *
     * @param  TIP_Source &$source The source instance
     * @param  TIP_Module &$module The caller module
     * @return bool                true on success or false on errors
     */
    abstract public function run(&$source, &$module);
}
?>
