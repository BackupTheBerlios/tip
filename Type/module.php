<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Module definition file
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
 * Base class for modules
 *
 * @package  TIP
 */
abstract class TIP_Module extends TIP_Type
{
    //{{{ Properties

    /**
     * The template engine: required for TIP_Application instances
     *
     * Contains a reference to the template engine to use when parsing a file.
     * See the TIP_Template class for details on what is a template engine.
     * If not configured, it defaults to the one of the main module
     * (that obviously MUST be configured).
     *
     * @var TIP_Template_Engine
     */
    protected $engine = null;

    /**
     * The locale prefix
     *
     * A string to be prepended while looking for locale strings. If not
     * specified, it defaults to getType().
     *
     * @var string
     */
    protected $locale_prefix = null;

    /**
     * The anonymous privilege level for this module
     *
     * If not specified, it defaults to the anonymous privilege of the main
     * application module, that must be configured.
     *
     * @var TIP_PRIVILEGE_...
     */
    protected $anonymous_privilege = null;

    /**
     * The default privilege level for this module
     *
     * If not specified, it defaults to the default privilege of the main
     * application module, that must be configured.
     *
     * @var TIP_PRIVILEGE_...
     */
    protected $default_privilege = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Check the options
     *
     * Overridable static method that checks $options for missing or invalid
     * values and eventually corrects its content.
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        TIP::requiredOption($options, 'engine');
        TIP::requiredOption($options, 'anonymous_privilege', TIP_PRIVILEGE_NONE);
        TIP::requiredOption($options, 'default_privilege', TIP_PRIVILEGE_NONE);

        if (is_string($options['engine'])) {
            $options['engine'] =& TIP_Type::singleton(array(
                'type' => array('template_engine', $options['engine'])
            ));
        } elseif (is_array($options['engine'])) {
            $options['engine'] =& TIP_Type::singleton($options['engine']);
        }

        if (!$options['engine'] instanceof TIP_Template_Engine) {
            return false;
        }

        isset($options['locale_prefix']) || $options['locale_prefix'] = end($options['type']);
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Module instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
        $this->keys['SELF'] = $this->id;
    }

    /**
     * Overridable post construction method
     *
     * Called after the construction happened. This can be overriden to do some
     * other post costruction operation.
     *
     * In TIP_Module, the postConstructor() method initializes the privilege
     * stuff. This cannot be done in the constructor itsself, because the
     * privilege level needs the TIP_User and TIP_Privilege modules to be
     * instantiated, so it will lead to a mutual recursion if this operation
     * is done directly in TIP_Module().
     */
    protected function postConstructor()
    {
        $this->refreshPrivileges();
    }

    //}}}
    //{{{ Methods

    /**
     * Refresh the privileges
     *
     * Refreshes the privileges of this module. If $privilege is not defined,
     * gets the proper privilege using the default TIP_Privilege instance.
     *
     * @param TIP_PRIVILEGE_... $privilege The new privilege level
     */
    protected function refreshPrivileges($privilege = null)
    {
        isset($privilege) || $privilege = TIP::getPrivilege($this->id);
        $this->privilege = $privilege;
        $this->keys['IS_MANAGER']   = false;
        $this->keys['IS_ADMIN']     = false;
        $this->keys['IS_TRUSTED']   = false;
        $this->keys['IS_UNTRUSTED'] = false;

        switch ($this->privilege) {

        case TIP_PRIVILEGE_MANAGER:
            $this->keys['IS_MANAGER']    = true;

        case TIP_PRIVILEGE_ADMIN:
            $this->keys['IS_ADMIN']     = true;

        case TIP_PRIVILEGE_TRUSTED:
            $this->keys['IS_TRUSTED']   = true;

        case TIP_PRIVILEGE_UNTRUSTED:
            $this->keys['IS_UNTRUSTED'] = true;
        }
    }

    /**
     * Get a localized text
     *
     * Gets the localized text for a specified module. $id is prefixed by the
     * 'locale_prefix' property.
     *
     * This method always returns a valid string: if the localized text
     * can't be retrieved, a string containing prefix.$id is returned and a
     * warning message is logged.
     *
     * See the TIP_Locale::get() method for technical details on how the text
     * is localized.
     *
     * @param  string $id      The identifier
     * @param  array  $context A context associative array
     * @param  bool   $cached  Whether to perform or not a cached read
     * @return string          The requested localized text
     */
    protected function getLocale($id, $context = null, $cached = true)
    {
        $text = TIP::getLocale($id, $this->locale_prefix, $context, $cached);
        if (empty($text)) {
            $text = $this->locale_prefix . '.' . $id;
            TIP::warning("localized text not found ($text)");
        }

        return $text;
    }

    /**
     * Localize an id
     *
     * Similar to getLocale() but the result is stored in $dst instead of
     * returned. Also, it provides a way to prepend a prefix and append
     * a suffix on $id before calling getLocale() by specifing them in the
     * $modifiers array.
     *
     * Useful as callback in array_walk arguments.
     *
     * @param string    &$dst       Where to store the localized text
     * @param string     $id        The id to localize
     * @param array|null $modifiers A ('prefix','suffix') array
     */
    protected function localize(&$dst, $id, $modifiers = null)
    {
        if (is_array($modifiers)) {
            $id = $modifiers[0] . $id . $modifiers[1];
        }
        $dst = $this->getLocale($id);
    }

    /**
     * Return the value of a generic item
     *
     * Gets the value of a generic item. The item is the basic form of
     * dynamic data in TIP: it is a generic pair of key => value data with a
     * dynamic value. Examples of items are keys fields.
     *
     * This method can be overriden by the children to provide a more
     * sophisticated interface, such as the fields management in the
     * TIP_DataModule class.
     *
     * @param  string     $id The item id
     * @return mixed|null     The item value or null if not found
     */
    protected function getItem($id)
    {
        $value = @$this->keys[$id];
        if (!is_null($value)) {
            return $value;
        }

        return @$GLOBALS[TIP_MAIN]->keys[$id];
    }

    /**
     * Get the value of a pair throught a "request" interface
     *
     * This method is usually used by the template engine interface methods
     * (the tag... functions) to access any pair information available
     * in the TIP system.
     *
     * A request can get the value of an item, a get, a post or a localized
     * text: the type of the request is obtained parsing the $request token.
     * Specify <code>item[...]</code> for items, <code>get[...]</code> for
     * gets, <code>post[...]</code> for posts and <code>locale[...]</code> for
     * localized text, specifying the id in place of the ellipsize.
     *
     * If no type is specified (that is, $request is directly an identifier),
     * the system will expand it in <code>item[...]</code>.
     * This means <code>getRequest('name')</code> is logically equivalent to
     * <code>getRequest('item[name]')</code>.
     *
     * @param string $request The item id
     * @return mixed|null The requested value or null if the request is invalid
     */
    protected function getRequest($request)
    {
        $open_brace = strpos($request, '[');
        if ($open_brace === false) {
            $type = 'item';
            $id = $request;
        } else {
            $close_brace = strrpos($request, ']');
            if ($close_brace === false || $close_brace < $open_brace) {
                return null;
            }
            $type = strtolower(trim(substr($request, 0, $open_brace)));
            $id = substr($request, $open_brace+1, $close_brace-$open_brace-1);
        }

        switch ($type) {

        case 'item':
            return $this->getItem($id);

        case 'get':
            return TIP::getGet($id, 'string');

        case 'post':
            return TIP::getPost($id, 'string');

        case 'locale':
            return $this->getLocale($id);

        case 'label':
            if (strpos('.', $id) > 0) {
                return TIP::getLocale($id);
            } else {
                return $this->getLocale('label.' . $id);
            }
        }

        return null;
    }

    /**
     * Get the first valid request
     *
     * Given an array of requests, gets the first request in this array that
     * has a not-null value.
     *
     * @param array $requests An array of request
     * @return mixed|null The first valid value or null if no valid request
     *                    are found
     */
    protected function getValidRequest ($requests)
    {
        if (!is_array($requests)) {
            return null;
        }

        foreach ($requests as $request) {
            $value = $this->getRequest($request);
            if (!is_null($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Try to execute a template file
     *
     * Parses and executes the specified file. Similar to run(),
     * but it does not raise any warning/error if $path is not found.
     *
     * @param  array|string $path The file path to run
     * @return bool               true on success or false on errors
     */
    public function tryRun($path)
    {
        is_string($path) && $path = array($this->id, $path);
        $template =& TIP_Type::singleton(array('type' => array('template'), 'path' => $path));
        return $template && $template->run($this);
    }

    /**
     * Execute a template file
     *
     * Parses and executes the specified file. If $path is a string,
     * the module id is prepended to the real path.
     *
     * @param  array|string $path The file path to run
     * @return bool               true on success or false on errors
     */
    public function run($path)
    {
        if (!$this->tryRun($path)) {
            is_array($path) && $path = implode(DIRECTORY_SEPARATOR, $path);
            TIP::error("Unable to run a file ($path)");
            return false;
        }

        return true;
    }

    /**
     * Prepend a template file to the page
     *
     * Runs the $path template using the current template engine and puts
     * the result at the beginning of the page.
     *
     * @param  array|string $path The file path to run
     * @return bool               true on success or false on errors
     */
    protected function insertInPage($path)
    {
        $content =& TIP_Application::getGlobal('content');
        $content = $this->tagRun($path) . $content;
        return true;
    }

    /**
     * Append a template file to the page
     *
     * Runs the $path template using the current template engine and puts
     * the result at the end of the page.
     *
     * @param  array|string $path The file path to run
     * @return bool               true on success or false on errors
     */
    protected function appendToPage($path)
    {
        $content =& TIP_Application::getGlobal('content');
        $content .= $this->tagRun($path);
        return true;
    }

    /**
     * Execute a tag
     *
     * Executes the specified tag, using $params as arguments. This
     * function prepend 'tag' to $name and try to call the so
     * formed method. If you, for instance, runs getTag('Test', ''), a
     * tagTest('') call will be performed.
     *
     * A tag is a request from the template engine to echoes something. It can
     * be tought as the dinamic primitive of the TIP system: every dinamic tag
     * parsed by the template engine runs a tag.
     *
     * The tags - as everything else - are inherited from the module parents,
     * so every TIP_Module tags are available to the TIP_Module children.
     *
     * @param string     $name   The tag name
     * @param string     $params Parameters to pass to the tag
     * @return bool|null         true on success, false on errors or
     *                           null if $name is not a valid tag
     */
    public function getTag($name, $params)
    {
        $name = strtolower($name);
        $method = 'tag' . $name;

        if (!method_exists($this, $method)) {
            TIP::error("the method does not exist ($method)");
            return null;
        }

        global $_tip_profiler;
        if (!is_object($_tip_profiler)) {
            return $this->$method($params);
        }

        $_tip_profiler->enterSection($name);
        $result = $this->$method($params);
        is_object($_tip_profiler) && $_tip_profiler->leaveSection($name);

        return $result;
    }

    /**
     * Execute an action
     *
     * Executes the Action action. This function tries to run Action
     * by calling the following protected methods in this order:
     *
     * - runManagerAction()
     * - runAdminAction()
     * - runTrustedAction()
     * - runUntrustedAction()
     * - runAction()
     *
     * The first method called depends on the current privilege, get throught a
     * TIP::getPrivilege() call. The first method that returns true (meaning
     * the requested action is executed) stops the chain.
     *
     * Usually the actions are called adding variables to the URI. An example of
     * an action call is the following:
     * <samp>http://www.example.org/?module=news&action=view&id=23</samp>
     *
     * This URI will call the "view" action on the "news" module, setting "id" to
     * 23 (it is request to view a news and its comments). You must check the
     * documentation of every module to see which actions are available and what
     * variables they require.
     *
     * @param string $action The action name
     * @return bool|null true on success, false on errors or null if the
     *                   action is not found
     */
    public function callAction($action)
    {
        $action = strtolower($action);

        global $_tip_profiler;
        if (is_object($_tip_profiler)) {
            $_tip_profiler->enterSection("callAction($action)");
        }

        switch ($this->privilege) {

        case TIP_PRIVILEGE_MANAGER:
            if (!is_null($result = $this->runManagerAction($action))) {
                break;
            }

        case TIP_PRIVILEGE_ADMIN:
            if (!is_null($result = $this->runAdminAction($action))) {
                break;
            }

        case TIP_PRIVILEGE_TRUSTED:
            if (!is_null($result = $this->runTrustedAction($action))) {
                break;
            }

        case TIP_PRIVILEGE_UNTRUSTED:
            if (!is_null($result = $this->runUntrustedAction($action))) {
                break;
            }

        default:
            $result = $this->runAction($action);
        }

        if (is_object($_tip_profiler)) {
            $_tip_profiler->leaveSection("callAction($action)");
        }

        return $result;
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param      string       $params Parameters of the tag
     * @return     string|null          The string result or null
     * @subpackage TemplateEngine
     */

    /**
     * Check if a string is not empty
     */
    protected function tagIsValue($params)
    {
        return (is_null($params) || $params == '') ? 'false' : 'true';
    }

    /**
     * Check if a request is set
     *
     * $params is a string in the form "request,request,...".
     *
     * @uses getValidRequest() The method used to resolve the requests
     */
    protected function tagIsSet($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        return $this->tagIsValue($value);
    }

    /**
     * Output the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * This tag will perform a serie of request and will echo the first
     * value found.
     *
     * @uses getValidRequest() The method used to resolve the requests
     */
    protected function tagRaw($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        if (is_null($value)) {
            TIP::error("no valid request found ($params)");
        }

        return $value;
    }

    /**
     * Try to output the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equal to tagRaw(), but do not log any warning if the request is not
     * found.
     *
     * @uses getValidRequest() The method used to resolve the requests
     */
    protected function tagTryRaw($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        isset($value) || $value = '';
        return $value;
    }

    /**
     * Htmlize the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equals to tagRaw(), but the result is converted throught TIP::toHtml()
     * before the output.
     */
    protected function tagHtml($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        if (is_null($value)) {
            TIP::error("no valid request found ($params)");
            return null;
        }

        return TIP::toHtml($value);
    }

    /**
     * Try to htmlize the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equals to tagTryRaw(), but the result is converted throught
     * TIP::toHtml() before the output.
     */
    protected function tagTryHtml($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        return TIP::toHtml($value);
    }

    /**
     * Localized label
     *
     * $params is the id of the string to localize. If there are no dots,
     * the current module id and '.label.' are prepended to $params.
     *
     * Output a properly localized string.
     */
    protected function tagLocalized($params)
    {
        if (strpos($params, '.') !== false) {
            list($prefix, $id) = explode('.', $params, 2);
        } else {
            $prefix = $this->locale_prefix;
            $id = 'label.' . $params;
        }

        return TIP::getLocale($id, $prefix);
    }

    /**
     * Get the property value specified in $params
     */
    protected function tagGetProperty($params)
    {
        return isset($this->$params) ? TIP::toHtml($this->$params) : '';
    }

    /**
     * Get the raw property value specified in $params
     */
    protected function tagGetPropertyRaw($params)
    {
        return isset($this->$params) ? $this->$params : '';
    }

    /**
     * Change a property value
     *
     * $params must be a string in the format 'property,value'.
     */
    protected function tagSetProperty($params)
    {
        list($property, $value) = explode(',', $params, 2);
        $this->$property = $value;
        return '';
    }

    /**
     * Append a string to a property
     *
     * $params must be a string in the format 'property,string'.
     */
    protected function tagAddToProperty($params)
    {
        list($property, $string) = explode(',', $params, 2);
        $this->$property .= $string;
        return '';
    }

    /**
     * Build a relative URI: $params must be referred to the site root
     */
    protected function tagUri()
    {
        return TIP::buildUri(func_get_args());
    }

    /**
     * Build a relative URI: $params must be referred to the template root
     *
     * If the URI does not point to a readable file, the fallback URI is used.
     */
    protected function tagTemplateUri()
    {
        $pieces = func_get_args();
        return file_exists(TIP::buildTemplatePath($pieces)) ? 
            TIP::buildTemplateUri($pieces) : TIP::buildFallbackUri($pieces);
    }

    /**
     * Build a relative URI: $params must be referred to the module data root
     */
    protected function tagDataUri()
    {
        $pieces = func_get_args();
        return TIP::buildDataUri($this->id, $pieces);
    }

    /**
     * Build a relative URI: $params must be referred to the module data root
     */
    protected function tagCacheUri()
    {
        static $base = null;
        if (is_null($base)) {
            $base = array_merge($this->engine->getProperty('cache_root'), TIP_Application::getGlobal('template_root'));
        }
        $pieces = func_get_args();
        return TIP::buildUri($base, $this->id, $pieces);
    }

    /**
     * Build a relative URI: $params must be referred to the module template root
     */
    protected function tagModuleUri()
    {
        $pieces = func_get_args();
        return $this->tagTemplateUri($this->id, $pieces);
    }

    /**
     * Build an icon URI
     */
    protected function tagIconUri()
    {
        $pieces = func_get_args();
        return TIP::buildUri(TIP_Application::getGlobal('icon_root'), $pieces);
    }

    /**
     * Build a relative action URI
     *
     * $params is a string in the form "action[,id[,param1=value1,param2=value2,...]]"
     * The module name can be overriden specifying it as module=...
     */
    protected function tagActionUri($params)
    {
        return TIP::toHtml(TIP::buildActionUriFromTag($params, $this->id));
    }

    /**
     * Check if $params is the current user id
     *
     * Expands to true if the current logged-in user id equals to $params or
     * false otherwise.
     */
    protected function tagIs($params)
    {
        return ((int) $params) === TIP::getUserId() ? 'true' : 'false';
    }

    /**
     * Execute a template
     *
     * Executes the template file $params found in the current module
     * template path, using the current template engine.
     */
    protected function tagRun($params)
    {
        ob_start();
        if ($this->run($params)) {
            return ob_get_clean();
        }
        ob_end_clean();
        return null;
    }

    /**
     * Check if a value is in a list
     *
     * $params is a string in the form "needle,value1,value2,...".
     *
     * Outputs true if needle is present in the comma separated list of values.
     * Useful to check if a value is contained (that is, if it is selected) in
     * a "set" or "enum" field.
     */
    protected function tagInList($params)
    {
        $pos = strpos($params, ',');
        if ($pos === false) {
            TIP::error("invalid inList parameter ($params)");
            return null;
        }

        $needle = substr($params, 0, $pos);
        $list  = explode(',', substr($params, $pos+1));
        return in_array($needle, $list) ? 'true' : 'false';
    }

    /**
     * Format a datetime
     *
     * Converts a datetime (in SQL format) to a specified format.
     *
     * $params must be a string in the format '[date][,format]', where format
     * is any format allowed by the TIP::formatDate() method. If the date is
     * not specified, the current date is assumed. If the format is not
     * specified, it defaults to 'date'.
     *
     * @uses TIP::formatDate() The date formatter
     */
    protected function tagDate($params)
    {
        @list($date, $format) = explode(',', $params, 2);
        empty($format) && $format = 'date';
        return empty($date) ? TIP::formatDate($format) : TIP::formatDate($format, $date, 'sql');
    }

    /**
     * Check if a module exists
     *
     * Expands to true if the module module exists or to false
     * otherwise. This tag only checks if the logic file for the $params
     * module exists, does not load the module itsself nor change
     * the default module.
     *
     * Useful to provide conditional links between different modules.
     */
    protected function tagModuleExists($params)
    {
        $file = TIP::buildLogicPath('module', $params) . '.php';
        return is_readable($file) ? 'true' : 'false';
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    /**
     * Executes a management action
     *
     * Executes an action that requires the 'manager' privilege.
     *
     * @param  string    $action The action name
     * @return bool|null         true on action executed,
     *                           false on action error or
     *                           null on action not found
     */
    protected function runManagerAction($action)
    {
        return null;
    }

    /**
     * Executes an administrator action
     *
     * Executes an action that requires at least the 'admin' privilege.
     *
     * @param  string    $action The action name
     * @return bool|null         true on action executed,
     *                           false on action error or
     *                           null on action not found
     */
    protected function runAdminAction($action)
    {
        return null;
    }

    /**
     * Executes a trusted action
     *
     * Executes an action that requires at least the 'trusted' privilege.
     *
     * @param  string    $action The action name
     * @return bool|null         true on action executed,
     *                           false on action error or
     *                           null on action not found
     */
    protected function runTrustedAction($action)
    {
        return null;
    }

    /**
     * Executes an untrusted action
     *
     * Executes an action that requires at least the 'untrusted' privilege.
     *
     * @param  string    $action The action name
     * @return bool|null         true on action executed,
     *                           false on action error or
     *                           null on action not found
     */
    protected function runUntrustedAction($action)
    {
        return null;
    }

    /**
     * Executes an unprivileged action
     *
     * Executes an action that does not require any privileges.
     *
     * @param  string    $action The action name
     * @return bool|null         true on action executed,
     *                           false on action error or
     *                           null on action not found
     */
    protected function runAction($action)
    {
        return null;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The current privilege descriptor
     * @var TIP_PRIVILEGE_...
     */
    protected $privilege = TIP_PRIVILEGE_NONE;

    /**
     * Custom keys
     *
     * Every TIP_Module object can have a bounch of key => value pairs. These
     * properties are mantained in this array and are used, for instance, by the
     * getItem() method. Also, remember an object inherits the keys from its
     * parents, in a hierarchy order.
     *
     * @var array
     */
    public $keys = array();

    //}}}
}
?>
