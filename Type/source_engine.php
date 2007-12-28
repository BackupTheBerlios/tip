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
     * @return bool|string           true on success,
     *                               false if the result must be cached or
     *                               a message string on errors
     */
    abstract public function runBuffer(&$instance, &$buffer, &$caller);

    abstract public function compileBuffer(&$instance, &$buffer, &$caller);

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
        $path =& $source->getProperty('path');

        // Check for cached result
        if (is_array($this->cache_root)) {
            $cache = implode(DIRECTORY_SEPARATOR, array_merge($this->cache_root, $path));
            if (is_readable($cache)) {
                return readfile($cache) !== false;
            }
        }

        // Check for compiled file
        if (is_array($this->compiled_root)) {
            $compiled = implode(DIRECTORY_SEPARATOR, array_merge(array('.'), $this->compiled_root, $path)) . '.php';
            if (is_readable($compiled)) {
                return (include $compiled) !== false;
            }
        }
 
        // No cache or compiled file found: parse and run this source
        isset($source->_buffer) || $source->_buffer = file_get_contents($source->__toString());

        if ($source->_buffer === false) {
            TIP::error("unable to read file ($source)");
            return false;
        }

        ob_start();

        // Try to compile
        if (isset($compiled) && $this->compileBuffer($source->_instance, $source->_buffer, $caller)) {
            // Compilation succesfull
            $dir = dirname($compiled);
            if (is_dir($dir) || mkdir($dir, 0777, true)) {
                file_put_contents($compiled, ob_get_clean(), LOCK_EX);
                return (include $compiled) !== false;
            } else {
                TIP::warning("Unable to create the compiled path ($dir)");
                ob_clean();
            }
        }

        $result = $this->runBuffer($source->_instance, $source->_buffer, $caller);
        if (is_string($result)) {
            ob_end_clean();
            TIP::error($result);
            return false;
        } elseif (!$result && isset($cache)) {
            // false returned: cache the result
            $dir = dirname($cache);
            if (is_dir($dir) || mkdir($dir, 0777, true)) {
                file_put_contents($cache, ob_get_contents(), LOCK_EX);
            } else {
                TIP::warning("Unable to create the cache path ($dir)");
            }
        }

        ob_end_flush();
        return true;
    }

    //}}}
}
?>
