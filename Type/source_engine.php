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
     * Parse and execute a source/template buffer
     * @param  TIP_Source &$source The source instance
     * @param  TIP_Module &$caller The caller module
     * @return bool|string         true on success,
     *                             false if the result must be cached or
     *                             a message string on errors
     */
    abstract public function runBuffer(&$source, &$caller);

    /**
     * Compile a source/template buffer
     * @param  TIP_Source &$source The source instance
     * @return string|null         The compiled code or null if not possible
     */
    abstract public function compileBuffer(&$source);

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
        $result = $this->runBuffer($source, $caller);
        if (is_string($result)) {
            ob_end_clean();
            TIP::error($result);
            return false;
        }
       
        if ($result == false && isset($cache)) {
            // Caching requested
            $dir = dirname($cache);
            if (is_dir($dir) || mkdir($dir, 0777, true)) {
                file_put_contents($cache, ob_get_contents(), LOCK_EX);
            } else {
                TIP::warning("Unable to create the cache path ($dir)");
            }
        } elseif (isset($compiled)) {
            // Compiling requested
            $result = $this->compileBuffer($source);
            if (is_string($result)) {
                // Compilation successfull
                $dir = dirname($compiled);
                if (is_dir($dir) || mkdir($dir, 0777, true)) {
                    file_put_contents($compiled, $result, LOCK_EX);
                } else {
                    TIP::warning("Unable to create the compiled path ($dir)");
                }
            }
        }

        ob_end_flush();
        return true;
    }

    /**
     * Get the path to the cache file, if it exists
     * @param  TIP_Source  &$source The source to check
     * @return string|null          Path to the cache file or null on problems
     */
    public function getCachePath(&$source)
    {
        if (is_array($this->cache_root)) {
            $path = implode(DIRECTORY_SEPARATOR, array_merge($this->cache_root, $source->getProperty('path')));
            if (is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Get the relative URL to the cached file
     *
     * Works in a similar way to getCachePath() but returning a relative URL
     * to the cached file.
     *
     * @param  TIP_Source  &$source The source to check
     * @return string|null          URL to the cache file or null on problems
     */
    public function getCacheURL(&$source)
    {
        if (is_array($this->cache_root)) {
            $dirs = array_merge($this->cache_root, $source->getProperty('path'));
            if (is_readable(implode(DIRECTORY_SEPARATOR, $dirs))) {
                return implode('/', $dirs);
            }
        }
        return null;
    }

    //}}}
}
?>
