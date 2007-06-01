<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Application definition file
 * @package TIP
 */

/**
 * The main module
 *
 * This module manages the generation of the "page" (the most dynamic part of
 * a TIP site). This is done by using a callback queue, stored in the private
 * $_callback_queue property, where the modules will prepend or append their
 * callbacks.
 *
 * When the commandPage() is called, usually throught a tag in the main source
 * file, the TIP_Application module will call the callbacks stored in the queue
 * in sequential order. The page is the output of these callbacks.
 *
 * After a TIP_Application instantiation, the global variable $GLOBALS[TIP_MAIN]
 * will contain a reference to this TIP_Application instantiated object.
 * Your main script, if the TIP system is properly configured, will usually be
 * as the following one:
 *
 * <code>
 * <?php
 * require_once './logic/Defs.php';
 * require_once './config.php';
 * require_once './logic/TIP.php';
 *
 * TIP_Type::getInstance('main');
 * $GLOBALS[TIP_MAIN]->go('index.src');
 * ?>
 * </code>
 *
 * @package  TIP
 * @tutorial TIP/TIP.pkg
 */
class TIP_Application extends TIP_Module
{
    const CALLBACK = 0;
    const ARGS = 1;

    private $_request = null;
    private $_referer = null;
    private $_callback_queue = array();


    protected function __construct($id)
    {
        parent::__construct($id);
        $GLOBALS[TIP_MAIN] =& $this;
    }

    protected function postConstructor()
    {
        parent::postConstructor();

        // Store request and referer in the session data
        TIP::startSession();
        $this->_request = self::_getRequest();
        $this->_referer = self::_getReferer($this->_request);
        HTTP_Session2::set('referer', $this->_referer);
        HTTP_Session2::set('request', $this->_request);

        $this->keys['TODAY'] = TIP::formatDate('date_iso8601');
        $this->keys['NOW'] = TIP::formatDate('datetime_iso8601');
        $this->keys['BASE_URL'] = TIP::getBaseURL();
        $this->keys['SCRIPT'] = TIP::getScriptURI();
        $this->keys['REFERER'] = $this->_referer['uri'];
        $this->keys['REQUEST'] = $this->_request['uri'];
        $this->keys['MODULE'] = $this->_request['module'];
        $this->keys['ACTION'] = $this->_request['action'];

        if ($this->keys['IS_ADMIN']) {
            require_once 'Benchmark/Profiler.php';
            $GLOBALS['_tip_profiler'] =& new Benchmark_Profiler;
            $GLOBALS['_tip_profiler']->start();
        }
    }

    static private function _getRequest()
    {
        // Get current module and action
        $module = TIP::getGet('module', 'string');
        if ($module) {
            $action = TIP::getGet('action', 'string');
        } else {
            $module = TIP::getPost('module', 'string');
            $action = TIP::getPost('action', 'string');
        }

        return array(
            'module' => $module,
            'action' => $action,
            'uri'    => @$_SERVER['REQUEST_URI']
        );
    }

    static private function _getReferer($request)
    {
        $old_request = HTTP_Session2::get('request');
        $old_referer = HTTP_Session2::get('referer');

        if (is_null($old_request)) {
            // Entry page or new session: the referer is the main page
            $referer = null;
        } elseif ($request['uri'] == $old_referer['uri']) {
            // Current URI equals to the old referer URI: probably a back action
            $referer = null;
        } elseif ($request['module'] != $old_request['module'] || $request['action'] != $old_request['action']) {
            // New action: the referer is the previous request
            $referer = $old_request;
        } else {
            // Same action: leave the old referer
            $referer = $old_referer;
        }

        if (!is_array($referer)) {
            $referer['module'] = null;
            $referer['action'] = null;
            $referer['uri'] = TIP::getScriptURI();
        }

        return $referer;
    }

    public function getRequestURI()
    {
        return $this->_request['uri'];
    }

    public function getRefererURI()
    {
        return $this->_referer['uri'];
    }

    /**
     * Output the page
     *
     * The output of every action is deferred to the page, that can be
     * placed anywhere in the main source.
     *
     * @param  string $params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function commandPage($params)
    {
        if (empty($this->_callback_queue)) {
            $this->commandRunShared('default.src');
        } else {
            foreach ($this->_callback_queue as $item) {
                call_user_func_array($item[self::CALLBACK], $item[self::ARGS]);
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
     *
     * @param  string $params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function commandDebug($params)
    {
        if ($this->keys['IS_TRUSTED']) {
            // Show logged messages
            $logger =& $this->getSharedModule('logger');
            if (is_object($logger)) {
                $logger->commandRun('browse.src');
            }
        }

        if ($this->keys['IS_ADMIN']) {
            // Display profiling informations
            global $_tip_profiler;
            if (is_object($_tip_profiler)) {
                // Leave itsself, that is the commandDebug section
                $_tip_profiler->leaveSection('debug');

                $_tip_profiler->stop();
                $_tip_profiler->display('html');

                // This prevent further operation on $_tip_profiler
                $_tip_profiler = null;
            }
        }

        if ($this->keys['IS_MANAGER']) {
            // Dump the singleton register content
            echo '<pre style="font-family: monospace">';
            self::_dumpRegister(TIP_Type::singleton(array()), '  ');
            echo "</pre>";
        }

        return true;
    }

    static private function _dumpRegister(&$register, $indent = '')
    {
        foreach ($register as $id => &$obj) {
            echo "$indent$id\n";
            is_array($obj) && self::_dumpRegister($obj, $indent . '  ');
        }
    }

    protected function runManagerAction($action)
    {
        switch($action) {

        case 'phpinfo':
            $GLOBALS[TIP_MAIN]->appendCallback('phpinfo');
            return true;
        }

        return null;
    }

    protected function runAction($action)
    {
        switch($action) {

        case 'fatal':
            return TIP::notifyError('fatal');
        }

        return null;
    }

    /**
     * The "main" function
     *
     * The starting point of the TIP system. This must be called somewhere from
     * your main script.
     *
     * @param string $main_source The main source program to run
     */
    public function go($main_source)
    {
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

        // Set the timezone
        date_default_timezone_set('Europe/Rome');

        // Executes the action
        if ($this->_request['module'] && $this->_request['action']) {
            if (is_null($module =& TIP_Type::getInstance($this->_request['module']))) {
                TIP::notifyError('module');
            } elseif (is_null($module->callAction($this->_request['action']))) {
                TIP::notifyError(is_null(TIP::getUserId()) ? 'reserved' : 'denied');
            }
        } elseif ($this->_request['module']) {
            TIP::notifyError('noaction');
        } elseif ($this->_request['action']) {
            TIP::notifyError('nomodule');
        }

        // Generates the page
        $this->commandRun($main_source);

        HTTP_Session2::pause();
    }

    /**
     * Get a shared module
     *
     * Some special modules are shared between the application. A common example
     * is the logger or the notify modules.
     * To provide maximum flexibility, this method will get a reference to these
     * kind of modules accessing them by $job, not by id. $job is an arbitrary
     * string identifying the type of work the module must do: maybe in the
     * future, when TIP will be more stable, the jobs will be standardized with
     * a bounch of constant values.
     *
     * @param  string        $job The job identifier
     * @return TIP_Type|null      The requested shared module or null if not found
     */
    public function &getSharedModule($job)
    {
        static $cache = array();

        if (!array_key_exists($job, $cache)) {
            $shared_modules = $this->getOption('shared_modules');
            if (array_key_exists($job, $shared_modules)) {
                $cache[$job] =& TIP_Type::getInstance($shared_modules[$job]);
            } else {
                $cache[$job] = null;
            }
        }

        return $cache[$job];
    }

    /**
     * Prepend a page callback
     *
     * Inserts at the beginning of $_callback_queue the specified callback,
     * that will be called while generating the page.
     *
     * The callback can be expressed in any format accettable by
     * the call_user_func() function.
     *
     * @param mixed $callback The callback
     * @param array $args     Arguments of the callback
     */
    public function prependCallback($callback, $args = array())
    {
        array_unshift($this->_callback_queue, null);
        $this->_callback_queue[0] = array(
            self::CALLBACK => $callback,
            self::ARGS     => $args
        );
    }

    /**
     * Append a page callback
     *
     * Appends at the end of $_callback_queue the specified callback, that will
     * be called while generating the page.
     *
     * The callback can be expressed in any format accettable by
     * the call_user_func() function.
     *
     * @param mixed $callback The callback
     * @param array $args     Arguments of the callback
     */
    public function appendCallback($callback, $args = array())
    {
        $this->_callback_queue[] = array(
            self::CALLBACK => $callback,
            self::ARGS     => $args
        );
    }
}
?>
