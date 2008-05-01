<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * Main definition file
 *
 * The TiP framework requires PHP v.5.2.0 or later.
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
 * This avoid E_STRICT errors generation. It will be removed when TIP will be
 * fully PHP-5 compliant.
 *
 * Now I have too many PHP-4 dependencies that cannot be updated (PEAR overall,
 * but also Text_Wiki, HTML_QuickForm and HTML_Menu).
 */
error_reporting(E_ALL);

/**
 * The default encoding for TIP based sites is utf8.
 */
mb_internal_encoding('UTF-8');

/**
 * Defs.php must precede anything because of TIP_ROOT
 */
require_once 'Defs.php';
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

        case '':
            return $timestamp;

        case 'date':
            // Custom locale manipulations
            switch (TIP::getLocaleId()) {

            case 'it_IT':
                $same_year = date('Y', $timestamp) == date('Y');
                $same_day = date('z', $timestamp) == date('z');
                if ($same_year && $same_day) {
                    return 'oggi';
                }
                return strftime($same_year ? '%d %B' : '%d %B %Y', $timestamp);
            }

            return strftime('%x', $timestamp);

        case 'datetime':
            // Custom locale manipulations
            switch (TIP::getLocaleId()) {

            case 'it_IT':
                $date = TIP::_formatTimestamp($timestamp, 'date');
                return $date . ' alle ' . strftime('%H:%M', $timestamp);
            }

            return strftime('%c', $timestamp);

        case 'date_sql':
        case 'date_iso8601':
            return strftime('%Y-%m-%d', $timestamp);

        case 'datetime_sql':
            return strftime('%Y-%m-%d %H:%M:%S', $timestamp);

        case 'datetime_iso8601':
            return date(DATE_ISO8601, $timestamp);

        case 'datetime_rfc3339':
            return date(DATE_RFC3339, $timestamp);
        }

        return strftime($format, $timestamp);
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
     * Convenience function to set a default array value
     *
     * @param  array &$array An array
     * @param  string $key   The item key on $array
     * @param  mixed  $value The value to use if $array[$key] is not set
     */
    static public function arrayDefault(&$array, $key, $value)
    {
        if (!is_array($array) || !array_key_exists($key, $array)) {
            $array[$key] = $value;
        }
    }

    /**
     * Get an option
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
     * Define a required option
     *
     * If $option is not defined in the $options array, try to guess the
     * default value by inspecting the configuration options of the first
     * 'application' module found in the global $cfg array.
     *
     * If yet not found, the $fallback value is used.
     *
     * @param  array  &$options  Array of options
     * @param  string  $option   The option to set
     * @param  mixed   $fallback The fallback value
     */
    static public function requiredOption(&$options, $option, $fallback = null)
    {
        if (isset($options[$option])) {
            return;
        } elseif (isset($GLOBALS[TIP_MAIN])) {
            $options[$option] =& TIP_Application::getGlobal($option);
        } else {
            static $main_cfg = null;

            if (is_null($main_cfg)) {
                // Search for the first application module
                global $cfg;
                $main_cfg = false;
                foreach (array_keys($cfg) as $id) {
                    if (end($cfg[$id]['type']) == 'application') {
                        $main_cfg = $cfg[$id];
                        break;
                    }
                }
            }

            if ($main_cfg && isset($main_cfg[$option])) {
                $options[$option] = $main_cfg[$option];
            } else {
                $options[$option] = $fallback;
            }
        }
    }

    /**
     * Set locale in a platform-independent way
     *
     * @param  string   $locale The locale name (such as 'en_US' or 'it_IT')
     * @return boolean          true on success or false if not possible
     * @throw  exception        If the locale could not be set
     *                          or the encoding will is not UTF-8
     */
    function setLocaleId($locale)
    {
        if (@strpos($locale, '_') === false) {
            TIP::warning ("Not a valid locale id ($locale)");
            return false;
        }

        list($language, $country) = explode('_', $locale);

        $result = setlocale(LC_ALL, $locale . '.UTF-8', $language, $locale);
        if(!$result) {
            throw new exception("Unknown locale name ($locale)");
        }

        // See if we have successfully set it to UTF-8
        if(!strpos($result, 'UTF-8')) {
            throw new exception('Unable to force UTF-8 encoding on this system');
        }

        return true;
    }

    /**
     * Get the current locale, such as 'en_US' or 'it_IT'
     * @return string The current locale
     */
    static public function getLocaleId()
    {
        static $locale = null;
        if (is_null($locale)) {
            $shared_modules = TIP_Application::getGlobal('shared_modules');
            $locale = TIP::getOption($shared_modules['locale'], 'locale');
        }

        // Fallback to en_US, so ensure the locale is set anyway
        return isset($locale) ? $locale : 'en_US';
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
        if (!$locale instanceof TIP_Type) {
            return null;
        }
        return $locale->get($id, $prefix, $context, $cached);
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
     * - 'sql' for SQL date or datetime (YYYY-MM-DD hh:mm:ss)
     *
     * @param  mixed    $date   The input date
     * @param  string   $format A supported date format
     * @return int|null         $date converted in timestamp or null on errors
     */
    static public function getTimestamp($date, $format)
    {
        switch ($format) {

        case 'sql':
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
     * - 'date' for a string with a date description (current locale)
     * - 'date_sql' for date in common SQL format ('%Y-%m-%d')
     * - 'date_iso8601' same as 'date_sql'
     * - 'datetime' for a string with date and time description (current locale)
     * - 'datetime_sql' for a string with a time description as '%Y-%m-%d %H:%M:%S'
     * - 'datetime_iso8601' for a string with time description in ISO 8601 format
     * - 'datetime_rfc3339' for a string as described in RFC3339 (atom format)
     *
     * Any other value will be passed directly to strftime().
     *
     * The $input_format parameter can be one of the following values:
     * - 'timestamp' for UNIX timestamps
     * - 'sql' for common SQL date/datetime
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
        return empty($timestamp) ? null : TIP::_formatTimestamp($timestamp, $format);
    }

    /**
     * Log a generic message
     *
     * Calls the TIP_Logger::log() function, if present, to log a custom
     * message.
     *
     * The parameters are passed throught: this is merely a shortcut.
     *
     * @param string $severity  The text of the log
     * @param string $message   A custom message
     */
    static public function log($severity, $message)
    {
        static $logger = false;
        if ($logger === false && isset($GLOBALS[TIP_MAIN])) {
            $logger =& TIP_Application::getSharedModule('logger');
        }

        is_object($logger) && $logger->log($severity, $message);
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
        TIP::log('WARNING', $message);
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
        TIP::log('ERROR', $message);
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
        debug_print_backtrace();
        flush();
        TIP::log('FATAL', $message);
        $fatal_uri = HTTP::absoluteURI(TIP_Application::getGlobal('fatal_uri'));
        if ($fatal_uri == $_SERVER['REQUEST_URI']) {
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
     * Recursively remove a directory
     * @param string $dir The directory to delete
     */
    static public function removeDir($dir, $self_remove = true)
    {
        $handle = @opendir($dir);
        if (!$handle) {
            return;
        }

        while (($file = readdir($handle)) !== false) {
            if ($file != '.' && $file != '..') {
                $file = $dir . DIRECTORY_SEPARATOR . $file;
                @unlink($file) || TIP::removeDir($file);
            }
        }

        closedir($handle);
        $self_remove && @rmdir($dir);
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
        if (is_null($base_path)) {
            // All the paths are relative to the running script
            $script = $_SERVER['SCRIPT_FILENAME'];
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
        static $logic_path = null;
        if (is_null($logic_path)) {
            $file = dirname(__FILE__);
            $logic_path = rtrim(realpath($file), DIRECTORY_SEPARATOR);
        }
        return TIP::deepImplode(array($logic_path, func_get_args()), DIRECTORY_SEPARATOR);
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
        if (is_null($source_path)) {
            $source_path = TIP::buildPath(TIP_Application::getGlobal('source_root'));
        }
        return TIP::deepImplode(array($source_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a source fallback path
     *
     * Shortcut for building a path prepending the application 'fallback_root'.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildFallbackPath()
    {
        static $fallback_path = null;
        if (is_null($fallback_path)) {
            $fallback_path = TIP::buildPath(TIP_Application::getGlobal('fallback_root'));
        }
        return TIP::deepImplode(array($fallback_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Build a data path
     *
     * Shortcut for building a path prepending the application 'data_root'.
     *
     * @param  string|array $subpath,... A list of partial paths
     * @return string                    The constructed path
     */
    static public function buildDataPath()
    {
        static $path = null;
        if (is_null($path)) {
            $path = TIP::buildPath(TIP_Application::getGlobal('data_root'));
        }
        return TIP::deepImplode(array($path, func_get_args()), DIRECTORY_SEPARATOR);
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
        if (is_null($cache_path)) {
            $cache_path = TIP::buildPath(TIP_Application::getGlobal('cache_root'));
        }
        return TIP::deepImplode(array($cache_path, func_get_args()), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the absolute root of the URIs in this server
     * @return string The URI root
     */
    static public function getRoot()
    {
        static $uri = null;
        if (is_null($uri)) {
            $uri = 'http://' . $_SERVER['SERVER_NAME'];
        }
        return $uri;
    }

    /**
     * Get the (relative) URI to the home page
     * @return string The home URI
     */
    static public function getHome()
    {
        static $uri = null;
        if (is_null($uri)) {
            $namespace = TIP_Application::getGlobal('namespace');
            if (is_null($namespace)) {
                $uri = basename($_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_FILENAME']);
            } else {
                $uri = empty($namespace) ? '' : $namespace . '/';
            }
            $uri = TIP::buildUri($uri);
        }
        return $uri;
    }

    /**
     * Build a relative URI
     * @param  string|array $suburi,... A list of partial URIs
     * @return string                   The constructed URI
     */
    static public function buildUri()
    {
        static $uri = null;
        if (is_null($uri)) {
            // $uri is the path from the server root to this site root
            $uri = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_FILENAME'];
            $uri = dirname($uri);
            substr($uri, -1) == '/' && $uri = substr($uri, 0, -1);
        }
        return TIP::deepImplode(array($uri, func_get_args()), '/');
    }

    /**
     * Build a data (relative) URI
     * @param  string|array $suburi,... A list of partial URIs
     * @return string                   The constructed URI
     */
    static public function buildDataUri()
    {
        static $uri = null;
        if (is_null($uri)) {
            $uri = TIP::buildUri(TIP_Application::getGlobal('data_root'));
        }
        return TIP::deepImplode(array($uri, func_get_args()), '/');
    }

    /**
     * Build a source (relative) URI
     * @param  string|array $suburi,... A list of partial URIs
     * @return string                   The constructed URI
     */
    static public function buildSourceUri()
    {
        static $uri = null;
        if (is_null($uri)) {
            $uri = TIP::buildUri(TIP_Application::getGlobal('source_root'));
        }
        return TIP::deepImplode(array($uri, func_get_args()), '/');
    }

    /**
     * Build a source fallback (relative) URI
     * @param  string|array $suburi,... A list of partial URIs
     * @return string                   The constructed URI
     */
    static public function buildFallbackUri()
    {
        static $uri = null;
        if (is_null($uri)) {
            $uri = TIP::buildUri(TIP_Application::getGlobal('fallback_root'));
        }
        return TIP::deepImplode(array($uri, func_get_args()), '/');
    }

    /**
     * Build an action (relative) URI
     * @param  string $module The module name
     * @param  string $action The action to perform
     * @param  string $id     The subject of the action
     * @param  array  $args   Optional additional query arguments
     * @return string         The constructed URI
     */
    static public function buildActionUri($module, $action, $id = null, $args = null)
    {
        $uri = TIP::getHome();

        if (empty($module) || empty($action)) {
            empty($args) || $uri .= '?' . http_build_query($args, '', '&');
            return $uri;
        }

        $namespace = TIP_Application::getGlobal('namespace');

        if (is_null($namespace)) {
            $args['module'] = $module;
            $args['action'] = $action;
            empty($id) || $args['id'] = $id;
            $uri .= '?' . http_build_query($args, '', '&');
            return $uri;
        }

        // Strip the namespace from the module name, if present
        if (strpos($module, $namespace . '_') === 0) {
            $module = substr($module, strlen($namespace)+1);
        }

        $uri .= $module . '/' . $action . '/';
        empty($id) || $uri .= $id . '/';
        empty($args) || $uri .= '?' . http_build_query($args, '', '&');
        return $uri;
    }

    /**
     * Build an action (relative) URI
     * @param  string $tag            A string in the format
     *                                'action,id[,arg1=value1,...]'
     * @param  string $default_module The module to use if not specified as arg
     * @return string                 The constructed URI
     */
    static public function buildActionUriFromTag($tag, $default_module)
    {
        @list($action, $id, $list) = explode(',', $tag, 3);

        if (is_string($list)) {
            $list = explode(',', $list);
            foreach ($list as $item) {
                list($arg, $value) = explode('=', $item, 2);
                $args[$arg] = $value;
            }
        } else {
            $args = null;
        }

        if (isset($args['module'])) {
            $module = $args['module'];
            unset($args['module']);
        } else {
            $module = $default_module;
        }

        return TIP::buildActionUri($module, $action, $id, $args);
    }

    /**
     * Build an action URI by modify the current action
     *
     * In this case, anything different from null will be applied to the
     * current action. The $args array will be merged to the current one.
     *
     * @param  string $module The module name
     * @param  string $action The action to perform
     * @param  string $id     The subject of the action
     * @param  array  $args   Optional additional query arguments
     * @return string         The constructed URI
     */
    static public function modifyActionUri($module, $action, $id = null, $args = null)
    {
        $gets = array();
        foreach ($_GET as $get => $value) {
            switch ($get) {
            case 'module':
                is_null($module) && $module = $value;
                break;
            case 'action':
                is_null($action) && $action = $value;
                break;
            case 'id':
                is_null($id) && $id = $value;
                break;
            default:
                $gets[$get] = $value;
            }
        }

        isset($args) && $gets = array_merge($gets, $args);
        return TIP::buildActionUri($module, $action, $id, $args);
    }

    /**
     * Get the referer URI
     *
     * The returned string is not the raw referer, but a logic referer. This
     * means page swapping on the same action or refreshing it does not change
     * the old referer.
     *
     * @return string The referer URI
     */
    static public function getRefererUri()
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
    static public function getRequestUri()
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
