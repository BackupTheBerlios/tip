<?php

/**
 * A collection of global functions.
 *
 * Base class for all the tip hierarchy. It provides some global useful
 * functions.
 **/
class tip
{
  /// @privatesection

  function GetTypedItem ($Id, $Type, &$Collection)
  {
    if (! array_key_exists ($Id, $Collection))
      return NULL;

    $Value =& $Collection[$Id];
    if (! settype ($Value, $Type))
      return NULL;

    if (is_string ($Value) || is_array ($Value))
      return get_magic_quotes_gpc () ? Unslashize ($Value) : $Value;

    return $Value;
  }

  function GetTimestamp ($Data, $Format)
  {
    switch ($Format)
      {
      case 'timestamp':
	return $Data;

      case 'now':
	return time ();

      case 'iso8601':
	list ($Year, $Month, $Day, $Hour, $Min, $Sec) = sscanf ($Data, '%d-%d-%d %d:%d:%d');
	return mktime ($Hour, $Min, $Sec, $Month, $Day, $Year);
      }

    tip::LogWarning ("Input time format `$Format' not recognized");
    return FALSE;
  }

  function FormatTimestamp ($Timestamp, $Format)
  {
    switch ($Format)
      {
      case 'date_iso8601':
	return strftime ('%F', $Timestamp);

      case 'datetime_iso8601':
	return strftime ('%F %H:%M:%S', $Timestamp);

      case 'date_it':
	$SameYear = date ('Y', $Timestamp) == date ('Y');
	$SameDay = date ('z', $Timestamp) == date ('z');

	if ($SameYear && $SameDay)
	  return 'oggi';

	$Result = strftime ('%d %B', $Timestamp);
	if (! $SameYear)
	  $Result .= strftime (' %Y', $Timestamp);

	return $Result;

      case 'datetime_it':
	$Result = tip::FormatTimestamp ($Timestamp, 'date_it');
	if (! $Result)
	  return FALSE;

	$Result .= strftime (' alle %H:%M', $Timestamp);
	return $Result;
      }

    tip::LogWarning ("Output time format `$Format' not recognized");
    return FALSE;
  }

  function LogGeneric ($Domain, $Message, $Notify = FALSE)
  {
    $Logger =& tipType::GetInstance ('logger', FALSE);

    // No tipLogger module present: logging disabled
    if (! is_object ($Logger))
      return;

    $Uri = @$_SERVER['REQUEST_URI'];
    $Logger->LogMessage ($Domain, $Message, $Uri, $Notify);
  }

  function Quit ($Message)
  {
    exit ("<h3>Errore fatale: $Message</h3><p>Si prega di comunicare il problema all'amministratore del sito inviando una email all'indirizzo <a href=\"mailto:webmaster@bresciapoint.it\">webmaster@bresciapoint.it</a>.</p><p>Grazie per la collaborazione.</p>");
  }


  /// @publicsection

  /**
   * Get the operating system descriptor.
   *
   * Checks the \c PHP_OS constant to get on which operating system the PHP is
   * running. If the \c PHP_OS constant is not defined, the function fallbacks
   * to 'unix'. The idea was picked from phpMyAdmin.
   *
   * @retval 'unix' Any unix or derived system (fallback choice)
   * @retval 'windows' Microsoft Windows (any version)
   * @retval 'os2' Ibm OS/2
   **/
  function GetOS()
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
   * Gets an option for the given type.
   * @param[in] Type   \c string Descriptor of the type.
   * @param[in] Option \c string The option to retrieve.
   *
   * Gets the option \p Option of the \p Type type. All the option values
   * must be defined in the config.php file.
   *
   * @return The value of the requested option, or \c FALSE on errors.
   **/
  function GetOption ($Type, $Option)
  {
    global $CFG;

    if (! is_array ($CFG))
      include_once 'config.php';

    if (! array_key_exists ($Type, $CFG))
      tip::LogFatal ("type `$Type' not configured");

    if (! array_key_exists ($Option, $CFG[$Type]))
      tip::LogFatal ("required option ['$Type']['$Option'] not specified");

    return $CFG[$Type][$Option];
  }

  /**
   * Deep addslashes().
   * @param[in] Value \c mixed Array or string to slashize.
   *
   * Wrappers addslashes() in a deeper form, allowing to slashize also
   * embedded arrays.
   *
   * @return A slashized copy of \p Value.
   **/
  function Slashize ($Value)
  {
    return is_array ($Value) ?  array_map ('tip::Slashize', $Value) : addslashes ($Value);
  }

  /**
   * Deep stripslashes().
   * @param[in] Value \c mixed Array or string to unslashize.
   *
   * Wrappers stripslashes() in a deeper form, allowing to unslashize also
   * embedded arrays.
   *
   * @return An unslashized copy of \p Value.
   **/
  function Unslashize ($Value)
  {
    return is_array ($Value) ?  array_map ('tip::Unslashize', $Value) : stripslashes ($Value);
  }

  /**
   * Checks if an item is specified in a set.
   * @param[in] Item \c string The item to find
   * @param[in] Set  \c string A comma delimited list of items
   *
   * Scans \p Set to check the presence of \p Item.
   *
   * @return \c TRUE if the item is inside the set, \c FALSE otherwise.
   **/
  function ItemExists ($Item, $Set)
  {
    $Token = strtok ($Set, ' ,');
    while ($Token !== FALSE)
      {
	if ($Token == $Item)
	  return TRUE;
	$Token = strtok (' ,');
      }

    return FALSE;
  }


  /**
   * Gets a $_GET field in a typesafe manner.
   * @param[in] Id   \c string GET identifier.
   * @param[in] Type \c string Type expected.
   *
   * Gets a value from the superglobal $_GET array, forcing the result to be
   * of \p Type type. Also, if the current PHP installation does not have the
   * "magic quote" feature turned on, the result is unslashized throught
   * Unslashize() to provide a consistent method on different PHP installations.
   *
   * \p Type can be any value accepted by settype(), that is:
   *
   * \li \c 'bool'   to force a boolean value
   * \li \c 'int'    to force an integer number
   * \li \c 'float'  to force a floating point number
   * \li \c 'string' to force a string
   * \li \c 'array'  to force an array
   * \li \c 'object' to force an object
   *
   * @return A copy of the requested get, or \c NULL if \c $_GET[$Id]
   *         does not exist.
   *
   * @see tip::GetPost(), tip::GetCookie()
   **/
  function GetGet ($Id, $Type)
  {
    return tip::GetTypedItem ($Id, $Type, $_GET);
  }

  /**
   * Gets a $_POST field in a typesafe manner.
   * @param[in] Id   \c string POST identifier.
   * @param[in] Type \c string Type expected.
   *
   * Performs the same job as tip::GetGet(), but using the superglobal $_POST
   * array.
   *
   * @return A copy of the requested post, or \c NULL if \c $_POST[$Id]
   *         does not exist.
   *
   * @see tip::GetGet(), tip::GetCookie()
   **/
  function GetPost ($Id, $Type)
  {
    return tip::GetTypedItem ($Id, $Type, $_POST);
  }

  /**
   * Gets a $_COOKIE field in a typesafe manner.
   * @param[in] Id   \c string COOKIE identifier.
   * @param[in] Type \c string Type expected.
   *
   * Performs the same job as tip::GetGet(), but using the superglobal $_COOKIE
   * array.
   *
   * @return A copy of the requested post, or \c NULL if \c $_COOKIE[$Id]
   *         does not exist.
   *
   * @see tip::GetGet(), tip::GetPost()
   **/
  function GetCookie ($Id, $Type)
  {
    return tip::GetTypedItem ($Id, $Type, $_COOKIE);
  }


  /**
   * Date/time formatter.
   * @param[in] Input        \c mixed  The source date to format.
   * @param[in] InputFormat  \c string The format of the source date.
   * @param[in] OutputFormat \c string The format of the resulting date.
   *
   * Converts the \p Input date, specified in \p InputFormat format, in the
   * \p OutputFormat format and returns the result.
   *
   * The \p InputFormat parameter can be one of the following values:
   *
   * \li \c 'timestamp' for UNIX timestamps.
   * \li \c 'now' for the actual time (the \p Input parameter will be ignored).
   * \li \c 'iso8601' for ISO8601 date (the format used, for instance, by MySql).
   *
   * The \p OutputFormat parameter can be one of the following values:
   *
   * \li <b>date_iso8601</b>\n
   *        Returns a string with a day description in ISO 8601 format.
   * \li <b>datetime_iso8601</b>\n
   *        Returns a string with day and hour description in ISO 8601 format.
   * \li <b>date_it</b>\n
   *        Returns a string with a day description (italian format).
   * \li <b>datetime_it</b>\n
   *        Returns a string with day and hour description (italian format).
   *
   * @return The formatted date, or \c FALSE on errors.
   **/
  function FormatDate ($Input, $InputFormat, $OutputFormat)
  {
    $Timestamp = tip::GetTimestamp ($Input, $InputFormat);
    if (! $Timestamp)
      return FALSE;

    return tip::FormatTimestamp ($Timestamp, $OutputFormat);
  }

  /**
   * Logs a warning message.
   * @param[in] Message \c string The warning message to log.
   *
   * Logs the specified warning message using the default logger mechanism.
   * The difference between warnings and errors is developer-dependent: TIP
   * does not make any assumption, apart of writing WARNING instead of ERROR.
   *
   * @see tip::LogError(), tip::LogFatal()
   **/
  function LogWarning ($Message)
  {
    tip::LogGeneric ('WARNING', $Message);
  }

  /**
   * Logs an error message.
   * @param[in] Message \c string The error message to log.
   *
   * Logs the specified error message using the default logger mechanism.
   * The difference between warnings and errors is developer-dependent: TIP
   * does not make any assumption, apart of writing ERROR instead of WARNING.
   *
   * @see tip::LogWarning(), tip::LogFatal()
   **/
  function LogError ($Message)
  {
    tip::LogGeneric ('ERROR', $Message);
  }

  /**
   * Logs an error message and quits the application.
   * @param[in] Message \c string The error message to log.
   *
   * Logs the specified error message using the default logger mechanism and
   * quits the application, trying to output the error in an HTML fashion with
   * some useful informations such as the webmaster email address.
   *
   * @todo The HTML output does not work well because it is generated in an
   *       unknown HTML context. Furthermore, I want to avoid output buffers
   *       on the whole context. So, what is the solution?
   *
   * @see tip::LogWarning(), tip::LogError()
   **/
  function LogFatal ($Message)
  {
    tip::LogGeneric ('FATAL', $Message);
    tip::Quit ($Message);
  }
}


/**
 * A generic callback.
 *
 * Provides some common stuff in callback management, such as a default return
 * value for undefined callbacks.
 **/
class tipCallback extends tip
{
  /// @privatesection

  var $CALLBACK;
  var $PARAMS;
  var $IS_DEFAULT;

  function DefaultCallback ()
  {
    return $this->RESULT;
  }


  /// @publicsection

  var $RESULT;


  /**
   * Callback constructor
   * @param[in] DefaultResult \c mixed  The default return value
   *
   * Initializes the callback class. The parameter \p Default is used as return
   * value of the Go() method when the callback is not callable.
   **/
  function tipCallback ($DefaultResult = TRUE)
  {
    $this->CALLBACK = array (&$this, 'DefaultCallback');
    $this->PARAMS = array ();
    $this->IS_DEFAULT = TRUE;
    $this->RESULT = $DefaultResult;
  }

  /**
   * Set a new callback
   * @param[in] Callback \c callback  The new callback
   * @param[in] Params   \c array     The params to pass to the callback
   *
   * Sets a new callback. If \p Params is omitted, no parameters will be passed
   * to \p Callback.
   **/
  function Set ($Callback, $Params = FALSE)
  {
    $this->CALLBACK = $Callback;
    if (is_array ($Params))
      $this->PARAMS = $Params;
    $this->IS_DEFAULT = FALSE;
  }

  /**
   * Callback call
   * @param[in] Params \c array  Parameters to pass to the callback
   *
   * Performs the callback call. If the callback was never set throught Set(),
   * a default callback is called; this callback simply returns the default
   * return value specified when constructing the callback.
   *
   * If \p Params is not specified, the parameters specified by Set() will be
   * used while calling the callback function.
   *
   * @return The callback return value
   **/
  function Go ($Params = FALSE)
  {
    if (! is_array ($Params))
      $Params =& $this->PARAMS;
    $this->RESULT = call_user_func_array ($this->CALLBACK, $Params);
    return $this->RESULT;
  }
}

?>
