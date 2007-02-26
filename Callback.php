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
    var $_args = array();


    function _defaultCallback()
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
     * @param array|null     $args    If $default is a callback, the arguments
     *                                to pass to the callback
     */
    function TIP_Callback($default = true, $args = null)
    {
        if (is_callable($default)) {
            $this->_callback =& $default;
            if (is_array($args)) {
                $this->_args =& $args;
            }
        } else {
            $this->_callback = array(&$this, '_defaultCallback');
            $this->result = $default;
        }
    }

    /**
     * Set a new callback
     *
     * Sets a new callback. If $args is omitted, no fallback arguments will be
     * used when calling the callback.
     *
     * @param callback $callback The new callback
     * @param array    $args     The arguments to pass to the callback
     */
    function set($callback, $args = null)
    {
        $this->_callback =& $callback;
        $this->_args = is_array($args) ? $args : array();
    }

    /**
     * Callback call
     *
     * Performs the callback call. If the callback was never set, a default
     * callback is called; it simply returns the default return value specified
     * when constructing the callback.
     *
     * The arguments, if any, are passed to the callback. If no arguments are
     * specified, the ones specified by set() will be used instead.
     *
     * @return mixed The callback return value
     */
    function go()
    {
        $args = func_get_args();
        if (empty($args)) {
            $args =& $this->_args;
        }

        $this->result = call_user_func_array($this->_callback, $args);
        return $this->result;
    }

    /**
     * Callback call with argument array
     *
     * Same as go(), but uses an array of arguments insead of specifing them
     * directly in the argument list. Useful if you need to pass any of the
     * arguments by reference.
     *
     * @param array $args An array of arguments
     * @return mixed The callback return value
     */
    function goWithArray($args)
    {
        $this->result = call_user_func_array($this->_callback, $args);
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
        return '_defaultCallback' == @$this->_callback[1] && $this == @$this->_callback[0];
    }

    /**#@-*/
}

?>
