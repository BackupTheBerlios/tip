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
   * Executes a management action.
   * @copydoc tipModule::RunManagerAction()
   **/
  function RunManagerAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
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
	/* TODO
	$Row =& $this->GetMyself ($Id);
	if (is_null ($Row))
	  return FALSE;
	*/
	return TRUE;

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

		    $DeltaRow['_user'] = 0;
		    $DeltaRow['_publicname'] = $Row['publicname'];
		    if (empty ($DeltaRow['_publicname']))
		      $DeltaRow['_publicname'] = $this->GetOption ('anonymous_name');

		    $NewsComment->DATA_ENGINE->Querify ($UserId);
		    $NewsComment->DATA_ENGINE->UpdateRows ("WHERE `_user`=$UserId",
							   $DeltaRow, $NewsComment);
		  }

		// Unreferences the blog comments (without deleting them)
		$BlogComment =& tipType::GetInstance ('blogcomment', FALSE);
		if ($BlogComment)
		  {
		    $UserId = $Id;

		    $DeltaRow['_user'] = 0;
		    $DeltaRow['_publicname'] = $Row['publicname'];
		    if (empty ($DeltaRow['_publicname']))
		      $DeltaRow['_publicname'] = $this->GetOption ('anonymous_name');

		    $BlogComment->DATA_ENGINE->Querify ($UserId);
		    $BlogComment->DATA_ENGINE->UpdateRows ("WHERE `_user`=$UserId",
							   $DeltaRow, $BlogComment);
		  }

		if ($Id == @$this->NEWROW['id'])
		  $this->LogOut ();

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
	return $this->LogOut ();

      /**
       * \li <b>edit</b>\n
       *     Requests the modification of the current user profile.
       **/
      case 'edit':
	return $this->AppendToContent ('module.src');

      /**
       * \li <b>doedit</b>\n
       *     Modifies the current user profile with the data found in the
       *     $_POST array.
       **/
      case 'doedit':
	if (! $this->ValidatePosts ())
	  return $this->AppendToContent ('module.src');
	if (! $this->StorePosts ($this->NEWROW))
	  return FALSE;
	return $this->OnRow ($this->NEWROW);
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
	    $Label = $this->GetLocale ('user_label');
	    $APPLICATION->Error ('E_VL_REQUIRED', " ($Label)");
	    return FALSE;
	  }

	$Password = tip::GetPost ('password', 'string');
	if (empty ($Password))
	  {
	    $Label = $this->GetLocale ('password_label');
	    $APPLICATION->Error ('E_VL_REQUIRED', " ($Label)");
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

	// No EndQuery() call to retain this row as default row
	return $this->LogIn ();

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
	return $this->AppendToContent ('module.html');

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

  function StartQuery ($Query)
  {
    $View =& new tipView ($this, $Query);
    $View->ON_ROW->Set (array (&$this, 'OnRow'));
    return $this->Push ($View);
  }

  function StartFields ()
  {
    $View =& new tipFieldView ($this);
    $View->ON_ROWS->Set (array (&$this, 'OnFieldRows'));
    return $this->Push ($View);
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

    $this->ActivateUser ();
    // No EndQuery() call to retain this query as the default one
  }

  function LogIn ()
  {
    if (! $this->ActivateUser ())
      return FALSE;

    $this->RefreshPrivileges ();

    $Expiration = strtotime ($this->GetOption ('expiration'));
    setcookie ('usrid', $this->NEWROW['id'], $Expiration);
    setcookie ('usrpwd', crypt ($this->NEWROW['password']), $Expiration);
    return TRUE;
  }

  function LogOut ()
  {
    $this->OLDROW = FALSE;
    $this->NEWROW = FALSE;
    $this->FIELDS['CID'] = NULL;

    $this->RefreshPrivileges ();

    setcookie ('usrid', '', time () - 3600);
    setcookie ('usrpwd', '', time () - 3600);
    return TRUE;
  }

  function ActivateUser ()
  {
    $this->NEWROW =& $this->GetCurrentRow ();
    $this->OLDROW = $this->NEWROW;
    if (is_null ($this->NEWROW))
      {
	$this->LogWarning ('No current user to activate');
	return FALSE;
      }

    $this->FIELDS['CID'] = @$this->NEWROW['id'];
    $this->NEWROW['_hits'] ++;
    $this->NEWROW['_lasthit'] = tip::FormatDate (FALSE, 'now', 'datetime_iso8601');
    register_shutdown_function (array (&$this, 'UpdateUser'));
    return TRUE;
  }

  function RefreshPrivileges ()
  {
    global $APPLICATION;
    $APPLICATION->PRIVILEGE = NULL;
    $APPLICATION->PostConstructor ();

    $this->PRIVILEGE = NULL;
    $this->PostConstructor ();
  }
  
  function UpdateUser ()
  {
    if (is_array ($this->OLDROW) && is_array ($this->NEWROW))
      $this->DATA_ENGINE->UpdateRow ($this->OLDROW, $this->NEWROW, $this);
  }

  function OnRow (&$Row)
  {
    $Row['OA'] = $Row['sex'] == 'female' ? 'a' : 'o';
    return TRUE;
  }

  function ValidateUser (&$Field, $Value)
  {
    global $APPLICATION;
    $this->DATA_ENGINE->Querify ($Value, $this);
    if (! $this->StartQuery ("WHERE `user`=$Value"))
      {
	$APPLICATION->Error ('E_DATA_SELECT');
	return FALSE;
      }

    $UserId = $this->ResetRow () ? $this->GetField ('id') : $this->FIELDS['CID'];
    $this->EndQuery ();

    if (@$this->FIELDS['CID'] != $UserId)
      {
	$APPLICATION->Error ('E_VL_GENERIC', $this->GetLocale ('user_validator'));
	return FALSE;
      }

    return TRUE;
  }

  function ValidatePublicName (&$Field, $Value)
  {
    global $APPLICATION;
    $this->DATA_ENGINE->Querify ($Value, $this);
    if (! $this->StartQuery ("WHERE `publicname`=$Value"))
      {
	$APPLICATION->Error ('E_DATA_SELECT');
	return FALSE;
      }

    $UserId = $this->ResetRow () ? $this->GetField ('id') : $this->FIELDS['CID'];
    $this->EndQuery ();

    if (@$this->FIELDS['CID'] != $UserId)
      {
	$APPLICATION->Error ('E_VL_GENERIC', $this->GetLocale ('publicname_validator'));
	return FALSE;
      }

    return TRUE;
  }

  function OnFieldRows (&$View)
  {
    $Fields = array
      ('user'		=> array ('mode'	=> 'entry',
				  'importance'	=> 1,
				  'validator'	=> new tipCallback (array (&$this, 'ValidateUser'))),
       'password'	=> array ('mode'	=> 'secret',
				  'importance'	=> 1),
       'publicname'	=> array ('mode'	=> 'entry',
				  'importance'	=> 2,
				  'validator'	=> new tipCallback (array (&$this, 'ValidatePublicName'))),
       'sex'		=> array ('mode'	=> 'choice',
				  'importance'	=> 2),
       'email'		=> array ('mode'	=> 'entry',
				  'importance'	=> 2),
       'mail'		=> array ('mode'	=> 'entry',
				  'importance'	=> 3),
       'mobile'		=> array ('mode'	=> 'entry',
				  'importance'	=> 3),
       'phone'		=> array ('mode'	=> 'entry',
				  'importance'	=> 3)
      );

    $View->ROWS = array_merge_recursive ($Fields, $View->ROWS);
    return TRUE;
  }
}

?>
