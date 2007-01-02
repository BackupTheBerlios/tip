<?php

class tipLogger extends tipModule
{
  /// @publicsection

  function LogMessage ($Domain, $Message, $Uri, $Notify = FALSE)
  {
    $UserId = tipApplication::GetUserId ();
    if ($UserId > 0)
      $Row['user'] = $UserId;

    if ($Notify)
      $Row['notify'] = $Notify;

    $Row['when'] = tip::FormatDate (FALSE, 'now', 'datetime_iso8601');
    $Row['domain'] = $Domain;
    $Row['message'] = $Message;
    $Row['uri'] = $Uri;

    $this->DATA_ENGINE->PutRow ($Row, $this);
  }


  /// @privatesection

  function tipLogger ()
  {
    $this->tipModule ();
  }
}

?>
