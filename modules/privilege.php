<?php

/**
 * The privilege manager.
 *
 * TIP uses different security levels, here called "privilege descriptors", in
 * a top-down fashion: every level allows the actions of the lower levels.
 * The following is the privilege descriptors list, ordered from the highest to
 * the lowest level:
 *
 * \par manager
 * The highest privilege. This allows every available action provided by the
 * module to be executed.
 *
 * \par admin
 * The administrator privilege. It allows to do everything but modifying the
 * overall module structure.
 *
 * \par trusted
 * The trusted (or registered) user privilege. This allows to do read actions
 * on the module content and write actions only on content owned directly by
 * the user.
 *
 * \par untrusted
 * The untrusted (anonymous) user privilege. This allows only read actions on
 * the module content.
 *
 * \par none
 * The lowest privilege. This disallows all the actions that require any
 * privilege. A module can anyway have some action that does not require
 * privileges.
 *
 * The description under every privilege is purely indicative: you must check
 * the documentation of every module to see which action are allowed by a
 * specific level and which are disallowed.
 *
 * The privileges are stored in a "Module-User" way, so for every pair of
 * module-user there will be a specific privilege descriptor. If a specific
 * module-user pair is not stored in the privilege database, the default
 * privilege descriptor will be used.
 *
 * The default privileges MUST be specified in the configure file
 * (logic/config.php) for every used module.
 **/
class tipPrivilege extends tipModule
{
  /// @protectedsection

  function RunAdminAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
      /**
       * \adminaction <b>Edit</b>\n
       * Requests a privilege change. You must specify in $_GET['user'] the
       * user id.
       **/
      case 'edit':
	if ($this->CheckUserId ())
	  $this->AppendToContent ('edit.src');
	return TRUE;

      /**
       * \adminaction <b>DoEdit</b>\n
       * Changes the privileges of a user. You must specify in $_GET['user']
       * the user id, in $_GET['where'] the module name and in
       * $_GET['privilege'] the new privilege descriptor.
       **/
      case 'doedit':
	if (! $this->CheckUserId ())
	  return TRUE;

	$ModuleName = tip::GetGet ('where', 'string');
	$Privilege = tip::GetGet ('privilege', 'string');
	if (empty ($ModuleName) || empty ($Privilege))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    $this->AppendToContent ('edit.src');
	    return TRUE;
	  }

	$Query = 'WHERE `_user`=' . $this->FIELDS['UID'];
	$OldRow = FALSE;
	$NewRow['privilege'] = $Privilege;
	$NewRow['_user'] = $this->FIELDS['UID'];
	$NewRow['_module'] = $ModuleName;

	if ($this->StartView ($Query))
	  {
	    while ($this->NextRow ())
	      {
		$Row =& $this->GetCurrentRow ();
		if ($ModuleName == @$Row['_module'])
		  {
		    $OldRow =& $Row;
		    break;
		  }
	      }
	    $this->EndView ();
	  }

	// Remove the query from the cache
	unset ($this->VIEW_CACHE[$Query]);

	if ($OldRow)
	  {
	    if ($this->DATA_ENGINE->UpdateRow ($OldRow, $NewRow, $this))
	      $APPLICATION->Info ('I_DONE');
	    else
	      $APPLICATION->Error ('E_DATA_UPDATE');
	  }
	else
	  {
	    if ($this->DATA_ENGINE->PutRow ($NewRow, $this))
	      $APPLICATION->Info ('I_DONE');
	    else
	      $APPLICATION->Error ('E_DATA_INSERT');
	  }

	$this->AppendToContent ('edit.src');
	return TRUE;

      /**
       * \adminaction <b>Restore</b>\n
       * Restores all the privileges of a user to their defaults. You must
       * specify in $_GET['user'] the user id.
       **/
      case 'restore':
	if (! $this->CheckUserId ())
	  return TRUE;

	$Query = 'WHERE `_user`=' . $this->FIELDS['UID'];
	if ($this->DATA_ENGINE->DeleteRows ($Query, $this))
	  $APPLICATION->Info ('I_DONE');
	else
	  $APPLICATION->Error ('E_DATA_DELETE');

	// Remove the query from the cache
	unset ($this->VIEW_CACHE[$Query]);

	$this->AppendToContent ('edit.src');
	return TRUE;
      }

    return parent::RunAdminAction ($Action);
  }


  /// @publicsection

  /**
   * Gets a stored privilege.
   * @param[in] Module \c tipModule The requesting module
   * @param[in] UserId \c mixed     The user id
   *
   * Returns the privilege stored for the \p Module and \p UserId pair. If
   * \p UserId is omitted, the current user id is used.
   * This method returns the privilege descriptor only if it is explicitily
   * stored in the data, does not provide any fallback or default value.
   *
   * @return The stored privilege, or \c FALSE if not found.
   **/
  function GetStoredPrivilege (&$Module, $UserId = FALSE)
  {
    if ($UserId === FALSE)
      $UserId = tipApplication::GetUserId ();

    if (is_null ($UserId) || $UserId === FALSE)
      return FALSE;

    /**
     * The internal query is based only on the user id. The query could be
     * <tt>"WHERE `_user`=$UsedId AND `module`='$ModuleName'"</tt>, but
     * filtering only by user id allows the next requests, with the same user id
     * but different module (which are expected to be done), to be cached.
     **/
    if (! $this->StartView ("WHERE `_user`=$UserId"))
      return FALSE;

    $StoredPrivilege = FALSE;
    $ModuleName = $Module->GetName ();

    while ($this->NextRow ())
      {
	$Row =& $this->GetCurrentRow ();
	if ($ModuleName == @$Row['_module'])
	  {
	    $StoredPrivilege = $Row['privilege'];
	    break;
	  }
      }

    $this->EndView ();
    return $StoredPrivilege;
  }

  function StartSpecialView ($Name)
  {
    if (strcasecmp ($Name, 'MODULES') != 0)
      return parent::StartSpecialView ($Name);

    $View =& new tipModulesView ($this);
    $View->ON_ROW->Set (array (&$this, 'OnModuleRow'));
    return $this->Push ($View);
  }


  /// @privatesection

  var $PRIVILEGES;


  function tipPrivilege ()
  {
    $this->tipModule ();
    $this->PRIVILEGES = array ('none', 'untrusted', 'trusted', 'admin', 'manager');
    $this->FIELDS['UID'] = tip::GetGet ('user', 'integer');
  }

  function GetAvailablePrivileges (&$Module, $From, $To)
  {
    $Available = array ();
    $Store = FALSE;

    foreach ($this->PRIVILEGES as $Privilege)
      {
	$Store = $Store || $Privilege == $From;
	if ($Store && $Module->GetLocale (strtoupper ($Privilege) . '_HELP'))
	  $Available[] = $Privilege;

	if ($Privilege == $To)
	  break;
      }

    return $Available;
  }

  function CheckUserId ()
  {
    global $APPLICATION;
    if (is_null ($this->FIELDS['UID']))
      {
	$APPLICATION->Error ('E_NOTSPECIFIED');
	return FALSE;
      }
    if ($this->FIELDS['UID'] == tipApplication::GetUserId ())
      {
	$APPLICATION->Error ('E_DENIED');
	return FALSE;
      }
    return TRUE;
  }

  function OnModuleRow (&$Row)
  {
    $Module =& tipType::GetInstance ($Row['id']);
    $From = $Module->GetOption ('default_privilege');
    $To = $this->FIELDS['IS_MANAGER'] ? 'manager' : tipApplication::GetPrivilege ($Module);
    $Available = $this->GetAvailablePrivileges ($Module, $From, $To);

    if (count ($Available) <= 1)
      return FALSE;

    $Row['active'] = tipApplication::GetPrivilege ($Module, $this->FIELDS['UID']);
    foreach ($this->PRIVILEGES as $Privilege)
      $Row['can_' . $Privilege] = in_array ($Privilege, $Available);
    return TRUE;
  }

}

?>
