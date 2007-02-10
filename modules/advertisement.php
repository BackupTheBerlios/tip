<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Advertisement definition file
 * @package Modules
 **/

/**
 * The advertisement block
 *
 * Enables advertisement management.
 *
 * @package Modules
 **/
class TIP_Advertisement extends TIP_Block
{
    /**#@+ @access private */

    function& _getSubjectRow()
    {
        $row = null;

        $advertisement_id = TIP::getGet('advertisement', 'int');
        if (is_null($advertisement_id)) {
            TIP::error('E_NOTSPECIFIED');
            return $row;
        }

        $view =& $this->startView($this->data->rowFilter($advertisement_id));
        if (is_null($view)) {
            TIP::error('E_DATA_SELECT');
            return $row;
        }

        $row =& $this->getRow($advertisement_id);
        if (is_null($row)) {
            TIP::error('E_NOTFOUND');
        }

        $this->endView ();
        return $row;
    }

    function _isOwner(&$row)
    {
        $user_id = TIP::getUserId();
        if (is_null($user_id) || $user_id === FALSE) {
            TIP::error('E_RESERVED');
            return false;
        }

        return $row['_user'] == $user_id;
    }


    /**#@-*/


    /**#@+ @access protected */

    function runAction($action)
    {
        switch ($action)
        {
            /**
             * \action <b>browse</b>\n
             * Shows the advertisement published by the current user.
             * The user must be registered to do this.
             **/
        case 'browse':
            $user_id = TIP::GetUserId ();
            if (is_null ($user_id) || $user_id === FALSE)
            {
                TIP::error ('E_RESERVED');
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
                TIP::error ('E_NOTSPECIFIED');
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
            $row =& $this->_getSubjectRow($Id);
            if (is_null($row)) {
                return false;
            }

            $this->appendToContent ('view.src');

            $old_row = $row;
            $row['_hits'] ++;
            $row['_lasthit'] = TIP::formatDate('datetime_iso8601');
            if (! $this->data->updateRow($old_row, $row)) {
                TIP::error('E_DATA_UPDATE');
                return false;
            }

            return true;

            /**
             * \action <b>update</b>\n
             * Requests an update on the specified advertisement. You must
             * specify in $_GET['advertisement'] the advertisement id. Update means to
             * post the expiration date as the advertisement was published 
             * today.
             **/
        case 'update':
            /**
             * \action <b>doupdate</b>\n
             * Updates the specified advertisement. You must specify
             * in $_GET['advertisement'] the advertisement id. Update means to post the
             * expiration date as the advertisement was published today.
             **/
        case 'doupdate':
            $row =& $this->_getSubjectRow();
            if (is_null($row) || ! $this->isOwner($row)) {
                return false;
            }

            if (substr ($action, 0, 2) == 'do')
            {
                $old_row = $row;
                $expiration = strtotime($this->getOption('expiration'));
                $row['_expiration'] = TIP::formatDate('datetime_iso8601', $expiration);
                if (! $this->engine->updateRow ($old_row, $row, $this))
                    TIP::error ('E_DATA_UPDATE');
                else
                    TIP::info ('I_DONE');

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
             * specify in $_GET['advertisement'] the advertisement id.
             **/
        case 'delete':
            /**
             * \action <b>dodelete</b>\n
             * Deletes the specified advertisement. You must specify in
             * $_GET['advertisement'] the advertisement id.
             **/
        case 'dodelete':
            $row =& $this->_getSubjectRow();
            if (is_null($row) || ! $this->isOwner($row)) {
                return false;
            }

            if (substr ($action, 0, 2) == 'do')
            {
                $Query = $this->DATA_ENGINE->QueryById ($Id, $this);
                if (is_null ($Query) || ! $this->DATA_ENGINE->DeleteRows ($Query, $this))
                {
                    TIP::error ('E_DATA_DELETE');
                }
                else
                {
                    $Rows =& $this->GetCurrentRows ();
                    unset ($Rows[$Id]);
                    TIP::info ('I_DONE');
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
            $user_id = TIP::GetUserId ();
            if (is_null ($user_id) || $user_id === FALSE)
            {
                TIP::error ('E_RESERVED');
                return FALSE;
            }

            $Id = tip::GetGet ('id', 'integer');
            if (is_null ($Id))
            {
                TIP::error ('E_NOTSPECIFIED');
                return FALSE;
            }

            $Query = $this->DATA_ENGINE->QueryById ($Id, $this);
            if (! $this->StartView ($Query))
            {
                TIP::error('E_DATA_SELECT');
                return FALSE;
            }

            if (! $this->ResetRow ())
            {
                TIP::error ('E_NOTFOUND');
                $this->EndView ();
                return FALSE;
            }

            $row =& $this->GetCurrentRow ();

            if (substr ($action, 0, 2) == 'do')
            {
                $old_row = $row;
                $row['_illegal'] = 'yes';
                $row['_illegalby'] = $user_id;
                $row['_illegalon'] = TIP::formatDate('datetime_iso8601');

                if (! $this->DATA_ENGINE->UpdateRow ($old_row, $row, $this))
                {
                    TIP::error ('E_DATA_UPDATE');
                }
                else
                {
                    $User =& TIP_Module::getInstance('user');
                    $UserRow =& $User->GetCurrentUser ();
                    if (is_array ($UserRow))
                        $UserRow['_signaled'] ++;

                    $Query = $User->DATA_ENGINE->QueryById ($row['_user'], $User);
                    if ($Query)
                    {
                        $RealQuery = "UPDATE `tip_user` SET `_ownsignaled`=`_ownsignaled`+1 $Query";
                        $User->DATA_ENGINE->runQuery ($RealQuery);
                    }

                    TIP::info ('I_DONE');
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
                TIP::error ('E_NOTSPECIFIED');
                return FALSE;
            }

            $Query = $this->DATA_ENGINE->QueryById ($Id, $this);
            if (! $this->StartView ($Query))
            {
                TIP::error('E_DATA_SELECT');
                return FALSE;
            }

            if (! $this->ResetRow ())
            {
                TIP::error ('E_NOTFOUND');
                $this->EndView ();
                return FALSE;
            }

            switch ($action)
            {
            case 'dolegalize':
                $row =& $this->GetCurrentRow ();
                $old_row = $row;
                $row['_public'] = 'yes';
                $row['_illegal'] = 'no';

                if (! $this->DATA_ENGINE->UpdateRow ($old_row, $row, $this))
                    TIP::error ('E_DATA_UPDATE');
                else
                    TIP::info ('I_DONE');

                $this->AppendToContent ('browse-illegal.src');
                break;

            case 'doillegalize':
                $row =& $this->GetCurrentRow ();
                $old_row = $row;
                $row['_public'] = 'no';

                if (! $this->DATA_ENGINE->UpdateRow ($old_row, $row, $this))
                {
                    TIP::error ('E_DATA_UPDATE');
                }
                else
                {
                    $User =& TIP_Module::getInstance('user');
                    $UserRow =& $User->GetCurrentUser ();
                    if (is_array ($UserRow))
                        $UserRow['_signaled'] ++;

                    $Query = $User->DATA_ENGINE->QueryById ($row['_user'], $User);
                    if ($Query)
                    {
                        $RealQuery = "UPDATE `tip_user` SET `_ownillegalized`=`_ownillegalized`+1 $Query";
                        $User->DATA_ENGINE->runQuery ($RealQuery);
                    }

                    $Query = $User->DATA_ENGINE->QueryById ($row['_illegalby'], $User);
                    if ($Query)
                    {
                        $RealQuery = "UPDATE `tip_user` SET `_illegalized`=`_illegalized`+1 $Query";
                        $User->DATA_ENGINE->runQuery ($RealQuery);
                    }

                    TIP::info ('I_DONE');
                }

                $this->AppendToContent ('browse-illegal.src');
                break;

            default:
                $this->AppendToContent ('check.src');
            }

            $this->EndView ();
            return TRUE;
        }

        return parent::runAction ($action);
    }

    /**#@-*/
}

return new TIP_Advertisement;

?>
