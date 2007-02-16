<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP definition file
 * @package TIP
 */

/**
 * The TIP prefix 
 *
 * This is the prefix prepended by the type system to the instantiable object
 * to get the class name. This means all the object inherited from TIP_Type
 * must be prefixed with this string.
 */
define('TIP_PREFIX', 'TIP_');

/**
 * The name of the main module
 *
 * The name of the global variable holding the reference to the main module.
 * It defaults to '_tip_application' and can be accessed throught
 * <code>$GLOBALS[TIP_MAIN_MODULE]</code>.
 */
define('TIP_MAIN_MODULE', '_tip_application');


require_once 'config.php';
require_once 'HTTP.php';


/**
 * A collection of global functions
 *
 * A static class root of all the TIP hierarchy. It provides some global useful
 * functions.
 *
 * @static
 * @package TIP 
 */
class TIP
{
    /**#@+ @access private */

    function getTyped($id, $type, &$collection)
    {
        $value = @$collection[$id];
        if (is_null($value) || ! settype($value, $type)) {
            return null;
        }

        if (! is_string($value) && ! is_array($value)) {
            return $value;
        }

        return get_magic_quotes_gpc() ? TIP::deepStripSlashes($value) : $value;
    }

    function getTimestamp($date, $format)
    {
        switch ($format) {
        case 'timestamp':
            return $date;

        case 'iso8601':
            list($year, $month, $day, $hour, $min, $sec) = sscanf($date, '%d-%d-%d %d:%d:%d');
            return mktime($hour, $min, $sec, $month, $day, $year);
        }

        TIP::logWarning("Input time format not recognized ($format)");
        return false;
    }

    function formatTimestamp ($timestamp, $format)
    {
	switch ($format) {
	case 'date_iso8601':
	    return strftime ('%F', $timestamp);

	case 'datetime_iso8601':
	    return strftime ('%F %H:%M:%S', $timestamp);

	case 'date_it':
	    $SameYear = date ('Y', $timestamp) == date ('Y');
	    $SameDay = date ('z', $timestamp) == date ('z');

	    if ($SameYear && $SameDay)
		return 'oggi';

	    $result = strftime ('%d %B', $timestamp);
	    if (! $SameYear)
		$result .= strftime (' %Y', $timestamp);

	    return $result;

	case 'datetime_it':
	    $result = TIP::formatTimestamp ($timestamp, 'date_it');
	    if (! $result)
		return null;

	    $result .= strftime (' alle %H:%M', $timestamp);
	    return $result;
	}

	TIP::logWarning ("Output time format not recognized ($format)");
	return null;
    }

    function logGeneric($domain, $message, $notify = false)
    {
        $logger =& TIP_Module::getInstance('logger', false);
        if (is_object($logger)) {
            $logger->logMessage ($domain, $message, @$_SERVER['REQUEST_URI'], $notify);
        }
    }

    function quit($message)
    {
        exit("<h3>Errore fatale: $message</h3><p>Si prega di comunicare il problema all'amministratore del sito inviando una email all'indirizzo <a href=\"mailto:webmaster@bresciapoint.it\">webmaster@bresciapoint.it</a>.</p><p>Grazie per la collaborazione.</p>");
    }

    /**#@-*/


    /**#@+
     * @access public
     * @static
     */

    /**
     * Get the operating system descriptor
     *
     * Checks the PHP_OS constant to get on which operating system the PHP is
     * running. If the PHP_OS constant is not defined, the function fallbacks
     * to 'unix'. The idea was picked from phpMyAdmin.
     *
     * @return 'unix'|'windows'|'os2' The guessed operating system descriptor
     */
    function getOS()
    {
	$OS = 'unix';

	if (defined ('PHP_OS'))
	{
	    if (stristr (PHP_OS, 'win'))
		$OS = 'windows';
	    elseif (stristr (PHP_OS, 'OS/2'))
		$OS = 'os2';
	}

	return $OS;
    }

    /**
     * Gets an option
     *
     * Gets a configuration option for a specified type. All the option values
     * must be defined in the config.php file.
     *
     * @param string $type     Descriptor of the type
     * @param string $option   The option to retrieve
     * @return mixed|null The value of the requested option or null on errors
     */
    function getOption($type, $option, $required = false)
    {
        $value = @$GLOBALS['cfg'][$type][$option];
        if ($required && is_null($value)) {
            TIP::logFatal("Required option not defined (\$cfg['$type']['$option'])");
        }
        return $value;
    }

    /**
     * Deep addslashes()
     *
     * Wrappes addslashes() in a deeper form, allowing to add slashes also to
     * embedded arrays.
     *
     * @param array|string $value Array or string to add slashes
     * @return array|string The slashized copy of $value
     */
    function deepAddSlashes($value)
    {
        return is_array($value) ? array_map(array('TIP', 'deepAddSlashes'), $value) : addslashes($value);
    }

    /**
     * Deep stripslashes()
     *
     * Wrappes stripslashes() in a deeper form, allowing to strip slashes also
     * to embedded arrays.
     *
     * @param array|string $value Array or string to strip slashes
     * @return array|string The unslashized copy of $value
     */
    function deepStripSlashes($value)
    {
        return is_array($value) ? array_map(array('TIP', 'deepStripSlashes'), $value) : stripslashes($value);
    }

    /**
     * Deep implode()
     *
     * Wrappes implode() in a deeper form, allowing to implode also embedded
     * arrays. Be careful the order of the arguments is not the same of the
     * original implode() function. This because the array_map() recursive
     * calls pass the array as the first argument.
     *
     * @param array|string $value Array or string to implode
     * @return array|string The imploded copy of $value
     */
    function deepImplode ($value, $glue = null)
    {
	static $the_glue = null;
	if (! is_null ($glue))
	    $the_glue = $glue;
	return is_array ($value) ? implode ($the_glue, array_map (array ('TIP', 'deepImplode'), $value)) : $value;
    }

    /**
     * Checks if a value is present in a list
     *
     * Scans a comma or space separated list for a specific value.
     *
     * @param string $value The value to find
     * @param string $list  The list of values
     * @return bool true if the value is found or false if not found
     */
    function inList ($value, $list)
    {
	for ($token = strtok ($list, ' ,'); $token !== false; $token = strtok (' ,'))
	    if ($token == $value)
		return true;

	return false;
    }

    /**
     * Gets a $_GET in a typesafe manner
     *
     * Gets a value from the superglobal $_GET array, forcing the result to
     * $type. Also, if the current PHP installation has the "magic quote"
     * feature turned on, the result is unslashized throught deepStripSlashes()
     * to provide a consistent method on different PHP installations.
     *
     * $type can be any value accepted by settype(), that is:
     *
     * - 'bool'   to force a boolean value
     * - 'int'    to force an integer number
     * - 'float'  to force a floating point number
     * - 'string' to force a string
     * - 'array'  to force an array
     * - 'object' to force an object
     *
     * @param string $id   The get identifier
     * @param string $type The expected type
     * @return mixed|null The content of the requested get or null on errors
     * @see getPost(),getCookie()
     */
    function getGet ($id, $type)
    {
	return TIP::getTyped ($id, $type, $_GET);
    }

    /**
     * Get a $_POST in a typesafe manner
     *
     * Performs the same job as getGet(), but using the superglobal $_POST
     * array.
     *
     * @param string $id   The post identifier
     * @param string $type The expected type
     * @return mixed|null The content of the requested post or null on errors
     * @see getGet(),getCookie()
     */
    function getPost ($id, $type)
    {
	return TIP::getTyped ($id, $type, $_POST);
    }

    /**
     * Get a $_COOKIE in a typesafe manner
     *
     * Performs the same job as getGet(), but using the superglobal $_COOKIE
     * array.
     *
     * @param string $id   The cookie identifier
     * @param string $type The expected type
     * @return mixed|null The content of the requested cookie or null on errors
     * @see getGet(),getPost()
     */
    function getCookie($id, $type)
    {
        return TIP::getTyped($id, $type, $_COOKIE);
    }


    /**
     * Date/time formatter
     *
     * Converts a date, specified in $input_format format, in the $format
     * format and returns the result. If $input is not defined, the current
     * time is used as input.
     *
     * The $format parameter can be one of the following values:
     *
     * - 'date_iso8601' for a string with a day description in ISO 8601 format
     * - 'datetime_iso8601' for a string with day and hour description in ISO 8601 format
     * - 'date_it' for a string with a day description (italian locale)
     * - 'datetime_it' for a string with day and hour description (italian locale)
     *
     * The $input_format parameter can be one of the following values:
     *
     * - 'timestamp' for UNIX timestamps
     * - 'iso8601' for ISO8601 date (the format used, for instance, by MySql)
     *
     * @param string $format       The format of the resulting date
     * @param mixed  $input        The source date to format
     * @param string $input_format The format of the source date
     * @return mixed|null The formatted date or null on errors
     */
    function formatDate($format, $input = null, $input_format = 'timestamp')
    {
        $timestamp = is_null($input) ? time() : TIP::getTimestamp($input, $input_format);
        if (! $timestamp)
            return null;

        return TIP::formatTimestamp($timestamp, $format);
    }

    /**
     * Log a warning message
     *
     * Logs the specified warning message using the default logger mechanism.
     * The difference between warnings and errors is developer-dependent: TIP
     * does not make any assumption, apart of writing WARNING instead of ERROR.
     *
     * @param string $message The message to log
     * @see logError(),logFatal()
     */
    function logWarning ($message)
    {
        TIP::logGeneric('WARNING', $message);
    }

    /**
     * Log an error message
     *
     * Logs the specified error message using the default logger mechanism.
     * The difference between warnings and errors is developer-dependent: TIP
     * does not make any assumption, apart of writing ERROR instead of WARNING.
     *
     * @param string $message The message to log
     * @see logWarning(),logFatal()
     */
    function logError ($message)
    {
	TIP::logGeneric ('ERROR', $message);
    }

    /**
     * Log an error message and quits the application
     *
     * Logs the specified error message using the default logger mechanism and
     * quits the application, trying to output the error in an HTML fashion with
     * some useful informations such as the webmaster email address.
     *
     * @param string $message The message to log
     * @see logWarning(),logError()
     * @todo The HTML output does not work well because it is generated in an
     *       unknown HTML context. Furthermore, I want to avoid output buffers
     *       on the whole context. So, what is the solution?
     */
    function logFatal($message)
    {
        TIP::logGeneric('FATAL', $message);
        TIP::quit($message);
    }

    /**
     * Info notification to the user
     *
     * Outputs the specified info message to notify the user about something.
     * This is merely a wrapper that calls the TIP_Notify::echoInfo() method,
     * so check the TIP_Notify documentation for further informations.
     *
     * If the info id is not found, a new call to echoInfo() without any
     * argument is performed to try to get a default info message.
     *
     * @param mixed  $id              The id of the system message
     * @param string $context_message A custom message to append
     */
    function info($id, $context_message = '')
    {
        $notify =& TIP_Module::getInstance('notify');
        if (is_object($notify)) {
            $notify->keys['CONTEXT_MESSAGE'] = $context_message;

            if (! $notify->echoInfo($id)) {
                $notify->echoInfo();
            }
        }
    }

    /**
     * Error notification to the user
     *
     * Outputs the specified error message to notify the user about something
     * wrong. This is merely a wrapper that calls the TIP_Notify::echoError()
     * method, so check the TIP_Notify documentation for further informations.
     *
     * If the error id is not found, a new call to echoError() without any
     * argument is performed to try to get a default error message.
     *
     * @param mixed  $id              The id of the system message
     * @param string $context_message A custom message to append
     */
    function error($id, $context_message = '')
    {
        $notify =& TIP_Module::getInstance('notify');
        if (is_object($notify)) {
            $notify->keys['CONTEXT_MESSAGE'] = $context_message;

            if (! $notify->echoError($id)) {
                $notify->echoError();
            }
        }
    }

    /**
     * Build a path
     *
     * Constructs a path from the argument list and prepending the application
     * base path.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildPath()
    {
        static $base_path = null;
        if (is_null($base_path)) {
            $script = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : __FILE__;
            $base_path = rtrim(realpath(dirname($script)), DIRECTORY_SEPARATOR);
        }

        return TIP::deepImplode(array($base_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a logic path
     *
     * Shortcut for building a path prepending the application 'logic_root'.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildLogicPath()
    {
        static $logic_path = null;
        if (is_null($logic_path)) {
            $logic_root = TIP::getOption('application', 'logic_root', true);
            $logic_path = TIP::buildPath($logic_root);
        }

        return TIP::deepImplode(array($logic_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a source path
     *
     * Shortcut for building a path prepending the application 'source_root'.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildSourcePath()
    {
        static $source_path = null;
        if (is_null($source_path)) {
            $source_root = TIP::getOption('application', 'source_root', true);
            $source_path = TIP::buildPath($source_root);
        }

        return TIP::deepImplode(array($source_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a locale path
     *
     * Shortcut for building a path prepending the application 'locale_root'
     * and the application 'locale'.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildLocalePath()
    {
        static $locale_path = null;
        if (is_null($locale_path)) {
            $locale_root = TIP::getOption('application', 'locale_root', true);
            $locale = TIP::getOption('application', 'locale', true);
            $locale_path = TIP::buildPath($locale_root, $locale);
        }

        return TIP::deepImplode (array ($locale_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a URL
     *
     * Constructs a URL from the argument list and prepending the application
     * base URL.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     */
    function buildUrl()
    {
        static $base_url = null;
        if (is_null($base_url)) {
            $base_url = rtrim(HTTP::absoluteURI('/'), ' /');
        }

        return TIP::deepImplode(array($base_url, func_get_args()), '/');
    }

    /**
     * Build a source URL
     *
     * Shortcut for building a URL prepending the application 'source_root'.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     */
    function buildSourceUrl()
    {
        static $source_url = null;
        if (! $source_url) {
            $source_url = TIP::buildUrl(TIP::getOption('application', 'source_root'));
        }

        return TIP::deepImplode(array($source_url, func_get_args()), '/');
    }

    /**
     * Convert to HTML a value
     *
     * Converts the $value content in HTML safe manner, accordling to its type.
     *
     * @param mixed $value The value to convert
     * @return string The converted value 
     */
    function toHtml($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (empty($value)) {
            return '';
        } elseif (is_string($value)) {
            return htmlentities($value, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    /**
     * Strip the TIP prefix
     *
     * Removes the TIP prefix, defined in the TIP_PREFIX constant, from a
     * string.
     *
     * @param string $id A TIP prefixed identifier
     * @return string The identifier without the TIP prefix
     */
    function stripTipPrefix($id)
    {
        return substr($id, strlen(TIP_PREFIX));
    }

    /**
     * Gets the current user id
     *
     * Returns the id of the logged in user.
     *
     * @param bool $refresh Forces the update of the internal user id cache
     * @return mixed|false|null The current user id, null if this is an
     *                          anonymous session or false if the user module
     *                          is not present
     */
    function getUserId($refresh = false)
    {
        static $initialized = false;
        static $user_id;

        if (! $initialized || $refresh) {
            $user =& TIP_Module::getInstance('user');
            if (! is_object($user)) {
                $user_id = false;
            } else {
                $user_id = @$user->keys['CID'];
            }

            $initialized = true;
        }

        return $user_id;
    }

    /**
     * Get the privilege for the specified module
     *
     * Returns the privilege for a module of the specified user.  If $user_id
     * is omitted, the current user id is used. Check TIP_Privilege to see how the
     * privileges are used.
     *
     * @param TIP_Module &$module The requesting module
     * @param mixed      $user_id A user id
     * @return 'manager'|'admin'|'trusted'|'untrusted'|null The requested privilege
     */
    function getPrivilege(&$module, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = TIP::getUserId();
        }

        $anonymous = is_null($user_id) || $user_id === false;
        if (! $anonymous) {
            $privilege =& TIP_Module::getInstance('privilege');
            if (is_object($privilege)) {
                $stored_privilege = $privilege->getStoredPrivilege($module, $user_id);
                if (! is_null($stored_privilege)) {
                    return $stored_privilege;
                }
            }
        }

        $privilege_type = $anonymous ? 'anonymous_privilege' : 'default_privilege';
        $result = $module->getOption($privilege_type);
        if (is_null($result)) {
            $result = TIP::getOption('application', $privilege_type);
        }

        return $result;
    }

    /**#@-*/
}

require_once 'Type.php';
require_once 'Callback.php';
require_once 'SourceEngine.php';
require_once 'Module.php';
require_once 'DataEngine.php';
require_once 'Data.php';
require_once 'View.php';
require_once 'Block.php';


/**
 * Application entry point
 *
 * Every TIP based site must have a starting point (in C terms, it must have a
 * "main" function), that is an object that runs a specified source program.
 * This is what $_tip_application is.
 *
 * @var TIP_Application
 */
$GLOBALS[TIP_MAIN_MODULE] =& TIP_Module::getInstance('application');

?>
