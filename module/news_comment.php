<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */


/**
 * Add comments to TIP_News
 *
 * @package TIP
 * @todo Implement a generic comment block
 */
class TIP_News_Comment extends TIP_Block
{
    function commandAddRow($params)
    {
        $row['_news'] = TIP::getGet('id', 'int');
        if (empty($row['_news'])) {
            TIP::error('no news specified');
            return false;
        }
        $row['_creation'] = TIP::formatDate('datetime_iso8601');
        $row['_user'] = TIP::getUserId();
        return !is_null($this->addRow($row, false, false));
    }

    function runManagerAction($action)
    {
        switch ($action) {
        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::notifyError('noparams');
                return false;
            }

            $row =& $this->data->getRow($id);
            if (is_null($row)) {
                TIP::notifyError('notfound');
                return false;
            }

            return !is_null($this->editRow($row));
        }

        return null;
    }

    function runAdminAction($action)
    {
        switch ($action) {
        case 'delete':
            // TODO
            return false;
        }

        return null;
    }

    function runTrustedAction($action)
    {
        switch ($action) {
        case 'add':
            $row['_creation'] = TIP::formatDate('datetime_iso8601');
            $row['_user'] = TIP::getUserId();
            return !is_null($this->addRow($row));
        }

        return null;
    }
}

return 'TIP_News_Comment';

?>
