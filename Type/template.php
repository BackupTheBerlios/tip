<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Template definition file
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
 * A generic template instance
 *
 * @package TIP
 */
class TIP_Template extends TIP_Type
{
    //{{{ Properties

    /**
     * The template engine to be used with this template file
     * @var TIP_Template_Engine
     */
    protected $engine = null;

    /**
     * The path to the template file
     * @var array
     */
    protected $path = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Check the options
     *
     * Builds an unique 'id' from the 'path' option (required).
     * It also checks for the template engine definition and
     * builds the template path, by checking its existence inside
     * "template_root" directory before and in "fallback_root" after.
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!isset($options['engine'])) {
            $options['engine'] =& TIP_Application::getGlobal('engine');
        }

        if (!isset($options['path']) ||
            !$options['engine'] instanceof TIP_Template_Engine) {
            return false;
        }

        $path =& $options['path'];
        $engine =& $options['engine'];

        if (is_null($path = $engine->getTemplatePath($path)))
            return false;

        $options['id'] = implode(DIRECTORY_SEPARATOR, $path);
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
     * Execute the template file
     *
     * Parses and executes this template.
     *
     * @param  TIP_Module &$caller The caller module
     * @return bool                true on success or false on errors
     */
    public function run(&$caller)
    {
        return $this->engine->run($this, $caller);
    }

    //}}}
    //{{{ Internal properties

    /**
     * The content of the template file
     * @var string
     * @internal
     */
    public $_buffer = null;

    /**
     * A custom property to be used by the template engine
     * @var mixed
     * @internal
     */
    public $_instance = null;

    //}}}
}
?>
