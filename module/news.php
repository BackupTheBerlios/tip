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
            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'defaults' => array(
                    '_creation' => TIP::formatDate('datetime_iso8601'),
                    '_user'     => TIP::getUserId(),
                    '_hits'     => 1,
                    '_lasthit'  => $row['_creation'],
                    '_comments' => 0
                )
            ));
            if (is_null($processed)) {
                return false;
            } elseif ($processed) {
                $id = TIP::getPost('id', 'integer');
                $filter = $this->data->rowFilter($id);
                if (!$this->startView($filter)) {
                    TIP::notifyError('select');
                    return false;
                }
                if (! $this->view->rowReset()) {
                    TIP::notifyError('notfound');
                    $this->endView();
                    return false;
                }
                $this->appendToContent('view.src');
                $this->endView();
            }
            return true;

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_EDIT, $id);
            return !is_null($processed);

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

    function runAction($action)
    {
        switch ($action) {
        case 'view':
            $id = TIP::getGet('id', 'integer');
            if (is_null($id)) {
                TIP::notifyError('noparams');
                return false;
            }

            $filter = $this->data->rowFilter($id);
            if (!$this->startView($filter)) {
                TIP::notifyError('select');
                return false;
            }

            if (! $this->view->rowReset()) {
                TIP::notifyError('notfound');
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
