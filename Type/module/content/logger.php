<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Logger definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Logger module
 *
 * Provides a way to log messages to a data source.
 *
 * @package TIP
 * @subpackage Module
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
    function _destruct()
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
     * To get a properly formatted log, you must generate the backtrace in
     * the method immediately following the source point to log.
     *
     * @param string  $severity  The text of the log
     * @param string  $message   A custom message
     * @param array  &$backtrace The backtrace array
     */
    public function log($severity, $message, &$backtrace)
    {
        static $running = false;

        // The running flag avoid recursive calls to log()
        if ($running) {
            return false;
        } else {
            $running = true;
        }

        // Carefully scans the backtrace to find useful informations
        // and store them in the $context array
        $context = array();
        foreach ($backtrace as $n => $trace) {
            if ($n == 0) {
                $file = @$trace['file'];
                $line = @$trace['line'];
                $context['origin'] = "$file on line $line";
                continue;
            } elseif (@$trace['type'] != '->') {
                continue;
            }

            $function = @strtolower($trace['function']);
            if ($function == 'calltag') {
                if (!array_key_exists('command', $context)) {
                    $module  = @$trace['class'];
                    $name = @$trace['args'][0];
                    $params  = @$trace['args'][1];
                    if (strlen($params) > 80) {
                        $params = substr($params, 0, 77) . '...';
                    }
                    $context['command'] = "$module::callTag($name, $params)";
                }
                continue;
            } elseif ($function == 'callaction') {
                if (!array_key_exists('action', $context)) {
                    $module = @$trace['class'];
                    $action = @$trace['args'][0];
                    $context['action'] = "$module::callAction($action)";
                }
                continue;
            }

            $class = @strtolower($trace['class']);
            if ($class == 'tip_source') {
                if ($n > 0 && !array_key_exists('source', $context)) {
                    $last =& $backtrace[$n-1];
                    if (is_object($last['args'][0])) {
                        $source =& $last['args'][0];
                        $method =  $last['function'];
                        $context['source'] = "$source on method $method";
                    }
                }
                continue;
            } elseif ($class == 'tip_data') {
                if ($n > 0 && !array_key_exists('data', $context)) {
                    $last =& $backtrace[$n-1];
                    if (is_object($last['args'][0])) {
                        $data   =& $last['args'][0];
                        $method =  $last['function'];
                        $context['data'] = "$data on method $method";
                    }
                }
                continue;
            }
        }

        $row['user']     = TIP::getUserId();
        $row['when']     = TIP::formatDate('datetime_iso8601');
        $row['severity'] = $severity;
        $row['message']  = $message;

        $fields =& $this->data->getFields(false);
        foreach (array_keys($context) as $key) {
            if (array_key_exists($key, $fields)) {
                $row[$key] = $context[$key];
            }
        }

        $this->_cache[] =& $row;
        $running = false;
        return true;
    }

    public function &getLogs()
    {
        return $this->_cache;
    }

    //}}}
}
?>
