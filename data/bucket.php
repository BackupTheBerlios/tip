<?php
   
/**
 * Bucket data engine.
 *
 * Dummy data engine that simply does nothing.
 * Anyway, all the requested functions return succesful results and log a
 * warning message for debugging purpose.
 *
 * @todo Must be implemented a function to show the row context, so you
 *       can see it in the logged warnings.
 **/
class tipBucket extends tipData
{
  /// @protectedsection

  function RealQueryById ($Id, &$Context)
  {
    $this->LogWarning (__FUNCTION__ . "($Id, $Context->DATAID)");
    return '';
  }

  function& Select ($Query, &$Context)
  {
    $this->LogWarning (__FUNCTION__ . "($Query, $Context->DATAID)");
    $Rows = array ();
    return $Rows;
  }

  function Insert (&$Row, &$Context)
  {
    $this->LogWarning (__FUNCTION__ . "(Row, $Context->DATAID)");
    return TRUE;
  }

  function Update ($Query, &$Row, &$Context)
  {
    $this->LogWarning (__FUNCTION__ . "($Query, Row, $Context->DATAID)");
    return TRUE;
  }

  function Delete ($Query, &$Context)
  {
    $this->LogWarning ("Delete ($Query, $DataId)");
    return TRUE;
  }
}

?>
