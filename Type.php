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
     * Singleton method
     *
     * Manages the singleton register. This static method can be used in three
     * different modes:
     *
     * - singleton()               returns the register content
     * - singleton($id)            gets the singleton of $id
     * - singleton($id, $instance) stores a singleton in the register and
     *                             returns a reference to the singleton
     *
     * @param mixed  $id       The id of the singleton
     * @param object $instance The instance to register
     * @return array|mixed|null The register content, a singleton reference or
     *                          null on errors, depending on the used mode
     * @static
     */
    function& singleton($id = null, $instance = null)
    {
        static $register = array();

        if (is_null($id)) {
            $instance =& $register;
        } elseif (is_null($instance)) {
            if (array_key_exists($id, $register)) {
                $instance =& $register[$id];
            }
        } else {
            $register[$id] =& $instance;
        }

        return $instance;
    }

    /**
     * Define a type dinamically
     *
     * The base of the TIP plug-in system: include dinamically a definition
     * file (called logic), allowing to build a real modular environement.
     * The file name included is <code>$id . '.php'</code> and will be searched
     * in $path (relative to the 'logic_root' application option).
     *
     * For instantiable types, the instance is the return type of include_once:
     * at the end of every logic file you must return an instantiated object.
     * For example, at the end of the TIP_Application logic you must have
     *
     * <code>return new TIP_Application;</code>
     *
     * For non-instantiable types you must omit the return statement: the
     * default return value for include_once will be used instead.
     *
     * @param mixed $file     The id of the type
     * @param array $path     The path where to search for
     * @param bool  $required Are the errors fatals?
     * @return TIP_Type|bool A reference to the instance or true if $id is not
     *                       instantiable but the logic is properly included,
     *                       false on errors
     * @static
     */
    function& factory($file, $path = null, $required = true)
    {
        if (is_null($path)) {
            $file = TIP::buildLogicPath($file) . '.php';
        } else {
            $file = TIP::buildLogicPath($path, $file) . '.php';
        }

        $instance = include_once $file;
        if (! $instance && $required) {
            TIP::logFatal("Unable to include file ($file)");
        }
        if (is_null($instance)) {
            TIP::logFatal("Unexpected null from the logic ($file)");
        }

        return $instance;
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
     * Gets an option for the current instance
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
     * Gets the name of an instantiated type
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
            $instance =& TIP_Type::singleton($id, TIP_Type::factory($id));
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
