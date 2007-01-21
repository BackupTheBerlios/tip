<?php

/**
 * A data view internally used by the tipModule type.
 **/
class tipView extends tip
{
  /// @publicsection

  var $MODULE;
  var $QUERY;
  var $ROWS;
  var $SUMMARY_FIELDS;
  var $ON_ROW;
  var $ON_ROWS;

  function tipView (&$Module, $Query)
  {
    $this->MODULE =& $Module;
    $this->QUERY =& $Query;
    $this->ROWS = NULL;
    $this->SUMMARY_FIELDS['COUNT'] = 0;
    $this->ON_ROW =& new tipCallback;
    $this->ON_ROWS =& new tipCallback;
  }

  function Populate ()
  {
    if (! $this->GetRows ())
      return FALSE;
    if (! is_array ($this->ROWS))
      return TRUE;

    $nRow = 1;
    foreach (array_keys ($this->ROWS) as $Id)
      {
	$Row =& $this->ROWS[$Id];
	$Row['ROW'] = $nRow;
	$Row['ODDEVEN'] = ($nRow & 1) > 0 ? 'odd' : 'even';

	if ($this->ON_ROW->Go (array (&$Row)))
	  ++ $nRow;
	else
	  unset ($this->ROWS[$Id]);
      }

    $this->SUMMARY_FIELDS['COUNT'] = $nRow-1;
    return $this->ON_ROWS->Go (array (&$this));
  }


  /// @protectedsection

  function GetRows ()
  {
    $this->ROWS =& $this->MODULE->DATA_ENGINE->GetRows ($this->QUERY, $this->MODULE);
    return $this->ROWS !== FALSE;
  }
}

class tipFieldView extends tipView
{
  /// @publicsection

  function tipFieldView (&$Module)
  {
    $this->tipView ($Module, '__FIELD__');
  }


  /// @protectedsection

  function GetRows ()
  {
    $this->ROWS =& $this->MODULE->DATA_ENGINE->GetFields ($this->MODULE);
    return TRUE;
  }
}

class tipModuleView extends tipView
{
  /// @publicsection

  function tipModuleView (&$Module)
  {
    $this->tipView ($Module, '__MODULE__');
  }


  /// @protectedsection

  function GetRows ()
  {
    global $CFG;
    foreach (array_keys ($CFG) as $ModuleName)
      {
	$Instance =& tipType::GetInstance ($ModuleName, FALSE);
	if (is_subclass_of ($Instance, 'tipModule'))
	  $this->ROWS[$ModuleName] = array ('id' => $ModuleName);
      }
    return TRUE;
  }
}

?>
