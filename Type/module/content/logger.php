<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Logger definition file
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
 * Logger module
 *
 * Provides a way to log messages to a data source.
 *
 * @package TIP
 */
class TIP_Logger extends TIP_Content
{
    //{{{ Internal properties

    /**
     * The cached array of logger rows 
     * @var array|null
     */
    private $_cache = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Logger instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    /**
     * Destructor
     *
     * Stores the cache content in once, if $_cache is an array.
     */
    function __destruct()
    {
        if (is_array($this->_cache)) {
            $this->data->putRows($this->_cache);
        }
    }

    //}}}
    //{{{ Methods

    /**
     * Append a log
     *
     * Appends a log message to the data source of the logger object.
     *
     * @param string  $severity  The text of the log
     * @param string  $message   A custom message
     */
    public function log($severity, $message)
    {
        static $running = false;

        // The running flag avoid recursive calls to log()
        if ($running) {
            return false;
        } else {
            $running = true;
        }

        // Generate the backtrace
        $bt = debug_backtrace();

        // Carefully scans the backtrace to find useful informations
        // and store them in the $code array
        $code = array(
            'origin' => '',
            'tag' => '',
            'action' => '',
            'template' => '',
            'data' => ''
        );
        foreach ($bt as $n => $trace) {
            $function = isset($trace['function']) ? strtolower($trace['function']) : '';

            if (!isset($code['origin'])) {
                // Skip the log wrappers
                if ($function == 'log' || $function == 'warning' || $function == 'error' || $function == 'fatal') {
                    continue;
                }
                $code['origin'] = "$trace[file] on line $trace[line]";
            }

            if ($function == 'gettag') {
                if (!array_key_exists('tag', $code)) {
                    $module  = $trace['class'];
                    $name = $trace['args'][0];
                    $params  = $trace['args'][1];
                    if (strlen($params) > 80) {
                        $params = substr($params, 0, 77) . '...';
                    }
                    $code['tag'] = "$module::getTag($name, $params)";
                }
                continue;
            } elseif ($function == 'callaction') {
                if (!array_key_exists('action', $code)) {
                    $module = $trace['class'];
                    $action = $trace['args'][0];
                    $code['action'] = "$module::callAction($action)";
                }
                continue;
            }

            $class = isset($trace['class']) ? strtolower($trace['class']) : '';
            if ($class == 'tip_template') {
                if (!array_key_exists('template', $code)) {
                    $last =& $bt[$n-1];
                    if (is_object($last['args'][0])) {
                        $template =& $last['args'][0];
                        $method =  $last['function'];
                        $code['template'] = "$template on method $method";
                    }
                }
                continue;
            } elseif ($class == 'tip_data') {
                if (!array_key_exists('data', $code)) {
                    $last =& $bt[$n-1];
                    if (is_object($last['args'][0])) {
                        $data   =& $last['args'][0];
                        $method =  $last['function'];
                        $code['data'] = "$data on method $method";
                    }
                }
                continue;
            }
        }

        unset($bt);

        $context = array(
            'user'     => TIP::getUserId(),
            'when'     => TIP::formatDate('datetime_sql'),
            'severity' => $severity,
            'message'  => $message
        );

        $this->_cache[] = array_merge($context, $code);
        $running = false;
        return true;
    }

    /**
     * Get the log array
     *
     * Gets the internal log cache.
     *
     * @return array The logs
     */
    public function &getLogs()
    {
        return $this->_cache;
    }

    /**
     * Echo the log list
     *
     * Dumps the log messages to the standard output, if the log cache
     * is not empty.
     */
    public function dumpLogs()
    {
        if (!empty($this->_cache)) {
            $this->run($this->browse_template);
        }
    }

    //}}}
}
?>
