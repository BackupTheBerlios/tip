<?php

class tipPoll extends tipModule
{
  /// @protectedsection

  /**
   * Adds calculated fields to the rows.
   * @copydoc tipModule::CalculatedFields()
   **/
  function CalculatedFields (&$Row)
  {
    $Total = $Row['votes1']+$Row['votes2']+$Row['votes3']+$Row['votes4']+$Row['votes5']+$Row['votes6'];

    /**
     * \li <b>TOTAL</b>\n
     *     The total number of votes for every poll.
     **/
    $Row['TOTAL'] = $Total;
    /**
     * \li <b>PERCENT1</b>\n
     *     The percentage of answer 1.
     **/
    $Row['PERCENT1'] = round ($Row['votes1'] * 100.0 / $Total);
    /**
     * \li <b>PERCENT2</b>\n
     *     The percentage of answer 2.
     **/
    $Row['PERCENT2'] = round ($Row['votes2'] * 100.0 / $Total);
    /**
     * \li <b>PERCENT3</b>\n
     *     The percentage of answer 3.
     **/
    $Row['PERCENT3'] = round ($Row['votes3'] * 100.0 / $Total);
    /**
     * \li <b>PERCENT4</b>\n
     *     The percentage of answer 4.
     **/
    $Row['PERCENT4'] = round ($Row['votes4'] * 100.0 / $Total);
    /**
     * \li <b>PERCENT5</b>\n
     *     The percentage of answer 5.
     **/
    $Row['PERCENT5'] = round ($Row['votes5'] * 100.0 / $Total);
    /**
     * \li <b>PERCENT6</b>\n
     *     The percentage of answer 6.
     **/
    $Row['PERCENT6'] = round ($Row['votes6'] * 100.0 / $Total);

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
       * \li <b>edit</b>\n
       *     Vote request. You must specify the answer code in the
       *     $_GET['answer'] field.
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
       * \li <b>doedit</b>\n
       *     Vote operation. You must specify the answer code in the
       *     $_GET['answer'] field.
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
	$this->CalculatedFields ($Row);
	$this->DATA_ENGINE->UpdateRow ($OldRow, $Row, $this);
	setcookie ('plvoted', 'true', strtotime ($this->GetOption ('expiration')));

      case 'browse':
	$this->AppendToContent ('browse.src');
	return TRUE;
      }

    return parent::RunAction ($Action);
  }


  /// @privatesection

  function tipPoll ()
  {
    $this->tipModule ();
    $this->StartQuery ('ORDER BY `date` DESC LIMIT 1');
    $this->ResetRow ();
    // No EndQuery() call to retain this row as default row
  }
}

?>
