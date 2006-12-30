<?php

class tipAdvertisementTree extends tipModule
{
  /// @protectedsection

  function SummaryFields (&$Rows, &$Fields)
  {
    $TotalCount = 0;

    foreach (array_keys ($Rows) as $Id)
      {
	$Row =& $Rows[$Id];
	$SortId = sprintf ("%03d", $Row['order']);
	$TotalCount += @$Row['_count'];
	$Current = $Id == @$this->FIELDS['CID'];
	$Title = @$Row['title'];

	$Level = 0;
	$ParentId = @$Row['parent'];
	while (array_key_exists ($ParentId, $Rows))
	  {
	    $ParentRow =& $Rows[$ParentId];
	    $SortId = sprintf ("%03d", $ParentRow['order']) . '.' . $SortId;
	    $Title = "{$ParentRow['title']}::$Title";
	    
	    $ParentRow['ISLEAF'] = FALSE;
	    $ParentRow['COUNT'] += @$Row['_count'];
	    $ParentId = $ParentRow['parent'];
	    ++ $Level;
	  }

	$Row['SORTID'] = $SortId;
	$Row['TITLE'] = $Title;
	$Row['LEVEL'] = $Level;
	if (! array_key_exists ('ISLEAF', $Row))
	  $Row['ISLEAF'] = TRUE;
	$Row['COUNT'] = @$Row['_count'];
      }

    $Fields['TOTALCOUNT'] = $TotalCount;
    uasort ($Rows, array (&$this, 'sortid_compare'));
    return parent::SummaryFields ($Rows, $Fields);
  }

  /**
   * Executes a command.
   * @copydoc tipModule::RunCommand()
   **/
  function RunCommand ($Command, &$Params)
  {
    global $APPLICATION;

    switch ($Command)
      {
      /**
       * \li <b>echoicon(</b>\a id<b>)</b>\n
       *     Outputs the icon name of the specified group id.
       **/
      case 'echoicon':
	$Row =& $this->GetRow ($Params);
	if (@array_key_exists ('TITLE', $Row))
	  echo $Row['icon'];
	return TRUE;
      /**
       * \li <b>echotitle(</b>\a id<b>)</b>\n
       *     Outputs the title of the specified group id.
       **/
      case 'echotitle':
	$Row =& $this->GetRow ($Params);
	if (@array_key_exists ('TITLE', $Row))
	  echo $Row['TITLE'];
	return TRUE;
      }

    return parent::RunCommand ($Command, $Params);
  }


  /// @privatesection

  function sortid_compare ($a, $b)
  {
    return strcmp ($a['SORTID'], $b['SORTID']);
  }

  function tipAdvertisementTree ()
  {
    $this->tipModule ();

    $this->FIELDS['CID'] = 0;

    $this->StartQuery ('');
    // No EndQuery() call to retain this query as the default one
  }
}


class tipAdvertisement extends tipModule
{
  /// @protectedsection

  /**
   * Adds calculated fields to the rows.
   * @copydoc tipModule::CalculatedFields()
   **/
  function CalculatedFields (&$Row)
  {
    global $APPLICATION;

    /**
     * \li <b>ISOWNER</b>\n
     *     \c TRUE if this advertisement is owned (was created by) the current
     *     logged in user, or \c FALSE otherwise.
     **/
    $Row['ISOWNER'] = $Row['_user'] == $APPLICATION->GetCurrentUserId ();

    return parent::CalculatedFields ($Row);
  }

  /**
   * Executes an action.
   * @copydoc tipModule::RunAction()
   **/
  function RunAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
	/**
	 * \li <b>browse</b>\n
	 *     Shows the advertisement published by the current user.
	 *     The user must be registered to do this.
	 **/
      case 'browse':
	$UserId = $APPLICATION->GetCurrentUserId ();
	if (is_null ($UserId))
	  {
	    $APPLICATION->Error ('E_RESERVED');
	    return FALSE;
	  }

	$this->AppendToContent ('browse-user.src');
	return TRUE;

	/**
	 * \li <b>browsegroup</b>\n
	 *     Shows the advertisement in a specified group. The group must be
	 *     present in $_GET['group'].
	 **/
      case 'browsegroup':
	$Id = tip::GetGet ('group', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$this->AppendToContent ('browse-group.src');
	return TRUE;

	/**
	 * \li <b>browseillegal</b>\n
	 *     Shows the illegalized advertisements. The current user must have
	 *     the 'advertisement_admin' privilege to do this.
	 **/
      case 'browseillegal':
	if (! $APPLICATION->CheckPrivilege ('advertisement_admin'))
	  {
	    $APPLICATION->Error ('E_DENIED');
	    return FALSE;
	  }

	$this->AppendToContent ('browse-illegal.src');
	return TRUE;

	/**
	 * \li <b>view</b>\n
	 *     Views in detail a specified advertisement. You must specify
	 *     in $_GET['id'] the advertisement id. Viewing an advertisement
	 *     also updates its internal fields (view counter ans so on).
	 **/
      case 'view':
	$Id = tip::GetGet ('id', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartQuery ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return FALSE;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndQuery ();
	    return FALSE;
	  }

	$Row =& $this->GetCurrentRow ();
	$OldRow = $Row;
	$Row['_hits'] ++;
	$Row['_lasthit'] = $APPLICATION->FIELDS['NOW'];

	if (! $this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this))
	  $Application->Error ('E_DATA_UPDATE');

	$this->AppendToContent ('view.src');
	$this->EndQuery ();
	return TRUE;

	/**
	 * \li <b>update</b>\n
	 *     Requests an update on the specified advertisement. You must
	 *     specify in $_GET['id'] the advertisement id. Update means to
	 *     post the expiration date as the advertisement was published 
	 *     today.
	 *     The user must own the advertisement (or have the
	 *     'advertisement_admin' privilege) to do this.
	 **/
      case 'update':
	/**
	 * \li <b>doupdate</b>\n
	 *     Updates the specified advertisement. You must specify
	 *     in $_GET['id'] the advertisement id. Update means to post the
	 *     expiration date as the advertisement was published today.
	 *     The user must own the advertisement (or have the
	 *     'advertisement_admin' privilege) to do this.
	 **/
      case 'doupdate':
	$Id = tip::GetGet ('id', 'integer');
	$Row =& $this->GetOwnedAdvertisement ($Id);
	if (is_null ($Row))
	  return FALSE;

	if (substr ($Action, 0, 2) == 'do')
	  {
	    $OldRow = $Row;
	    $Row['_expiration'] = $this->FIELDS['EXPIRATION'];
	    if (! $this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this))
	      $APPLICATION->Error ('E_DATA_UPDATE');
	    else
	      $APPLICATION->Info ('I_DONE');

	    $this->AppendToContent ('browse-user.src');
	  }
	else
	  {
	    $this->AppendToContent ('update.src');
	  }

	$this->EndQuery ();
	return TRUE;

	/**
	 * \li <b>delete</b>\n
	 *     Requests a delete of the specified advertisement. You must
	 *     specify in $_GET['id'] the advertisement id.
	 *     The user must own the advertisement (or have the
	 *     'advertisement_admin' privilege) to do this.
	 **/
      case 'delete':
	/**
	 * \li <b>dodelete</b>\n
	 *     Deletes the specified advertisement. You must specify in
	 *     $_GET['id'] the advertisement id.
	 *     The user must own the advertisement (or have the
	 *     'advertisement_admin' privilege) to do this.
	 **/
      case 'dodelete':
	$Id = tip::GetGet ('id', 'integer');
	$Row =& $this->GetOwnedAdvertisement ($Id);
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
		$Rows =& $this->GetCurrentRows ();
		unset ($Rows[$Id]);
		$APPLICATION->Info ('I_DONE');
	      }

	    $this->AppendToContent ('browse-user.src');
	  }
	else
	  {
	    $this->AppendToContent ('delete.src');
	  }

	$this->EndQuery ();
	return TRUE;

	/**
	 * \li <b>illegal</b>\n
	 *     Requests to illegalize a specified advertisement. You must
	 *     specify in $_GET['id'] the advertisement id.
	 *     The user must be registered to do this.
	 **/
      case 'illegal':
	/**
	 * \li <b>doillegal</b>\n
	 *     Illegalizes the specified advertisement. You must specify
	 *     in $_GET['id'] the advertisement id. Illegalize means to
	 *     notify the advertisement as unconformed, so the admins can
	 *     easely check the content.
	 *     The user must be registered to do this.
	 **/
      case 'doillegal':
	$UserId = $APPLICATION->GetCurrentUserId ();
	if (is_null ($UserId))
	  return FALSE;

	$Id = tip::GetGet ('id', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartQuery ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return FALSE;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndQuery ();
	    return FALSE;
	  }

	$Row =& $this->GetCurrentRow ();

	if (substr ($Action, 0, 2) == 'do')
	  {
	    $OldRow = $Row;
	    $Row['_illegal'] = 'yes';
	    $Row['_illegalby'] = $UserId;
	    $Row['_illegalon'] = $APPLICATION->FIELDS['NOW'];

	    if (! $this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this))
	      {
		$APPLICATION->Error ('E_DATA_UPDATE');
	      }
	    else
	      {
		$User =& tipType::GetInstance ('user');
		$UserRow =& $User->GetCurrentUser ();
		if (is_array ($UserRow))
		  $UserRow['_signaled'] ++;

		$Query = $User->DATA_ENGINE->QueryById ($Row['_user'], $User);
		if ($Query)
		  {
		    /**
		     * @todo Find a better (and overall a portable) approach
		     *       to update the statistics of the advertisement
		     *       owner while signaling an advertisement.
		     **/
		    $RealQuery = "UPDATE `tip_user` SET `_ownsignaled`=`_ownsignaled`+1 $Query";
		    $User->DATA_ENGINE->RunQuery ($RealQuery);
		  }

		$APPLICATION->Info ('I_DONE');
	      }
	  }
	else
	  {
	    $this->AppendToContent ('illegal.src');
	  }

	$this->EndQuery ();
	return TRUE;

	/**
	 * \li <b>legalize</b>\n
	 *     Requests to check a specified advertisement to legalize or
	 *     illegalize it. You must specify in $_GET['id'] the advertisement
	 *     id. The current user must have the 'advertisement_admin'
	 *     privilege to do this.
	 **/
      case 'check':
	/**
	 * \li <b>dolegalize</b>\n
	 *     Legalizes the specified advertisement. You must specify
	 *     in $_GET['id'] the advertisement id. Legalize is the opposite
	 *     of illegalize. The current user must have the
	 *     'advertisement_admin' privilege to do this.
	 **/
      case 'dolegalize':
	/**
	 * \li <b>doillegalize</b>\n
	 *     Illegalizes the specified advertisement. You must specify
	 *     in $_GET['id'] the advertisement id. Illegalize means to remove
	 *     the advertisement from the public view (setting the '_public'
	 *     field to 'no'). The advertisement will still be visible to its
	 *     owner, but not to the public. The current user must have the
	 *     'advertisement_admin' privilege to do this.
	 **/
      case 'doillegalize':
	if (! $APPLICATION->CheckPrivilege ('advertisement_admin'))
	  {
	    $APPLICATION->Error ('E_DENIED');
	    return FALSE;
	  }

	$Id = tip::GetGet ('id', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartQuery ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return FALSE;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndQuery ();
	    return FALSE;
	  }

	switch ($Action)
	  {
	  case 'dolegalize':
	    $Row =& $this->GetCurrentRow ();
	    $OldRow = $Row;
	    $Row['_public'] = 'yes';
	    $Row['_illegal'] = 'no';

	    if (! $this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this))
	      $APPLICATION->Error ('E_DATA_UPDATE');
	    else
	      $APPLICATION->Info ('I_DONE');

	    $this->AppendToContent ('browse-illegal.src');
	    break;

	  case 'doillegalize':
	    $Row =& $this->GetCurrentRow ();
	    $OldRow = $Row;
	    $Row['_public'] = 'no';

	    if (! $this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this))
	      {
		$APPLICATION->Error ('E_DATA_UPDATE');
	      }
	    else
	      {
		$User =& tipType::GetInstance ('user');
		$UserRow =& $User->GetCurrentUser ();
		if (is_array ($UserRow))
		  $UserRow['_signaled'] ++;

		$Query = $User->DATA_ENGINE->QueryById ($Row['_user'], $User);
		if ($Query)
		  {
		    /**
		     * @todo Find a better (and overall a portable) approach
		     *       to update the statistics of the advertisement
		     *       owner and of the signalling user while
		     *       illegalizing an advertisement.
		     **/
		    $RealQuery = "UPDATE `tip_user` SET `_ownillegalized`=`_ownillegalized`+1 $Query";
		    $User->DATA_ENGINE->RunQuery ($RealQuery);
		  }

		$Query = $User->DATA_ENGINE->QueryById ($Row['_illegalby'], $User);
		if ($Query)
		  {
		    $RealQuery = "UPDATE `tip_user` SET `_illegalized`=`_illegalized`+1 $Query";
		    $User->DATA_ENGINE->RunQuery ($RealQuery);
		  }

		$APPLICATION->Info ('I_DONE');
	      }

	    $this->AppendToContent ('browse-illegal.src');
	    break;

	  default:
	    $this->AppendToContent ('check.src');
	  }

	$this->EndQuery ();
	return TRUE;
      }

    return parent::RunAction ($Action);
  }


  /// @privatesection

  function tipAdvertisement ()
  {
    $this->tipModule ();

    global $APPLICATION;

    /**
     * \li <b>EXPIRATION</b>\n
     *     The estimated expiration date if updating an advertisement now.
     **/
    $this->FIELDS['EXPIRATION'] = date ('Y-m-d', strtotime ($this->GetOption ('expiration')));
    /**
     * \li <b>ISADMIN</b>\n
     *     \c TRUE if the current user is the administrator, \c FALSE otherwise.
     **/
    $this->FIELDS['ISADMIN'] = $APPLICATION->CheckPrivilege ('advertisement_admin');
  }

  function& GetOwnedAdvertisement ($Id)
  {
    global $APPLICATION;

    $Row = NULL;
    $UserId = $APPLICATION->GetCurrentUserId ();
    if (is_null ($UserId))
      return $Row;

    if (is_null ($Id))
      {
	$APPLICATION->Error ('E_NOTSPECIFIED');
	return $Row;
      }

    if ($this->FIELDS['ISADMIN'])
      {
	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartQuery ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return $Row;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndQuery ();
	  }
	else
	  {
	    $Row =& $this->GetCurrentRow ();
	  }
      }
    else
      {
	$this->DATA_ENGINE->Querify ($UserId, $this);
	if (! $this->StartQuery ("WHERE `user`=$UserId"))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return $Row;
	  }

	$Row =& $this->GetRow ($Id);
	if (is_null ($Row))
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->AppendToContent ('browse-user.src');
	    $this->EndQuery ();
	  }
      }

    return $Row;
  }
}

?>
