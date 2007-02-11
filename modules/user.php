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

    var $_old_row = false;


    function _login()
    {
        if (! $this->_activateUser())
            return false;

        $this->refreshPrivileges();

        $expiration = strtotime($this->getOption('expiration'));
        setcookie('usrid', $this->new_row['id'], $expiration);
        setcookie('usrpwd', crypt ($this->new_row['password']), $expiration);
        return true;
    }

    function _logout()
    {
        $this->_old_row = false;
        $this->new_row = false;
        $this->keys['CID'] = null;

        $this->refreshPrivilege();

        setcookie('usrid', '', time()-3600);
        setcookie('usrpwd', '', time()-3600);
        return true;
    }

    function _activateUser($row = null)
    {
        if (is_null($row)) {
            $this->new_row =& $this->view->rowCurrent();
        } else {
            $this->new_row =& $row;
        }

        $this->_old_row = $this->new_row;
        if (is_null($this->new_row)) {
            $this->logWarning('No current user to activate');
            return false;
        }

        $this->keys['CID'] = @$this->new_row['id'];
        TIP::getUserId(true);

        $modules =& TIP_Module::singleton();
        foreach (array_keys($modules) as $module_name) {
            $module =& $modules[$module_name];
            $module->refreshPrivilege();
        }

        $this->new_row['_hits'] ++;
        $this->new_row['_lasthit'] = TIP::formatDate('datetime_iso8601');
        return true;
    }

    function _onRow(&$row)
    {
        $row['OA'] = $row['sex'] == 'female' ? 'a' : 'o';
    }

    function _onFieldsView(&$view)
    {
        $fields = array (
            'user'          => array (
                'mode'      => 'entry',
                'importance'=> 1,
                'validator' => new TIP_Callback(array(&$this, 'ValidateUser'))),

            'password'      => array (
                'mode'      => 'secret',
                'importance'=> 1),

            'publicname'    => array (
                'mode'      => 'entry',
                'importance'=> 2,
                'validator' => new TIP_Callback(array (&$this, 'ValidatePublicName'))),
            
            'sex'           => array (
                'mode'      => 'choice',
                'importance'=> 2),

            'email'         => array (
                'mode'	    => 'entry',
                'importance'=> 2),

            'mail'          => array (
                'mode'      => 'entry',
                'importance'=> 3),

            'mobile'		=> array (
                'mode'	    => 'entry',
                'importance'=> 3),

            'phone'		    => array (
                'mode'	    => 'entry',
                'importance'=> 3)
            );

        $view->rows = array_merge_recursive($fields, $view->rows);
        return true;
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
        if (empty($crypted_password)) {
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

        $this->_activateUser($row);
        // No endView() call to retain this query as the default one

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

                    if ($id == @$this->new_row['id'])
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
            return $this->_logout ();

            /* \trustedaction <b>edit</b>\n
             * Requests the modification of the current user profile.
             */
        case 'edit':
            return $this->appendToContent('module.src');

            /* \trustedaction <b>doedit</b>\n
             * Modifies the current user profile with the data found in $_POST.
             */
        case 'doedit':
            if (! $this->ValidatePosts ())
                return $this->AppendToContent ('module.src');
            if (! $this->StorePosts ($this->new_row))
                return false;
            return $this->_onRow ($this->new_row);
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
            $User = TIP::GetPost ('user', 'string');
            if (empty ($User))
            {
                $Label = $this->GetLocale ('user_label');
                TIP::error ('E_VL_REQUIRED', " ($Label)");
                return false;
            }

            $password = TIP::GetPost ('password', 'string');
            if (empty ($password))
            {
                $Label = $this->GetLocale ('password_label');
                TIP::error('E_VL_REQUIRED', " ($Label)");
                return false;
            }

            $this->DATA_ENGINE->Querify ($User, $this);
            if (! $this->StartView ("WHERE `user`=$User"))
            {
                TIP::error('DB_SELECT');
                return false;
            }

            if ($this->RowsCount () < 1)
            {
                TIP::error ('U_NOTFOUND');
                $this->EndView ();
                return false;
            }

            $this->ResetRow ();
            $Row =& $this->GetCurrentRow ();
            if ($Row['password'] != $password)
            {
                TIP::error ('U_PWINVALID');
                $this->EndView ();
                return false;
            }

            // No EndView() call to retain this row as default row
            return $this->_login ();

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

    function& startSpecialView($name)
    {
        if (strcasecmp($name, 'FIELDS') != 0) {
            return parent::startSpecialView($name);
        }

        $view =& TIP_Fields_View::getInstance($this->data);
        $view->on_view->set(array('TIP_User', '_onFieldsView'));
        return $this->push($view);
    }

    /**#@-*/


    /**#@+ @access public */

    var $new_row = false;


    function updateUser ()
    {
        if (is_array ($this->_old_row) && is_array ($this->new_row))
            $this->data->updateRow ($this->_old_row, $this->new_row);
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
            TIP::error ('E_VL_GENERIC', $this->GetLocale ('user_validator'));
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
            TIP::error ('E_VL_GENERIC', $this->GetLocale ('publicname_validator'));
            return false;
        }

        return true;
    }

    /**#@-*/
}

return new TIP_User;

?>
