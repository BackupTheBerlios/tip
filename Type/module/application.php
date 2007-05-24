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
 * $_queue property, where the modules will prepend or append there callbacks.
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
    private $_queue = array();


    private function _dumpRegister(&$register, $indent)
    {
        foreach ($register as $id => $value) {
            echo "$indent$id\n";
            if (is_array($value)) {
                $this->_dumpRegister($register[$id], $indent . '    ');
            }
        }
    }

    protected function __construct($id)
    {
        parent::__construct($id);
        $GLOBALS[TIP_MAIN] =& $this;
    }

    protected function postConstructor()
    {
        parent::postConstructor();

        $this->keys['TODAY'] = TIP::formatDate('date_iso8601');
        $this->keys['NOW'] = TIP::formatDate('datetime_iso8601');
        $this->keys['BASE_URL'] = TIP::getBaseURL();
        $this->keys['SCRIPT'] = TIP::getScriptURI();
        $this->keys['REFERER'] = TIP::getRefererURI();
        $this->keys['REQUEST'] = TIP::getRequestURI();

        if ($this->keys['IS_ADMIN']) {
            require_once 'Benchmark/Profiler.php';
            $GLOBALS['_tip_profiler'] =& new Benchmark_Profiler;
            $GLOBALS['_tip_profiler']->start();
        }
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
        if (empty($this->_queue)) {
            $this->commandRunShared('default.src');
        } else {
            foreach ($this->_queue as $item) {
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
            $logger =& $this->getSharedModule('logger');
            if (is_object($logger)) {
                $logger->commandRun('browse.src');
            }
        }

        if ($this->keys['IS_ADMIN']) {
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
            echo '<pre style="font-family: monospace">';
            $register =& TIP_Type::singleton(array());
            $this->_dumpRegister($register, '');
            echo "</pre>";
        }

        return true;
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

        // Executes the action
        $action = TIP::getGet('action', 'string');
        if ($action) {
            $module_name = TIP::getGet('module', 'string');
        } else {
            $action = TIP::getPost('action', 'string');
            $module_name = TIP::getPost('module', 'string');
        }

        if ($module_name && !$action) {
            TIP::notifyError('noaction');
        } elseif (! $module_name && $action) {
            TIP::notifyError('nomodule');
        } elseif ($module_name) {
            $module =& TIP_Type::getInstance($module_name);
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
     * Inserts at the beginning of $_queue the specified callback, that will
     * be called while generating the page.
     *
     * The callback can be expressed in any format accettable by
     * the call_user_func() function.
     *
     * @param mixed $callback The callback
     * @param array $args     Arguments of the callback
     */
    public function prependCallback($callback, $args = array())
    {
        array_unshift($this->_queue, null);
        $this->_queue[0] = array(self::CALLBACK => $callback, self::ARGS => $args);
    }

    /**
     * Append a page callback
     *
     * Appends at the end of $_queue the specified callback, that will
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
        $this->_queue[] = array(self::CALLBACK => $callback, self::ARGS => $args);
    }
}
?>
