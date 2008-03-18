<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Source definition file
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
 * A generic source template
 *
 * @package  TIP
 */
class TIP_Source extends TIP_Type
{
    //{{{ Properties

    /**
     * The path to the source file
     * @var array
     */
    protected $path = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Check the options
     *
     * Builds an unique 'id' from the 'path' option (required).
     * It also checks for the source existence.
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!isset($options['path'])) {
            return false;
        }

        $path =& $options['path'];
        if (is_readable($file = TIP::buildSourcePath($path))) {
            // Found the source in the default path
            $path = array_merge(TIP_Application::getGlobal('source_root'), $path);
        } elseif (is_readable($file = TIP::buildFallbackPath($path))) {
            // Found the source in the fallback path
            $path = array_merge(TIP_Application::getGlobal('fallback_root'), $path);
        } else {
            // Source not found
            return false;
        }

        $options['id'] = $file;
        return parent::checkOptions($options);
    }

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
     * @param  TIP_Module &$caller The caller module
     * @return bool                true on success or false on errors
     */
    public function run(&$caller)
    {
        return $caller->getProperty('engine')->run($this, $caller);
    }

    //}}}
    //{{{ Internal properties

    /**
     * The content of the source file
     * @var string
     * @internal
     */
    public $_buffer = null;

    /**
     * A custom property to be used by the source engine
     * @var mixed
     * @internal
     */
    public $_instance = null;

    //}}}
}
?>
