<?php

/**
 * The user notification manager.
 *
 * Provides an easy way to display error/warning/whatever messages to the
 * users.
 *
 * \modulefield <b>CONTEXT_MESSAGE</b>\n
 * A text to append to the notify message.
 **/
class tipNotify extends tipModule
{
  /// @publicsection

  /**
   * Generic message notification.
   * @param[in] MessageId \c mixed  The message id
   * @param[in] Source    \c string The source to run
   *
   * Outputs a generic notification message running the specified source
   * program with the current source engine. The output will be inserted at the
   * beginning of the page content.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EchoMessage ($MessageId, $Source)
  {
    $Query = $this->DATA_ENGINE->QueryById ($MessageId, $this);
    if (! $this->StartView ($Query))
      return FALSE;

    if (! $this->ResetRow ())
      {
	$this->EndView ();
	return FALSE;
      }

    $Result = $this->InsertInContent ($Source);
    $this->EndView ();
    return $Result;
  }

  /**
   * User error notification.
   * @param[in] MessageId \c mixed The message id
   *
   * Outputs the specified error message to notify the user about something
   * wrong.  The output is generated running the 'error.src' source and using
   * the current source engine. If \p MessageId is not specified, the default
   * error id is used (\c E_FALLBACK).
   *
   * This is a convenience function that wraps EchoMessage().
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EchoError ($MessageId = NULL)
  {
    if (is_null ($MessageId))
      $MessageId = 'E_FALLBACK';

    return $this->EchoMessage ($MessageId, 'error.src');
  }

  /**
   * User warning notification.
   * @param[in] MessageId \c mixed The message id
   *
   * Outputs the specified warning message to notify the user about something
   * important.  The output is generated running the 'warning.src' source and
   * using the current source engine. If \p MessageId is not specified, the
   * default warning id is used (\c W_FALLBACK).
   *
   * This is a convenience function that wraps EchoMessage().
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EchoWarning ($MessageId = NULL)
  {
    if (is_null ($MessageId))
      $MessageId = 'W_FALLBACK';

    return $this->EchoMessage ($MessageId, 'warning.src');
  }

  /**
   * User info notification.
   * @param[in] MessageId \c mixed The message id
   *
   * Outputs the specified info message to notify the user about something.
   * The output is generated running the 'info.src' source and using the
   * current source engine. If \p MessageId is not specified, the default
   * info id is used (\c I_FALLBACK).
   *
   * This is a convenience function that wraps EchoMessage().
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EchoInfo ($MessageId = NULL)
  {
    if (is_null ($MessageId))
      $MessageId = 'I_FALLBACK';

    return $this->EchoMessage ($MessageId, 'info.src');
  }


  /// @privatesection

  function tipNotify ()
  {
    $this->tipModule ();
    $this->DATA_ENGINE->SetPrimaryKey ('id', 'string', $this);
    $this->FIELDS['CONTEXT_MESSAGE'] = '';
  }
}

/**
 * The main module.
 *
 * Every TIP based site must have a starting point (in C terms, it must have a
 * \a main function), that is a module that runs a specified source program.
 * This is the first module of the TIP system, and the only one automatically
 * instantiated.
 *
 * The global variable $APPLICATION contains a reference to this object.
 * Your index.php, other than includes the basic TIP files, must only call
 * the Go() method of $APPLICATION:
 *
 * @verbatim
$APPLICATION->Go ();
@endverbatim
 *
 * and TIP will start working.
 **/
class tipApplication extends tipModule
{
  /// @protectedsection

  function RunCommand ($Command, &$Params)
  {
    switch ($Command)
      {
      /**
       * \command <b>content()</b>\n
       * Outputs the content of the application. The content is the place where
       * the responses of any action must go.
       **/
      case 'content':
	if (empty ($this->CONTENT))
	  $this->RunShared ('welcome.src');
	else
	  echo $this->CONTENT;

	$this->CONTENT = FALSE;

	if ($this->FIELDS['IS_MANAGER'])
	  $this->Run ('logger.src');

	return TRUE;
      }

    return parent::RunCommand ($Command, $Params);
  }

  /// @privatesection

  function tipApplication ()
  {
    $this->tipModule ();

    $this->CONTENT = '';

    $this->FIELDS['LOCALE'] = $this->GetOption ('locale');
    $this->FIELDS['ROOT'] = $this->GetOption ('root');
    $this->source_root = $this->GetOption ('source_root');
    $this->FIELDS['SOURCE_ROOT'] = $this->GetOption ('source_root');
    $this->FIELDS['REFERER'] = @$_SERVER['HTTP_REFERER'];
    $this->FIELDS['TODAY'] = date ('Y-m-d');
    $this->FIELDS['NOW'] = date ('Y-m-d H:i:s');
  }


  /// @publicsection

  var $CONTENT;
  var $source_root;

  /**
   * The "main" function.
   * @param[in] MainFile \c string The main source program to run.
   *
   * The starting point of the TIP system. This must be called somewhere from
   * your index.php.
   **/
  function Go ($MainFile)
  {
    // Locale settings
    switch (tip::GetOS ())
      {
      case 'unix':
	setlocale (LC_ALL, $this->FIELDS['LOCALE'] . '_' . strtoupper ($this->FIELDS['LOCALE']));
	break;
      case 'windows':
	setlocale (LC_ALL, $this->FIELDS['LOCALE']);
	break;
      default:
	break;
      }

    // Executes the action
    $Action = tip::GetGet ('action', 'string');
    if ($Action)
      {
	$ModuleName = tip::GetGet ('module', 'string');
      }
    else
      {
	$Action = tip::GetPost ('action', 'string');
	$ModuleName = tip::GetPost ('module', 'string');
      }

    if ($ModuleName)
      {
	$Module =& tipType::GetInstance ($ModuleName, FALSE);
	if ($Module === FALSE)
	  {
	    $this->Error ('E_URL_MODULE');
	  }
	else
	  {
	    if (empty ($Action))
	      {
		$this->Error ('E_URL_ACTION');
	      }
	    elseif (is_null ($Module->CallAction ($Action)))
	      {
		if (is_null ($this->GetUserId ()))
		  $this->Error ('E_URL_RESERVED');
		else
		  $this->Error ('E_URL_DENIED');
	      }
	  }
      }

    // Generates the page
    $this->Run ($MainFile, $this);
  }

  /**
   * User error notification.
   * @param[in] ErrorId        \c mixed   The error id
   * \param[in] ContextMessage \c string  A custom message to append
   *
   * Outputs the specified error message to notify the user about something
   * wrong. This is merely a wrapper that calls the tipNotify::EchoError()
   * method, so check the tipNotify documentation for further informations.
   *
   * @note If the error id is not found, a new call to EchoError() without
   *       any argument is performed to try to get a default error message.
   **/
  function Error ($ErrorId, $ContextMessage = '')
  {
    $Notify =& tipType::GetInstance ('notify');
    $Notify->FIELDS['CONTEXT_MESSAGE'] = $ContextMessage;

    if (! $Notify->EchoError ($ErrorId))
      $Notify->EchoError ();
  }

  /**
   * Info notification.
   * @param[in] InfoId         \c mixed   The info id
   * @param[in] ContextMessage \c string  A text to append to the message
   *
   * Outputs the specified info message to notify the user about something.
   * This is merely a wrapper that calls the tipNotify::EchoInfo() method, so
   * check the tipNotify documentation for further informations.
   *
   * @note If the info id is not found, a new call to EchoInfo() without
   *       any argument is performed to try to get a default info message.
   **/
  function Info ($InfoId, $ContextMessage = '')
  {
    $Notify =& tipType::GetInstance ('notify');
    $Notify->FIELDS['CONTEXT_MESSAGE'] = $ContextMessage;

    if (! $Notify->EchoInfo ($InfoId))
      $Notify->EchoInfo ();
  }

  /**
   * Gets the current user id.
   *
   * Returns the id of the logged in user. If the tipUser module does not
   * exists, returns `1' as dummy user id. If there no is current user (no
   * logins done), a user error is notified (E_RESERVED) and \c NULL is
   * returned.
   *
   * @note This is a static function: call with tipApplication::GetUserId()
   *
   * @return The current user id, \c NULL if this is an anonymous session or
   *         \c FALSE if the module 'user' is not present.
   **/
  function GetUserId ()
  {
    static $Initialized = FALSE;
    static $UserId;

    if (! $Initialized)
      {
	$User =& tipType::GetInstance ('user', FALSE);
	if (! is_object ($User))
	  $UserId = FALSE;
	elseif (! array_key_exists ('CID', $User->FIELDS))
	  $UserId = NULL;
	else
	  $UserId = $User->FIELDS['CID'];
      }
    
    return $UserId;
  }

  /**
   * Gets the privilege for the specified module.
   * @param[in] Module \c tipModule The requesting module
   * @param[in] UserId \c mixed     The user id
   *
   * Returns the privilege for the \p Module and \p UserId pair. If \p UserId
   * is omitted, the current user id is used. Check tipPrivilege to see how the
   * privileges are used.
   *
   * @note This is a static function: call with tipApplication::GetPrivilege()
   *
   * @return The privilege, or \c FALSE on errors.
   **/
  function GetPrivilege (&$Module, $UserId = FALSE)
  {
    if ($UserId === FALSE)
      $UserId = tipApplication::GetUserId ();

    $Anonymous = is_null ($UserId) || $UserId === FALSE;
    if (! $Anonymous)
      {
	$Privilege =& tipType::GetInstance ('privilege', FALSE);

	$Anonymous = ! is_object ($Privilege);
	if (! $Anonymous)
	  {
	    $StoredPrivilege = $Privilege->GetStoredPrivilege ($Module, $UserId);
	    if ($StoredPrivilege !== FALSE)
	      return $StoredPrivilege;
	  }
      }

    return $Module->GetOption ($Anonymous ? 'anonymous_privilege' : 'default_privilege');
  }
}

?>
