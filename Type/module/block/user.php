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

    var $_constructor_error = null;
    var $_old_row = null;
    var $_new_row = null;


    function _login()
    {
        $row =& $this->view->rowCurrent();
        if ($row) {
            $this->_activateUser($row);
            $expiration = strtotime($this->getOption('expiration'));
            setcookie('TIP_User', $row['id'] . ',' . crypt($row['password']), $expiration);
            $this->_refreshUser();
        } else {
            $this->_logout();
        }
    }

    function _logout()
    {
        require_once 'HTTP/Session.php';

        $fake_null = null;
        $this->_activateUser($fake_null);
        setcookie('TIP_User', '', time()-3600);
        HTTP_Session::destroy();
        $this->_refreshUser();
    }

    function _activateUser(&$row)
    {
        $this->_new_row =& $row;
        $this->_old_row = $this->_new_row;

        if (is_null($row)) {
            $this->keys['CID'] = null;
        } else {
            $this->keys['CID'] = $row['id'];
            register_shutdown_function(array(&$this, '_updateUser'));

            if (array_key_exists('_hits', $row)) {
                $row['_hits'] ++;
            }

            if (array_key_exists('_lasthit', $row)) {
                $row['_lasthit'] = TIP::formatDate('datetime_iso8601');
            }
        }
    }

    function _refreshUser()
    {
        // Refresh the new user id cache
        TIP::getUserId(true);

        // Refresh the privileges of the yet loaded modules
        $this->_refreshModule(TIP_Type::singleton(array('module')));
    }

    function _refreshModule(&$module)
    {
        if (is_array($module)) {
            array_walk($module, array(&$this, '_refreshModule'));
        } else {
            $module->_privilege = TIP::getPrivilege($module->getId(), $this->keys['CID']);
            $module->refreshPrivileges();
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

    /**
     * Constructor
     *
     * Initializes a TIP_User instance.
     *
     * Performs the same initializations of TIP_Block, adding the user
     * authentication feature.
     *
     * Notice in this constructor no external modules can be called, because
     * many of them (if not all) depend on TIP_User. So the eventual errors
     * are stored in the $_constructor_error private property and processed
     * in the postConstructor() method.
     *
     * @param mixed $id Identifier of this instance
     */
    function TIP_User($id)
    {
        $this->TIP_Block($id);

        $this->keys['CID'] = null;

        // Get user id and password from the TIP_User cookie
        @list($id, $password) = explode(',', TIP::getCookie('TIP_User', 'string'), 2);
        if (is_null($id) || is_null($password)) {
            return;
        }

        // Get user id and password from the data source
        $view =& $this->startView($this->data->rowFilter((int) $id));
        if (is_null($view)) {
            $this->_constructor_error = 'select';
            return;
        }

        // Check for user id presence in the data source
        $row =& $view->rowReset();
        if (is_null($row)) {
            $this->endView();
            $this->_constructor_error = 'notfound';
            return;
        }

        // Check for password validity
        if (crypt($row['password'], $password) != $password) {
            $this->endView();
            $this->_constructor_error = 'denied';
            return;
        }

        // No endView() call to retain this query as the default one
        $this->_activateUser($row);
    }

    /**
     * Custom post construction method
     *
     * Overrides the default post-constructor method providing the constructor
     * error processing, as described in TIP_User().
     */
    function postConstructor()
    {
        TIP_Block::postConstructor();
        if (isset($this->_constructor_error)) {
            TIP::notifyError($this->_constructor_error);
            $this->_constructor_error = null;
        }
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
            $processed = $this->form(TIP_FORM_ACTION_DELETE);
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

            $processed = $this->form(TIP_FORM_ACTION_ADD);
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
        return TIP_Block::startView($filter, array('on_row' => array(&$this, '_onRow')));
    }

    /**#@-*/
}
?>
