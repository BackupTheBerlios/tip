<?php

class tipNews extends tipModule
{
  // Overriden:

  /**
   * Executes an action.
   * @copydoc tipModule::RunAction()
   **/
  function RunAction ($Action)
  {
    global $APPLICATION;

    /**
     * The following are added by the 'news' module:
     **/
    switch ($Action)
      {
	/**
	 * \li <b>view</b>\n
	 *     Detailed view of a specific news, including its comments. You
	 *     must specify the news id in the $_GET['id'] field.
	 **/
      case 'view':
	$Id = tip::GetGet ('id', 'integer');
	if (! $Id > 0)
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Query = $this->DATA_ENGINE->QueryById ($Id, $this);
	if (! $this->StartQuery ($Query))
	  {
	    $APPLICATION->Error ('E_SELECT');
	    return FALSE;
	  }

	if ($this->RowsCount () < 1)
	  {
	    $APPLICATION->Error ('E_NOTFOUND');
	    $this->EndQuery ();
	    return FALSE;
	  }

	$this->ResetRow ();
	$NewRow =& $this->GetCurrentRow ();
	$OldRow = $NewRow;
	$NewRow['_hits'] += 1;
	$NewRow['_lasthit'] = $APPLICATION->FIELDS['NOW'];
	$this->DATA_ENGINE->UpdateRow ($OldRow, $NewRow, $this);

	$this->AppendToContent ('view.src');
	$this->EndQuery ();

	return TRUE;

      case 'browse':
	global $USER;
	if (! $USER->Populate () || ! $USER->ID)
	  {
	    Error ('E_DENIED');
	    return FALSE;
	  }

	$this->Spopulate ();
	$this->TABLE->SetQuery ("WHERE `_user`=$USER->ID ORDER BY `_creation` DESC");
	$this->EchoInContent ('browse-user.src');
	return TRUE;

      case 'add':
	if (! $User->IsAllowed ('news_add'))
	  {
	    Error ('NWS_DENIED');
	    return FALSE;
	  }

	if ($this->PopulateWithSource ($_POST))
	  $this->EchoInContent ('add-edit.html');
	return TRUE;

      case 'doadd':
	global $USER, $FIELDS;
	if ($USER && ! $USER->IsAllowed ('news_add'))
	  {
	    Error ('VL_DENIED');
	    return FALSE;
	  }

	if (! $this->PopulateWithSource ($_POST) || ! $this->Validate ())
	  {
	    $FIELDS['ACTION'] = 'add';
	    $this->EchoInContent ('add-edit.html');
	    return FALSE;
	  }

	$Row =& $this->ROW;
	$Row['_user'] = $USER->ID;
	$Row['_creation'] = date ('Y-m-d H:i:s');
	$Row['_hits'] = 1;
	$Row['_lasthit'] = $Row['_creation'];

	$this->UpdateTable ();
	$FIELDS['ACTION'] = 'view';
	$this->actionView ($Row['id']);
	return TRUE;

      case 'edit':
	if (! $this->AccessAllowed (@$_GET['id']))
	  return FALSE;

	$this->EchoInContent ('add-edit.html');
	return TRUE;

      case 'doedit':
	global $FIELDS;

	if (! $this->AccessAllowed (@$_POST['id']))
	  return FALSE;

	$OldGroup = $this->ROW['group'];

	if (! $this->PopulateWithSource ($_POST) || ! $this->Validate ())
	  {
	    $FIELDS['ACTION'] = 'edit';
	    $this->EchoInContent ('add-edit.html');
	    return FALSE;
	  }

	$Id =& $this->ROW['id'];
	if (@empty ($this->ROW['id']))
	  {
	    Error ('NWS_NOTSPECIFIED');
	    $FIELDS['ACTION'] = 'edit';
	    $this->EchoInContent ('add-edit.html');
	    return FALSE;
	  }

	if (! $this->UpdateTable ())
	  {
	    Error ('DB_UPDATE');
	    $FIELDS['ACTION'] = 'edit';
	    $this->EchoInContent ('add-edit.html');
	    return FALSE;
	  }

	$this->Spopulate ();
	$FIELDS['ACTION'] = 'browse';
	$this->actionBrowse ();
	return TRUE;

      case 'delete':
	if (! $this->AccessAllowed (@$_GET['id']))
	  return;

	$this->EchoInContent ('delete.html');
	return TRUE;

      case 'dodelete':
	$Id = @$_GET['id'];

	if (! $this->AccessAllowed ($Id))
	  return FALSE;

	$this->SetQuery ("WHERE `id`=$Id");
	if ($this->DeleteTable ())
	  {
	    global $NEWS_COMMENT;
	    $NEWS_COMMENT->SetQuery ("WHERE `_news`=$Id");
	    $NEWS_COMMENT->DeleteTable ();
	  }

	$this->Spopulate ();
	$GLOBALS['FIELDS']['ACTION'] = 'browse';
	$this->actionBrowse ();
	return TRUE;
      }

    return parent::RunAction ($Action);
  }


  /// @privatesection

  function tipNews ()
  {
    $this->tipModule ();
  }


  // protected virtual:

  function Validate ()
  {
    $Validate =& new cValidate ();
    $Validate->Rule ('subject', VL_REQUIRED, 'NWS_SUBJECTREQ');
    $Validate->Rule ('remark',  VL_REQUIRED, 'NWS_REMARKREQ');
    $Validate->Rule ('content', VL_REQUIRED, 'NWS_CONTENTREQ');
    return $Validate->CheckRules ($this->ROW, $this->TABLE);
  }
}


class tipNewsComment extends tipModule
{
  // Overriden:

  function RunAction ($Action)
  {
    $User =& tipType::GetInstance ('user');

    switch ($Action)
      {
      case 'add':
	$Content = @$_POST['content'];
	if (empty ($Content))
	  {
	    Error ('NWS_COMMENTREQ');
	    return FALSE;
	  }

	$this->AppendToContent ('comment.src');
	return TRUE;

      case 'doadd':
	if (! $User->IsAllowed ('news_comment'))
	  {
	    Error ('VL_DENIED');
	    return FALSE;
	  }

	$NewsId = @$_POST['id'];
	if (empty ($NewsId))
	  {
	    Error ('NWS_NEWSREQ');
	    return FALSE;
	  }

	$Content = @$_POST['content'];
	if (empty ($Content))
	  {
	    Error ('NWS_COMMENTREQ');
	    return FALSE;
	  }

	$Row =& $this->ROW;
	$Row['_creation'] = date ('Y-m-d H:i:s');
	$Row['_user'] = $USER->ID;
	$Row['_news'] = $NewsId;
	$Row['content'] = $Content;

	$this->UpdateTable ();

	global $FIELDS, $NEWS;
	$FIELDS['MODULE'] = 'news';
	$FIELDS['ACTION'] = 'view';
	$NEWS->actionView ($NewsId);
	return TRUE;
      }

    return parent::RunAction ($Action);
  }
}

?>
