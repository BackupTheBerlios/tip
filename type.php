<?php

/**
 * Base type class.
 *
 * Manages all the registrable TIP classes (types).
 * Inheriting a class from tipType gives the ability to instantiate this
 * class only when requested (usually throught a call to GetInstance()).
 * Multiple requests to GetInstance() will references the same - unique -
 * created instance.
 *
 * Also, the PHP file declaring the new type will be included only
 * when required, enabling a real modular environement.
 *
 * \section typeinstantiation How the types are instantiated?
 *
 * Suppose your application calls tip::GetInstance ('test').
 *
 * GetInstance holds a static array (called register) containing all the
 * previous instantiated types. If 'test' exists in the register, this
 * function simply returns a reference to this object. Instead, if it not
 * exists, the type must be instantiated.
 *
 * The following steps are performed:
 *
 * \li <b><tt>$Logic = tip::GetOption ('test', 'logic');</tt></b>\n
 *     The configuration array must holds the path to the PHP file declaring
 *     the test type. This means in <tt>config.php</tt> there must be a line
 *     similar to the following:\n
 *     <tt>$CFG['test']['logic'] = 'logic/sources/test.php';</tt>\n
 *     or whatever fit your needs.
 * \li <b><tt>require_once $Logic;</tt></b>\n
 *     The PHP file is included (of course only once).
 * \li <b><tt>$Class = "tip$Type";</tt></b>\n
 *     The class name is created prepending 'tip' to the type name. In this
 *     case, the type 'test' will need a class named 'tipTest' (the case is not
 *     significant). So, in the included PHP file there must be somewhere a
 *     <tt>class tipTest</tt> declaration.
 * \li <b><tt>$Instance =& new $Class ();</tt></b>\n
 *     The new class is instantiated.
 *
 * After this steps, the new instance is stored in the register array and a
 * reference to it is returned to the application. Any error during this
 * procedure is fatal, so GetInstance ever returns a valid reference or not
 * returns at all.
 **/
class tipType extends tip
{
  /// @privatesection

  var $ERROR;

  function& CreateInstance ($Type, $Fatal)
  {
    $Instance = FALSE;
    $Logic = tip::GetOption ($Type, 'logic');
    if (! $Logic)
      {
	if ($Fatal)
	  tip::LogFatal ("Type `$Type' not configured");
	return $Instance;
      }

    require_once $Logic;

    $Class = "tip$Type";
    if (! class_exists ($Class))
      {
	if ($Fatal)
	  tip::LogFatal ("CreateInstance ($Type) failed");
	return $Instance;
      }

    $Instance =& new $Class ();
    return $Instance;
  }


  /// @protectedsection

  /**
   * Constructor.
   *
   * Initializes a tipType instance.
   **/
  function tipType ()
  {
    $this->ERROR = FALSE;
  }

  /**
   * Overridable post construction method.
   *
   * Called after the construction happened. This can be overriden to do some
   * post costruction operation. A good example of operation to do after the
   * type instantiation is the initialization of the privileges of a tipModule
   * class. Because this operation needs some modules yet instantiated, if this
   * is done in the module constructor there will be a mutual recursion when
   * instantiating these modules.
   *
   * @note Remember to always chain up the parent method.
   **/
  function PostConstructor ()
  {
  }

  /**
   * Sets an error message.
   *
   * Sets or appends to the internal type error string a message. This error
   * is publicly available throught the GetError() method.
   **/
  function SetError ($Message)
  {
    if (empty ($Message))
      return;

    if (empty ($this->ERROR))
      $this->ERROR = $Message;
    else
      $this->ERROR .= "\n$Message";
  }

  /**
   * Resets the error messages.
   *
   * Resets the internal type error string.
   **/
  function ResetError ()
  {
    $this->ERROR = FALSE;
  }

  /**
   * Gets an option for the current instance.
   * @param[in] Option \c string The option to retrieve.
   *
   * Wrappers the more general tip::GetOption() function without the need to
   * specify the type.
   *
   * @return The value of the requested option, or \c FALSE on errors.
   **/
  function GetOption ($Option)
  {
    return tip::GetOption ($this->GetName (), $Option);
  }
  
  /**
   * Gets the name of an instantiated type.
   *
   * Returns the name of the current - instantiated - type. This function
   * simply gets the class name (in lowercase) and strips the first 3 chars,
   * that is the "tip" prefix.
   *
   * @return The instance name.
   **/
  function GetName ()
  {
    return strtolower (substr (get_class ($this), 3));
  }

  /**
   * Gets a type instance.
   * @param[in] Type  \c string  A type name.
   * @param[in] Fatal \c boolean Are errors fatals?
   *
   * Gets the registered instance of the \p Type type. If \p Type is not yet
   * registered (this is the first call to GetInstance with this particular
   * type), the \p Type type is registered and then returned. Any error during
   * the instantiation of a type (if \p Fatal is \c TRUE) is fatal, so this
   * function returns a valid reference or not returns at all.
   *
   * @return A reference to the \p Type type.
   **/
  function& GetInstance ($Type, $Fatal = TRUE)
  {
    static $Register = array ();
    $Type = strtolower ($Type);

    if (! array_key_exists ($Type, $Register))
      {
	$Register[$Type] =& tipType::CreateInstance ($Type, $Fatal);
	$Register[$Type]->PostConstructor ();
      }

    return $Register[$Type];
  }

  /**
   * Logs a warning message.
   *
   * Wrappes tip::LogWarning() appending specific type informations.
   *
   * @see LogError(), LogFatal()
   **/
  function LogWarning ($Message)
  {
    $Type = $this->GetName ();
    tip::LogWarning ("$Message in the `tip$Type' class");
  }

  /**
   * Logs an error message.
   *
   * Wrappes tip::LogError() appending specific type informations.
   *
   * @see LogWarning(), LogFatal()
   **/
  function LogError ($Message)
  {
    $Type = $this->GetName ();
    tip::LogError ("$Message in the `tip$Type' class");
  }

  /**
   * Logs an error message and quits the application.
   *
   * Wrappes tip::LogFatal() appending specific type informations.
   *
   * @see LogWarning(), LogError()
   **/
  function LogFatal ($Message)
  {
    $Type = $this->GetName ();
    tip::LogFatal ("$Message in the `tip$Type' class");
  }


  /// @publicsection

  /**
   * The last error descriptor.
   *
   * Gets the description of the last error set by this module. If there was
   * not errors, this function simply returns \c FALSE.
   *
   * After a call to this function, the internal error is reset, so further
   * calls does not return any error message.
   *
   * @returns The last error description, or \c FALSE if there are not errors.
   **/
  function GetError ()
  {
    $ErrorMessage = $this->ERROR;
    $this->ResetError ();
    return $ErrorMessage;
  }
}

?>
