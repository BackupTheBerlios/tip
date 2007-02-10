<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Module definition file
 * @package TIP
 **/

/**
 * Base class for modules
 *
 * @abstract
 * @package TIP
 **/
class TIP_Module extends TIP_Type
{
    /**#@+ @access private */

    var $_locales = null;

    /**
     * The current privilege descriptor
     *
     * @var 'manager'|'admin'|'trusted'|'untrusted'|null
     **/
    var $_privilege = null;

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Module instance.
     **/
    function TIP_Module()
    {
        $this->TIP_Type();

        $source_engine = $this->getOption('source_engine');
        if (is_null($source_engine)) {
            $source_engine = TIP::getOption('application', 'source_engine');
        }
        $this->engine =& TIP_Source_Engine::getInstance($source_engine);
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
     **/
    function postConstructor()
    {
        $this->refreshPrivilege();
    }

    /**
     * Refresh the privileges
     *
     * Refreshes the privileges of the current module.
     **/
    function refreshPrivilege()
    {
        $this->_privilege = TIP::getPrivilege($this);

        $this->keys['IS_MANAGER'] = false;
        $this->keys['IS_ADMIN'] = false;
        $this->keys['IS_TRUSTED'] = false;
        $this->keys['IS_UNTRUSTED'] = false;

        switch ($this->_privilege) {
        case 'manager':
            $this->keys['IS_MANAGER'] = true;
        case 'admin':
            $this->keys['IS_ADMIN'] = true;
        case 'trusted':
            $this->keys['IS_TRUSTED'] = true;
        case 'untrusted':
            $this->keys['IS_UNTRUSTED'] = true;
        }
    }

    /**
     * Gets a localized text
     *
     * Gets the localized text from the specified id. The locale used is get
     * from the 'locale' option of the $application object, which must
     * properly set.
     *
     * @param string $id The identifier
     * @return string|null The localized text requested or null on errors
     * @todo Implement this function with a module.
     **/
    function getLocale ($id)
    {
	if (! $this->_locales) {
	    include_once TIP::buildLocalePath ($locale, $this->getName () . '.php');
	    $this->_locales = $messages;
	}

	return @$this->_locales[$id];
    }

    /**
     * Return the content of a generic item
     *
     * Gets the content of a generic item. The item is the basic form of
     * dynamic data in TIP: it is a generic pair of key => value data with a
     * dynamic value content. Examples of items are keys fields.
     *
     * This method can be overriden by the children to provide a more
     * sophisticated interface, such as the fields management in the
     * TIP_DataModule class.
     *
     * @param string $id The item id
     * @return mixed|null The content of the requested item or null if not found
     **/
    function getItem($id)
    {
        $value = @$this->keys[$id];
        if (! is_null($value))
            return $value;

        return @$GLOBALS['application']->keys[$id];
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
     **/
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
     **/
    function getValidRequest ($requests)
    {
        if (! is_array($requests)) {
            return null;
        }

        foreach ($requests as $request) {
            $value = $this->getRequest($request);
            if (! is_null($value))
                return $value;
        }

        return null;
    }

    /**
     * Build a module source path
     *
     * Shortcut for building a module source path using the name of the current
     * module as subpath of the source root.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     **/
    function buildModulePath()
    {
        $pieces = func_get_args();
        return TIP::buildSourcePath($this->getName(), $pieces);
    }

    /**
     * Build a module source URL
     *
     * Shortcut for building a URL using the source_path of the current
     * module as base URL.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     **/
    function buildModuleUrl()
    {
        $pieces = func_get_args();
        return TIP::buildSourceUrl($this->getName(), $pieces);
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @usedby callCommand() An available command
     * @subpackage Commands
     **/

    /**
     * Outputs the content of the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * This command will perform a serie of request and will echo the first
     * value found, converting the result with TIP::toHtml().
     *
     * @uses getValidRequest() The method used to resolve the requests
     **/
    function commandHtml($params)
    {
        if (! strpos(',', $params)) {
            $value = $this->getRequest($params);
        } else {
            $requests = explode(',', $params);
            $value = $this->getValidRequest($requests);
        }
        if (is_null($value)) {
            $this->setError("no valid request found ($params)");
            return false;
        }

        echo TIP::toHtml($value);
        return true;
    }

    /**
     * Tries to output the content of the first defined request
     *
     * $params is a string in the form "request,request,...".
     *
     * Equal to commandHtml(), but do not log any message if the request is not
     * found.
     *
     * @uses getValidRequest() The method used to resolve the requests
     **/
    function commandTryHtml ($params)
    {
        $requests = explode (',', $params);
        $value = $this->getValidRequest ($requests);
        echo TIP::toHtml ($value);
        return true;
    }

    /**
     * Echo a URL
     *
     * Prepends the root URL to $params and outputs the result.
     **/
    function commandUrl($params)
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
     **/
    function commandSourceUrl($params)
    {
        echo TIP::buildSourceUrl($params);
        return true;
    }

    /**
     * Echo a module URL
     *
     * Prepends the source URL of the current module to $params and
     * outputs the result.
     **/
    function commandModuleUrl($params)
    {
        echo $this->buildModuleUrl($params);
        return true;
    }

    /**
     * Echo an icon URL
     *
     * Shortcut for the often used icon url. The icon URL is in the source
     * root URL, under the "shared/icons" path.
     **/
    function commandIconUrl($params)
    {
        echo TIP::buildSourceUrl('shared', 'icons', $params);
        return true;
    }

    /**
     * Check if $params is the current user id
     *
     * Expands to true if the current logged-in user id equals to $params or
     * false otherwise.
     **/
    function commandIs($params)
    {
        echo ((int) $params) === TIP::getUserId() ? 'true' : 'false';
        return true;
    }

    /**
     * Execute a source
     *
     * Executes the source file $params found in the current module source path,
     * using the current source engine.
     **/
    function commandRun($params)
    {
        return $this->run($this->buildModulePath($params));
    }

    /**
     * Execute a shared source
     *
     * Executes the source file $params found in the shared source path, using
     * the current source engine.
     **/
    function commandRunShared($params)
    {
        return $this->run(TIP::buildSourcePath('shared', $params));
    }

    /**
     * Check if a value is in a list
     *
     * $params is a string in the form "value,list".
     *
     * Outputs true if the value is present in the comma (or space) separated
     * list. Useful to check if a value is contained (that is, if it is on) in
     * a "set" field.
     **/
    function commandInList ($params)
    {
	$pos = strpos ($params, ',');
	if ($pos === false)
	    return false;

	$value = substr ($params, 0, $pos);
	$list  = substr ($params, $pos+1);
	echo TIP::inList ($value, $list) ? 'true' : 'false';
	return true;
    }

    /**
     * Format a date
     *
     * Formats the date (specified in $params in iso8601) in the format
     * "date_" . $cfg['application']['locale'].
     * For instance, if the current locale is 'it', the format used will be
     * "date_it".
     *
     * @uses TIP::formatDate() The date formatter
     **/
    function commandDate($params)
    {
        $format = 'date_' . TIP::getOption('application', 'locale');
        echo TIP::formatDate($format, $params, 'iso8601');
        return true;
    }

    /**
     * Format a date time
     *
     * Formats the datetime date (specified in iso8601) in the format
     * "datetime_" . $cfg['application']['locale'].
     *
     * @uses TIP::formatDate() The date formatter
     **/
    function commandDateTime($params)
    {
        $format = 'datetime_' . TIP::getOption('application', 'locale');
        echo TIP::formatDate($format, $params, 'iso8601');
        return true;
    }

    /**
     * Format a multiline text
     *
     * $params is a string in the form "replacer,text".
     *
     * Replaces all the occurrences of a newline in text with the
     * replacer string.
     **/
    function commandNlReplace($params)
    {
        $pos = strpos ($params, ',');
        if ($pos === false) {
            $this->setError('no text to replace');
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
     * otherwise. This command only checks if any configuration option
     * for the $params module exists, does not load the module itsself.
     *
     * Useful to provide conditional links between different modules.
     **/
    function commandModuleExists($params)
    {
        echo array_key_exists ($params, $GLOBALS['cfg']) ? 'true' : 'false';
        return true;
    }

    /**#@-*/


    /**#@+
     * @param string $action The action name
     * @return bool|null true on command executed, false on command error or
     *                   null on command not found
     **/

    /**
     * Executes a management action
     *
     * Executes an action that requires the 'manager' privilege.
     **/
    function runManagerAction ($action)
    {
	return null;
    }

    /**
     * Executes an administrator action
     *
     * Executes an action that requires at least the 'admin' privilege.
     **/
    function runAdminAction ($action)
    {
	return null;
    }

    /**
     * Executes a trusted action
     *
     * Executes an action that requires at least the 'trusted' privilege.
     **/
    function runTrustedAction ($action)
    {
        return null;
    }

    /**
     * Executes an untrusted action
     *
     * Executes an action that requires at least the 'untrusted' privilege.
     **/
    function runUntrustedAction ($action)
    {
	return null;
    }

    /**
     * Executes an unprivileged action
     *
     * Executes an action that does not require any privileges.
     **/
    function runAction ($action)
    {
	return null;
    }

    /**#@-*/


    /**#@+
     * @param string $file The source file
     * @return bool true on success or false on errors
     **/

    /**
     * Execute a file, appending the result to content
     *
     * Executes the File source found in the module path using the current
     * source engine and appends the result to the end of the application
     * content.
     **/
    function appendToContent($file)
    {
        $path = $this->buildModulePath($file);
        $buffer = '';
        $result = $this->runTo($path, $buffer);
        if (! empty($buffer)) {
            $content =& $GLOBALS['application']->content;
            $content .= $buffer;
        }

        return $result;
    }

    /**
     * Execute a file, inserting the result in content
     *
     * Executes the File source found in the module path using the current
     * source engine and inserts the result at the beginning of the application
     * content.
     **/
    function insertInContent($file)
    {
        $path = $this->buildModulePath($file);
        $buffer = '';
        $result = $this->runTo($path, $buffer);

        if (! empty($buffer)) {
            $content =& $GLOBALS['application']->content;
            $content = $buffer . $content;
        }

        return $result;
    }

    /**#@-*/

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
     **/
    var $keys = array ();

    /**
     * The source (or template) engine
     *
     * Contains a reference to the source engine to use when parsing a file.
     * See the TIP_Source class for details on what is a source engine.
     * If not configured, it defaults to the one of $application (that obviously
     * MUST be configured).
     *
     * @var TIP_SourceEngine
     **/
    var $engine = null;


    /**
     * Get a module instance
     *
     * Gets the singleton instance of a module. The $module_name, if not yet
     * registered, is defined by calling TIP_Type::factory().
     *
     * A module is instantiated by includind its logic file found in the
     * 'logic_module_root' directory (relative to 'logic_root').
     *
     * To improve consistency, the $module_name is always converted lowercase.
     * This means also the logic file name must be lowecase.
     *
     * @param string $module_name The module name
     * @param bool   $required    Are the errors fatals?
     * @return TIP_Module A reference to a TIP_Module derived instance
     * @static
     **/
    function& getInstance($module_name, $required = true)
    {
        $id = strtolower($module_name);
        $instance =& TIP_Module::singleton($id);
        if (is_null($instance)) {
            $path = TIP::getOption('application', 'logic_module_root');
            $instance =& TIP_Module::singleton($id, TIP_Type::factory($id, $path, $required));
            if (is_object($instance)) {
                $instance->postConstructor();
            } elseif ($required) {
                TIP::logFatal("Module logic not valid (id: $id; path: $path)");
            }
        }

        return $instance;
    }

    /**
     * Execute a command
     *
     * Executes the specified command, using $params as arguments. This
     * function prepend 'command' to $command and try to call the so
     * formed method. If you, for instance, runs callCommand ('Test', ''), a
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
     * @tutorial TIP/Commands
     **/
    function callCommand($command, $params)
    {
        $method = 'command' . $command;
        if (! method_exists($this, $method)) {
            $class = $this->getName();
            $this->setError("the method does not exist ($class::$method)");
            return null;
        }

        /* The 'debug' command is a special case to be skipped because can stop
           the profiler */
        global $_tip_profiler;
        if (is_object($_tip_profiler) && strcasecmp($command, 'debug')) {
            $_tip_profiler->enterSection($command);
            $done = $this->$method($params);
            $_tip_profiler->leaveSection($command);
            return $done;
        }

        return $this->$method($params);
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
     **/
    function callAction($action)
    {
        $action = strtolower($action);

        global $_tip_profiler;
        if (is_object($_tip_profiler)) {
            $_tip_profiler->enterSection("callAction($action)");
        }

        switch ($this->_privilege) {
        case 'manager':
            $result = $this->runManagerAction($action);
            if (! is_null($result)) {
                break;
            }

        case 'admin':
            $result = $this->runAdminAction($action);
            if (! is_null($result)) {
                break;
            }

        case 'trusted':
            $result = $this->runTrustedAction($action);
            if (! is_null($result)) {
                break;
            }

        case 'untrusted':
            $result = $this->runUntrustedAction($action);
            if (! is_null($result)) {
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
     **/
    function run($file)
    {
        if (empty($file)) {
            $this->setError('file not specified');
            return false;
        }

        $buffer = file_get_contents($file, false);
        if (! $buffer) {
            $this->setError("file not found ($file)");
            return false;
        }

        if (! $this->engine->run($buffer, $this, "Source '$file'")) {
            $this->setError($this->engine->resetError());
            return false;
        }

        return true;
    }

    /**
     * Execute a source file redirecting the output
     *
     * Similar to run(), but redirects the output to $buffer.
     *
     * @param string  $file   The file to run
     * @param string &$buffer The destination buffer
     * @return bool true on success or false on errors
     **/
    function runTo($file, &$buffer)
    {
        ob_start();
        $result = $this->run($file);
        $buffer = ob_get_clean();
        return $result;
    }

    /**#@-*/
}

?>
