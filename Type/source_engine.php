<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

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
    //{{{ Interface

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

    //}}}
}
?>
