<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Application definition file
 * @package TIP
 */

/**
 * The main module
 *
 * This module manages the generation of the "page" (the most dynamic part of
 * a TIP site). This is done by using a callback queue, stored in the internal
 * $_queue property, where the modules will prepend or append their
 * callbacks.
 *
 * When the tagPage() is called, usually throught a tag in the main source
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
    //{{{ Properties

    /**
     * The default data engine to use: required
     * @var TIP_Data_Engine
     */
    protected $data_engine = null;

    /**
     * The source root path
     * @var string
     */
    protected $source_root = null;

    /**
     * The source fallback path
     * @var string
     */
    protected $source_fallback = 'style';

    /**
     * The upload root path
     * @var string
     */
    protected $upload_root = 'upload';

    /**
     * The cache root path
     * @var string
     */
    protected $cache_root = 'cache';

    /**
     * The shared modules interface
     *
     * The associative array of shared modules, organized by "job".
     * The job is the key, while the instance id is the value.
     *
     * @var array
     */
    protected $shared_modules = array(
        'user'      => 'user',
        'privilege' => 'privilege',
        'notify'    => 'notify',
        'locale'    => 'locale',
        'logger'    => 'logger'
    );

    /**
     * A prefix to prepend to all data ids
     * @var string
     */
    protected $data_prefix = '';

    /**
     * The url to redirect the browse on fatal errors
     * @var string
     */
    protected $fatal_url = null;

    /**
     * The file to run to notify errors
     * @var string
     */
    protected $error_source = 'error.src';

    /**
     * The file to run to notify warnings
     * @var string
     */
    protected $warning_source = 'warning.src';

    /**
     * The file to run to notify informations
     * @var string
     */
    protected $info_source = 'info.src';

    //}}}
    //{{{ Constructor/destructor

    /**
     * Check the options
     *
     * Overridable static method that checks $options for missing or invalid
     * values and eventually corrects its content.
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['engine'], $options['data_engine'])) {
            return false;
        }

        if (is_string($options['data_engine'])) {
            $options['data_engine'] =& TIP_Type::singleton(array(
                'id'   => $options['data_engine'],
                'type' => array('data_engine', $options['data_engine'])
            ));
        } elseif (is_array($options['data_engine'])) {
            $options['data_engine'] =& TIP_Type::singleton($options['data_engine']);
        }
        if (is_null($options['data_engine'])) {
            return false;
        }

        isset($options['fatal_url']) || $options['fatal_url'] = TIP::getScriptURI() . '?module=' . $options['id'] . '&action=fatal';
        isset($options['anonymous_privilege']) || $options['anonymous_privilege'] = TIP_PRIVILEGE_NONE;
        isset($options['default_privilege']) || $options['default_privilege'] = TIP_PRIVILEGE_NONE;
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Application instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        $GLOBALS[TIP_MAIN] =& $this;
        parent::__construct($options);
    }

    protected function postConstructor()
    {
        parent::postConstructor();

        $this->keys['TODAY'] = TIP::formatDate('date_iso8601');
        $this->keys['NOW'] = TIP::formatDate('datetime_iso8601');
        $this->keys['BASE_URL'] = TIP::getBaseURL();
        $this->keys['DOMAIN'] = rtrim($this->keys['BASE_URL'], '/');
        $this->keys['SCRIPT'] = TIP::getScriptURI();
        $this->keys['REFERER'] = '';

        // Set $_request
        $module = TIP::getGet('module', 'string');
        if ($module) {
            $action = TIP::getGet('action', 'string');
        } else {
            $module = TIP::getPost('module', 'string');
            $action = TIP::getPost('action', 'string');
        }

        $this->_request = array(
            'uri'    => @$_SERVER['REQUEST_URI'],
            'module' => @strtolower($module),
            'action' => @strtolower($action)
        );

        $this->keys['REQUEST'] = $this->_request['uri'];
        $this->keys['MODULE'] = $this->_request['module'];
        $this->keys['ACTION'] = $this->_request['action'];

        if ($this->_request['module'] == $this->id && $this->_request['action'] == 'backup') {
            return;
        }

        // Start the session
        TIP::startSession();
        $this->_session_started = true;

        // Set $_referer
        $request = HTTP_Session2::get('request');
        $referer = HTTP_Session2::get('referer');

        if (is_null($request)) {
            // Entry page or new session: the referer is the main page
            $this->_referer = null;
        } elseif ($this->_request['uri'] == $referer['uri']) {
            // Current URI equals to the old referer URI: probably a back action
            $this->_referer = null;
        } elseif ($this->_request['module'] != $request['module'] || $this->_request['action'] != $request['action']) {
            // New action: the referer is the previous request
            $this->_referer = $request;
        } else {
            // Same action: leave the old referer
            $this->_referer = $referer;
        }

        if (!is_array($this->_referer)) {
            $this->_referer = array(
                'uri'    => TIP::getScriptURI(),
                'module' => null,
                'action' => null
            );
            $this->_referer['action'] = null;
        }

        $this->keys['REFERER'] = $this->_referer['uri'];

        // Store request and referer
        HTTP_Session2::set('referer', $this->_referer);
        HTTP_Session2::set('request', $this->_request);

        if ($this->keys['IS_ADMIN']) {
            require_once 'Benchmark/Profiler.php';
            $GLOBALS['_tip_profiler'] =& new Benchmark_Profiler;
            $GLOBALS['_tip_profiler']->start();
        }
    }

    //}}}
    //{{{ Methods

    /**
     * Get a global property
     *
     * Returns a reference to a property in the current application instance.
     *
     * @param  string $property The property name
     * @return mixed            A reference to the requested property
     */
    static public function &getGlobal($property)
    {
        return $GLOBALS[TIP_MAIN]->$property;
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
    static public function &getSharedModule($job)
    {
        static $cache = array();

        if (!isset($cache[$job])) {
            $cache[$job] =& TIP_Type::getInstance($GLOBALS[TIP_MAIN]->shared_modules[$job]);
        }

        return $cache[$job];
    }

    /**
     * Prepend a page callback
     *
     * Inserts at the beginning of $_queue the specified callback,
     * that will be called while generating the page.
     *
     * The callback can be expressed in any format accettable by
     * the call_user_func() function.
     *
     * @param mixed $callback The callback
     * @param array $args     Arguments of the callback
     */
    static public function prependCallback($callback, $args = array())
    {
        array_unshift($GLOBALS[TIP_MAIN]->_queue, null);
        $GLOBALS[TIP_MAIN]->_queue[0] = array($callback, $args);
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
    static public function appendCallback($callback, $args = array())
    {
        $GLOBALS[TIP_MAIN]->_queue[] = array($callback, $args);
    }

    /**
     * Generic message notification
     *
     * Outputs a generic notification message running the specified source
     * program with the current source engine. The output will be inserted
     * at the beginning of the page content.
     *
     * @param  TIP_SEVERITY_... $severity Severity level
     * @param  mixed            $id       The id of the message, without the
     *                                    "notify.$severity." prefix
     * @return bool                       true on success or false on errors
     */
    static public function notify($severity, $id)
    {
        static $running = false;

        // The running flag avoid recursive calls to notify()
        if ($running) {
            TIP::warning('recursive call to notify()');
            return false;
        }

        $running = true;
        if (is_null($locale =& TIP_Type::getInstance('locale'))) {
            TIP::warning('TIP_Notify without TIP_Locale is not implemented');
            return false;
        }

        $title_id = 'notify.' . $severity;
        $message_id = $title_id . '.' . $id;
        $data =& $locale->getProperty('data');

        $key = $data->getProperty('primary_key');
        $filter = $data->filter($key, $title_id) . $data->addFilter('OR', $key, $message_id);
        if (!is_null($view =& $locale->startDataView($filter))) {
            $rows =& $view->getProperty('rows');
            $locale->endView();
            $locale_id = $locale->getProperty('locale');
            $title = @$rows[$title_id][$locale_id];
            $message = @$rows[$message_id][$locale_id];
        }

        // Fallback values
        isset($title) || ($title = 'UNDEFINED TITLE') && TIP::warning("localized id not found ($title_id)");
        isset($message) || ($message = 'Undefined message') && TIP::warning("localized id not found ($message_id)");

        $main = $GLOBALS[TIP_MAIN];
        $source_property = $severity . '_source';
        $source = $main->$source_property;
        $main->prependCallback(array(&$main, '_runNotify'), array($source, $title, $message));

        $running = false;
        return true;
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
        $locale = TIP::getOption($this->shared_modules['locale'], 'locale');

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
            if (is_null($module =& TIP_Type::getInstance($this->_request['module'], false))) {
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
        $this->tagRun($main_source);

        $this->_session_started && HTTP_Session2::pause();
    }

    //}}}
    //{{{ Tags

    /**
     * Output the page
     *
     * The output of every action is deferred to the page, that can be
     * placed anywhere in the main source.
     *
     * @param  string $params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function tagPage($params)
    {
        if (empty($this->_queue)) {
            $this->tagRunShared('default.src');
        } else {
            foreach ($this->_queue as $item) {
                call_user_func_array($item[0], $item[1]);
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
    protected function tagDebug($params)
    {
        if ($this->keys['IS_TRUSTED']) {
            // Show logged messages
            $logger =& $this->getSharedModule('logger');
            if (is_object($logger)) {
                $logger->tagRun('browse.src');
            }
        }

        if ($this->keys['IS_ADMIN']) {
            // Display profiling informations
            global $_tip_profiler;
            if (is_object($_tip_profiler)) {
                echo "<h1>Profiler</h1>\n";

                // Leave itsself, that is the tagDebug section
                $_tip_profiler->leaveSection('debug');

                $_tip_profiler->stop();
                $_tip_profiler->display('html');

                // This prevent further operation on $_tip_profiler
                $_tip_profiler = null;
            }
        }

        if ($this->keys['IS_MANAGER']) {
            // Dump the singleton register content
            echo "<h1>Register content</h1>\n<pre>\n";
            self::_dumpRegister(TIP_Type::singleton(), '  ');
            echo "</pre>\n";
        }

        return true;
    }

    //}}}
    //{{{ Actions

    protected function runManagerAction($action)
    {
        switch($action) {

        case 'phpinfo':
            $GLOBALS[TIP_MAIN]->appendCallback('phpinfo');
            return true;
        }

        return null;
    }

    protected function runUntrustedAction($action)
    {
        switch($action) {

        case 'backup':
            include_once 'HTTP/Download.php';
            include_once 'Archive/Tar.php';

            if (!$this->data_engine->dump(TIP::buildUploadPath('dump'))) {
                TIP::notifyError('backup');
                return false;
            }

            $tar_file = TIP::buildCachePath($this->id . '-' . TIP::formatDate('date_sql') . '.tar.gz');
            $tar_object = new Archive_Tar($tar_file, 'gz');
            $result = $tar_object->createModify(TIP::buildUploadPath(), '', TIP::buildPath());
            unset($tar_object);

            if ($result !== true) {
                return false;
            }
        
            HTTP_Download::staticSend(array(
                'file'               => $tar_file,
                'contenttype'        => 'application/x-gzip',
                'contentdisposition' => HTTP_DOWNLOAD_ATTACHMENT));
            exit;
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

    //}}}
    //{{{ Internal properties

    /**
     * The current request data
     *
     * Contains three items identifying the current request:
     * - $_request['uri']:    the absolute requesting uri
     * - $_request['module']: the requesting module
     * - $_request['action']: the requesting action
     *
     * @var array
     * @internal
     */
    private $_request = null;

    /**
     * The current referer data
     *
     * Contains three items identifying the current referer:
     * - $_referer['uri']:    the absolute referring uri
     * - $_referer['module']: the refererring module
     * - $_referer['action']: the refererring action
     *
     * @var array
     * @internal
     */
    private $_referer = null;

    /**
     * The callback queue to scan to generate the page
     * @var array
     * @internal
     */
    private $_queue = array();

    /**
     * Session started flag
     * @var boolean
     * @internal
     */
    private $_session_started = false;

    //}}}
    //{{{ Internal methods

    /**
     * Internal debug function
     *
     * Used to dump the register for debugging purpose.
     *
     * @param array  &$register The register to dump
     * @param string  $indent   The indentation text
     * @internal
     */
    static private function _dumpRegister(&$register, $indent = '')
    {
        foreach ($register as $id => &$obj) {
            echo "$indent$id\n";
            is_array($obj) && self::_dumpRegister($obj, $indent . '  ');
        }
    }

    private function _runNotify($source, $title, $message)
    {
        // Run the source
        $this->keys['TITLE'] = $title;
        $this->keys['MESSAGE'] = $message;
        $this->tagRun($source);
    }

    //}}}
}
?>
