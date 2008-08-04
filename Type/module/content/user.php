<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_User definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
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
 * @package TIP
 * @subpackage Module
 */
class TIP_User extends TIP_Content
{
    //{{{ Properties

    /**
     * The template to run to view the registration conditions
     * @var string
     */
    protected $conditions_template = 'conditions';

    /**
     * The default expiration for the cookie
     * @var string
     */
    protected $expiration = '+1 year';

    //}}}
    //{{{ Constructor/destructor

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        TIP::arrayDefault($options, 'owner_field', 'id');
        TIP::arrayDefault($options, 'statistics', array());
        TIP::arrayDefault($options, 'browsable_fields', array(
            TIP_PRIVILEGE_ADMIN => array('__ALL__')
        ));

        return true;
    }

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
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);

        $this->keys['CID'] = null;

        // Get user id and password from the TIP_User cookie
        @list($id, $password) = explode(',', TIP::getCookie('TIP_User', 'string'), 2);
        if (is_null($id) || is_null($password)) {
            // Anonymous access
            return;
        }

        // Get user id and password from the data source
        $view = $this->startDataView($this->data->rowFilter((int) $id));
        if (is_null($view)) {
            $this->_constructor_error = 'select';
            return;
        }

        $this->_row =& $view->current();
        $this->endView();

        if (is_null($this->_row)) {
            // User id not found in the data source
            $this->_constructor_error = 'notfound';
        } elseif (crypt($this->_row['password'], $password) != $password) {
            // Invalid password
            $this->_constructor_error = 'denied';
        }
    }

    /**
     * Custom post construction method
     *
     * Overrides the default post-constructor method appending the constructor
     * error processing.
     */
    protected function postConstructor()
    {
        if (isset($this->_constructor_error)) {
            TIP::notifyError($this->_constructor_error);
            $this->_constructor_error = null;
            $this->_row = null;
        } elseif (is_array($this->_row)) {
            $this->_activateUser();
        }
        parent::postConstructor();
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
        isset($this->hits_field) &&
            array_key_exists($this->hits_field, $this->_row) &&
            ++ $this->_row[$this->hits_field];
        isset($this->last_hit_field) &&
            array_key_exists($this->last_hit_field, $this->_row) &&
            $this->_row[$this->last_hit_field] = TIP::formatDate('datetime_sql');

        $this->data->updateRow($this->_row, $this->_old_row);
    }

    //}}}
    //{{{ Methods

    /**
     * Login a registered user
     *
     * Performs the login option for a specific user. The row data of the user
     * to login must be present in the '_row' internal property: if not
     * defined, a logout action is called.
     *
     * @return bool true on success or false on errors
     */
    protected function login()
    {
        if (!isset($this->_row)) {
            // No user to login
            $this->logout();
            return false;
        }

        $this->_updateCookie();
        $this->_activateUser();
        $this->_refreshUser();
        return true;
    }

    /**
     * Logout the current user
     *
     * Performs the logout option for the current user. The row data of the
     * user to login must be present in the '_row' internal property.
     *
     * @return bool true on success or false on errors
     */
    protected function logout()
    {
        require_once 'HTTP/Session2.php';
        HTTP_Session2::destroy();
        $this->_row = null;
        $this->_updateCookie();
        $this->_activateUser();
        $this->_refreshUser();
        return true;
    }

    /**
     * Get a field value
     *
     * Overrides the default method accessing the _row private property
     * if there is no current view.
     *
     * @param  string     $field The field id
     * @return mixed|null        The field value or null on errors
     */
    public function getField($field)
    {
        if (is_null($result = parent::getField($field))) {
            $result = $this->getLoggedField($field);
        }

        return $result;
    }

    /**
     * Get a field value of the logged user
     *
     * Retrieves a field value for the current logged-in user. If no user is
     * logged or the field is not found, it returns null.
     *
     * @param  string     $field The field id
     * @return mixed|null        The field value or null on errors
     */
    public function getLoggedField($field)
    {
        return @$this->_row[$field];
    }

    /**
     * Set a field value of the logged user
     *
     * Changes a field value for the current logged-in user. If no user is
     * logged or the field is not found, it returns false.
     *
     * @param  string $field The field id
     * @param  mixed  $value The new field value
     * @return bool          true on success or false on errors
     */
    public function setLoggedField($field, $value)
    {
        if (!@array_key_exists($field, $this->_row)) {
            return false;
        }

        $this->_row[$field] = $value;
        return true;
    }

    /**
     * Increment a field
     *
     * Shortcut for a often used operation that increment a field value. Often
     * used to update user statistics.
     *
     * @param  string $field The field id
     * @return bool          true on success or false on errors
     */
    public function increment($field)
    {
        if (!@array_key_exists($field, $this->_row)) {
            return false;
        }

        ++ $this->_row[$field];
        return true;
    }

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Overrides the default add action, showing the conditions to accept
     * before registering a new user and performing the autologin.
     *
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionAdd($options = array())
    {
        if (TIP::getGet('accept', 'int') == 1) {
            $this->appendToPage($this->conditions_template);
            return true;
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onAdd'));

        $processed = $this->form(TIP_FORM_ACTION_ADD, null, $options);
        if ($processed &&
            !is_null($id = $this->data->getLastId()) &&
            !is_null($filter = $this->data->rowFilter($id)) &&
            !is_null($view = $this->startDataView($filter)) &&
            !is_null($this->_row = $view->current())) {
            $this->login();
        }

        return !is_null($processed);
    }

    /**
     * Perform a login action
     *
     * Presents a login form and process the submitted fields accordling.
     *
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionLogin($options = array())
    {
        if (!array_key_exists('fields', $options)) {
            $fields =& $this->data->getFields();
            $options['fields'] = array(
                'user'     => &$fields['user'],
                'password' => &$fields['password']
            );
        }

        TIP::arrayDefault($options, 'action_id', 'login');
        TIP::arrayDefault($options, 'validator', array(&$this, '_checkLogin'));
        TIP::arrayDefault($options, 'on_process', array(&$this, '_onLogin'));
        TIP::arrayDefault($options, 'valid_render', TIP_FORM_RENDER_NOTHING);

        return !is_null($this->form(TIP_FORM_ACTION_ADD, null, $options));
    }

    /**
     * Perform a logout action
     *
     * @return bool true on success or false on errors
     */
    protected function actionLogout()
    {
        return $this->logout();
    }

    protected function runAdminAction($action)
    {
        switch ($action) {

        case 'view':
            // Moved from runAction()
            return $this->actionView(TIP_Application::getGlobalItem('ID'));
        }

        return parent::runAdminAction($action);
    }

    protected function runTrustedAction($action)
    {
        switch ($action) {

        case 'logout':
            return $this->actionLogout();
        }

        return parent::runTrustedAction($action);
    }

    protected function runUntrustedAction($action)
    {
        switch ($action) {

        case 'login':
            return $this->actionLogin();
        }

        return parent::runUntrustedAction($action);
    }

    protected function runAction($action)
    {
        switch ($action) {

        case 'view':
            // In the user module, the view action requires 'admin' privileges
            return null;
        }

        return parent::runAction($action);
    }

    //}}}
    //{{{ Internal properties

    /**
     * The error message, if any, generated by __construct.
     * @var string|null
     * @internal
     */
    private $_constructor_error = null;

    /**
     * The current logged in user data, if a logged in user exists.
     * @var string|null
     * @internal
     */
    private $_row = null;

    /**
     * The original current logged in user data, if a logged in user exists.
     * @var string|null
     * @internal
     */
    private $_old_row = null;

    //}}}
    //{{{ Callbacks

    /**
     * 'on_row' callback for TIP_Data_View
     *
     * Adds the following calculated fields to every data row:
     * - 'OA': 'a' if 'sex' is female or 'o' otherwise
     *
     * @param  array &$row The row as generated by TIP_Data_View
     * @return bool        always true
     */
    public function _onDataRow(&$row)
    {
        array_key_exists('sex', $row) && $row['OA'] = $row['sex'] == 'female' ? 'a' : 'o';
        return true;
    }

    /**
     * Called by the login form to validate user and password
     * @param  array      &$row The data row
     * @return true|array       true on success or an associative array in the
     *                          form array(field => error_message)
     */
    public function _checkLogin(&$row)
    {
        $filter = $this->data->filter('user', $row['user']) . ' LIMIT 1';
        if (is_null($view =& $this->startDataView($filter))) {
            TIP::notifyError('select');
            return array('user' => TIP::getLocale('error.select', 'notify', null, false));
        }

        if (is_null($this->_row = $view->current())) {
            $this->endView();
            return array('user' => $this->getLocale('notfound'));
        }

        if ($this->_row['password'] != $row['password']) {
            $this->endView();
            $this->_row = null;
            return array('password' => $this->getLocale('wrongpassword'));
        }

        return true;
    }

    /**
     * Called by the login form to process the login
     * @param  array      &$row The data row
     * @return false            Always false, to not execute the form action
     */
    public function _onLogin(&$row)
    {
        $this->login();
        return false;
    }

    /**
     * Update the cookie on password changed
     *
     * @param  array &$row     The subject row
     * @param  array  $old_row The old row
     * @return bool            true on success, false on errors
     */
    public function _onEdit(&$row, $old_row = null)
    {
        // Ensure $old_row is properly populated
        is_array($old_row) || $old_row =& $this->_old_row;
        if (!is_array($old_row) || !parent::_onEdit($row, $old_row)) {
            return false;
        }

        // Update the internal data
        $this->_row = $row;
        $this->_old_row = $row;

        // Update the cookie on password change: the expiration is reset
        if (array_key_exists('password', $row) && array_key_exists('password', $old_row) &&
            strcmp($row['password'], $old_row['password']) != 0) {
            $this->_updateCookie();
        }

        return true;
    }

    /**
     * Refresh module privileges
     *
     * Called by _refreshUser() to recursively refresh the privilege of the
     * yet loaded modules.
     *
     * @param array|TIP_Module &$module A module or an array of modules
     */
    private function _refreshModule(&$module)
    {
        if (is_array($module)) {
            array_walk($module, array(&$this, '_refreshModule'));
        } else {
            $module->refreshPrivileges(TIP::getPrivilege($module, $this->keys['CID']));
        }
    }

    //}}}
    //{{{ Internal methods

    /**
     * Update the authentication cookie using the $_row internal property
     */
    private function _updateCookie()
    {
        if (is_array($this->_row)) {
            // Registered user request
            $id = $this->_row[$this->data->getProperty('primary_key')];
            $password = crypt($this->_row['password']);
            $expiration = strtotime($this->expiration);
            setcookie('TIP_User', $id . ',' . $password, $expiration, '/');
        } else {
            // Anonymous request
            setcookie('TIP_User', '', time()-3600, '/');
        }
    }

    /**
     * Activate the current user
     *
     * Simply copies the '_row' internal property in '_old_row' and sets the
     * 'CID' global key. This means the user data must resides in the '_row'
     * internal property.
     *
     * If the '_row' property is not set, all the values are unset.
     */
    private function _activateUser()
    {
        $this->_old_row = $this->_row;
        $this->keys['CID'] = @$this->_row[$this->data->getProperty('primary_key')];
    }

    /**
     * Refresh the current user
     *
     * Refreshes the user id cache of TIP::getUserId() and the privileges
     * of the yet loaded module.
     */
    private function _refreshUser()
    {
        // Refresh the new user id cache
        TIP::getUserId(true);

        // Refresh the privileges of the yet loaded modules
        $this->_refreshModule(TIP_Type::singleton('module'));
    }

    //}}}
}
?>
