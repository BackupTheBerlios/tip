<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP definition file
 * @package TIP
 */


/**#@+ Backward compatibily functions */
require_once 'PHP/Compat/Function/array_intersect_key.php';
require_once 'PHP/Compat/Function/array_combine.php';
/**#@-*/


/**
 * The TIP prefix 
 *
 * This is the prefix used by the TIP objects. It is used in various place,
 * such as to get the type name from the class by stripping this prefix
 * (avoiding tedious prefix repetitions) and to manage the view names.
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

    function _getTyped($id, $type, &$collection)
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

    function _formatTimestamp($timestamp, $format)
    {
        switch ($format) {
        case 'date_iso8601':
            return strftime('%F', $timestamp);

        case 'datetime_iso8601':
            return strftime('%F %H:%M:%S', $timestamp);

        case 'date_it':
            $same_year = date('Y', $timestamp) == date('Y');
            $same_day = date('z', $timestamp) == date('z');
            if ($same_year && $same_day) {
                return 'oggi';
            }
            return strftime($same_year ? 'il %d %B' : 'il %d %B %Y', $timestamp);

        case 'datetime_it':
            $date = TIP::_formatTimestamp($timestamp, 'date_it');
            $time = strftime('%H:%M', $timestamp);
            return $date . ' alle ' . $time;
        }

        TIP::logWarning("Output time format not recognized ($format)");
        return null;
    }

    function _logGeneric($domain, $message, $notify = false)
    {
        $logger =& TIP_Module::getInstance('logger', false);
        if (is_object($logger)) {
            $logger->logMessage($domain, $message, @$_SERVER['REQUEST_URI'], $notify);
        }
    }

    function quit($message)
    {
        exit("<h3>Errore fatale: $message</h3><p>Si prega di comunicare il problema all'amministratore del sito inviando una email all'indirizzo <a href=\"mailto:webmaster@bresciapoint.it\">webmaster@bresciapoint.it</a>.</p><p>Grazie per la collaborazione.</p>");
    }

    function _startSession()
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        require_once 'HTTP/Session.php';

        $user_id = TIP::getUserId();
        if (! is_null($user_id) && $user_id !== false) {
            // For a logged in user, the session id is its user_id
            HTTP_Session::useCookies(false);
            HTTP_Session::useTransSID(false);
            HTTP_Session::start('TIP', $user_id);
        } else {
            // For anonymous users, try to use the TransSID feature of PHP.
            // If not available, as last resort, use cookies.
            HTTP_Session::useTransSID(true);
            HTTP_Session::useTransSID() || HTTP_Session::useCookies(true);
            HTTP_Session::start('TIP');
        }

        if (HTTP_Session::isExpired()) {
            HTTP_Session::destroy();
            HTTP::redirect($_SERVER['REQUEST_URI']);
            exit;
        }

        $initialized = true;
        HTTP_Session::setExpire(3600, true);
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
        $os = 'unix';

        if (defined ('PHP_OS')) {
            if (stristr(PHP_OS, 'win')) {
                $os = 'windows';
            } elseif (stristr (PHP_OS, 'OS/2')) {
                $os = 'os2';
            }
        }

        return $os;
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
     * Get a localized text
     *
     * Gets the localized text for the specified id and module. The locale used
     * is get from the 'locale' option of the application, which must be
     * properly set.
     *
     * See the TIP_Locale::get() method for technical details on how the text
     * is localized.
     *
     * @param string $id     The text identifier
     * @param string $module The name of the caller module
     * @param bool   $cached Whether to perform or not a cached read
     * @return string The requested localized text
     */
    function getLocale($id, $module = 'application', $cached = true)
    {
        static $locale = false;
        if ($locale === false) {
            $locale =& TIP_Module::getInstance('locale', false);
        }

        return is_object($locale) ? $locale->get($id, $module, $cached) : $id;
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
     * @param array  $pieces The array to implode
     * @param string $glue   The glue to use while imploding
     * @return string The imploded copy of $pieces
     */
    function deepImplode($pieces, $glue = null)
    {
        static $the_glue = null;
        if (! is_null ($glue)) {
            $the_glue = $glue;
        }
        return is_array($pieces) ? implode($the_glue, array_map(array('TIP', 'deepImplode'), $pieces)) : $pieces;
    }

    /**
     * Urlencode an assignment
     *
     * Urlencodes only the value part of an assignment. An assignment is a
     * string in the form 'param=value', often used as construct to pass values
     * in URLs. This function works also on array.
     *
     * @param string|array $assignment The assignment (or array of assignments) to encode
     * @return string|array The encoded copy of $assignment
     */
    function urlEncodeAssignment($assignment)
    {
        if (is_array($assignment)) {
            return array_map(array('TIP', 'urlEncodeAssignment'), $assignment);
        }

        list($param, $value) = explode('=', $assignment);
        return $param . '=' . urlencode($value);
    }

    /**
     * Double explode a string
     *
     * Given an item separator and a pair separator, performs the explode()
     * operation twice and return the result as an associative array.
     *
     * The $string must have the following format:
     * <code>key1{$pair_separator}value1{$item_separator}key2{$pair_separator}value2{$item_separator}...</code>
     * If, for instance, $item_separator is ',' and $pair_separator is '=',
     * this function will properly parse the following string:
     * <code>key1=value1,key2=value2,key_n=value_n</code>
     *
     * The spaces are no stripped, so be aware to keep $string compact.
     *
     * @param string $item_separator The item separator character
     * @param string $pair_separator The key-value separator character
     * @param string $string         The string to parse
     * @return array The resulting associative array
     */
    function doubleExplode($item_separator, $pair_separator, $string)
    {
        $GLOBALS['_TIP_ARRAY'] = false;
        $callback = create_function(
            '$v, $k',
            'list($k, $v) = @explode(\'' . $pair_separator . '\', $v, 2);
             $GLOBALS[\'_TIP_ARRAY\'][$k] = $v;');
        $items = explode($item_separator, $string);
        array_walk($items, $callback);
        $result =& $GLOBALS['_TIP_ARRAY'];
        unset($GLOBALS['_TIP_ARRAY']);
        return $result;
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
    function getGet($id, $type)
    {
        return TIP::_getTyped($id, $type, $_GET);
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
    function getPost($id, $type)
    {
        return TIP::_getTyped($id, $type, $_POST);
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
        return TIP::_getTyped($id, $type, $_COOKIE);
    }

    /**
     * Get the timestamp from a special date
     *
     * Parses $date, specified in $format format, and return the timestamp.
     * The currently supported formats are:
     *
     * - 'iso8601' for ISO8601 date or datetime (the format used, for instance, by MySql)
     *
     */
    function getTimestamp($date, $format)
    {
        switch ($format) {
        case 'iso8601':
            @list($year, $month, $day, $hour, $min, $sec) = sscanf($date, '%d-%d-%d %d:%d:%d');
            return mktime($hour, $min, $sec, $month, $day, $year);
        }

        TIP::logWarning("Input time format not recognized ($format)");
        return false;
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
        if (is_null($input)) {
            $timestamp = time();
        } elseif ($input_format == 'timestamp') {
            $timestamp = $input;
        } else {
            $timestamp = TIP::getTimestamp($input, $input_format);
        }
        return is_null($timestamp) ? null : TIP::_formatTimestamp($timestamp, $format);
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
    function logWarning($message)
    {
        TIP::_logGeneric('WARNING', $message);
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
    function logError($message)
    {
        TIP::_logGeneric('ERROR', $message);
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
        TIP::_logGeneric('FATAL', $message);
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
     * Build a data path
     *
     * Shortcut for building a path prepending the application 'data_root'.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildDataPath()
    {
        static $data_path = null;
        if (is_null($data_path)) {
            $data_root = TIP::getOption('application', 'data_root', true);
            $data_path = TIP::buildPath($data_root);
        }

        return TIP::deepImplode(array($data_path, func_get_args()), DIRECTORY_SEPARATOR);
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

    /**
     * Get the Text_Wiki instance
     *
     * Singleton to get the Text_Wiki instance properly configured for the
     * TIP system. You can specify an array of rules to use in the $rules
     * array, or leave it undefined to use all the available rules.
     *
     * @param array|null $rules The array of rules to enable
     * @return Text_Wiki The requested instance
     */
    function& getWiki($rules = null)
    {
        static $wiki = null;
        static $all_rules = null;

        if (is_null($wiki)) {
            require_once 'Text/Wiki.php';
            $wiki =& Text_Wiki::singleton('Default', array('Prefilter', 'Heading', 'Toc', 'Horiz', 'Blockquote', 'List', 'Deflist', 'Table', 'Center', 'Paragraph', 'Url', 'Strong', 'Emphasis', 'Revise', 'Tighten'));
            $all_rules = array('Heading', 'Toc', 'Horiz', 'Blockquote', 'List', 'Deflist', 'Table', 'Center', 'Url', 'Strong', 'Emphasis', 'Revise');
            $wiki->setFormatConf('Xhtml', 'charset', 'UTF-8');
        }

        $wiki->disable = is_array($rules) ? array_diff($all_rules, $rules) : array();
        return $wiki;
    }

    /**
     * Add a new value to the session
     *
     * Associates $value to $id and adds this pairs to the current session.
     * The session, if does not exist, it is created on-the-fly.
     *
     * @param string $id    The id of the pair
     * @param mixed  $value The value to store
     */
    function setSession($id, $value)
    {
        TIP::_startSession();
        HTTP_Session::set($id, $value);
    }

    /**
     * Get a value from the session
     *
     * Gets the previously stored value of the pair identified by $id.
     * If the session is expired, it is destroyed and regenerated and
     * null is returned.
     *
     * @param string $id The id of the pair
     * @param mixed|null The requested value or null on errors
     */
    function getSession($id)
    {
        TIP::_startSession();
        return HTTP_Session::get($id);
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
