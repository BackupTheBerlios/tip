<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

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
class TIP_Logger extends TIP_Block
{
    /**#@+ @access private */

    var $_rows = null;


    function _TIP_Logger()
    {
        if (is_array($this->_rows)) {
            $this->data->putRows($this->_rows);
        }
    }

    /**#@-*/


    /**#@+ @access protected */

    function& startSpecialView($name)
    {
        if (strcasecmp($name, 'LOGS') != 0) {
            return parent::startSpecialView($name);
        }

        //$view =& TIP_Array_View::getInstance($this->_rows);
        return $this->push(new TIP_Array_View($this->_rows));
        //return $this->push($view);
    }

    /**#@-*/


    /**#@+ @access public */

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
     * @static
     */
    function log($severity, $message, &$backtrace)
    {
        $context = array();

        // Careful scans the backtrace to find useful informations
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
            if ($function == 'callcommand') {
                if (!array_key_exists('command', $context)) {
                    $module  = @$trace['class'];
                    $command = @$trace['args'][0];
                    $params  = @$trace['args'][1];
                    if (strlen($params) > 80) {
                        $params = substr($params, 0, 77) . '...';
                    }
                    $context['command'] = "$module::callCommand($command, $params)";
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
                        $id     =  $source->getId();
                        $line   =  $source->getLine();
                        $context['source'] = "$id on line $line";
                    }
                }
                continue;
            } elseif ($class == 'tip_data') {
                if ($n > 0 && !array_key_exists('data', $context)) {
                    $last =& $backtrace[$n-1];
                    if (is_object($last['args'][0])) {
                        $data   =& $last['args'][0];
                        $id     =  $data->getId();
                        $method =  $last['function'];
                        $context['data'] = "$id on method $method";
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

        $this->_rows[] =& $row;
    }

    /**#@-*/
}

return 'TIP_Logger';

?>
