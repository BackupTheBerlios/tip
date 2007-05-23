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
class TIP_User extends TIP_Content
{
    private $_constructor_error = null;
    private $_row = null;
    private $_old_row = null;

    function _login()
    {
        if ($this->view) {
            $row =& $this->view->rowCurrent();
            $this->endView();
        }

        if (isset($row)) {
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
        $this->_row =& $row;
        $this->_old_row = $this->_row;
        $this->keys['CID'] = is_null($row) ? null : $row['id'];
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
            return array('user' => TIP::getLocale('error.select', 'notify', null, false));
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

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_User instance and performs the user authentication.
     *
     * Notice in this constructor no external modules can be called, because
     * many of them (if not all) depend on TIP_User. So the eventual errors
     * are stored in the $_constructor_error private property and processed
     * in the postConstructor() method.
     *
     * @param mixed $id Identifier of this instance
     */
    function __construct($id)
    {
        parent::__construct($id);

        $this->keys['CID'] = null;

        // Get user id and password from the TIP_User cookie
        @list($id, $password) = explode(',', TIP::getCookie('TIP_User', 'string'), 2);
        if (is_null($id) || is_null($password)) {
            // Anonymous access
            return;
        }

        // Get user id and password from the data source
        $view =& $this->startView($this->data->rowFilter((int) $id));
        if (is_null($view)) {
            $this->_constructor_error = 'select';
            return;
        }

        $row =& $view->rowReset();
        $this->endView();

        if (is_null($row)) {
            // User id not found in the data source
            $this->_constructor_error = 'notfound';
        } elseif (crypt($row['password'], $password) != $password) {
            // Invalid password
            $this->_constructor_error = 'denied';
        } else {
            $this->_activateUser($row);
        }
    }

    /**
     * Custom post construction method
     *
     * Overrides the default post-constructor method appending the constructor
     * error processing.
     */
    function postConstructor()
    {
        parent::postConstructor();
        if (isset($this->_constructor_error)) {
            TIP::notifyError($this->_constructor_error);
            $this->_constructor_error = null;
        }
    }

    /**
     * Destructor
     *
     * Updates the record of the current logged-in user, if any.
     */
    function __destruct()
    {
        if (!is_array($this->_old_row) || !is_array($this->_row)) {
            return;
        }

        // Update statistic fields
        if (array_key_exists('_hits', $this->_row)) {
            $this->_row['_hits'] ++;
        }
        if (array_key_exists('_lasthit', $this->_row)) {
            $this->_row['_lasthit'] = TIP::formatDate('datetime_iso8601');
        }

        $this->data->updateRow($this->_row, $this->_old_row);
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
            $this->appendToPage('browse.src');
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
                $this->appendToPage('conditions.src');
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
        return parent::startView($filter, array('on_row' => array(&$this, '_onRow')));
    }

    /**#@-*/

    /**
     * Get a field value
     *
     * Overrides the default method accessing the _row private property
     * if there is no current view.
     *
     * @param  string     $id The field id
     * @return mixed|null     The field value or null on errors
     */
    public function getField($id)
    {
        if (is_null($result = parent::getField($id))) {
            $result = $this->getLoggedField($id);
        }

        return $result;
    }

    /**
     * Get a field value of the logged user
     *
     * Retrieves a field value for the current logged-in user. If no user is
     * logged or the field is not found, it returns null.
     *
     * @param  string     $id The field id
     * @return mixed|null     The field value or null on errors
     */
    public function getLoggedField($id)
    {
        return @$this->_row[$id];
    }

    /**
     * Set a field value of the logged user
     *
     * Changes a field value for the current logged-in user. If no user is
     * logged or the field is not found, it returns false.
     *
     * @param  string $id    The field id
     * @param  mixed  $value The new field value
     * @return bool          true on success or false otherwise
     */
    public function setLoggedField($id, $value)
    {
        if (!@array_key_exists($id, $this->_row)) {
            return false;
        }

        $this->_row[$id] = $value;
        return true;
    }
}
?>
