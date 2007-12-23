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
    //{{{ Properties

    /**
     * The source base url
     * @var array
     */
    protected $source_root = array('style');

    /**
     * The fallback base url
     * @var array
     */
    protected $fallback_root = null;

    /**
     * The cache base url
     * @var array
     */
    protected $cache_root = null;

    /**
     * The compiled base url
     * @var array
     */
    protected $compiled_root = null;

    //}}}
    //{{{ Interface

    /**
     * Execute a source/template buffer
     *
     * Parses and executes the specified buffer.
     *
     * @param  mixed      &$instance An engine-dependent instance
     * @param  string     &$buffer   The buffer to run
     * @param  TIP_Module &$caller   The caller module
     * @return bool                  true on success or false on errors
     */
    abstract public function runBuffer(&$instance, &$buffer, &$caller);

    //}}}
    //{{{ Methods

    /**
     * Execute a source file
     *
     * Parses and executes a source.
     *
     * @param  TIP_Source &$source The source to run
     * @param  TIP_Module &$caller The caller module
     * @return bool                true on success or false on errors
     */
    public function run(&$source, &$caller)
    {
        if (is_null($source->_buffer)) {
            $path =& $source->getProperty('path');
            $file = TIP::buildSourcePath($path);
            is_readable($file) || $file = TIP::buildFallbackPath($path);
            $source->_buffer = file_get_contents($file);
        }

        // Check for reading errors
        if ($source->_buffer === false) {
            TIP::error("error in reading file ($file)");
            return false;
        }

        return $this->runBuffer($source->_instance, $source->_buffer, $caller);
    }

    //}}}
}
?>
