<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Content definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Generic content management
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Content extends TIP_Block
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

    function TIP_Content($id)
    {
        $this->TIP_Block($id);
    }

    /**#@+
     * @param string $action The action name
     * @return bool|null true on action executed, false on action error or
     *                   null on action not found
     */

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
                TIP::warning('no id specified');
                TIP::notifyError('noparams');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete'))
            );
            return !is_null($processed);
        }

        return parent::runManagerAction($action);
    }

    function runAdminAction($action)
    {
        switch ($action) {

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::warning('no id specified');
                TIP::notifyError('noparams');
                return false;
            }
            if (!$this->rowOwner($id)) {
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete'))
            );
            return !is_null($processed);
        }

        return parent::runAdminAction($action);
    }

    function runTrustedAction($action)
    {
        switch ($action) {

        case 'add':
            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'valid_render' => TIP_FORM_RENDER_NOTHING
            ));

            if ($processed) {
                $id = $this->data->getLastId();
                if (empty($id) || is_null($this->getRow($id, false))) {
                    return false;
                }

                $this->appendToContent('view.src');
                $this->endView();
            }
            return !is_null($processed);

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
        }

        return parent::runTrustedAction($action);
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

            $row =& $this->view->rowReset();
            if (!$row) {
                TIP::notifyError('notfound');
                $this->endView();
                return false;
            }

            if (array_key_exists('_public', $row) && !$row['_public']) {
                TIP::notifyError('denied');
                $this->endView();
                return false;
            }

            $this->appendToContent('view.src');
            $this->endView();

            if (array_key_exists('_hits', $row)) {
                $old_row = $row;
                $row['_hits'] += 1;
                $row['_lasthit'] = TIP::formatDate('datetime_iso8601');
                $this->data->updateRow($row, $old_row);
            }

            return true;

        case 'browse':
            $user = TIP::getGet('user', 'integer');
            if ($user) {
                $filter[] = $this->data->filter('_user', $user);
            }

            $group = TIP::getGet('group', 'integer');
            if ($group) {
                $filter[] = $this->data->filter('group', $group);
            }

            if (!$this->startView(implode(' AND ', $filter))) {
                TIP::notifyError('select');
                return false;
            }

            $this->appendToContent('browse.src');
            $this->endView();
            return true;
        }

        return parent::runUntrustedAction($action);
    }

    /**#@-*/

    /**#@-*/
}
?>
