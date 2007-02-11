<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */


/**
 * Add comments to TIP_News
 *
 * @package TIP
 */
class TIP_News_Comment extends TIP_Block
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
	    error ('NWS_COMMENTREQ');
	    return FALSE;
	  }

	$this->AppendToContent ('comment.src');
	return TRUE;

      case 'doadd':
	if (! $User->IsAllowed ('news_comment'))
	  {
	    error ('VL_DENIED');
	    return FALSE;
	  }

	$NewsId = @$_POST['id'];
	if (empty ($NewsId))
	  {
	    error ('NWS_NEWSREQ');
	    return FALSE;
	  }

	$Content = @$_POST['content'];
	if (empty ($Content))
	  {
	    error ('NWS_COMMENTREQ');
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

return new TIP_News_Comment;

?>
