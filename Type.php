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
 * instantiated object.
 *
 * Also, the PHP file declaring the new type will be included only
 * when required, enabling a real modular environement.
 *
 * @abstract
 * @package  TIP
 * @tutorial TIP/Module.pkg#TIP_Type
 */
class TIP_Type
{
    /**#@+ @access private */

    var $_type = null;
    var $_id = null;

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Type instance.
     *
     * Basically, this class set the $_type and $_id private properties.
     * The $_type property is the name of the derived class in lowercase and
     * with TIP_PREFIX stripped, while the $_id property is the identifier of
     * the instance.
     *
     * TIP_Type does not use any constructor argument.
     *
     * @param string $id Instance identifier
     */
    function TIP_Type($id)
    {
        $this->_type = strtolower(TIP::stripTipPrefix(get_class($this)));
        $this->_id = $id;
    }

    /**
     * Get the identifier for the given constructor arguments
     *
     * Overridable static method to build an identifier for the passed in
     * arguments. This must be overriden by classes that want to provide
     * complex identifiers (such as TIP_View): in any case, this method
     * must be static, so the identifier can be built without the need to
     * instantiate the object. This is needed by the singleton() method.
     *
     * @param  mixed  $args The contructor arguments
     * @return string       The instance identifier
     */
    function buildId($args)
    {
        return $args;
    }

    /**
     * Singleton method
     *
     * Manages the singletons. Given a hierarchy of parent types and a string
     * identifier, this method returns a singleton of the instantiated object.
     * If the object is not found, it is dinamically defined and instantiated.
     *
     * The singletons are stored in a static tree, called register, containing
     * all the hierarchy of the instantiated types. Not providing the $id
     * argument, a reference to the register content of the portion specified
     * with $hierarchy is returned. To get the whole register content, use an
     * empty array as $hierarchy.
     *
     * If $args is not null, an id is built by a static call to the buildId()
     * method of the $hierarchy type. If an instance with this id is found, it
     * is returned, otherwise it will be dynamically defined and instantiated.
     *
     * $hierarchy must be an array containing the parent types of the object.
     * These types must be lowercase strings specifying the parent classes of
     * the instance (TIP_Type excluded) without TIP_PREFIX. Using
     * array('module', 'block', 'content') as $hierarchy, for instance, will
     * instantiate a TIP_Content object.
     *
     * @param  array              $hierarchy  The parent types of the instance
     * @param  mixed|null         $args       The constructor arguments
     * @return array|object|null              The register content, a reference
     *                                        to the requested instance or null
     *                                        on instantiation errors
     * @static
     */
    function& singleton($hierarchy, $args = null)
    {
        static $register = array();

        $path = TIP::buildLogicPath();
        $node =& $register;

        // Hierarchy scan
        foreach ($hierarchy as $type) {
            $path .= $type;
            if (!array_key_exists($type, $node)) {
                if (is_null($args)) {
                    // Requested register content, but $hierarchy not defined
                    return $args;
                } else {
                    // Dynamic type definition
                    $file = $path . '.php';
                    if (include_once $file) {
                        $node[$type] = array();
                    } else {
                        // Definition impossible: this avoid next attempts
                        $node[$type] = null;
                        return $node[$type];
                    }
                }
            }
            $node =& $node[$type];
            $path .= DIRECTORY_SEPARATOR;
        }

        if (is_null($args)) {
            // Return the register content
            return $node;
        }
       
        $class = TIP_PREFIX . $type;
        $id = call_user_func(array($class, 'buildId'), $args);

        if (!array_key_exists($id, $node)) {
            // Object instantiation
            $node[$id] =& new $class($id, $args);

            // postConstructor() call: must be done after the registration to avoid
            // circular dependency
            if (is_callable(array(&$node[$id], 'postConstructor'))) {
                $node[$id]->postConstructor();
            }
        }

        return $node[$id];
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
        return TIP::getOption($this->_id, $option);
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

    /**#@-*/


    /**#@+ @access public */

    /**
     * Type instantiation
     *
     * Gets the singleton of a configured object. $id could be any identifier
     * defined in config.php.
     *
     * @param  mixed    $id       Instance identifier
     * @param  bool     $required true if errors must be fatals
     * @return TIP_Type           The reference to the requested instance or
     *                            false on errors
     * @static
     */
    function& getInstance($id, $required = true)
    {
        $id = strtolower($id);
        $instance =& TIP_Type::singleton($GLOBALS['cfg'][$id]['type'], $id);
        if (!is_object($instance) && $required) {
            TIP::fatal("unable to instantiate the configured object ($id)");
            exit;
        }
        return $instance;
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
    function getType()
    {
        return $this->_type;
    }

    /**
     * Get the id of a TIP instance
     *
     * Returns the id of the current - instantiated - TIP object. The id is
     * a string that univoquely identifies this instance. Check the TIP_Type
     * constructor for further details.
     *
     * @return string The id name
     */
    function getId()
    {
        return $this->_id;
    }

    /**#@-*/
}
?>
