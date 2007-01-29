<?php

class TipAdvertisementTree extends TipTreeModule
{
  function TipAdvertisementTree ()
  {
    $this->tipTreeModule ();
    $this->StartView ('');
    // No EndView() call to retain this query as the default one
  }
}


class tipAdvertisement extends tipModule
{
  /// @protectedsection

  function RunAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
	/**
	 * \action <b>browse</b>\n
	 * Shows the advertisement published by the current user.
	 * The user must be registered to do this.
	 **/
      case 'browse':
	$UserId = tipApplication::GetUserId ();
	if (is_null ($UserId) || $UserId === FALSE)
	  {
	    $APPLICATION->Error ('E_RESERVED');
	    return FALSE;
	  }

	$this->AppendToContent ('browse-user.src');
	return TRUE;

	/**
	 * \action <b>browsegroup</b>\n
	 * Shows the advertisement in a specified group. The group must be
	 * present in $_GET['group'].
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
	 * \action <b>browseillegal</b>\n
	 * Shows the illegalized advertisements.
	 **/
      case 'browseillegal':
	$this->AppendToContent ('browse-illegal.src');
	return TRUE;

	/**
	 * \action <b>view</b>\n
	 * Views in detail a specified advertisement. You must specify
	 * in $_GET['id'] the advertisement id. Viewing an advertisement
	 * also updates its internal fields (view counter ans so on).
	 **/
      case 'view':
	$Id = tip::GetGet ('id', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartView ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return FALSE;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndView ();
	    return FALSE;
	  }

	$Row =& $this->GetCurrentRow ();
	$OldRow = $Row;
	$Row['_hits'] ++;
	$Row['_lasthit'] = $APPLICATION->FIELDS['NOW'];

	if (! $this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this))
	  $Application->Error ('E_DATA_UPDATE');

	$this->AppendToContent ('view.src');
	$this->EndView ();
	return TRUE;

	/**
	 * \action <b>update</b>\n
	 * Requests an update on the specified advertisement. You must
	 * specify in $_GET['id'] the advertisement id. Update means to
	 * post the expiration date as the advertisement was published 
	 * today.
	 **/
      case 'update':
	/**
	 * \action <b>doupdate</b>\n
	 * Updates the specified advertisement. You must specify
	 * in $_GET['id'] the advertisement id. Update means to post the
	 * expiration date as the advertisement was published today.
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

	$this->EndView ();
	return TRUE;

	/**
	 * \action <b>delete</b>\n
	 * Requests a delete of the specified advertisement. You must
	 * specify in $_GET['id'] the advertisement id.
	 **/
      case 'delete':
	/**
	 * \action <b>dodelete</b>\n
	 * Deletes the specified advertisement. You must specify in
	 * $_GET['id'] the advertisement id.
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

	$this->EndView ();
	return TRUE;

	/**
	 * \action <b>illegal</b>\n
	 * Requests to illegalize a specified advertisement. You must
	 * specify in $_GET['id'] the advertisement id.
	 * The user must be registered to do this.
	 **/
      case 'illegal':
	/**
	 * \action <b>doillegal</b>\n
	 * Illegalizes the specified advertisement. You must specify
	 * in $_GET['id'] the advertisement id. Illegalize means to
	 * notify the advertisement as unconformed, so the admins can
	 * easely check the content.
	 * The user must be registered to do this.
	 **/
      case 'doillegal':
	$UserId = tipApplication::GetUserId ();
	if (is_null ($UserId) || $UserId === FALSE)
	  {
	    $APPLICATION->Error ('E_RESERVED');
	    return FALSE;
	  }

	$Id = tip::GetGet ('id', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartView ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return FALSE;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndView ();
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

	$this->EndView ();
	return TRUE;

	/**
	 * \action <b>legalize</b>\n
	 * Requests to check a specified advertisement to legalize or
	 * illegalize it. You must specify in $_GET['id'] the advertisement id.
	 **/
      case 'check':
	/**
	 * \action <b>dolegalize</b>\n
	 * Legalizes the specified advertisement. You must specify in $_GET['id']
	 * the advertisement id. Legalize is the opposite of illegalize.
	 **/
      case 'dolegalize':
	/**
	 * \action <b>doillegalize</b>\n
	 * Illegalizes the specified advertisement. You must specify
	 * in $_GET['id'] the advertisement id. Illegalize means to remove
	 * the advertisement from the public view (setting the '_public'
	 * field to 'no'). The advertisement will still be visible to its
	 * owner, but not to the public.
	 **/
      case 'doillegalize':
	$Id = tip::GetGet ('id', 'integer');
	if (is_null ($Id))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartView ($Query))
	  {
	    $Application->Error ('E_DATA_SELECT');
	    return FALSE;
	  }

	if (! $this->ResetRow ())
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndView ();
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

	$this->EndView ();
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
     * \modulefield <b>EXPIRATION</b>\n
     * The estimated expiration date if updating an advertisement now.
     **/
    $this->FIELDS['EXPIRATION'] = date ('Y-m-d', strtotime ($this->GetOption ('expiration')));
  }

  function& GetOwnedAdvertisement ($Id)
  {
    global $APPLICATION;

    $Row = NULL;
    $UserId = tipApplication::GetUserId ();
    if (is_null ($UserId) || $UserId === FALSE)
      return $Row;

    if (is_null ($Id))
      {
	$APPLICATION->Error ('E_NOTSPECIFIED');
	return $Row;
      }

    $this->DATA_ENGINE->Querify ($UserId, $this);
    if (! $this->StartView ("WHERE `user`=$UserId"))
      {
	$Application->Error ('E_DATA_SELECT');
	return $Row;
      }

    $Row =& $this->GetRow ($Id);
    if (is_null ($Row))
      {
	$APPLICATION->Error ('E_NOTFOUND');
	$this->AppendToContent ('browse-user.src');
	$this->EndView ();
      }

    return $Row;
  }
}

?>
