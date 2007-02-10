<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Application definition file
 * @package TIP
 **/

/**
 * The main module
 *
 * The global variable $application contains a reference to the
 * TIP_Application instantiated object.
 *
 * Your index.php, other than includes the basic TIP files, must only call
 * the go() method of $application:
 * <code>$application->go ();</code>
 * and TIP will (hopefully) start working.
 *
 * @final
 * @package TIP
 **/
class TIP_Application extends TIP_Module
{
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
     * @subpackage Commands
     **/

    /**
     * Output the page content
     *
     * The output of every action is deferred to the page content, that can be
     * placed anywhere in the main source.
     **/
    function commandContent($params)
    {
        if (empty($this->content)) {
            $this->commandRunShared('welcome.src');
        } else {
            echo $this->content;
        }

        $this->content = null;
        return true;
    }

    /**
     * Output some debug information
     *
     * This echoes some output and profiler information, useful in the
     * developement process. This works only if the current user has manager
     * privileges on the application module.
     **/
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
            $_tip_profiler->stop();
            $_tip_profiler->display('html');
            $_tip_profiler = null;
        }

        return true;
    }

    /**#@-*/

    /**#@-*/


    /**#@+ @access public */

    /**
     * The page contents
     *
     * A buffer containing the page contents.
     *
     * @var string
     **/
    var $content = null;


    /**
     * The "main" function
     *
     * The starting point of the TIP system. This must be called somewhere from
     * your index.php.
     *
     * @param string $main_source The main source program to run
     **/
    function go($main_source)
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

        if ($module_name && ! $action) {
            TIP::error('E_URL_ACTION');
        } elseif (! $module_name && $action) {
            TIP::error('E_URL_MODULE');
        } elseif ($module_name) {
            $module =& TIP_Module::getInstance($module_name, false);
            if (is_object($module)) {
                $result = $module->callAction($action);
                if (is_null($result)) {
                    $anonymous = is_null(TIP::getUserId());
                    TIP::error($anonymous ? 'E_URL_RESERVED' : 'E_URL_DENIED');
                } elseif ($result === false) {
                    TIP::error('E_FALLBACK');
                    $module->logError($module->resetError());
                }
            } else {
                TIP::error('E_URL_MODULE');
            }
        }

        // Generates the page
        if (! $this->commandRun($main_source)) {
            TIP::error('E_FALLBACK');
            $this->logError($this->resetError());
        }
    }

    /**#@-*/
}


return new TIP_Application;

?>
