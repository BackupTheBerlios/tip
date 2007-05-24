<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */

/**
 * Base class for modules
 *
 * @package  TIP
 * @tutorial TIP/Module.pkg#TIP_Module
 */
abstract class TIP_Module extends TIP_Type
{
    /**#@+ @access private */

    /**
     * The current privilege descriptor
     *
     * @var TIP_PRIVILEGE_...
     */
    var $_privilege = TIP_PRIVILEGE_NONE;

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Module instance.
     * You must redefine the constructor as public to be able to use it.
     *
     * @param mixed $id Identifier of this module
     */
    protected function __construct($id)
    {
        parent::__construct($id);

        if (is_null($engine_name = $this->getOption('source_engine')) &&
            is_null($engine_name = $GLOBALS[TIP_MAIN]->getOption('source_engine'))) {
            return;
        }

        $this->engine =& TIP_Type::getInstance($engine_name);
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
        $this->_privilege = TIP::getPrivilege($this->getId());
        $this->refreshPrivileges();
    }

    /**
     * Refresh the privileges
     *
     * Refreshes the privileges of the current module.
     */
    function refreshPrivileges()
    {
        $this->keys['IS_MANAGER']   = false;
        $this->keys['IS_ADMIN']     = false;
        $this->keys['IS_TRUSTED']   = false;
        $this->keys['IS_UNTRUSTED'] = false;

        switch ($this->_privilege) {

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
     * Gets the localized text for a specified module. The prefix to build the
     * row id is get from the 'locale_prefix' option of the caller module. If
     * not specified, it defaults to getType().
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
    function getLocale($id, $context = null, $cached = true)
    {
        if (is_null($prefix = $this->getOption('locale_prefix'))) {
            $prefix = $this->getType();
        }

        $text = TIP::getLocale($id, $prefix, $context, $cached);
        if (empty($text)) {
            $text = $prefix . '.' . $id;
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
    function localize(&$dst, $id, $modifiers = null)
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
    function getItem($id)
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
     * This method is usually used by the source engine interface methods
     * (the command... functions) to access any pair information available
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
    function getRequest($request)
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
            return $this->getLocale('label.' . $id);
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
    function getValidRequest ($requests)
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
     * Build a source URL
     *
     * Shortcut for building a source URL: if the URL does not point to a
     * readable file, the fallback URL is used.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     */
    function buildSourceUrl()
    {
        $pieces = func_get_args();
        $url = TIP::buildSourceUrl($pieces);
        return is_readable($url) ? $url : TIP::buildFallbackUrl($pieces);
    }

    /**
     * Build a source path
     *
     * Shortcut for building a module source path using the name of the current
     * module as subpath of the source root.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildSourcePath()
    {
        $pieces = func_get_args();
        $path = TIP::buildSourcePath($pieces);
        return is_readable($path) ? $path : TIP::buildFallbackPath($pieces);
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Output the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * This command will perform a serie of request and will echo the first
     * value found.
     *
     * @uses getValidRequest() The method used to resolve the requests
     */
    protected function commandRaw($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        if (is_null($value)) {
            TIP::error("no valid request found ($params)");
            return false;
        }

        echo $value;
        return true;
    }

    /**
     * Try to output the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equal to commandRaw(), but do not log any warning if the request is not
     * found.
     *
     * @uses getValidRequest() The method used to resolve the requests
     */
    protected function commandTryRaw($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        if (isset($value)) {
            echo $value;
        }
        return true;
    }

    /**
     * Htmlize the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equals to commandRaw(), but the result is converted throught TIP::toHtml()
     * before the output.
     */
    protected function commandHtml($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        if (is_null($value)) {
            TIP::error("no valid request found ($params)");
            return false;
        }

        echo TIP::toHtml($value);
        return true;
    }

    /**
     * Try to htmlize the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equals to commandTryRaw(), but the result is converted throught
     * TIP::toHtml() before the output.
     */
    protected function commandTryHtml($params)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        echo TIP::toHtml($value);
        return true;
    }

    /**
     * Link to an action
     *
     * $params is a string in the form "action,param1=value1,param2=value2,..."
     *
     * Output a proper link to the specified action. The values are urlencoded
     * to avoid collateral effects.
     */
    protected function commandAction($params)
    {
        $pos = strpos ($params, ',');
        if ($pos === false) {
            $action = $params;
            $list = array();
        } else {
            $action = substr($params, 0, $pos);
            $list = explode(',', substr($params, $pos+1));
        }

        if (!empty($action)) {
            array_unshift($list, 'action=' . $action);
        }
        if (strpos($params, ',module=') === false) {
            array_unshift($list, 'module=' . $this->getId());
        }
        $args = implode('&amp;', TIP::urlEncodeAssignment($list));
        echo TIP::getScriptURI() . '?' . $args;
        return true;
    }

    /**
     * Echo a URL
     *
     * Prepends the root URL to $params and outputs the result.
     */
    protected function commandUrl($params)
    {
        echo TIP::buildUrl($params);
        return true;
    }

    /**
     * Echo a source URL
     *
     * Prepends the source root URL $params and outputs the result.
     * This command (or any of its variants) MUST be used for every file
     * reference if you want a theme-aware site, because enabling themes will
     * make the prepending path a dynamic variable.
     */
    protected function commandSourceUrl($params)
    {
        echo $this->buildSourceUrl($params);
        return true;
    }

    /**
     * Echo a module URL
     *
     * Prepends the source URL of the current module to $params and
     * outputs the result.
     */
    protected function commandModuleUrl($params)
    {
        echo $this->buildSourceUrl($this->getId(), $params);
        return true;
    }

    /**
     * Echo an icon URL
     *
     * Shortcut for the often used icon url. The icon URL is in the source
     * root URL, under the "shared/icons" path.
     */
    protected function commandIconUrl($params)
    {
        static $icon_url = null;
        if (!$icon_url) {
            $icon_url = $this->buildSourceUrl('shared', 'icons');
        }
        echo $icon_url . '/' . $params;
        return true;
    }

    /**
     * Check if $params is the current user id
     *
     * Expands to true if the current logged-in user id equals to $params or
     * false otherwise.
     */
    protected function commandIs($params)
    {
        echo ((int) $params) === TIP::getUserId() ? 'true' : 'false';
        return true;
    }

    /**
     * Execute a source
     *
     * Executes the source file $params found in the current module source path,
     * using the current source engine.
     */
    protected function commandRun($params)
    {
        return $this->run($this->buildSourcePath($this->getId(), $params));
    }

    /**
     * Execute a shared source
     *
     * Executes the source file $params found in the shared source path, using
     * the current source engine.
     */
    protected function commandRunShared($params)
    {
        return $this->run($this->buildSourcePath('shared', $params));
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
    protected function commandInList($params)
    {
        $pos = strpos($params, ',');
        if ($pos === false) {
            TIP::error("invalid inList parameter ($params)");
            return false;
        }

        $needle = substr($params, 0, $pos);
        $list  = explode(',', substr($params, $pos+1));
        echo in_array($needle, $list) ? 'true' : 'false';
        return true;
    }

    /**
     * Format a date
     *
     * Formats the date (specified in $params in iso8601) in the format
     * "date_" . locale.
     * For instance, if the current locale is 'it', the format used will be
     * "date_it".
     *
     * @uses TIP::formatDate() The date formatter
     */
    protected function commandDate($params)
    {
        $format = 'date_' . $GLOBALS[TIP_MAIN]->getOption('locale');
        echo TIP::formatDate($format, $params, 'iso8601');
        return true;
    }

    /**
     * Format a date time
     *
     * Formats the datetime date (specified in iso8601) in the format
     * "datetime_" . locale.
     *
     * @uses TIP::formatDate() The date formatter
     */
    protected function commandDateTime($params)
    {
        static $format = null;
        if (is_null($format)) {
            $format = 'datetime_' . $GLOBALS[TIP_MAIN]->getOption('locale');
        }
        echo TIP::toHtml(TIP::formatDate($format, $params, 'iso8601'));
        return true;
    }

    /**
     * Format a multiline text
     *
     * $params is a string in the form "replacer,text".
     *
     * Replaces all the occurrences of a newline in text with the
     * replacer string.
     */
    protected function commandNlReplace($params)
    {
        $pos = strpos ($params, ',');
        if ($pos === false) {
            TIP::error('no text to replace');
            return false;
        }

        $from   = "\n";
        $to     = substr($params, 0, $pos);
        $buffer = str_replace("\r", '', substr ($params, $pos+1));

        echo str_replace($from, $to, $buffer);
        return true;
    }

    /**
     * Check if a module exists
     *
     * Expands to true if the module module exists or to false
     * otherwise. This command only checks if the logic file for the $params
     * module exists, does not load the module itsself nor change
     * the default module.
     *
     * Useful to provide conditional links between different modules.
     */
    protected function commandModuleExists($params)
    {
        $file = TIP::buildLogicPath('module', $params) . '.php';
        echo is_readable($file) ? 'true' : 'false';
        return true;
    }

    /**#@-*/


    /**#@+
     * @param string $action The action name
     * @return bool|null true on action executed, false on action error or
     *                   null on action not found
     */

    /**
     * Executes a management action
     *
     * Executes an action that requires the 'manager' privilege.
     */
    protected function runManagerAction($action)
    {
        return null;
    }

    /**
     * Executes an administrator action
     *
     * Executes an action that requires at least the 'admin' privilege.
     */
    protected function runAdminAction($action)
    {
        return null;
    }

    /**
     * Executes a trusted action
     *
     * Executes an action that requires at least the 'trusted' privilege.
     */
    protected function runTrustedAction($action)
    {
        return null;
    }

    /**
     * Executes an untrusted action
     *
     * Executes an action that requires at least the 'untrusted' privilege.
     */
    protected function runUntrustedAction($action)
    {
        return null;
    }

    /**
     * Executes an unprivileged action
     *
     * Executes an action that does not require any privileges.
     */
    protected function runAction($action)
    {
        return null;
    }

    /**#@-*/


    /**
     * Prepend a source file to the page
     *
     * Runs $file using the current source engine and puts the result at the
     * beginning of the page.
     *
     * @param  string $file The source file
     * @return bool         true on success or false on errors
     */
    function insertInPage($file)
    {
        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildSourcePath($this->getId(), $file);
        }
        $GLOBALS[TIP_MAIN]->prependCallback(array(&$this, 'run'), array($file));
        return true;
    }

    /**
     * Append a source file to the page
     *
     * Runs $file using the current source engine and puts the result at the
     * end of the page.
     *
     * @param  string $file The source file
     * @return bool         true on success or false on errors
     */
    function appendToPage($file)
    {
        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildSourcePath($this->getId(), $file);
        }
        $GLOBALS[TIP_MAIN]->appendCallback(array(&$this, 'run'), array($file));
        return true;
    }

    /**#@-*/


    /**#@+ @access public */

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
    var $keys = array ();

    /**
     * The source (or template) engine
     *
     * Contains a reference to the source engine to use when parsing a file.
     * See the TIP_Source class for details on what is a source engine.
     * If not configured, it defaults to the one of the main module
     * (that obviously MUST be configured).
     *
     * @var TIP_SourceEngine
     */
    var $engine = null;


    /**
     * Execute a command
     *
     * Executes the specified command, using $params as arguments. This
     * function prepend 'command' to $command and try to call the so
     * formed method. If you, for instance, runs callCommand('Test', ''), a
     * commandTest('') call will be performed.
     *
     * A command is a request from the source engine to echoes something. It can
     * be tought as the dinamic primitive of the TIP system: every dinamic tag
     * parsed by the source engine runs a command.
     *
     * The commands - as everything else - are inherited from the module parents,
     * so every TIP_Module commands are available to the TIP_Module children.
     *
     * @param string $command The command name
     * @param string $params  Parameters to pass to the command
     * @return bool|null true on success, false on errors or null if the
     *                   command is not found
     * @tutorial TIP/SourceEngine/SourceEngine.pkg#commands
     */
    function callCommand($command, $params)
    {
        $command = strtolower($command);
        $method = 'command' . $command;

        if (!method_exists($this, $method)) {
            TIP::error("the method does not exist ($method)");
            return null;
        }

        global $_tip_profiler;
        if (is_object($_tip_profiler)) {
            $_tip_profiler->enterSection($command);
        }

        $done = $this->$method($params);

        if (is_object($_tip_profiler)) {
            $_tip_profiler->leaveSection($command);
        }

        return $done;
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
     * Usually the actions are called adding variables to the URL. An example of
     * an action call is the following URL:
     * <samp>http://www.example.org/?module=news&action=view&id=23</samp>
     *
     * This URL will call the "view" action on the "news" module, setting "id" to
     * 23 (it is request to view a news and its comments). You must check the
     * documentation of every module to see which actions are available and what
     * variables they require.
     *
     * @param string $action The action name
     * @return bool|null true on success, false on errors or null if the
     *                   action is not found
     */
    function callAction($action)
    {
        $action = strtolower($action);

        global $_tip_profiler;
        if (is_object($_tip_profiler)) {
            $_tip_profiler->enterSection("callAction($action)");
        }

        switch ($this->_privilege) {

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

    /**
     * Execute a source file
     *
     * Parses and executes the specified file.
     *
     * @param string $file The file to run
     * @return bool true on success or false on errors
     */
    function run($file)
    {
        $source =& TIP_Type::singleton(array('source'), array('path' => $file, 'engine' => &$this->engine));
        return $source->run($this);
    }

    /**#@-*/
}
?>
