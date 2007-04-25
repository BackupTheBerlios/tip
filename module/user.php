<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 * @subpackage Module
 */


/**
 * User management
 *
 * This module provides user management to the site, allowing:
 *
 * - Logins and logouts
 * - User management
 * - Statistics on logged users
 *
 * The following keys are provided by this module:
 *
 * - 'CID': the user id of the current user, that is the user logged in.
 *          In anonymous sections, this field is not defined (it is null).
 *
 * @final
 * @package TIP
 * @subpackage Module
 */
class TIP_User extends TIP_Block
{
    /**#@+ @access private */

    var $_old_row = null;
    var $_new_row = null;


    function _login()
    {
        $row =& $this->view->rowCurrent();
        if (!empty($row)) {
            $this->_activateUser($row);
            $expiration = strtotime($this->getOption('expiration'));
            setcookie('TIP_User', $row['id'] . ',' . crypt($row['password']), $expiration);
        }
    }

    function _logout()
    {
        require_once 'HTTP/Session.php';

        $fake_null = null;
        $this->_activateUser($fake_null);
        setcookie('TIP_User', '', time()-3600);
        HTTP_Session::destroy();
    }

    function _activateUser(&$row)
    {
        $this->_new_row =& $row;
        $this->_old_row = $this->_new_row;

        if (is_null($row)) {
            $this->keys['CID'] = null;
        } else {
            $this->keys['CID'] = $row['id'];
            $row['_hits'] ++;
            $row['_lasthit'] = TIP::formatDate('datetime_iso8601');
        }

        // Refresh the new user id cache
        TIP::getUserId(true);

        // Refresh the privileges of the yet loaded modules
        $modules =& TIP_Module::singleton();
        foreach (array_keys($modules) as $id) {
            $modules[$id]->refreshPrivilege();
        }
    }

    function _onRow(&$row)
    {
        $row['OA'] = $row['sex'] == 'female' ? 'a' : 'o';
        return true;
    }

    function _checkLogin(&$input)
    {
        if (!$this->startView($this->data->filter('user', $input['user']) . ' LIMIT 1')) {
            TIP::notifyError('select');
            return array('user' => 'errore');
        }

        $row =& $this->view->rowReset();
        if (!$row) {
            $this->endView();
            return array('user' => $this->getLocale('notfound'));
        }

        if ($row['password'] != $input['password']) {
            $this->endView();
            return array('password' => $this->getLocale('wrongpassword'));
        }

        return true;
    }

    function _updateUser()
    {
        if (is_array($this->_old_row) && is_array($this->_new_row)) {
            $this->data->updateRow($this->_new_row, $this->_old_row);
        }
    }

    /**#@-*/


    /**#@+ @access protected */

    function postConstructor()
    {
        parent::postConstructor();

        @list($id, $password) = explode(',', TIP::getCookie('TIP_User', 'string'), 2);
        if (is_null($id) || is_null($password)) {
            return;
        }

        $filter = $this->data->rowFilter($id);
        $view =& $this->startView($filter);
        if (is_null($view)) {
            TIP::notifyError('select');
            return;
        }

        $row =& $view->rowReset();
        if (is_null($row)) {
            $this->_logout();
            $this->endView();
            return;
        }

        if (crypt($row['password'], $password) != $password) {
            TIP::notifyError('denied');
            $this->endView();
            return;
        }

        // No endView() call to retain this query as the default one
        $this->_activateUser($row);
        register_shutdown_function(array(&$this, '_updateUser'));
    }

    function runManagerAction($action)
    {
        switch ($action) {

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'int'))) {
                $id = TIP::getPost('id', 'int');
            }
            return !is_null($this->form(TIP_FORM_ACTION_EDIT, $id));
        }

        return parent::runManagerAction($action);
    }

    function runAdminAction($action)
    {
        switch ($action) {

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                return null;
            }
            return !is_null($this->form(TIP_FORM_ACTION_DELETE, $id));
        }

        return parent::runAdminAction($action);
    }

    function runTrustedAction($action)
    {
        switch ($action) {

        case 'browse':
            $this->appendToContent('browse.src');
            return true;
        }

        return parent::runTrustedAction($action);
    }

    function runUntrustedAction($action)
    {
        switch ($action) {

        case 'delete':
            $processed = $this->form(TIP_FORM_ACTION_DELETE, null, array(
                'referer' => TIP::getRootUrl()
            ));
            if ($processed) {
                $this->_logout();
            }

            return !is_null($processed);

        case 'unset':
            $this->_logout();
            return true;

        case 'edit':
            $processed = $this->form(TIP_FORM_ACTION_EDIT, null, array(
                'buttons' => TIP_FORM_BUTTON_SUBMIT+TIP_FORM_BUTTON_CANCEL+TIP_FORM_BUTTON_DELETE
            ));
            return !is_null($processed);
        }

        return parent::runUntrustedAction($action);
    }

    function runAction($action)
    {
        switch ($action) {

        case 'set':
            $fields =& $this->data->getFields();
            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'fields'        => array(
                    'user'      => $fields['user'],
                    'password'  => $fields['password']
                ),
                'command'       => 'set',
                'validator'     => array(&$this, '_checkLogin'),
                'on_process'    => array(&$this, '_login'),
                'valid_render'  => TIP_FORM_RENDER_NOTHING
            ));
            return !is_null($processed);

        case 'add':
            if (TIP::getGet('accept', 'int') == 1) {
                $this->appendToContent('conditions.src');
                return true;
            }

            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'defaults'      => array(
                    '_creation' => TIP::formatDate('datetime_iso8601'),
                    '_hits'     => 1,
                    '_lasthit'  => TIP::formatDate('datetime_iso8601')
                )
            ));

            if ($processed &&
                !is_null($id = $this->data->getLastId()) &&
                !is_null($filter = $this->data->rowFilter($id)) &&
                !is_null($view =& $this->startView($filter)) &&
                !is_null($view->rowReset())) {
                $this->_login();
            }

            return !is_null($processed);
        }

        return parent::runAction($action);
    }

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_row->set(array(&$this, '_onRow'));
        return $this->push($view);
    }

    /**#@-*/
}

return 'TIP_User';

?>
