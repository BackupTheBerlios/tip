<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Callback definition file
 * @package TIP
 */

/**
 * A generic callback
 *
 * Provides some common stuff in callback management, such as a default return
 * value for undefined callbacks.
 *
 * @package TIP
 */
class TIP_Callback extends TIP
{
    /**#@+ @access private */

    var $_callback = null;
    var $_params = array();

    function defaultCallback()
    {
        return $this->result;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * The result of the callback
     *
     * This property retains the return value of the last callback. Every time
     * a new call is performed, this value is overwrote.
     *
     * @var mixed
     */
    var $result = null;


    /**
     * Callback constructor
     *
     * Initializes the callback class. The $default parameter is used as
     * default callback (if it is callable) or as default return value of the
     * go() method when the callback is not set.
     *
     * @param mixed|callback $default The default return value or the default callback
     */
    function TIP_Callback($default = true)
    {
        if (is_callable($default)) {
            $this->_callback =& $default;
        } else {
            $this->_callback = array(&$this, 'defaultCallback');
            $this->result = $default;
        }
    }

    /**
     * Set a new callback
     *
     * Sets a new callback. If $params is omitted, no parameters will be passed
     * when calling the callback.
     *
     * @param callback $callback The new callback
     * @param array    $params   The parameters to pass to the callback
     */
    function set($callback, $params = null)
    {
        $this->_callback =& $callback;
        if (is_array($params)) {
            $this->_params =& $params;
        }
    }

    /**
     * Callback call
     *
     * Performs the callback call. If the callback was never set, a default
     * callback is called; it simply returns the default return value specified
     * when constructing the callback.
     *
     * If $params is not specified, the parameters specified by set() will be
     * passed to the call.
     *
     * @param array $params Parameters to pass to the callback
     * @return mixed The callback return value
     */
    function go($params = null)
    {
        if (! is_array($params)) {
            $params =& $this->_params;
        }

        $this->result = call_user_func_array($this->_callback, $params);
        return $this->result;
    }

    /**
     * Check for a default callback
     *
     * Checks if the current callback is the default one.
     *
     * @return bool true if the callback is the default one or false otherwise
     */
    function isDefault()
    {
        return 'defaultCallback' == @$this->_callback[1] && $this == @$this->_callback[0];
    }

    /**#@-*/
}

?>
