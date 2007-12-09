<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

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
 * instantiated object.
 *
 * Also, the PHP file declaring the new type will be included only
 * when required, enabling a real modular environement.
 *
 * @package  TIP
 * @tutorial TIP/Module.pkg#TIP_Type
 */
abstract class TIP_Type
{
    //{{{ Properties

    /**
     * Instance identifier
     * @var string
     */
    protected $id = null;

    /**
     * An array of parent classes (without TIP_PREFIX)
     * @var array
     */
    protected $type = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Check the options
     *
     * Overridable static method that checks $options for missing or invalid
     * values and eventually corrects its content.
     *
     * @param  array &$options Property values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!isset($options['type'])) {
            return false;
        }

        isset($options['id']) || $options['id'] = end($options['type']);
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Type instance.
     *
     * Basically, this class set the properties values by parsing the $options
     * array.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        // Properties initialization
        foreach ($options as $property => &$value) {
            $this->$property =& $value;
        }
    }

    /**
     * Overridable post construction method
     *
     * Called after the construction happened. This can be overriden to do some
     * other post costruction operation.
     *
     * The TIP_Type::postConstructor() method does nothing.
     */
    protected function postConstructor()
    {
    }

    //}}}
    //{{{ Methods

    /**
     * Singleton method
     *
     * Manages the singletons. Given a hierarchy of parent types and a string
     * identifier, this method returns a singleton of the instantiated object.
     * If the object is not found, it is dinamically defined and instantiated.
     *
     * The singletons are stored in a static tree, called register, containing
     * all the hierarchy of the instantiated types.
     *
     * If $options is not specified, the whole register is returned.
     *
     * If $options is a string, it must specify a valid type: a partial
     * register content of this type is returned.
     *
     * In the other cases, $options must be an array of options, and a
     * singleton for the specified object is returned. In this situation,
     * $options must have at least the following items:
     * - $options['id']:   the instance identifier
     * - $options['type']: an array containing the parent types of the instance.
     *                     These types must be lowercase strings specifying the
     *                     parent classes of the instance (TIP_Type excluded)
     *                     without TIP_PREFIX. Using array('module', 'content'),
     *                     for instance, will instantiate a TIP_Content object.
     *
     * Some type automatically fills $options['id'] after the checkOptions()
     * call: check the documentation for each class for further information.
     *
     * @param  array|string|null $options Constructor options
     * @return array|object|null          The register content, a reference
     *                                    to the requested instance or null
     *                                    on instantiation errors
     */
    static protected function &singleton($options = null)
    {
        static $register = array();
        static $flat_register = array();

        if (empty($options)) {
            // Return the whole register content
            return $register;
        } elseif (is_string($options)) {
            // Return a partial register content
            return $flat_register[$options];
        }

        $type = end($options['type']);
        if (array_key_exists($type, $flat_register)) {
            // Hierarchy yet defined
            $list =& $flat_register[$type];
        } else {
            // Hierarchy to be defined
            $path = TIP::buildLogicPath('Type');
            $list =& $register;

            foreach ($options['type'] as $type) {
                $path .= DIRECTORY_SEPARATOR . $type;
                if (!array_key_exists($type, $list)) {
                    // Dynamic type definition
                    $file = $path . '.php';
                    if (include_once $file) {
                        $list[$type] = array();
                        $flat_register[$type] =& $list[$type];
                    } else {
                        // Definition impossible: this avoid next attempts
                        $list[$type] = $flat_register[$type] = null;
                        return $list[$type];
                    }
                }
                $list =& $list[$type];
            }
        }

        $class = TIP_PREFIX . $type;
        if (!call_user_func_array(array($class, 'checkOptions'), array(&$options))) {
            // Invalid options, maybe a requested option is not specified
            $fake_null = null;
            return $fake_null;
        }

        $id = $options['id'];
        if (!array_key_exists($id, $list)) {
            // Object instantiation
            $list[$id] = new $class($options);

            // postConstructor() call: must be done after the registration
            // to avoid circular dependency
            isset($GLOBALS[TIP_FLAG_AVOID_PC]) || $list[$id]->postConstructor();
        }

        return $list[$id];
    }

    /**
     * Type instantiation
     *
     * Gets the singleton of a configured object. $id could be any identifier
     * defined in $GLOBALS['cfg'].
     *
     * An internal register is mantained to avoid singleton() calls with the
     * same $id.
     *
     * @param  mixed    $id       Instance identifier
     * @param  bool     $required true if errors must be fatals
     * @return TIP_Type           The reference to the requested instance or
     *                            false on errors
     */
    static public function &getInstance($id, $required = true)
    {
        static $register = array();

        $id = strtolower($id);
        if (array_key_exists($id, $register)) {
            return $register[$id];
        }

        if (isset($GLOBALS['cfg'][$id])) {
            $options = $GLOBALS['cfg'][$id];
            isset($options['id']) || $options['id'] = $id;
            $instance =& TIP_Type::singleton($options);
        } else {
            $instance = null;
        }

        if (is_null($instance) && $required) {
            TIP::fatal("unable to instantiate the requested object ($id)");
            exit;
        }

        $register[$id] =& $instance;
        return $instance;
    }

    /**
     * Return the id of a TIP instance
     * @return string The instance identifier
     */
    public function __toString()
    {
        return $this->id;
    }

    /**
     * Get the type of a TIP instance
     *
     * Returns the type of the current - instantiated - TIP object. This
     * function simply gets the class name (in lowercase) and strips the
     * TIP_PREFIX from the string.
     *
     * The result is converted to lowecase to avoid discrepancies between
     * different PHP versions.
     *
     * @return string The type name
     */
    public function getType()
    {
        return end($this->type);
    }

    /**
     * Get a property of this instance
     *
     * @param  string     $property The property name
     * @return mixed|null           The property value or null on errors
     */
    public function &getProperty($property)
    {
        return $this->$property;
    }

    /**
     * Get a global option for the current instance
     *
     * Wrappers the more general TIP::getOption() function without the need to
     * specify the type.
     *
     * @param  string     $option The option to retrieve
     * @return mixed|null         The value of the requested option or null on errors
     */
    public function getOption($option)
    {
        return @$GLOBALS['cfg'][$this->id][$option];
    }

    //}}}
}
?>
