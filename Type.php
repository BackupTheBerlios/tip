<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */

/**
 * TIP type system
 *
 * Manages all the instantiable TIP objects (types).
 * Inheriting a class from TIP_Type gives the ability to instantiate this
 * class only when requested (usually throught a call to getInstance()).
 * Multiple requests to getInstance() will references the same - unique -
 * created instance.
 *
 * Also, the PHP file declaring the new type will be included only
 * when required, enabling a real modular environement.
 *
 * @abstract
 * @package TIP
 * @tutorial Module.pkg#TIP_Type
 */
class TIP_Type extends TIP
{
    /**#@+ @access private */

    var $_name = null;
    var $_error = null;


    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Type instance.
     *
     * @todo Derive this class from PEAR instead of TIP and remove
     *       all error management from TIP_Type.
     */
    function TIP_Type()
    {
        $this->_name = strtolower(TIP::stripTipPrefix(get_class($this)));
    }

    /**
     * Get the name of an instantiated type
     *
     * Returns the name of the current - instantiated - type. This function
     * simply gets the class name (in lowercase) and strips the TIP_PREFIX
     * from the string.
     *
     * The result is converted to lowecase to avoid discrepancies between
     * different PHP versions.
     *
     * @return string The type name
     */
    function getName()
    {
        return $this->_name;
    }

    /**
     * Singleton method
     *
     * Manages the singletons. It keeps a static array where you can get the
     * singleton instances with the $id key. Not providing the $id argument, a
     * reference to the array itsself is returned. You can add more items by
     * specifing them in $instance in the following allowed ways:
     *
     * - string $instance The new $id singleton is created throught a
     *                    TIP_Type::factory() call passing $instance as
     *                    file argument
     * - object $instance The $id singleton is created with this instance
     * - array  $instance The associative array $instance is appended "as is"
     *                    to the register
     *
     * WARNING: you can't append singletons by reference using the object mode.
     * Specify in $instance array('id' => &$instance) instead.
     *
     * @param mixed|array         $id       The id of the singleton to retrieve
     * @param string|object|array $instance The instance to store in the register
     * @param bool                $required Are the errors fatal?
     * @return array|mixed|null The singleton register, the singleton of $id
     *                          or null on errors
     * @static
     */
    function& singleton($id = null, $instance = null, $required = true)
    {
        static $register = array();

        if (is_null($id)) {
            return $register;
        } elseif (is_null($instance)) {
            if (array_key_exists($id, $register)) {
                return $register[$id];
            } else {
                return $instance; // <= return null
            }
        }

        if (is_string($instance)) {
            $register[$id] =& TIP_Type::factory($instance);
            if (! $register[$id] && $required) {
                TIP::logFatal("unable to include logic file ($instance)");
            }
            return $register[$id];
        } elseif (is_array($instance)) {
            $register += $instance;
            return $register[$id];
        } elseif (is_object($instance)) {
            return $register[$id] =& $instance;
        }

        TIP::logFatal('unhandled instance type (' . gettype($instance) . ')');

    }

    /**
     * Define a type dinamically
     *
     * The base of the TIP plug-in system: include dinamically a definition
     * file (called logic), allowing to build a real modular environement.
     *
     * For instantiable types, the return type of include_once must return the
     * class name to instantiate: at the end of every logic file you must
     * return a string with the class name to instantiate.
     * For example, at the end of the TIP_Application logic you must have
     *
     * <code>return 'TIP_Application';</code>
     *
     * For non-instantiable types you must omit the return statement: the
     * default return value for include_once will be used instead.
     *
     * @param array  $file     The logic file
     * @return TIP_Type|bool A reference to the instance or true if $id is not
     *                       instantiable but the logic is properly included,
     *                       false on errors
     * @static
     */
    function& factory($file)
    {
        $result = is_readable($file);
        if ($result && is_string($result = include_once $file)) {
            $result =& new $result;
        }

        return $result;
    }

    /**
     * Sets an error message
     *
     * Sets or appends to the internal error string a message. This error is
     * publicly available throught the getError() method.
     */
    function setError ($message)
    {
	if (empty ($message))
	    return;

	if ($this->_error)
	    $this->_error .= '\n' . $message;
	else
	    $this->_error = $message;
    }

    /**
     * Get an option for the current instance
     *
     * Wrappers the more general TIP::getOption() function without the need to
     * specify the type.
     *
     * @param string $option The option to retrieve
     * @return mixed|null The value of the requested option or null on errors
     */
    function getOption($option)
    {
        return TIP::getOption($this->_name, $option);
    }

    /**
     * Create a TIP_Callback
     *
     * Shortcut to build a TIP_Callback from a method of this class.
     *
     * @param string     $method The method name
     * @param array|null $args   The arguments to pass to the callback if the
     *                           callback is called without arguments
     */
    function& callback($method, $args = null)
    {
        $callback =& new TIP_Callback(array(&$this, $method), $args);
        return $callback;
    }

    /**
     * Logs a warning message
     *
     * Wrappes TIP::logWarning() appending specific type informations.
     *
     * @see logError(),logFatal()
     */
    function logWarning ($message)
    {
	TIP::logWarning ($message . " from the '$this->_name' type");
    }

    /**
     * Logs an error message
     *
     * Wrappes TIP::logError() appending specific type informations.
     *
     * @see logWarning(),logFatal()
     */
    function logError ($message)
    {
	TIP::logError ($message . " from the '$this->_name' type");
    }

    /**
     * Logs an error message and quits the application
     *
     * Wrappes TIP::logFatal() appending specific type informations.
     *
     * @see logWarning(),logError()
     */
    function logFatal ($message)
    {
	TIP::logFatal ($message . " from the '$this->_name' type");
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Overridable type instantiation
     *
     * Defines a type dinamically. If the $id type is not defined, the logic
     * file found in the 'logic_root' path is included by TIP_Type::factory().
     *
     * @param string $id The type name without the 'TIP_' prefix
     * @return bool Always returns true because errors are fatals
     * @static
     */
    function& getInstance($id)
    {
        $instance =& TIP_Type::singleton($id);
        if (is_null($instance)) {
            $file = TIP::buildLogicPath($id) . '.php';
            $instance =& TIP_Type::singleton($id, $file);
        }
        return $instance;
    }

    /**
     * Resets the error messages
     *
     * Resets the internal error messages. The previous list of error messages
     * is returned to the caller.
     *
     * @return string|null The error messages or null if there are no errors.
     */
    function resetError ()
    {
	$result = $this->_error;
	$this->_error = null;
	return $result;
    }

    /**
     * Get the list of error messages
     *
     * Gets the description of the errors set by this module. If there are no
     * errors, this function simply returns null.
     *
     * @return string|null The error messages or null if there are no errors.
     */
    function getError ()
    {
	return $this->_error;
    }

    /**#@-*/
}

?>
