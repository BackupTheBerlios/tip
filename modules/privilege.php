<?php

/**
 * The privilege manager.
 *
 * TIP uses different security levels, here called "privilege descriptors", in
 * a top-down fashion: every level allows the actions of the lower levels.
 * The following is the privilege descriptors list, ordered from the highest to
 * the lowest level:
 *
 * \li <b>manager</b>\n
 *     The highest privilege. This allows every available action provided by
 *     the module to be executed.
 * \li <b>admin</b>\n
 *     The administrator privilege. It allows to do everything but modifying
 *     the overall module structure.
 * \li <b>trusted</b>\n
 *     The trusted (or registered) user privilege. This allows to do read
 *     actions on the module content and write actions only on content owned
 *     directly by the user.
 * \li <b>untrusted</b>\n
 *     The untrusted (anonymous) user privilege. This allows only read
 *     actions on the module content.
 * \li <b>none</b>\n
 *     The lowest privilege. This disallows all the actions that require any
 *     privilege. A module can anyway have some action that does not require
 *     privileges.
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

  /**
   * Executes a command.
   * @copydoc tipModule::RunCommand()
   **/
  function RunCommand ($Command, &$Params)
  {
    switch ($Command)
      {
      /**
       * \li <b>ForEachModule(</b>\a source<b>)</b>\n
       *     For each module present in this site, run the \p source file.
       **/
      case 'foreachmodule':
	$UserId = tip::GetGet ('user', 'integer');
	if ($UserId === FALSE)
	  $UserId = tipApplication::GetUserId ();

	global $CFG;
	$nRow = 1;
	$this->FIELDS['LOCAL_USER'] = $UserId;

	foreach (array_keys ($CFG) as $ModuleName)
	  {
	    $Module =& tipType::GetInstance ($ModuleName, FALSE);
	    if (is_subclass_of ($Module, 'tipModule'))
	      {
		$this->FIELDS['LOCAL_ROW'] = $nRow;
		$this->FIELDS['LOCAL_ODDEVEN'] = ($nRow & 1) > 0 ? 'odd' : 'even';
		$this->FIELDS['LOCAL_MODULE'] = $ModuleName;
		$this->FIELDS['LOCAL_NAME'] = $Module->GetLocale ('NAME');
		$this->FIELDS['LOCAL_DESCRIPTION'] = $Module->GetLocale ('DESCRIPTION');
		$this->FIELDS['LOCAL_PRIVILEGE'] = tipApplication::GetPrivilege ($Module, $UserId);
		$this->FIELDS['LOCAL_DEFAULT'] = $CFG[$ModuleName]['default_privilege'];
		if (! $this->Run ($Params))
		  break;
		++ $nRow;
	      }
	  }

	unset ($this->FIELDS['LOCAL_MODULE']);
	return TRUE;
      }

    return parent::RunCommand ($Command, $Params);
  }

  /**
   * Executes a management action.
   * @copydoc tipModule::RunManagerAction()
   **/
  function RunManagerAction ($Action)
  {
    switch ($Action)
      {
	/**
	 * \li <b>edit</b>\n
	 *     Requests a privilege change for a specified user. You must specify
	 *     in $_GET['user'] the user id.
	 **/
      case 'edit':
	$this->AppendToContent ('edit.src');
	return TRUE;

	/**
	 * \li <b>doedit</b>\n
	 *     Changes the privileges of a user. You must specify in $_GET['user']
	 *     the user id, in $_GET['module'] the module name and in
	 *     $_GET['privilege'] the new privilege descriptor.
	 **/
      case 'doedit':
	// TODO
	$this->AppendToContent ('edit.src');
	return TRUE;
      }

    return parent::RunManagerAction ($Action);
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
    if (! $this->StartQuery ("WHERE `_user`=$UserId"))
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

    $this->EndQuery ();
    return $StoredPrivilege;
  }


  /// @privatesection

  function tipPrivilege ()
  {
    $this->tipModule ();
  }
}

?>
