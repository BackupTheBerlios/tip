<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Static definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Static module
 *
 * Provides a basic interface to the localization of long texts (more than
 * 256 bytes).
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Static extends TIP_Block
{
    /**#@+ @access protected */

    function TIP_Static($id)
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

        case 'add':
            return !is_null($this->form(TIP_FORM_ACTION_ADD));

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::notifyError('noparams');
                return false;
            }
            return !is_null($this->form(TIP_FORM_ACTION_DELETE, $id));
        }

        return parent::runManagerAction($action);
    }

    function runAdminAction($action)
    {
        switch ($action) {

        case 'browse':
            $group = TIP::getGet('group', 'integer');
            if (isset($group)) {
                return null;
            }

            $this->appendToContent('browse-all.src');
            return true;

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::notifyError('noparams');
                return false;
            }
            return !is_null($this->form(TIP_FORM_ACTION_EDIT, $id));
        }

        return parent::runAdminAction($action);
    }

    function runUntrustedAction($action)
    {
        switch ($action) {

        case 'browse':
            $group = TIP::getGet('group', 'integer');
            if (is_null($group)) {
                TIP::notifyError('noparams');
                return false;
            }

            $hierarchy =& TIP_Module::getInstance('static_hierarchy');
            $hierarchy->setCurrent($group);
            $this->appendToContent('browse.src');
            return true;

        case 'view':
            $id = TIP::getGet('id', 'integer');
            if (is_null($id)) {
                TIP::notifyError('noparams');
                return false;
            }

            $view =& $this->startView($this->data->filter('id', $id));
            if (is_null($view)) {
                TIP::error('select');
                return false;
            }

            if ($view->rowReset()) {
                $this->appendToContent('view.src');
            } else {
                TIP::notifyError('notfound');
            }

            $this->endView();
            return true;
        }

        return parent::runUntrustedAction($action);
    }

    /**#@-*/

    /**#@-*/
}
?>
