<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP definition file
 * @package TIP
 */

/**
 * This avoid E_STRICT errors generation. It will be removed when TIP will be
 * fully PHP-5 compliant.
 *
 * Now I have too many PHP-4 dependencies that cannot be updated (PEAR overall,
 * but also Text_Wiki and HTML_Menu).
 */
error_reporting(E_ALL);

require_once TIP::buildLogicPath('Type.php');
require_once TIP::buildLogicPath('Callback.php');

set_include_path(TIP::buildLogicPath('pear'));

/**#@+ Backward compatibily functions */
require_once 'PHP/Compat/Function/array_intersect_key.php';
require_once 'PHP/Compat/Function/array_combine.php';
/**#@-*/

require_once 'PEAR.php';
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
            return strftime($same_year ? '%d %B' : '%d %B %Y', $timestamp);

        case 'datetime_it':
            $date = TIP::_formatTimestamp($timestamp, 'date_it');
            $time = strftime('%H:%M', $timestamp);
            return $date . ' alle ' . $time;
        }

        TIP::warning("Output time format not recognized ($format)");
        return null;
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
     * are defined in the config file.
     *
     * @param string $type     Descriptor of the type
     * @param string $option   The option to retrieve
     * @return mixed|null The value of the requested option or null on errors
     */
    function getOption($type, $option, $required = false)
    {
        $value = @$GLOBALS['cfg'][$type][$option];
        if ($required && !isset($value)) {
            TIP::fatal("Required option not defined (\$cfg['$type']['$option'])");
        }
        return $value;
    }

    /**
     * Get a localized text
     *
     * Gets the localized text for the specified id and prefix.
     * The parameters are passed throught: this is merely a shortcut.
     *
     * See the TIP_Locale::get() method for technical details on how the text
     * is localized.
     *
     * @param  string      $id      The identifier
     * @param  string      $prefix  The prefix
     * @param  array       $context A context associative array
     * @param  bool        $cached  Whether to perform or not a cached read
     * @return string|null          The localized text or null if not found
     */
    function getLocale($id, $prefix, $context = null, $cached = true)
    {
        static $locale = false;
        if ($locale === false) {
            $locale =& $GLOBALS[TIP_MAIN]->getSharedModule('locale');
        }

        return @$locale->get($id, $prefix, $context, $cached);
    }

    /**
     * Start the session
     */
    function startSession()
    {
        require_once 'HTTP/Session.php';

        HTTP_Session::useTransSID(false);

        $user_id = TIP::getUserId();
        if ($user_id) {
            // For a logged in user, the session id is its user_id
            HTTP_Session::useCookies(false);
            HTTP_Session::start('TIP_Session', $user_id);
        } else {
            // For anonymous users, an automatic session id is used
            HTTP_Session::useCookies(true);
            HTTP_Session::start('TIP_Session');
        }

        HTTP_Session::setExpire(time() + 3600*2);
        if (HTTP_Session::isExpired()) {
            HTTP_Session::destroy();
            TIP::notifyInfo('session');
        }
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
        if (isset($glue)) {
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
     * The $buffer must have the following format:
     * <code>key1{$pair_separator}value1{$item_separator}key2{$pair_separator}value2{$item_separator}...</code>
     * If, for instance, $item_separator is ',' and $pair_separator is '=',
     * this function will properly parse the following string:
     * <code>key1=value1,key2=value2,key_n=value_n</code>
     *
     * The spaces are no stripped, so be aware to keep $buffer compact.
     *
     * @param  string     $item_separator The item separator character
     * @param  string     $pair_separator The key-value separator character
     * @param  string     $buffer         The buffer to parse
     * @return array|null                 The resulting associative array or null on errors
     */
    function doubleExplode($item_separator, $pair_separator, $buffer)
    {
        $items = explode($item_separator, $buffer);
        $result = null;
        foreach ($items as $item) {
            @list($k, $v) = explode($pair_separator, $item, 2);
            $result[$k] = $v;
        }
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

        TIP::warning("Input time format not recognized ($format)");
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
     * Log a generic message
     *
     * Calls the TIP_Logger::log() function, if present, to log a custom
     * message.
     *
     * The parameters are passed throught: this is merely a shortcut.
     *
     * @param string  $severity  The text of the log
     * @param string  $message   A custom message
     * @param array  &$backtrace The backtrace array
     */
    function log($severity, $message, &$backtrace)
    {
        $logger =& $GLOBALS[TIP_MAIN]->getSharedModule('logger');
        if (is_object($logger)) {
            $logger->log($severity, $message, $backtrace);
        }
    }

    /**
     * Application warnings
     *
     * Logs the specified warning message (for developement purpose only)
     * using the TIP_Logger instance, if present.
     *
     * The difference between warnings and errors is that errors generate a
     * notifyError() call while warnings don't.
     *
     * @param string $message A custom message
     */
    function warning($message)
    {
        $backtrace = debug_backtrace();
        TIP::log('WARNING', $message, $backtrace);
    }

    /**
     * Application errors
     *
     * Logs the specified warning message (for developement purpose only)
     * using the TIP_Logger instance, if present.
     *
     * The difference between warnings and errors is that errors generate a
     * notifyError() call while warnings don't.
     *
     * @param string $message A custom message
     */
    function error($message)
    {
        $backtrace = debug_backtrace();
        TIP::log('ERROR', $message, $backtrace);
        TIP::notifyError();
    }

    /**
     * Application fatal errors
     *
     * Log an error message and quits the application. This is done by
     * redirecting the user agent to a special page.
     *
     * @param string $message A custom message
     */
    function fatal($message)
    {
        $backtrace = debug_backtrace();
        TIP::log('FATAL', $message, $backtrace);
        $fatal_uri = HTTP::absoluteURI($GLOBALS[TIP_MAIN]->getOption('fatal_url'));
        if ($fatal_uri == @$_SERVER['REQUEST_URI']) {
            // This is a recursive redirection
            HTTP::redirect('/fatal.html');
        } else {
            // This is the first redirection
            HTTP::redirect($fatal_uri);
        }
        exit;
    }


    /** #@+
     * The parameters are passed throught: this is merely a shortcut.
     * @return bool|null The return value of the wrapped function or null if
     *                   the TIP_Notify module is not present
     */

    /**
     * Error notification to the user
     *
     * Notifies an error to the user throught TIP_Notify::notifyError().
     * Check the TIP_Notify documentation for further informations.
     */
    function notifyError()
    {
        $notify =& $GLOBALS[TIP_MAIN]->getSharedModule('notify');
        if (is_object($notify)) {
            $args = func_get_args();
            return call_user_func_array(array(&$notify, 'notifyError'), $args);
        }
        return null;
    }

    /**
     * Warning notification to the user
     *
     * Notifies a warning to the user throught TIP_Notify::notifyWarning().
     * Check the TIP_Notify documentation for further informations.
     */
    function notifyWarning()
    {
        $notify =& $GLOBALS[TIP_MAIN]->getSharedModule('notify');
        if (is_object($notify)) {
            $args = func_get_args();
            return call_user_func_array(array(&$notify, 'notifyWarning'), $args);
        }
        return null;
    }

    /**
     * Notification to the user
     *
     * Notifies a generic information to the user throught TIP_Notify::notifyInfo().
     * Check the TIP_Notify documentation for further informations.
     */
    function notifyInfo()
    {
        $notify =& $GLOBALS[TIP_MAIN]->getSharedModule('notify');
        if (is_object($notify)) {
            $args = func_get_args();
            return call_user_func_array(array(&$notify, 'notifyInfo'), $args);
        }
        return null;
    }

    /**#@-*/


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
        if (!$base_path) {
            ($script = @$_SERVER['SCRIPT_FILENAME']) || ($script = __FILE__);
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
        return TIP::buildPath(array(TIP_ROOT, func_get_args()));
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
        if (!$source_path) {
            $source_path = TIP::buildPath($GLOBALS[TIP_MAIN]->getOption('source_root'));
        }

        return TIP::deepImplode(array($source_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a source fallback path
     *
     * Shortcut for building a path prepending the application 'source_fallback'.
     *
     * @param string|array $subpath,... A list of partial paths
     * @return string The constructed path
     */
    function buildFallbackPath()
    {
        static $fallback_path = null;
        if (!$fallback_path) {
            $fallback_path = TIP::buildPath($GLOBALS[TIP_MAIN]->getOption('source_fallback'));
        }

        return TIP::deepImplode(array($fallback_path, func_get_args()), DIRECTORY_SEPARATOR);
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
        if (!$data_path) {
            $data_path = TIP::buildPath($GLOBALS[TIP_MAIN]->getOption('data_root'));
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
    function buildURL()
    {
        static $root_url = null;
        if (is_null($root_url)) {
            $script_uri = TIP::getScriptURI();
            $root_url = substr($script_uri, 0, strrpos($script_uri, '/'));
        }

        return TIP::deepImplode(array($root_url, func_get_args()), '/');
    }

    /**
     * Build a source URL
     *
     * Shortcut for building a URL prepending the application 'source_root'.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     */
    function buildSourceURL()
    {
        static $source_url = null;
        if (!$source_url) {
            $source_url = TIP::buildURL($GLOBALS[TIP_MAIN]->getOption('source_root'));
        }

        return TIP::deepImplode(array($source_url, func_get_args()), '/');
    }

    /**
     * Build a source fallback URL
     *
     * Shortcut for building a URL prepending the application 'source_fallback'.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     */
    function buildFallbackURL()
    {
        static $fallback_url = null;
        if (!$fallback_url) {
            $fallback_url = TIP::buildURL($GLOBALS[TIP_MAIN]->getOption('source_fallback'));
        }

        return TIP::deepImplode(array($fallback_url, func_get_args()), '/');
    }

    /**
     * Build a data URL
     *
     * Shortcut for building a URL prepending the application 'data_root'.
     *
     * @param string|array $suburl,... A list of partial URLs
     * @return string The constructed URL
     */
    function buildDataURL()
    {
        static $data_url = null;
        if (!$data_url) {
            $data_url = TIP::buildURL($GLOBALS[TIP_MAIN]->getOption('data_root'));
        }

        return TIP::deepImplode(array($data_url, func_get_args()), '/');
    }

    /**
     * Get the base URL
     *
     * Returns the absoute base URL of this application.
     *
     * @return string The base URL
     */
    function getBaseURL()
    {
        static $base_url = null;
        if (!$base_url) {
            $base_url = HTTP::absoluteURI(TIP::buildURL());
        }
        return $base_url;
    }

    /**
     * Get the URI of the current script
     *
     * @return string The requested URI
     */
    function getScriptURI()
    {
        static $script = null;
        if (!$script) {
            ($script = @$_SERVER['SCRIPT_NAME']) || ($script = @$_SERVER['PHP_SELF']);
        }
        return $script;
    }

    /**
     * Get the referer URI
     *
     * If a double click on the same page is catched, the old referer is retained.
     *
     * @return string The referer URI
     */
    function getRefererURI()
    {
        static $referer = null;
        if (is_null($referer)) {
            TIP::startSession();
            $old_request_uri = HTTP_Session::get('request_uri');
            $request_uri = TIP::getRequestURI();

            if ($request_uri == $old_request_uri) {
                // Page not changed: leave the old referer
                $referer = HTTP_Session::get('referer');
            } else {
                // Page changed: save the new state
                $referer = $old_request_uri ? $old_request_uri : @$_SERVER['HTTP_REFERER'];
                HTTP_Session::set('referer', $referer);
                HTTP_Session::set('request_uri', $request_uri);
            }

            if (empty($referer)) {
                $referer = TIP::getScriptURI();
            }
        }

        return $referer;
    }

    /**
     * Get the request URI
     *
     * @return string The request URI
     */
    function getRequestURI()
    {
        return @$_SERVER['REQUEST_URI'];
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

        if (!$initialized || $refresh) {
            $user =& $GLOBALS[TIP_MAIN]->getSharedModule('user');
            $user_id = is_object($user) ? @$user->keys['CID'] : false;
            $initialized = true;
        }

        return $user_id;
    }

    /**
     * Get the privilege for the specified module
     *
     * Returns the privilege for a module and the specified user.  If $user_id
     * is omitted, the current user id is used. Check TIP_Privilege to see how the
     * privileges are used.
     *
     * @param  string           $module_id The requesting module identifier
     * @param  mixed            $user_id   A user id
     * @return TIP_PRIVILEGE...            The requested privilege
     */
    function getPrivilege($module_id, $user_id = null)
    {
        static $privilege = false;
        if ($privilege === false) {
            $privilege =& $GLOBALS[TIP_MAIN]->getSharedModule('privilege');
        }

        if ($privilege) {
            return $privilege->getPrivilege($module_id, $user_id);
        }

        return TIP::getDefaultPrivilege($module_id, $user_id);
    }

    /**
     * Get the default fallback privilege for the specified module
     *
     * Returns the default privilege for a module and a specified user.
     *
     * @param  string           $module_id The requesting module identifier
     * @param  mixed            $user_id   A user id
     * @return TIP_PRIVILEGE...            The requested privilege
     */
    function getDefaultPrivilege($module_id, $user_id)
    {
        $privilege_type = $user_id ? 'default_privilege' : 'anonymous_privilege';
        $result = TIP::getOption($module_id, $privilege_type);
        if (is_null($result)) {
            $result = $GLOBALS[TIP_MAIN]->getOption('privilege_type');
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
     * @param  array|null $enabled The array of rules to enable
     * @return Text_Wiki           The requested instance
     */
    function& getWiki($enabled = null)
    {
        static $rules = null;
        static $forced_rules = null;

        if (is_null($rules)) {
            // All the rules, in order, made available for the TIP system.
            // The case is important!
            $rules = array(
                'Prefilter', 'Heading', 'Toc', 'Horiz', 'Break', 'Blockquote', 
                'List', 'Deflist', 'Table', 'Center', 'Paragraph', 'Url',
                'Strong', 'Emphasis', 'Revise', 'Tighten'
            );
            // Rules always included
            $forced_rules = array('Prefilter', 'Break', 'Paragraph', 'Tighten');
        }

        require_once 'Text/Wiki.php';
        if (is_array($enabled)) {
            // Capitalize the $enabled values
            $enabled = array_map('ucfirst', array_map('strtolower', $enabled));
            // Join the forced rules
            $enabled = array_merge($enabled, $forced_rules);
            // Get the real rules to apply
            $real_rules = array_intersect($rules, $enabled);
        } else {
            //$real_rules =& $rules;
            $real_rules = $forced_rules;
        }

        $wiki =& Text_Wiki::singleton('Default', $real_rules);
        $wiki->setFormatConf('Xhtml', 'charset', 'UTF-8');
        return $wiki;
    }

    /**#@-*/
}
?>
