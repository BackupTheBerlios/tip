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
        $this->_activateUser($row);
        $expiration = strtotime($this->getOption('expiration'));
        setcookie('TIP_User', $row['id'] . ',' . crypt($row['password']), $expiration);
    }

    function _logout()
    {
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
            TIP::notifyError('E_DATA_SELECT');
            return;
        }

        $row =& $view->rowReset();
        if (is_null($row)) {
            TIP::notifyError('E_NOTFOUND');
            $this->endView();
            return;
        }

        if (crypt($row['password'], $password) != $password) {
            TIP::notifyError('E_DENIED');
            $this->endView();
            return;
        }

        // No endView() call to retain this query as the default one
        $this->_activateUser($row);
        register_shutdown_function (array (&$this, 'updateUser'));
    }

    function runManagerAction ($action)
    {
        switch ($action) {

        case 'edit':
            return !is_null($this->form(TIP_FORM_ACTION_EDIT, TIP::getGet('id', 'integer')));
        }

        return parent::runManagerAction ($action);
    }

    function runAdminAction ($action)
    {
        switch ($action) {

        case 'browse':
            $this->appendToContent('browse.src');
            return true;

        case 'delete':
            /*
             * \manageraction <b>dodelete</b>\n
             * Deletes the specified user. You must specify in $_GET['id'] the user
             * id.
             */
        case 'dodelete':
            $id = TIP::GetGet ('id', 'integer');
    /* TODO
    $Row =& $this->GetMyself ($id);
    if (is_null ($Row))
      return false;
     */
            return true;

            if (substr ($action, 0, 2) == 'do')
            {
                if ($this->data->deleteRow($id)) {
                    // Deletes the owned advertisements
                    $advertisement =& TIP_Module::getInstance('advertisement', false);
                    if ($advertisement) {
                        $filter = $advertisement->data->filter('_user', $id);
                        $advertisement->data->deleteRows($filter);
                    }

                    if ($id == @$this->_new_row['id'])
                        $this->_logout ();

                    TIP::notifyInfo('done');
                } else {
                    TIP::notifyError('delete');
                }
            }
            else
            {
                $this->AppendToContent ('delete.src');
            }

            $this->EndView ();
            return true;
        }

        return parent::runAdminAction ($action);
    }

    function runTrustedAction ($action)
    {
        switch ($action)
        {
        case 'unset':
            $this->_logout();
            return true;

        case 'edit':
            return !is_null($this->form(TIP_FORM_ACTION_EDIT));
        }

        return parent::runTrustedAction ($action);
    }

    function runUntrustedAction ($action)
    {
        switch ($action)
        {
            /* \untrustedaction <b>set</b>\n
             * Login request. You must specify the user name and its password in
             * $_POST['user'] and $_POST['password'].
             */
        case 'set':
            $user = TIP::getPost('user', 'string');
            if (empty($user)) {
                $label = $this->getLocale('user_label');
                TIP::notifyError ('E_GENERIC', " ($label)");
                return false;
            }

            $password = TIP::getPost('password', 'string');
            if (empty($password)) {
                $label = $this->getLocale('password_label');
                TIP::notifyError('E_GENERIC', " ($label)");
                return false;
            }

            $filter = $this->data->filter('user', $user);
            if (! $this->startView($filter)) {
                TIP::notifyError('E_DATA_SELECT');
                return false;
            }

            if (! $this->view->rowReset()) {
                $this->endView();
                TIP::notifyError('E_GENERIC');
                return false;
            }

            if ($this->getField('password') != $password) {
                $this->endView();
                TIP::notifyError('E_GENERIC');
                return false;
            }

            // No EndView() call to retain this row as default row
            $this->_login();
            return true;

            /*
             * \untrustedaction <b>condition</b>\n
             * Shows the conditions imposed by the registration.
             */
        case 'conditions':
            return $this->AppendToContent ('conditions.src');

            /* \untrustedaction <b>add</b>\n
             * Registration request.
             */
        case 'add':
            return !is_null($this->form(TIP_FORM_ACTION_ADD));
        }

        return parent::runUntrustedAction ($action);
    }

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_row->set(array(&$this, '_onRow'));
        return $this->push($view);
    }

    /**#@-*/


    /**#@+ @access public */

    function updateUser ()
    {
        if (is_array($this->_old_row) && is_array($this->_new_row)) {
            $this->data->updateRow($this->_new_row, $this->_old_row);
        }
    }

    function ValidateUser (&$Field, $Value)
    {
        $this->DATA_ENGINE->Querify ($Value, $this);
        if (! $this->StartView ("WHERE `user`=$Value"))
        {
            TIP::notifyError ('E_DATA_SELECT');
            return false;
        }

        $user_id = $this->ResetRow () ? $this->GetField ('id') : $this->keys['CID'];
        $this->EndView ();

        if (@$this->keys['CID'] != $user_id)
        {
            TIP::notifyError ('E_VL_GENERIC', $this->getLocale('user_validator'));
            return false;
        }

        return true;
    }

    function ValidatePublicName (&$Field, $Value)
    {
        $this->DATA_ENGINE->Querify ($Value, $this);
        if (! $this->StartView ("WHERE `publicname`=$Value"))
        {
            TIP::notifyError ('E_DATA_SELECT');
            return false;
        }

        $user_id = $this->ResetRow () ? $this->GetField ('id') : $this->keys['CID'];
        $this->EndView ();

        if (@$this->keys['CID'] != $user_id)
        {
            TIP::notifyError ('E_VL_GENERIC', $this->getLocale ('publicname_validator'));
            return false;
        }

        return true;
    }

    /**#@-*/
}

return 'TIP_User';

?>
