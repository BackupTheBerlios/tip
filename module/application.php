<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Application definition file
 * @package TIP
 */

/**
 * The main module
 *
 * This module manages the generation of the page content (the most dynamic
 * part of a TIP site). This is done by using a callback queue, stored in the
 * private $_queue property, where the modules will prepend or append there
 * callbacks.
 *
 * When the commandContent() is called, usually throught a tag in the main
 * source file, the TIP_Application module will call the callbacks stored in
 * the queue in sequential order. The page content is the output of these
 * callbacks.
 *
 * The global variable $GLOBALS[TIP_MAIN_MODULE] contains a reference to the
 * TIP_Application instantiated object. Your index.php, if the TIP system is
 * properly configured, will usually be as the following one:
 *
 * <code>
 * <?php
 *
 * require_once 'logic/TIP.php';
 * $GLOBALS[TIP_MAIN_MODULE]->go('index.src');
 *
 * ?>
 * </code>
 *
 * @final
 * @package  TIP
 * @tutorial TIP/TIP.pkg
 */
class TIP_Application extends TIP_Module
{
    /**#@+ @access private */

    var $_queue = array();

    /**#@-*/


    /**#@+ @access protected */

    function postConstructor()
    {
        parent::postConstructor();

        $this->keys['ROOT'] = TIP::buildUrl('index.php');
        $this->keys['REFERER'] = @$_SERVER['HTTP_REFERER'];
        $this->keys['TODAY'] = TIP::formatDate('date_iso8601');
        $this->keys['NOW'] = TIP::formatDate('datetime_iso8601');

        if ($this->keys['IS_MANAGER']) {
            require_once 'Benchmark/Profiler.php';
            $GLOBALS['_tip_profiler'] =& new Benchmark_Profiler;
            $GLOBALS['_tip_profiler']->start();
        }
    }


    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Output the page content
     *
     * The output of every action is deferred to the page content, that can be
     * placed anywhere in the main source.
     */
    function commandContent($params)
    {
        if (empty($this->_queue)) {
            $this->commandRunShared('welcome.src');
        } else {
            foreach (array_keys($this->_queue) as $id) {
                $this->_queue[$id]->go();
            }
        }
        return true;
    }

    /**
     * Output some debug information
     *
     * This echoes some output and profiler information, useful in the
     * developement process. This works only if the current user has manager
     * privileges on the application module.
     */
    function commandDebug($params)
    {
        if (! $this->keys['IS_MANAGER']) {
            return true;
        }

        $logger =& TIP_Module::getInstance('logger');
        if (is_object($logger)) {
            $logger->commandRun('browse.src');
        }

        global $_tip_profiler;
        if (is_object($_tip_profiler)) {
            // Leave itsself, that is the commandDebug section
            $_tip_profiler->leaveSection('debug');

            $_tip_profiler->stop();
            $_tip_profiler->display('html');

            // This prevent further operation on $_tip_profiler
            $_tip_profiler = null;
        }

        return true;
    }

    /**#@-*/


    function runAction($action)
    {
        switch($action) {
        case 'fatal':
            return TIP::notifyError('fatal');
        }

        return null;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * The "main" function
     *
     * The starting point of the TIP system. This must be called somewhere from
     * your index.php.
     *
     * @param string $main_source The main source program to run
     */
    function go($main_source)
    {
        TIP::_startSession();
        $locale = $this->getOption('locale');

        // Locale settings
        switch (TIP::getOS()) {
        case 'unix':
            // Dirty hack to set the locale on unix systems
            setlocale(LC_ALL, $locale . '_' . strtoupper($locale));
            break;
        case 'windows':
            setlocale(LC_ALL, $locale);
            break;
        default:
            break;
        }

        // Executes the action
        $action = TIP::getGet('action', 'string');
        if ($action) {
            $module_name = TIP::getGet('module', 'string');
        } else {
            $action = TIP::getPost('action', 'string');
            $module_name = TIP::getPost('module', 'string');
        }

        if ($module_name && ! $action) {
            TIP::notifyError('noaction');
        } elseif (! $module_name && $action) {
            TIP::notifyError('nomodule');
        } elseif ($module_name) {
            $module =& TIP_Module::getInstance($module_name, false);
            if (is_object($module)) {
                $this->keys['ACTION'] = $action;
                if (is_null($module->callAction($action))) {
                    $anonymous = is_null(TIP::getUserId());
                    TIP::notifyError($anonymous ? 'reserved' : 'denied');
                }
            } else {
                TIP::notifyError('module');
            }
        }

        // Generates the page
        $this->commandRun($main_source);
    }

    /**
     * Prepend a page content callback
     *
     * Inserts at the beginning of $_queue the specified callback, that will
     * be called while generating the page content.
     *
     * @param TIP_Callback &$callback The callback
     */
    function prependCallback(&$callback)
    {
        array_unshift($this->_queue, null);
        $this->_queue[0] =& $callback;
    }

    /**
     * Append a page content callback
     *
     * Appends at the end of $_queue the specified callback, that will
     * be called while generating the page content.
     *
     * @param TIP_Callback &$callback The callback
     */
    function appendCallback(&$callback)
    {
        $this->_queue[] =& $callback;
    }

    /**#@-*/
}


return 'TIP_Application';

?>
