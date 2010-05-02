<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Application definition file
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
 * The main module
 *
 * This module manages the generation of the "page" (the most dynamic part of
 * a TIP site). This is done by using the "content" property as a text buffer.
 *
 * When the tagPage() is called, usually throught a tag in the main template,
 * the TIP_Application module will call the callbacks stored in the queue
 * in sequential order. The page is the output of these callbacks.
 *
 * After a TIP_Application instantiation, the global variable $GLOBALS[TIP_MAIN]
 * will contain a reference to this TIP_Application instantiated object.
 * Your index.php, if the TIP system is properly configured, will usually be
 * as the following one:
 *
 * <code>
 * <?php
 * require_once './logic/TIP.php';
 *
 * TIP_Type::getInstance('main');
 * $GLOBALS[TIP_MAIN]->go();
 * ?>
 * </code>
 *
 * @package  TIP
 */
class TIP_Application extends TIP_Module
{
    //{{{ Properties

    /**
     * Page begin string (incipit)
     * @var string
     */
    protected $incipit = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"{locale}\" lang=\"{locale}\">\n\n";

    /**
     * Page end string (explicit)
     * @var string
     */
    protected $explicit = '</html>';

    /**
     * Namespace
     * @var string
     */
    protected $namespace = null;

    /**
     * Flag to enable global JSON infrastructure for unobtrusive javascript
     * @var boolean
     */
    protected $json = false;

    /**
     * Page title
     * @var string
     */
    protected $title = 'Untitled TIP site';

    /**
     * Page description
     * @var string
     */
    protected $description = null;

    /**
     * Robots metatag
     * @var string
     */
    protected $robots = 'index,follow';

    /**
     * Page keywords
     * @var string
     */
    protected $keywords = 'tip';

    /**
     * Additional code to add at the end of the <head> section
     * @var string
     */
    protected $additional = '';

    /**
     * The default data engine to use: required
     * @var TIP_Data_Engine
     */
    protected $data_engine = null;

    /**
     * The data root path
     * @var string
     */
    protected $data_root = array('data');

    /**
     * The icon root path
     * @var string
     */
    protected $icon_root = array('style', 'icons');

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
     * The uri to redirect the browse on fatal errors
     * @var string
     */
    protected $fatal_uri = '/fatal.html';

    /**
     * The template to run to generate the <head>
     * @var string
     */
    protected $head_template = 'head';

    /**
     * The template to run to generate the <body>
     * @var string
     */
    protected $body_template = 'body';

    /**
     * The template to run to generate the default page content
     * @var string
     */
    protected $default_template = 'default';

    /**
     * The file to run to notify errors
     * @var string
     */
    protected $error_template = 'error';

    /**
     * The file to run to notify warnings
     * @var string
     */
    protected $warning_template = 'warning';

    /**
     * The file to run to notify informations
     * @var string
     */
    protected $info_template = 'info';

    /**
     * The page content
     * @var string
     */
    protected $content = '';

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

        // This must be called here (not in checkOptions()) to avoid
        // buildActionUri() call before application instantiation
        isset($this->fatal_uri) || $this->fatal_uri = TIP::buildActionUri($this->id, 'fatal');

        $this->keys['TITLE'] =& $this->title;
        $this->keys['DESCRIPTION'] =& $this->description;
        $this->keys['KEYWORDS'] =& $this->keywords;
        $this->keys['ROOT'] = TIP::getRoot();
        $this->keys['HOME'] = TIP::getHome();
        $this->keys['REFERER'] = '';

        // Set $_request
        $module = TIP::getGet('module', 'string');
        $action = TIP::getGet('action', 'string');

        if (!$action) {
            $module = TIP::getPost('module', 'string');
            $action = TIP::getPost('action', 'string');
        }

        $this->_request = array(
            'uri'    => @$_SERVER['REQUEST_URI'],
            'module' => @strtolower($module),
            'action' => @strtolower($action),
            'id'     => TIP::getGetOrPost('id', 'string')
        );

        $this->keys['REQUEST'] = $this->_request['uri'];
        $this->keys['MODULE']  = $this->_request['module'];
        $this->keys['ACTION']  = $this->_request['action'];

        // The ID global key will be assigned when the requested module
        // is loaded, so a type casting can be forced (because the id_type
        // of the module is known)
        $this->keys['ID']      = '';

        // Start the session
        TIP_AHAH || $this->_startSession();
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
     * Get a global item
     *
     * @param  string $id The item id
     * @return mixed      The item value or null if not found
     */
    static public function getGlobalItem($id)
    {
        return $GLOBALS[TIP_MAIN]->getItem($id);
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

        if (!array_key_exists($job, $cache)) {
            $cache[$job] =& TIP_Type::getInstance($GLOBALS[TIP_MAIN]->shared_modules[$job], false);
        }

        return $cache[$job];
    }

    /**
     * Get a shared template
     *
     * Shared templates reside in the application directory and do not
     * depend on any particular module.
     *
     * @param  string            $name The template name
     * @return TIP_Template|null       The requested template or
     *                                 null if not found
     */
    static public function &getSharedTemplate($name)
    {
        $main =& $GLOBALS[TIP_MAIN];

        // Initialize the template instance
        $template =& TIP_Type::singleton(array(
            'type'   => array('template'),
            'engine' => &$main->engine,
            'path'   => array($main->id, $name)
        ));

        return $template;
    }

    /**
     * Generic message notification
     *
     * Outputs a generic notification message running the specified template
     * program with the current template engine. The output will be inserted
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

        // Check for the locale instance (required)
        $locale =& TIP_Type::getInstance('locale', false);
        if (!$locale instanceof TIP_Module) {
            return false;
        }

        $running = true;
        $header_id = 'notify.' . $severity;
        $message_id = $header_id . '.' . $id;
        $data =& $locale->getProperty('data');

        $key = $data->getProperty('primary_key');
        $filter = $data->filter($key, $header_id) . $data->addFilter('OR', $key, $message_id);
        if (!is_null($view =& $locale->startDataView($filter))) {
            $rows =& $view->getProperty('rows');
            $locale->endView();
            $locale_id = $locale->getProperty('locale');
            $header = @$rows[$header_id][$locale_id];
            $message = @$rows[$message_id][$locale_id];
        }

        // Fallback values
        isset($header) || ($header = 'UNDEFINED') && TIP::warning("localized id not found ($header_id)");
        isset($message) || ($message = 'Undefined message') && TIP::warning("localized id not found ($message_id)");

        $main =& $GLOBALS[TIP_MAIN];
        $template = $main->{$severity . '_template'};

        $main->keys['NOTIFY_HEADER'] = $header;
        $main->keys['NOTIFY_MESSAGE'] = $message;
        empty($template) || $main->content = $main->tagRun($template) . $main->content;
        unset($main->keys['NOTIFY_HEADER'], $main->keys['NOTIFY_MESSAGE']);

        $running = false;
        return true;
    }

    /**
     * The "main" function
     *
     * The starting point of the TIP system. This must be called somewhere from
     * your index.php.
     */
    public function go()
    {
        // Configure the locale
        $locale_module = $this->shared_modules['locale'];
        if (TIP::setLocaleId(TIP::getOption($locale_module, 'locale'))) {
            date_default_timezone_set(TIP::getOption($locale_module, 'timezone'));
        }

        // Executes the action
        if ($this->_request['module'] && $this->_request['action']) {
            if (is_null($module =& TIP_Type::getInstance($this->_request['module'], false))) {
                TIP::notifyError('module');
            } else {
                if (isset($this->_request['id'])) {
                    // Now the module is loaded: force id type casting
                    $id_type = $module->getProperty('id_type');
                    isset($id_type) || $id_type = 'integer';
                    settype($this->_request['id'], $id_type);
                    $this->keys['ID'] = $this->_request['id'];
                }

                if (is_null($module->callAction($this->_request['action']))) {
                    TIP::notifyError(is_null(TIP::getUserId()) ? 'reserved' : 'denied');
                }
            }
        } elseif ($this->_request['module']) {
            TIP::notifyError('noaction');
        } elseif ($this->_request['action']) {
            TIP::notifyError('nomodule');
        } elseif (TIP_AHAH) {
            // AHAH request without module/action specified: perform a test
            $this->content = "<pre>\n" . TIP::toHtml(print_r($_SERVER, true)) . "\n</pre>\n";
        }

        if (TIP_AHAH) {
            // AHAH request: output the page and return
            header('Content-Type: application/xml');
            echo $this->content;
            return;
        }

        // Generates the page: body must be called before the head because
        // some head tags can be modified by body templates
        $body = $this->tagRun($this->body_template);

        echo str_replace('{locale}', TIP::getLocaleId('-'), $this->incipit);
        $this->run($this->head_template);
        echo $body . $this->explicit;

        $this->_session_started && HTTP_Session2::pause();
    }

    static public function setRobots($index, $follow)
    {
        $robots =& TIP_Application::getGlobal('robots');
        $values = explode(',', $robots);

        if (is_bool($index)) {
            if ($index) {
                $old_value = 'noindex';
                $new_value = 'index';
            } else {
                $old_value = 'index';
                $new_value = 'noindex';
            }
            if (!in_array($new_value, $values)) {
                $key = array_search($old_value, $values);
                $key === false && $key = count($values);
                $values[$key] = $new_value;
            }
        }

        if (is_bool($follow)) {
            if ($follow) {
                $old_value = 'nofollow';
                $new_value = 'follow';
            } else {
                $old_value = 'follow';
                $new_value = 'nofollow';
            }
            if (!in_array($new_value, $values)) {
                $key = array_search($old_value, $values);
                $key === false && $key = count($values);
                $values[$key] = $new_value;
            }
        }

        $robots = implode(',', $values);
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param  string      $params Parameters of the tag
     * @return string|null         The string result or null on errors
     */

    /**
     * Output the page content
     */
    protected function tagPage($params)
    {
        // Check for a notification request (from a redirection, from instance)
        $notify = HTTP_Session2::get('notify');
        if (isset($notify)) {
            // Show the notification and remove it from the session data
            list($severity, $id) = $notify;
            $this->notify($severity, $id);
            HTTP_Session2::set('notify', null);
        }

        return empty($this->content) ? $this->tagRun($this->default_template) : $this->content;
    }

    /**
     * Output some debug information
     *
     * Echoes some output and profiler information, useful in the developement
     * process. This works only if the current user has some privilege on the
     * application module.
     */
    protected function tagDebug($params)
    {
        if (!$this->keys['IS_TRUSTED']) {
            return '';
        }

        ob_start();

        // Show logged messages
        $logger =& $this->getSharedModule('logger');
        if (is_object($logger) && $logger->keys['IS_UNTRUSTED']) {
            $logger->dumpLogs();
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
            echo "\n<h1>Register content</h1>\n<pre>\n";
            self::_dumpRegister(TIP_Type::singleton(), '  ');
            echo "</pre>\n";
        }

        return ob_get_clean();
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    protected function runManagerAction($action)
    {
        switch($action) {

        case 'phpinfo':
            ob_start();
            phpinfo();
            $this->content .= ob_get_clean();
            return true;

        case 'clear':
            if (is_null($dir = TIP::getGet('id', 'string'))) {
                TIP::warning("GET not found ($id)");
                TIP::notifyError('noparams');
                return false;
            }

            $dir = TIP::buildDataPath(urldecode($dir));
            TIP::removeDir($dir, false);
            TIP::notifyInfo('done');
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

            if (!$this->data_engine->dump(TIP::buildDataPath('dump'))) {
                TIP::notifyError('backup');
                return false;
            }

            $tar_file = TIP::buildCachePath($this->id . '-' . TIP::formatDate('date_sql') . '.tar.gz');
            $tar_object = new Archive_Tar($tar_file, 'gz');
            $result = $tar_object->createModify(TIP::buildDataPath(), '', TIP::buildPath());
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

    private function _startSession()
    {
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
                'uri'    => TIP::getHome(),
                'module' => null,
                'action' => null
            );
            $this->_referer['action'] = null;
        }

        $this->keys['REFERER'] = $this->_referer['uri'];

        // Store request and referer
        HTTP_Session2::set('referer', $this->_referer);
        HTTP_Session2::set('request', $this->_request);

        // Profiler initialization in "admin" mode
        if ($this->keys['IS_ADMIN']) {
            require_once 'Benchmark/Profiler.php';
            $GLOBALS['_tip_profiler'] = new Benchmark_Profiler;
            $GLOBALS['_tip_profiler']->start();
        }
    }

    //}}}
}
?>
