<?php

/**
 * The user notification manager.
 *
 * Provides an easy way to display error/warning/whatever messages to the
 * users.
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
    if (! $this->StartQuery ($Query))
      return FALSE;

    if (! $this->ResetRow ())
      {
	$this->EndQuery ();
	return FALSE;
      }

    $Result = $this->InsertInContent ($Source);
    $this->EndQuery ();
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
  }
}

/**
 * The main module.
 *
 * This is the first module of the TIP system, and the only one automatically
 * instantiated. It provides the entry point of a typical TIP application.
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

  /**
   * Executes a command.
   * @copydoc tipModule::RunCommand()
   **/
  function RunCommand ($Command, &$Params)
  {
    switch ($Command)
      {
      /**
       * \li <b>content()</b>\n
       *     Outputs the content of the application. The content is the place
       *     where the responses of any action must go.
       **/
      case 'content':
	if (empty ($this->CONTENT))
	  return $this->DataRun ('welcome.src');

	echo $this->CONTENT;
	$this->CONTENT = FALSE;
	return TRUE;
      }

    return parent::RunCommand ($Command, $Params);
  }

  /// @privatesection

  function tipApplication ()
  {
    $this->tipModule ();
    $this->PRIVILEGE = 'none';

    $this->CONTENT = '';

    $this->FIELDS['LOCALE'] = $this->GetOption ('locale');
    $this->FIELDS['ROOT'] = $this->GetOption ('root');
    $this->FIELDS['SOURCE_ROOT'] = $this->GetOption ('source_root');
    $this->FIELDS['REFERER'] = @$_SERVER['HTTP_REFERER'];
    $this->FIELDS['TODAY'] = date ('Y-m-d');
    $this->FIELDS['NOW'] = date ('Y-m-d H:i:s');
  }


  /// @publicsection

  var $CONTENT;

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

    if ($ModuleName && $Action)
      {
	$Module =& tipType::GetInstance ($ModuleName, FALSE);
	if ($Module !== FALSE)
	  $Module->CallAction ($Action);
      }

    // Generates the page
    $this->Run ($MainFile, $this);
  }

  /**
   * User error notification.
   * @param[in] ErrorId \c mixed The error id
   *
   * Outputs the specified error message to notify the user about something
   * wrong. This is merely a wrapper that calls the tipNotify::EchoError()
   * method, so check the tipNotify documentation for further informations.
   *
   * @note If the error id is not found, a new call to EchoError() without
   *       any argument is performed to try to get a default error message.
   **/
  function Error ($ErrorId)
  {
    $ErrorModule =& tipType::GetInstance ('notify');

    if (! $ErrorModule->EchoError ($ErrorId))
      $ErrorModule->EchoError ();
  }

  /**
   * Info notification.
   * @param[in] InfoId \c mixed The info id
   *
   * Outputs the specified info message to notify the user about something.
   * This is merely a wrapper that calls the tipNotify::EchoInfo() method, so
   * check the tipNotify documentation for further informations.
   *
   * @note If the info id is not found, a new call to EchoInfo() without
   *       any argument is performed to try to get a default info message.
   **/
  function Info ($InfoId)
  {
    $ErrorModule =& tipType::GetInstance ('notify');

    if (! $ErrorModule->EchoInfo ($InfoId))
      $ErrorModule->EchoInfo ();
  }

  /**
   * Gets the current user id.
   *
   * Returns the id of the logged in user. If the tipUser module does not
   * exists, returns `1' as dummy user id. If there no is current user (no
   * logins done), a user error is notified (E_RESERVED) and \c NULL is
   * returned.
   *
   * @return The current user id, \c NULL if this is an anonymous session or
   *         \c FALSE if the module 'user' is not present.
   **/
  function GetUserId ()
  {
    $User =& tipType::GetInstance ('user', FALSE);
    if (! is_object ($User))
      return FALSE;

    if (! array_key_exists ('CID', $User->FIELDS))
      return NULL;

    return $User->FIELDS['CID'];
  }

  /**
   * Gets the privilege for the specified module.
   * @param[in] Module \c tipModule The requesting module
   *
   * Returns the current privilege for the specified module. Check tipPrivilege
   * to see how the privileges are used.
   *
   * @return The privilege, or \c FALSE on errors.
   **/
  function GetPrivilege (&$Module)
  {
    $UserId = $this->GetUserId ();

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
