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
    /**#@+
     * @param string $action The action name
     * @return bool|null true on action executed, false on action error or
     *                   null on action not found
     */

    /**
     * Executes a management action
     *
     * Executes an action that requires the 'manager' privilege.
     */
    function runManagerAction($action)
    {
        return null;
    }

    /**
     * Executes an administrator action
     *
     * Executes an action that requires at least the 'admin' privilege.
     */
    function runAdminAction($action)
    {
        switch ($action) {
        case 'add':
            $row['_user'] = TIP::getUserId();
            $row['_creation'] = TIP::formatDate('datetime_iso8601');
            $row['_hits'] = 1;
            $row['_lasthit'] = $row['_creation'];
            $processed = $this->addRow($row, false);
            if (is_null($processed)) {
                return false;
            } elseif ($processed) {
                $id = TIP::getPost('id', 'integer');
                $filter = $this->data->rowFilter($id);
                if (!$this->startView($filter)) {
                    TIP::notifyError('E_SELECT');
                    return false;
                }
                if (! $this->view->rowReset()) {
                    TIP::notifyError('E_NOTFOUND');
                    $this->endView();
                    return false;
                }
                $this->appendToContent('view.src');
                $this->endView();
            }
            return true;

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::notifyError('E_NOTSPECIFIED');
                return false;
            }

            $row =& $this->data->getRow($id);
            if (is_null($row)) {
                TIP::notifyError('E_NOTFOUND');
                return false;
            }

            $processed = $this->editRow($row, false);
            if (is_null($processed)) {
                return false;
            } elseif ($processed) {
                $id = TIP::getPost('id', 'integer');
                $filter = $this->data->rowFilter($id);
                if (!$this->startView($filter)) {
                    TIP::notifyError('E_SELECT');
                    return false;
                }
                if (! $this->view->rowReset()) {
                    TIP::notifyError('E_NOTFOUND');
                    $this->endView();
                    return false;
                }
                $this->appendToContent('view.src');
                $this->endView();
            }
            return true;

        case 'delete':
            // TODO
            return false;
        }

        return null;
    }

    /**
     * Executes a trusted action
     *
     * Executes an action that requires at least the 'trusted' privilege.
     */
    function runTrustedAction($action)
    {
        switch ($action) {
        case 'browse':
            return $this->appendToContent('browse-user.src');
        }

        return null;
    }

    /**
     * Executes an untrusted action
     *
     * Executes an action that requires at least the 'untrusted' privilege.
     */
    function runUntrustedAction($action)
    {
        return null;
    }

    /**
     * Executes an unprivileged action
     *
     * Executes an action that does not require any privileges.
     */
    function runAction($action)
    {
        switch ($action) {
        case 'view':
            $id = TIP::getGet('id', 'integer');
            if (is_null($id)) {
                TIP::notifyError('E_NOTSPECIFIED');
                return false;
            }

            $filter = $this->data->rowFilter($id);
            if (!$this->startView($filter)) {
                TIP::notifyError('E_SELECT');
                return false;
            }

            if (! $this->view->rowReset()) {
                TIP::notifyError('E_NOTFOUND');
                $this->endView();
                return false;
            }

            $row =& $this->view->rowCurrent();
            $old_row = $row;
            $row['_hits'] += 1;
            $row['_lasthit'] = TIP::formatDate('datetime_iso8601');
            $this->data->updateRow($row, $old_row);

            $this->appendToContent('view.src');
            $this->endView();
            return true;
        }

        return null;
    }

    /**#@-*/
}

return 'TIP_News';

?>
