<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_News definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * News and blog management
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_News extends TIP_Block
{
    // Overriden:

    function RunAction ($Action)
    {

        switch ($Action)
        {
            /**
             * \action <b>view</b>\n
             * Detailed view of a specific news, including its comments. You
             * must specify the news id in the $_GET['id'] field.
             */
        case 'view':
            $id = TIP::GetGet ('id', 'integer');
            if (! $id > 0)
            {
                TIP::error ('E_NOTSPECIFIED');
                return FALSE;
            }

            $Query = $this->DATA_ENGINE->QueryById ($id, $this);
            if (! $this->StartView ($Query))
            {
                TIP::error ('E_SELECT');
                return FALSE;
            }

            if ($this->RowsCount () < 1)
            {
                TIP::error ('E_NOTFOUND');
                $this->EndView ();
                return FALSE;
            }

            $this->ResetRow ();
            $NewRow =& $this->GetCurrentRow ();
            $OldRow = $NewRow;
            $NewRow['_hits'] += 1;
            $NewRow['_lasthit'] = TIP::formatDate('datetime_iso8601');
            $this->DATA_ENGINE->UpdateRow ($OldRow, $NewRow, $this);

            $this->AppendToContent ('view.src');
            $this->EndView ();

            return TRUE;

        case 'browse':
            global $USER;
            if (! $USER->Populate () || ! $USER->ID)
            {
                error ('E_DENIED');
                return FALSE;
            }

            $this->Spopulate ();
            $this->TABLE->SetQuery ("WHERE `_user`=$USER->ID ORDER BY `_creation` DESC");
            $this->EchoInContent ('browse-user.src');
            return TRUE;

        case 'add':
            if (! $User->IsAllowed ('news_add'))
            {
                error ('NWS_DENIED');
                return FALSE;
            }

            if ($this->PopulateWithSource ($_POST))
                $this->EchoInContent ('add-edit.html');
            return TRUE;

        case 'doadd':
            global $USER, $FIELDS;
            if ($USER && ! $USER->IsAllowed ('news_add'))
            {
                error ('VL_DENIED');
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

            $id =& $this->ROW['id'];
            if (@empty ($this->ROW['id']))
            {
                error ('NWS_NOTSPECIFIED');
                $FIELDS['ACTION'] = 'edit';
                $this->EchoInContent ('add-edit.html');
                return FALSE;
            }

            if (! $this->UpdateTable ())
            {
                error ('DB_UPDATE');
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
            $id = @$_GET['id'];

            if (! $this->AccessAllowed ($id))
                return FALSE;

            if ($this->data->deleteRow($id)) {
                global $NEWS_COMMENT;
                $NEWS_COMMENT->SetQuery ('WHERE `_news`=' . $id);
                $NEWS_COMMENT->DeleteTable ();
            } else {
                TIP::error('E_DATA_DELETE');
            }

            $this->Spopulate ();
            $GLOBALS['FIELDS']['ACTION'] = 'browse';
            $this->actionBrowse ();
            return TRUE;
        }

        return parent::RunAction ($Action);
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

return new TIP_News;

?>
