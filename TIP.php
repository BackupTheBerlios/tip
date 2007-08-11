<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP definition file
 *
 * The TIP system require PHP v.5.2.0 or later.
 *
 * @author    Nicola Fontana <ntd@users.sourceforge.net>
 * @copyright Copyright &copy; 2006-2007 Nicola Fontana
 * @license   http://www.php.net/license/3_0.txt The PHP License, Version 3.0
 * @category  HTML
 * @package   TIP
 * @since     0.0.1
 */

/**
 * This avoid E_STRICT errors generation. It will be removed when TIP will be
 * fully PHP-5 compliant.
 *
 * Now I have too many PHP-4 dependencies that cannot be updated (PEAR overall,
 * but also Text_Wiki, HTML_QuickForm and HTML_Menu).
 */
error_reporting(E_ALL);

require_once 'Defs.php';
require_once './config.php';
require_once 'Renderer.php';

set_include_path(TIP::buildLogicPath('pear'));
require_once 'PEAR.php';
require_once 'HTTP.php';
require_once TIP::buildLogicPath('Type.php');
require_once TIP::buildLogicPath('Callback.php');


/**
 * A collection of global functions
 *
 * A static class root of all the TIP hierarchy. It provides some global useful
 * functions.
 *
 *
 * @package TIP 
 */
class TIP
{
    //{{{ Internal methods

    static private function _getTyped($id, $type, &$collection)
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

    static private function _formatTimestamp($timestamp, $format)
    {
        switch ($format) {
        case 'date_iso8601':
            return strftime('%Y-%m-%d', $timestamp);

        case 'date_sql':
            return strftime('%Y%m%d', $timestamp);

        case 'datetime_iso8601':
            return strftime('%Y-%m-%d %H:%M:%S', $timestamp);

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

    //}}}
    //{{{ Static methods

    /**
     * Get the operating system descriptor
     *
     * Checks the PHP_OS constant to get on which operating system the PHP is
     * running. If the PHP_OS constant is not defined, the function fallbacks
     * to 'unix'. The idea was picked from phpMyAdmin.
     *
     * @return 'unix'|'windows'|'os2' The guessed operating system descriptor
     */
    static public function getOS()
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
     * @param string      $type   Descriptor of the type
     * @param string      $option The option to retrieve
     * @return mixed|null         The requested option or null on errors
     */
    static public function getOption($type, $option, $required = false)
    {
        $value = @$GLOBALS['cfg'][$type][$option];
        if ($required && !isset($value)) {
            TIP::fatal("Required option not defined (\$cfg['$type']['$option'])");
        }
        return $value;
    }

    /**
     * Get the current locale id
     *
     * Gets the currently active locale id, such as 'en' or 'it'.
     *
     * @param  string      $id      The identifier
     * @param  string      $prefix  The prefix
     * @param  array       $context A context associative array
     * @param  bool        $cached  Whether to perform or not a cached read
     * @return string|null          The localized text or null if not found
     */
    static public function getLocaleId()
    {
        static $locale = null;
        if (is_null($locale)) {
            $shared_modules = TIP_Application::getGlobal('shared_modules');
            $locale = TIP::getOption($shared_modules['locale'], 'locale');
        }
        return $locale;
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
    static public function getLocale($id, $prefix, $context = null, $cached = true)
    {
        static $locale = false;
        if ($locale === false) {
            $locale =& TIP_Application::getSharedModule('locale');
        }

        return @$locale->get($id, $prefix, $context, $cached);
    }

    /**
     * Start the session
     */
    static public function startSession()
    {
        require_once 'HTTP/Session2.php';

        $user_id = TIP::getUserId();
        if ($user_id) {
            // For a logged in user, use the special TIP container
            HTTP_Session2::useCookies(false);
            HTTP_Session2::setContainer('TIP');
            HTTP_Session2::start('TIP_Session', $user_id);
        } else {
            // For anonymous users, cookie with an automatic session id is used
            HTTP_Session2::useCookies(true);
            HTTP_Session2::start('TIP_Session');
        }

        HTTP_Session2::setExpire(time() + 3600*4);
        if (HTTP_Session2::isExpired()) {
            HTTP_Session2::destroy();
            TIP::notifyInfo('session');
        }
    }

    /**
     * Extended trim function
     *
     * The same as trim(), but converts '&nbsp;' and '&#160;' to spaces before
     * trimming the string.
     *
     * @param  string $str The string to trim
     * @return             The trimmed string
     */
    static public function extendedTrim($str)
    {
        return trim(str_replace(array('&#160', '&nbsp;'), ' ', $str));
    }

    /**
     * Deep addslashes()
     *
     * Wrappes addslashes() in a deeper form, allowing to add slashes also to
     * embedded arrays.
     *
     * @param  array|string $value Array or string to add slashes
     * @return array|string        The slashized copy of $value
     */
    static public function deepAddSlashes($value)
    {
        return is_array($value) ? array_map(array('TIP', 'deepAddSlashes'), $value) : addslashes($value);
    }

    /**
     * Deep stripslashes()
     *
     * Wrappes stripslashes() in a deeper form, allowing to strip slashes also
     * to embedded arrays.
     *
     * @param  array|string $value Array or string to strip slashes
     * @return array|string        The unslashized copy of $value
     */
    static public function deepStripSlashes($value)
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
     * @param  array  $pieces The array to implode
     * @param  string $glue   The glue to use while imploding
     * @return string         The imploded copy of $pieces
     */
    static public function deepImplode($pieces, $glue = null)
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
     * @param  string|array $assignment The assignment (or array of assignments) to encode
     * @return string|array             The encoded copy of $assignment
     */
    static public function urlEncodeAssignment($assignment)
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
    static public function doubleExplode($item_separator, $pair_separator, $buffer)
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
     * @param  string     $id   The get identifier
     * @param  string     $type The expected type
     * @return mixed|null       The content of the requested get or null on errors
     * @see                     getPost(),getCookie()
     */
    static public function getGet($id, $type)
    {
        return TIP::_getTyped($id, $type, $_GET);
    }

    /**
     * Get a $_POST in a typesafe manner
     *
     * Performs the same job as getGet(), but using the superglobal $_POST
     * array.
     *
     * @param  string     $id   The post identifier
     * @param  string     $type The expected type
     * @return mixed|null       The content of the requested post or null on errors
     * @see                     getGet(),getCookie()
     */
    static public function getPost($id, $type)
    {
        return TIP::_getTyped($id, $type, $_POST);
    }

    /**
     * Get a $_COOKIE in a typesafe manner
     *
     * Performs the same job as getGet(), but using the superglobal $_COOKIE
     * array.
     *
     * @param  string     $id   The cookie identifier
     * @param  string     $type The expected type
     * @return mixed|null       The content of the requested cookie or null on errors
     * @see                     getGet(),getPost()
     */
    static public function getCookie($id, $type)
    {
        return TIP::_getTyped($id, $type, $_COOKIE);
    }

    /**
     * Get the timestamp from a special date
     *
     * Parses $date, specified in $format format, and return the timestamp.
     * The currently supported formats are:
     * - 'iso8601' for ISO8601 date or datetime (the format used, for instance, by MySql)
     *
     * @param  mixed    $date   The input date
     * @param  string   $format A supported date format
     * @return int|null         $date converted in timestamp or null on errors
     */
    static public function getTimestamp($date, $format)
    {
        switch ($format) {

        case 'iso8601':
            @list($year, $month, $day, $hour, $min, $sec) = sscanf($date, '%d-%d-%d %d:%d:%d');
            return mktime($hour, $min, $sec, $month, $day, $year);
        }

        TIP::warning("Input time format not recognized ($format)");
        return null;
    }

    /**
     * Date/time formatter
     *
     * Converts a date, specified in $input_format format, in the $format
     * format and returns the result. If $input is not defined, the current
     * time is used as input.
     *
     * The $format parameter can be one of the following values:
     * - 'date_iso8601' for a string with a day description in ISO 8601 format
     * - 'date_sql' for a string with a day description as '%Y%m%d'
     * - 'datetime_iso8601' for a string with day and hour description in ISO 8601 format
     * - 'date_it' for a string with a day description (italian locale)
     * - 'datetime_it' for a string with day and hour description (italian locale)
     *
     * The $input_format parameter can be one of the following values:
     * - 'timestamp' for UNIX timestamps
     * - 'iso8601' for ISO8601 date (the format used, for instance, by MySql)
     *
     * @param  string     $format       The format of the resulting date
     * @param  mixed      $input        The source date to format
     * @param  string     $input_format The format of the source date
     * @return mixed|null               The formatted date or null on errors
     */
    static public function formatDate($format, $input = null, $input_format = 'timestamp')
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
    static public function log($severity, $message, &$backtrace)
    {
        $logger =& TIP_Application::getSharedModule('logger');
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
    static public function warning($message)
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
    static public function error($message)
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
    static public function fatal($message)
    {
        $backtrace = debug_backtrace();
        TIP::log('FATAL', $message, $backtrace);
        $fatal_uri = HTTP::absoluteURI(TIP_Application::getGlobal('fatal_url'));
        if ($fatal_uri == @$_SERVER['REQUEST_URI']) {
            // This is a recursive redirection
            HTTP::redirect('/fatal.html');
        } else {
            // This is the first redirection
            HTTP::redirect($fatal_uri);
        }
        exit;
    }

    /**
     * Error notification to the user
     * @param  string $id The notification id
     * @return bool       true on success or false on errors
     */
    static public function notifyError($id = 'fallback')
    {
        return TIP_Application::notify(TIP_SEVERITY_ERROR, $id);
    }

    /**
     * Warning notification to the user
     * @param  string $id The notification id
     * @return bool       true on success or false on errors
     */
    static public function notifyWarning($id = 'fallback')
    {
        return TIP_Application::notify(TIP_SEVERITY_WARNING, $id);
    }

    /**
     * Notification to the user
     * @param  string $id The notification id
     * @return bool       true on success or false on errors
     */
    static public function notifyInfo($id = 'fallback')
    {
        return TIP_Application::notify(TIP_SEVERITY_INFO, $id);
    }

    /**
     * Build a path
     *
     * Constructs a path from the argument list and prepending the application
     * base path.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildPath()
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
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildLogicPath()
    {
        return TIP::buildPath(array(TIP_ROOT, func_get_args()));
    }

    /**
     * Build a source path
     *
     * Shortcut for building a path prepending the application 'source_root'.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildSourcePath()
    {
        static $source_path = null;
        if (!$source_path) {
            $source_path = TIP::buildPath(TIP_Application::getGlobal('source_root'));
        }

        return TIP::deepImplode(array($source_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a source fallback path
     *
     * Shortcut for building a path prepending the application 'source_fallback'.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildFallbackPath()
    {
        static $fallback_path = null;
        if (!$fallback_path) {
            $fallback_path = TIP::buildPath(TIP_Application::getGlobal('source_fallback'));
        }

        return TIP::deepImplode(array($fallback_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build an upload path
     *
     * Shortcut for building a path prepending the application 'upload_root'.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildUploadPath()
    {
        static $upload_path = null;
        if (!$upload_path) {
            $upload_path = TIP::buildPath(TIP_Application::getGlobal('upload_root'));
        }

        return TIP::deepImplode(array($upload_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a cached path
     *
     * Shortcut for building a path prepending the application 'cache_root'.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildCachePath()
    {
        static $cache_path = null;
        if (!$cache_path) {
            $cache_path = TIP::buildPath(TIP_Application::getGlobal('cache_root'));
        }

        return TIP::deepImplode(array($cache_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a URL
     *
     * Constructs a URL from the argument list and prepending the application
     * base URL.
     *
     * @param  string|array $suburl,... A list of partial URLs
     * @return string                   The constructed URL
     */
    static public function buildURL()
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
     * @param  string|array $suburl,... A list of partial URLs
     * @return string                   The constructed URL
     */
    static public function buildSourceURL()
    {
        static $source_url = null;
        if (!$source_url) {
            $source_url = TIP::buildURL(TIP_Application::getGlobal('source_root'));
        }

        return TIP::deepImplode(array($source_url, func_get_args()), '/');
    }

    /**
     * Build a source fallback URL
     *
     * Shortcut for building a URL prepending the application 'source_fallback'.
     *
     * @param  string|array $suburl,... A list of partial URLs
     * @return string                   The constructed URL
     */
    static public function buildFallbackURL()
    {
        static $fallback_url = null;
        if (!$fallback_url) {
            $fallback_url = TIP::buildURL(TIP_Application::getGlobal('source_fallback'));
        }

        return TIP::deepImplode(array($fallback_url, func_get_args()), '/');
    }

    /**
     * Build an upload URL
     *
     * Shortcut for building a URL prepending the application 'upload_root'.
     *
     * @param  string|array $suburl,... A list of partial URLs
     * @return string                   The constructed URL
     */
    static public function buildUploadURL()
    {
        static $upload_url = null;
        if (!$upload_url) {
            $upload_url = TIP::buildURL(TIP_Application::getGlobal('upload_root'));
        }

        return TIP::deepImplode(array($upload_url, func_get_args()), '/');
    }

    /**
     * Get the base URL
     *
     * Returns the absoute base URL of this application.
     *
     * @return string The base URL
     */
    static public function getBaseURL()
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
    static public function getScriptURI()
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
     * The returned string is not the raw referer, but a logic referer. This
     * means page swapping on the same action or refreshing it does not change
     * the old referer. Also, in the entry page the referer URI is set to
     * TIP::getScriptURI().
     *
     * @return string The referer URI
     */
    static public function getRefererURI()
    {
        static $referer_uri = null;
        if (is_null($referer_uri)) {
            $referer =& TIP_Application::getGlobal('_referer');
            $referer_uri = $referer['uri'];
        }
        return $referer_uri;
    }

    /**
     * Get the request URI
     *
     * @return string The request URI
     */
    static public function getRequestURI()
    {
        static $request_uri = null;
        if (is_null($request_uri)) {
            $request =& TIP_Application::getGlobal('_request');
            $request_uri = $request['uri'];
        }
        return $request_uri;
    }

    /**
     * Convert to HTML a value
     *
     * Converts the $value content in HTML safe manner, accordling to its type.
     *
     * @param  mixed  $value The value to convert
     * @return string        The converted value 
     */
    static public function toHtml($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    /**
     * Function wrapper of the 'echo' construct
     *
     * @param mixed $buffer The buffer to echo
     */
    public static function echo_wrapper($buffer)
    {
        echo $buffer;
    }


    /**
     * Strip the TIP prefix
     *
     * Removes the TIP prefix, defined in the TIP_PREFIX constant, from a
     * string.
     *
     * @param  string $id A TIP prefixed identifier
     * @return string     The identifier without the TIP prefix
     */
    static public function stripTipPrefix($id)
    {
        return substr($id, strlen(TIP_PREFIX));
    }

    /**
     * Gets the current user id
     *
     * Returns the id of the logged in user.
     *
     * @param  bool             $refresh Forces the update of the internal cache
     * @return mixed|false|null          The current user id,
     *                                   null for anonymous session or
     *                                   false if the user module does not exist
     */
    static public function getUserId($refresh = false)
    {
        static $initialized = false;
        static $user_id;

        if (!$initialized || $refresh) {
            $user =& TIP_Application::getSharedModule('user');
            $user_id = is_object($user) ? @$user->keys['CID'] : false;
            $initialized = true;
        }

        return $user_id;
    }

    /**
     * Get the privilege for the specified module
     *
     * Returns the privilege for a module and the specified user.  If $user
     * is omitted, the current user id is used. Check TIP_Privilege to see how the
     * privileges are used.
     *
     * @param  string           $module The requesting module identifier
     * @param  mixed            $user   A user id
     * @return TIP_PRIVILEGE...         The requested privilege
     */
    static public function getPrivilege($module, $user = null)
    {
        static $privilege = false;
        if ($privilege === false) {
            $privilege =& TIP_Application::getSharedModule('privilege');
        }

        if ($privilege) {
            return $privilege->getPrivilege($module, $user);
        }

        return TIP::getDefaultPrivilege($module, $user);
    }

    /**
     * Get the default fallback privilege for the specified module
     *
     * Returns the default privilege for a module and a specified user.
     *
     * @param  string           $module The requesting module identifier
     * @param  mixed            $user   A user id
     * @return TIP_PRIVILEGE...         The requested privilege
     */
    static public function getDefaultPrivilege($module, $user)
    {
        $privilege_type = $user ? 'default_privilege' : 'anonymous_privilege';
        $result = @$GLOBALS['cfg'][(string) $module][$privilege_type];
        if (is_null($result)) {
            $result = TIP_Application::getGlobal($privilege_type);
        }

        return $result;
    }

    //}}}
}
?>
