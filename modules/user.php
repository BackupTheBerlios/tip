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
        setcookie('usrid', $row['id'], $expiration);
        setcookie('usrpwd', crypt($row['password']), $expiration);
    }

    function _logout()
    {
        $fake_null = null;
        $this->_activateUser($fake_null);
        setcookie('usrid', '', time()-3600);
        setcookie('usrpwd', '', time()-3600);
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

        $id = TIP::getCookie('usrid', 'int');
        if (is_null($id))
            return;

        $crypted_password = TIP::getCookie('usrpwd', 'string');
        if (is_null($crypted_password)) {
            return;
        }

        $filter = $this->data->rowFilter($id);
        $view =& $this->startView($filter);
        if (is_null($view)) {
            TIP::error('E_DATA_SELECT');
            return;
        }

        $row =& $view->rowReset();
        if (is_null($row)) {
            TIP::error('E_NOTFOUND');
            $this->endView();
            return;
        }

        if (crypt($row['password'], $crypted_password) != $crypted_password) {
            TIP::error('E_DENIED');
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
            /*
             * \manageraction <b>delete</b>\n
             * Requests a delete of the specified user. You must specify in
             * $_GET['id'] the user id.
             */
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

                    TIP::info ('I_DONE');
                } else {
                    TIP::error('E_DATA_DELETE');
                }
            }
            else
            {
                $this->AppendToContent ('delete.src');
            }

            $this->EndView ();
            return true;
        }

        return parent::runManagerAction ($action);
    }

    function runAdminAction ($action)
    {
        switch ($action)
        {
            /* \adminaction <b>browse</b>\n
             * Shows all the registered users.
             */
        case 'browse':
            $this->appendToContent('browse.src');
            return true;
        }

        return parent::runAdminAction ($action);
    }

    function runTrustedAction ($action)
    {
        switch ($action)
        {
            /* \trustedaction <b>unset</b>\n
             * Logout the current user (if any).
             */
        case 'unset':
            $this->_logout();
            return true;

            /* \trustedaction <b>edit</b>\n
             * Requests the modification of the current user profile.
             */
        case 'edit':
            return $this->editRow();

            /* \trustedaction <b>doedit</b>\n
             * Modifies the current user profile with the data found in $_POST.
             */
        case 'doedit':
            return false;
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
                TIP::error ('E_VL_REQUIRED', " ($label)");
                return false;
            }

            $password = TIP::getPost('password', 'string');
            if (empty($password)) {
                $label = $this->getLocale('password_label');
                TIP::error('E_VL_REQUIRED', " ($label)");
                return false;
            }

            $filter = $this->data->filter('user', $user);
            if (! $this->startView($filter)) {
                TIP::error('DB_SELECT');
                return false;
            }

            if (! $this->view->rowReset()) {
                $this->endQuery();
                TIP::error('U_NOTFOUND');
                return false;
            }

            if ($this->getField('password') != $password) {
                $this->endQuery();
                TIP::error('U_PWINVALID');
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
            return $this->AppendToContent ('module.html');

            /* \untrustedaction <b>doadd</b>\n
             * New user registration. The user data must be filled in the $_POST
             * array (as for every module).
             */
        case 'doadd':
            // TODO
            return true;
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
            $this->data->updateRow($this->_old_row, $this->_new_row);
        }
    }

    function ValidateUser (&$Field, $Value)
    {
        $this->DATA_ENGINE->Querify ($Value, $this);
        if (! $this->StartView ("WHERE `user`=$Value"))
        {
            TIP::error ('E_DATA_SELECT');
            return false;
        }

        $user_id = $this->ResetRow () ? $this->GetField ('id') : $this->keys['CID'];
        $this->EndView ();

        if (@$this->keys['CID'] != $user_id)
        {
            TIP::error ('E_VL_GENERIC', $this->getLocale('user_validator'));
            return false;
        }

        return true;
    }

    function ValidatePublicName (&$Field, $Value)
    {
        $this->DATA_ENGINE->Querify ($Value, $this);
        if (! $this->StartView ("WHERE `publicname`=$Value"))
        {
            TIP::error ('E_DATA_SELECT');
            return false;
        }

        $user_id = $this->ResetRow () ? $this->GetField ('id') : $this->keys['CID'];
        $this->EndView ();

        if (@$this->keys['CID'] != $user_id)
        {
            TIP::error ('E_VL_GENERIC', $this->getLocale ('publicname_validator'));
            return false;
        }

        return true;
    }

    /**#@-*/
}

return 'TIP_User';

?>
