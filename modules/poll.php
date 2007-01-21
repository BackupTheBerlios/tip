<?php

class tipPoll extends tipModule
{
  /// @protectedsection

  function RunAction ($Action)
  {
    global $APPLICATION;

    switch ($Action)
      {
      /**
       * \action <b>edit</b>\n
       * Vote request. You must specify the answer code in the
       * $_GET['answer'] field.
       **/
      case 'edit':
	if (! array_key_exists ('answer', $_GET))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Row =& $this->GetCurrentRow ();
	$Votes = "votes$_GET[answer]";
	if (! array_key_exists ($Votes, $Row))
	  {
	    $APPLICATION->Error ('E_INVALID');
	    return FALSE;
	  }

	setcookie ('plvoting', 'true');
	$this->AppendToContent ('vote.src');
	return TRUE;

      /**
       * \action <b>doedit</b>\n
       * Vote operation. You must specify the answer code in the
       * $_GET['answer'] field.
       **/
      case 'doedit':
	if (! array_key_exists ('plvoting', $_COOKIE))
	  {
	    $APPLICATION->Error ('E_COOKIEOFF');
	    return FALSE;
	  }

	setcookie ('plvoting', '');

	if (array_key_exists ('plvoted', $_COOKIE))
	  {
	    $APPLICATION->Error ('E_PL_DOUBLE');
	    return FALSE;
	  }

	if (! array_key_exists ('answer', $_GET))
	  {
	    $APPLICATION->Error ('E_NOTSPECIFIED');
	    return FALSE;
	  }

	$Row =& $this->GetCurrentRow ();
	$OldRow = $Row;
	$Votes = "votes$_GET[answer]";
	if (! array_key_exists ($Votes, $Row))
	  {
	    $APPLICATION->Error ('E_INVALID');
	    return FALSE;
	  }

	++ $Row[$Votes];
	$this->OnRow ($Row);
	$this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this);
	setcookie ('plvoted', 'true', strtotime ($this->GetOption ('expiration')));

      case 'browse':
	$this->AppendToContent ('browse.src');
	return TRUE;
      }

    return parent::RunAction ($Action);
  }

  function StartView ($Query)
  {
    $View =& new tipView ($this, $Query);
    $View->ON_ROW->Set (array (&$this, 'OnRow'));
    return $this->Push ($View);
  }


  /// @privatesection

  function tipPoll ()
  {
    $this->tipModule ();
    $this->StartView ('ORDER BY `date` DESC LIMIT 1');
    $this->ResetRow ();
    // No EndView() call to retain this row as default row
  }

  function OnRow (&$Row)
  {
    $Total = $Row['votes1']+$Row['votes2']+$Row['votes3']+$Row['votes4']+$Row['votes5']+$Row['votes6'];

    $Row['TOTAL'] = $Total;
    $Row['PERCENT1'] = round ($Row['votes1'] * 100.0 / $Total);
    $Row['PERCENT2'] = round ($Row['votes2'] * 100.0 / $Total);
    $Row['PERCENT3'] = round ($Row['votes3'] * 100.0 / $Total);
    $Row['PERCENT4'] = round ($Row['votes4'] * 100.0 / $Total);
    $Row['PERCENT5'] = round ($Row['votes5'] * 100.0 / $Total);
    $Row['PERCENT6'] = round ($Row['votes6'] * 100.0 / $Total);

    return TRUE;
  }

}

?>
