<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Template_Engine definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
 */

/**
 * Base class for template engines
 *
 * Provides a common interface to run template files.
 *
 * @package  TIP
 */
abstract class TIP_Template_Engine extends TIP_Type
{
    //{{{ Properties

    /**
     * The extension to append to the template files
     * @var string
     */
    protected $extension = null;

    /**
     * The template root path
     * @var string
     */
    protected $template_root = array('style');

    /**
     * The template fallback path
     * @var string
     */
    protected $fallback_root = null;

    /**
     * The cache root
     * @var array
     */
    protected $cache_root = array('data', 'cache');

    /**
     * The compiled root
     * @var array
     */
    protected $compiled_root = array('data', 'rcbtng');

    /**
     * Is the caching feature enabled or not?
     * @var boolean
     */
    protected $caching = false;

    /**
     * Is the compiling feature enabled or not?
     * @var boolean
     */
    protected $compiling = false;

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        if (isset($options['cache_root']) && is_string($options['cache_root'])) {
            $options['cache_root'] = array($options['cache_root']);
        }
        if (isset($options['compiled_root']) && is_string($options['compiled_root'])) {
            $options['compiled_root'] = array($options['compiled_root']);
        }

        return true;
    }

    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Interface

    /**
     * Parse and execute a template buffer
     * @param  TIP_Template &$template The template instance
     * @param  TIP_Module   &$caller   The caller module
     * @return bool|string             true on success,
     *                                 false if the result must be cached or
     *                                 a message string on errors
     */
    abstract public function runBuffer(&$template, &$caller);

    /**
     * Compile a template buffer
     * @param  TIP_Template &$template The template instance
     * @return string|null             The compiled code or null on errors
     */
    abstract public function compileBuffer(&$template);

    //}}}
    //{{{ Methods

    /**
     * Execute a template file
     *
     * Parses and executes a template.
     *
     * @param  TIP_Template &$template The template to run
     * @param  TIP_Module   &$caller   The caller module
     * @return bool                    true on success or false on errors
     */
    public function run(&$template, &$caller)
    {
        $path =& $template->getProperty('path');

        // Check for cached result
        if ($this->caching) {
            $cache = implode(DIRECTORY_SEPARATOR, array_merge($this->cache_root, $path));
            if (is_readable($cache)) {
                return readfile($cache) !== false;
            }
        }

        // Check for compiled file
        if ($this->compiling) {
            $compiled = implode(DIRECTORY_SEPARATOR, array_merge(array('.'), $this->compiled_root, $path)) . '.php';
            if (is_readable($compiled)) {
                return (include $compiled) !== false;
            }
        }
 
        // No cache or compiled file found: parse and run this template
        $file = $template->__toString();
        isset($this->extension) && $file .= $this->extension;
        isset($template->_buffer) || $template->_buffer = file_get_contents($file);
        if ($template->_buffer === false) {
            TIP::error("unable to read file ($file)");
            return false;
        }

        ob_start();
        $result = $this->runBuffer($template, $caller);
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
            $result = $this->compileBuffer($template);
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
     * Get the path nodes to a template file
     *
     * Check for the template file identified by path in the 'template_root'
     * directory. If the template does not exist, try to get it from
     * 'fallback_root'. The "extension" property, if defined by the template
     * engine, is used to build the file name.
     *
     * Return the path as an array of nodes (directory and file names), so
     * it can be easily used to build both paths and URIs.
     *
     * @param  array|string &$path The path nodes to analyze
     * @return array|null          New path or null on errors
     */
    public function getTemplatePath(&$path)
    {
        is_string($path) && $path = explode(DIRECTORY_SEPARATOR, $path);
        $ext = isset($this->extension) ? $this->extension : '';

        // Search in the template_root directory
        $new_path = array_merge($this->template_root, $path);
        if (is_readable(implode(DIRECTORY_SEPARATOR, $new_path) . $ext)) {
            return $new_path;
        }

        // Search in the fallback_root directory
        $new_path = array_merge($this->fallback_root, $path);
        if (is_readable(implode(DIRECTORY_SEPARATOR, $new_path) . $ext)) {
            return $new_path;
        }

        // Template file not found
        return null;
    }

    /**
     * Get the path to the cache file without checking for file existence
     * @param  TIP_Template &$template The template to build
     * @return string|null             Path to the cache or null on errors
     */
    public function buildCachePath(&$template)
    {
        return implode(DIRECTORY_SEPARATOR, array_merge($this->cache_root, $template->getProperty('path')));
    }

    /**
     * Get the path to the cache file, if it exists
     * @param  TIP_Template &$template The template to check
     * @return string|null             Path to the cache or null on errors
     */
    public function getCachePath(&$template)
    {
        $path = $this->buildCachePath($template);
        return is_readable($path) ? $path : null;
    }

    /**
     * Get the relative URI of the cached file, if it exists
     * @param  TIP_Template &$template The template to check
     * @return string|null             URI to the cache or null on errors
     */
    public function getCacheUri(&$template)
    {
        $dirs = array_merge($this->cache_root, $template->getProperty('path'));
        if (!is_readable(implode(DIRECTORY_SEPARATOR, $dirs))) {
            return null;
        }
        return TIP::buildUri($dirs);
    }

    //}}}
}
?>
