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
    /**#@+ @access private */

    function _onDelete(&$form, &$row)
    {
        $id = $row[$this->data->getPrimaryKey()];
        if (empty($id)) {
            TIP::error('no primary key found');
            return;
        }

        $comments =& TIP_Module::getInstance($this->getId() . '_comments');
        if ($comments->parentRemoved($id)) {
            $form->process($row);
        }
    }

    /**#@-*/


    /**#@+ @access protected */

    function runManagerAction($action)
    {
        switch ($action) {

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_EDIT, $id);
            return !is_null($processed);

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete'))
            );
            return !is_null($processed);
        }

        return null;
    }

    function runAdminAction($action)
    {
        switch ($action) {

        case 'add':
            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'defaults' => array(
                    '_creation' => TIP::formatDate('datetime_iso8601'),
                    '_user'     => TIP::getUserId(),
                    '_hits'     => 1,
                    '_lasthit'  => TIP::formatDate('datetime_iso8601'),
                    '_comments' => 0
                ),
                'valid_render'  => TIP_FORM_RENDER_NOTHING
            ));

            if ($processed) {
                $id = $this->data->getLastId();
                if (empty($id) || is_null($this->getRow($id, false))) {
                    return false;
                }

                $this->appendToContent('view.src');
                $this->endView();
            }
            return is_null($processed);

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }

            if (!$this->rowOwner($id)) {
                return false;
            }

            $processed = $this->form(TIP_FORM_ACTION_EDIT, $id);
            return !is_null($processed);

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }

            if (!$this->rowOwner($id)) {
                return false;
            }

            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete'))
            );
            return !is_null($processed);

        case 'browse':
            return $this->appendToContent('browse-user.src');
        }

        return null;
    }

    function runUntrustedAction($action)
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

            if (!$this->view->rowReset()) {
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
