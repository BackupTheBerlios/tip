<?php

/**
 * User management.
 *
 * This module provides user management to the site, allowing:
 *
 * \li Logins and logouts
 * \li User management
 * \li Statistics on logged users
 *
 * Provided module fields:
 *
 * \li <b>CID</b>\n
 *     The user id of the current user, that is the user logged in.
 *     In anonymous sections, this field is not defined (it is \c NULL).
 **/
class tipUser extends tipModule
{
  /// @protectedsection

  /**
   * Adds calculated fields to the rows.
   * @copydoc tipModule::CalculatedFields()
   **/
  function CalculatedFields (&$Row)
  {
    /**
     * \li <b>OA</b>\n
     *     Evaluates to 'a' if the field 'sex' is 'female', 'o' otherwise.
     **/
    $Row['OA'] = $Row['sex'] == 'female' ? 'a' : 'o';

    return parent::CalculatedFields ($Row);
  }

  /**
   * Executes a management action.
   * @copydoc tipModule::RunManagerAction()
   **/
  function RunManagerAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
	/**
	 * \li <b>privilege</b>\n
	 *     Requests a privilege change of the specified user. You must
	 *     specify in $_GET['id'] the user id.
	 **/
      case 'privilege':
	/**
	 * \li <b>doprivilege</b>\n
	 *     Changes the privileges of a user. You must specify in
	 *     $_GET['id'] the user id.
	 **/
      case 'doprivilege':
	$Id = tip::GetGet ('id', 'integer');
	$Row =& $this->GetMyself ($Id);
	if (is_null ($Row))
	  return FALSE;

	if (substr ($Action, 0, 2) == 'do')
	  {
	    // todo
	    $APPLICATION->Info ('I_DONE');
	  }
	else
	  {
	    $this->AppendToContent ('privilege.src');
	  }

	$this->EndQuery ();
	return TRUE;


	/**
	 * \li <b>delete</b>\n
	 *     Requests a delete of the specified user. You must specify in
	 *     $_GET['id'] the user id.
	 **/
      case 'delete':
	/**
	 * \li <b>dodelete</b>\n
	 *     Deletes the specified user. You must specify in $_GET['id'] the
	 *     user id.
	 **/
      case 'dodelete':
	$Id = tip::GetGet ('id', 'integer');
	$Row =& $this->GetMyself ($Id);
	if (is_null ($Row))
	  return FALSE;

	if (substr ($Action, 0, 2) == 'do')
	  {
	    $Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	    if (is_null ($Query) || ! $this->DATA_ENGINE->DeleteRows ($Query, $this))
	      {
		$APPLICATION->Error ('E_DATA_DELETE');
	      }
	    else
	      {
		// Deletes the owned advertisements
		$Advertisement =& tipType::GetInstance ('advertisement', FALSE);
		if ($Advertisement)
		  {
		    $UserId = $Id;
		    $Advertisement->DATA_ENGINE->Querify ($UserId);
		    $Advertisement->DATA_ENGINE->DeleteRows ("WHERE `_user`=$UserId",
							     $Advertisement);
		  }

		// Unreferences the news comments (without deleting them)
		$NewsComment =& tipType::GetInstance ('newscomment', FALSE);
		if ($NewsComment)
		  {
		    $UserId = $Id;
		    $NewsComment->DATA_ENGINE->Querify ($UserId);

		    $DeltaRow['_user'] = 0;
		    $DeltaRow['_publicname'] = $Row['publicname'];
		    if (empty ($DeltaRow['_publicname']))
		      $DeltaRow['_publicname'] = $this->GetOption ('anonymous_name');

		    $NewsComment->DATA_ENGINE->UpdateRows ("WHERE `_user`=$UserId",
							   $DeltaRow, $NewsComment);
		  }

		// Unreferences the blog comments (without deleting them)
		$BlogComment =& tipType::GetInstance ('blogcomment', FALSE);
		if ($BlogComment)
		  {
		    $UserId = $Id;
		    $BlogComment->DATA_ENGINE->Querify ($UserId);

		    $DeltaRow['_user'] = 0;
		    $DeltaRow['_publicname'] = $Row['publicname'];
		    if (empty ($DeltaRow['_publicname']))
		      $DeltaRow['_publicname'] = $this->GetOption ('anonymous_name');

		    $BlogComment->DATA_ENGINE->UpdateRows ("WHERE `_user`=$UserId",
							   $DeltaRow, $BlogComment);
		  }

		if ($Id == @$this->NEWROW['id'])
		  {
		    setcookie ('usrid', '', time () - 3600);
		    setcookie ('usrpwd', '', time () - 3600);
		    $this->SwitchUser ();
		  }

		$APPLICATION->Info ('I_DONE');
	      }
	  }
	else
	  {
	    $this->AppendToContent ('delete.src');
	  }

	$this->EndQuery ();
	return TRUE;
      }

    return parent::RunManagerAction ($Action);
  }

  /**
   * Executes an administator action.
   * @copydoc tipModule::RunAdminAction()
   **/
  function RunAdminAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
	/**
	 * \li <b>browse</b>\n
	 *     Shows all the registered users.
	 **/
      case 'browse':
	$this->AppendToContent ('browse.src');
	return TRUE;
      }

    return parent::RunAdminAction ($Action);
  }

  /**
   * Executes a trusted action.
   * @copydoc tipModule::RunTrustedAction()
   **/
  function RunTrustedAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
      /**
       * \li <b>unset</b>\n
       *     Logout the current user (if any).
       **/
      case 'unset':
	setcookie ('usrid', '', time () - 3600);
	setcookie ('usrpwd', '', time () - 3600);
	$this->SwitchUser ();
	return TRUE;

      /**
       * \li <b>edit</b>\n
       *     Requests the modification of the current user profile.
       **/
      case 'edit':
	return $this->AppendToContent ('add-edit.src');

      /**
       * \li <b>doedit</b>\n
       *     Modifies the current user profile with the data found in the
       *     $_POST array.
       **/
      case 'doedit':
	// TODO: validation

	foreach (array_keys ($this->NEW_ROW) as $Id)
	  if (@array_key_exists ($Id, $_POST))
	    $this->NEW_ROW[$Id] = $_POST[$Id];

	return TRUE;
      }

    return parent::RunTrustedAction ($Action);
  }

  /**
   * Executes an untrusted action.
   * @copydoc tipModule::RunUntrustedAction()
   **/
  function RunUntrustedAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
      /**
       * \li <b>set</b>\n
       *     Login request. You must specify the user name and its password in
       *     $_POST['user'] and $_POST['password'].
       **/
      case 'set':
	$User = tip::GetPost ('user', 'string');
	if (empty ($User))
	  {
	    $APPLICATION->Error ('U_USERREQ');
	    return FALSE;
	  }

	$Password = tip::GetPost ('password', 'string');
	if (empty ($Password))
	  {
	    $APPLICATION->Error ('U_PWREQ');
	    return FALSE;
	  }

	$this->DATA_ENGINE->Querify ($User, $this);
	if (! $this->StartQuery ("WHERE `user`=$User"))
	  {
	    $APPLICATION->Error ('DB_SELECT');
	    return FALSE;
	  }

	if ($this->RowsCount () < 1)
	  {
	    $APPLICATION->Error ('U_NOTFOUND');
	    $this->EndQuery ();
	    return FALSE;
	  }

	$this->ResetRow ();
	$Row =& $this->GetCurrentRow ();
	if ($Row['password'] != $Password)
	  {
	    $APPLICATION->Error ('U_PWINVALID');
	    $this->EndQuery ();
	    return FALSE;
	  }

	$Expiration = strtotime ($this->GetOption ('expiration'));
	setcookie ('usrid', $Row['id'], $Expiration);
	setcookie ('usrpwd', crypt ($Row['password']), $Expiration);
	$this->SwitchUser ($Row);
	// No EndQuery() call to retain this row as default row
	return TRUE;

      /**
       * \li <b>conditions</b>\n
       *     Shows the conditions imposed by the registration.
       **/
      case 'conditions':
	return $this->AppendToContent ('conditions.src');

      /**
       * \li <b>add</b>\n
       *     Registration request.
       **/
      case 'add':
	return $this->AppendToContent ('add-edit.html');

      /**
       * \li <b>doadd</b>\n
       *     New user registration. The user data must be filled in the $_POST
       *     array (as for every module).
       **/
      case 'doadd':
	// TODO
	return TRUE;
      }

    return parent::RunUntrustedAction ($Action);
  }


  /// @publicsection

  /**
   * Gets the current user row.
   *
   * Gets a reference to the content of the current user row. You can use it
   * to update user statistic informations: the new content will be updated on
   * the script shutdown.
   **/
  function& GetCurrentUser ()
  {
    return $this->NEWROW;
  }


  /// @privatesection

  var $OLDROW;
  var $NEWROW;

  function tipUser ()
  {
    $this->tipModule ();

    $this->PRIVILEGE = NULL;
    $this->OLDROW = FALSE;
    $this->NEWROW = FALSE;

    register_shutdown_function (array (&$this, 'UpdateUser'));

    $Id = tip::GetCookie ('usrid', 'int');
    if (is_null ($Id))
      return;

    $CryptedPassword = tip::GetCookie ('usrpwd', 'string');
    if (empty ($CryptedPassword))
      return;

    global $APPLICATION;
    $Query = $this->DATA_ENGINE->QueryById ($Id, $this);
    if (! $this->StartQuery ($Query))
      {
	$APPLICATION->Error ('E_DATA_SELECT');
	return;
      }

    if (! $this->ResetRow ())
      {
	$APPLICATION->Error ('E_NOTFOUND');
	$this->EndQuery ();
	return;
      }

    $Password = $this->GetField ('password');
    if (crypt ($Password, $CryptedPassword) != $CryptedPassword)
      {
	$APPLICATION->Error ('E_DENIED');
	$this->EndQuery ();
	return;
      }

    $Row =& $this->GetCurrentRow ();
    $this->SwitchUser ($Row, TRUE);
    // No EndQuery() call to retain this query as the default one
  }

  function SwitchUser ($Row = FALSE, $IsConstructor = FALSE)
  {
    $this->OLDROW = $Row;
    $this->NEWROW = $Row;

    if (@array_key_exists ('id', $Row))
      $this->FIELDS['CID'] = $Row['id'];
    else
      unset ($this->FIELDS['CID']);

    if (is_array ($Row))
      {
	$this->NEWROW['_hits'] ++;
	$this->NEWROW['_lasthit'] = tip::FormatDate (FALSE, 'now', 'datetime_iso8601');
      }

    if (! $IsConstructor)
      {
	global $APPLICATION;
	$APPLICATION->PRIVILEGE = NULL;
	$APPLICATION->PostConstructor ();

	$this->PRIVILEGE = NULL;
	$this->PostConstructor ();
      }
  }
  
  function UpdateUser ()
  {
    if (is_array ($this->OLDROW) && is_array ($this->NEWROW))
      $this->DATA_ENGINE->UpdateRow ($this->OLDROW, $this->NEWROW, $this);
  }

  function& GetMyself ($Id)
  {
    global $APPLICATION;

    $Row = NULL;
    if (is_null ($Id))
      {
	$APPLICATION->Error ('E_NOTSPECIFIED');
	return $Row;
      }

    if ($Id == @$this->NEWROW['id'])
      return $this->NEWROW;

    $Query = $this->DATA_ENGINE->QueryById ($Id, $this);
    if (! $this->StartQuery ($Query))
      {
	$Application->Error ('E_DATA_SELECT');
	return $Row;
      }

    if ($this->ResetRow ())
      {
	$Row =& $this->GetCurrentRow ();
      }
    else
      {
	$APPLICATION->Error ('E_NOTFOUND');
	$this->EndQuery ();
      }

    return $Row;
  }
}

?>
