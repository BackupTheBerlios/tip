<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Privilege definition file
 * @package TIP
 * @subpackage Module
 */


/**
 * The privilege manager
 *
 * TIP uses different security levels, here called "privilege descriptors", in
 * a top-down fashion: every level allows the actions of the lower levels.
 * The following is the privilege descriptors list, ordered from the highest to
 * the lowest level:
 *
 * - manager: this allows every available action provided by the module to be
 *            executed
 *
 * - admin: administrator privileges allow to do everything but modifying the
 *          overall module structure
 *
 * - trusted: registered user privileges allow to do read actions on the module
 *            content and write actions on the content owned directly by the user
 *
 * - untrusted: anonymous privileges allow only read actions
 *
 * - none: this disallows all the actions that require any privilege
 *
 * The description under every privilege is purely indicative: you must check
 * the documentation of every module to see which action are allowed by a
 * specific level and which are disallowed.
 *
 * The privileges are stored in a "Module-User" way, so for every pair of
 * module-user there will be a specific privilege descriptor. If a specific
 * module-user pair is not stored in the privilege database, the default
 * privilege descriptor will be used.
 *
 * The default privileges SHOULD be specified in the config file
 * for every module in the 'default_privilege' and 'anonymous_privilege'
 * options. If not specified, the privilege defaults to the one of the
 * 'application' module.
 *
 * @final
 * @package TIP
 * @subpackage Module
 */
class TIP_Privilege extends TIP_Block
{
    /**#@+ @access private */

    var $_privileges = array(
        TIP_PRIVILEGE_INVALID   => '',
        TIP_PRIVILEGE_NONE      => 'none',
        TIP_PRIVILEGE_UNTRUSTED => 'untrusted',
        TIP_PRIVILEGE_TRUSTED   => 'trusted',
        TIP_PRIVILEGE_ADMIN     => 'admin',
        TIP_PRIVILEGE_MANAGER   => 'manager'
    );


    function _getSubjectId()
    {
        if (!array_key_exists('UID', $this->keys)) {
            if (is_null($subject_id = TIP::getGet('user', 'int'))) {
                TIP::notifyError('noparams');
            } elseif ($this->_privilege < TIP_PRIVILEGE_MANAGER && $subject_id == TIP::getUserId()) {
                TIP::notifyError('denied');
                $subject_id = null;
            }
            $this->keys['UID'] = $subject_id;
        }

        return $this->keys['UID'];
    }

    function _maxSettableLevel($module_name)
    {
        $user_level = TIP::getPrivilege($module_name);
        $subject_level = TIP::getPrivilege($module_name, $this->keys['UID']);

        switch ($this->_privilege) {

        case TIP_PRIVILEGE_MANAGER:
            // The manager of the privilege module can do anything
            return TIP_PRIVILEGE_MANAGER;

        case TIP_PRIVILEGE_ADMIN:
            // The administrator can modify anything up to his level
            return $user_level < $subject_level ?
                TIP_PRIVILEGE_INVALID : $user_level;

        case TIP_PRIVILEGE_TRUSTED:
            // The trusted can modify anything up to his level only in the
            // modules where he has at least the administrator level
            return $user_level < TIP_PRIVILEGE_ADMIN || $user_level < $subject_level ?
                TIP_PRIVILEGE_INVALID : $user_level;

        case TIP_PRIVILEGE_UNTRUSTED:
            // The untrusted can modify the privileges up to TIP_PRIVILEGE_UNTRUSTED
            // and only in the modules where he has at least the administrator level
            return $user_level < TIP_PRIVILEGE_ADMIN || $user_level <= $subject_level ?
                TIP_PRIVILEGE_INVALID : TIP_PRIVILEGE_UNTRUSTED;
        }

        return TIP_PRIVILEGE_INVALID;
    }

    function _onModuleRow(&$row)
    {
        $module_name = $row['id'];
        $up_to_level = $this->_maxSettableLevel($module_name);
        if ($up_to_level <= TIP_PRIVILEGE_NONE) {
            return false;
        }

        $row['ACTIVE'] = TIP::getPrivilege($module_name, $this->keys['UID']);
        foreach ($this->_privileges as $id => $privilege) {
            $row['CAN_' . strtoupper($privilege)] = $id <= $up_to_level;
        }

        return true;
    }

    /**#@-*/


    /**#@+ @access protected */

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Check if the current user is manager
     *
     * Expands to true if the current logged-in user is manager in the module
     * specified with $params, false otherwise.
     */
    function commandIsManager($params)
    {
        echo TIP::getPrivilege($params) >= TIP_PRIVILEGE_MANAGER ? 'true' : 'false';
        return true;
    }

    /**
     * Check if the current user is administrator
     *
     * Expands to true if the current logged-in user is administrator in the
     * module specified with $params, false otherwise.
     */
    function commandIsAdmin($params)
    {
        echo TIP::getPrivilege($params) >= TIP_PRIVILEGE_ADMIN ? 'true' : 'false';
        return true;
    }

    /**
     * Check if the current user is a trusted user
     *
     * Expands to true if the current logged-in user is trusted in the
     * module specified with $params, false otherwise.
     */
    function commandIsTrusted($params)
    {
        echo TIP::getPrivilege($params) >= TIP_PRIVILEGE_TRUSTED ? 'true' : 'false';
        return true;
    }

    /**
     * Check if the current user is an untrusted user
     *
     * Expands to true if the current logged-in user is untrusted in the
     * module specified with $params, false otherwise.
     */
    function commandIsUntrusted($params)
    {
        echo TIP::getPrivilege($params) >= TIP_PRIVILEGE_UNTRUSTED ? 'true' : 'false';
        return true;
    }

    /**#@-*/


    function runManagerAction($action)
    {
        switch ($action) {

        case 'restore':
            if (is_null($subject_id = $this->_getSubjectId())) {
                return false;
            }

            $filter = $this->data->filter('_user', $subject_id);
            if (!$this->data->deleteRows($filter)) {
                TIP::notifyError('delete');
                $this->appendToContent('edit.src');
                return false;
            }

            TIP::notifyInfo('done');
            $this->appendToContent('edit.src');
            return true;

        }

        return parent::runManagerAction($action);
    }

    function runUntrustedAction($action)
    {
        switch ($action) {

        case 'edit':
            if (is_null($subject_id = $this->_getSubjectId())) {
                return false;
            }

            $this->appendToContent('edit.src');
            return true;

        case 'change':
            if (is_null($subject_id = $this->_getSubjectId())) {
                return false;
            } elseif (is_null($module_name = TIP::getGet('where', 'string'))) {
                TIP::warning('no subject module specified');
                TIP::notifyError('noparams');
                return false;
            } elseif (is_null($privilege = TIP::getGet('privilege', 'string')) || ($level = @array_search($privilege, $this->_privileges)) < TIP_PRIVILEGE_NONE) {
                TIP::warning('no subject privilege specified');
                TIP::notifyError('noparams');
                return false;
            }

            if ($level > $this->_maxSettableLevel($module_name)) {
                TIP::notifyError('denied');
                $done = false;
            } else {
                $old_row = null;
                $view =& $this->startView($this->data->filter('_user', $subject_id));
                if ($view) {
                    while ($row =& $view->rowNext()) {
                        if ($module_name == @$row['_module']) {
                            $old_row =& $row;
                            break;
                        }
                    }
                }

                $new_row['privilege'] = $privilege;
                $new_row['_user']     = $subject_id;
                $new_row['_module']   = $module_name;

                if ($old_row) {
                    if ($done = $this->data->updateRow($new_row, $old_row)) {
                        TIP::notifyInfo('done');
                        $old_row = $new_row;
                    } else {
                        TIP::notifyError('update');
                    }
                } else {
                    if ($done = $this->data->putRow($new_row)) {
                        TIP::notifyInfo('done');
                        if ($view) {
                            $view->rows[$new_row['id']] = $new_row;
                        }
                    } else {
                        TIP::notifyError('insert');
                    }
                }

                if ($view) {
                    $this->endView();
                }
            }

            $this->appendToContent('edit.src');
            return $done;
        }

        return parent::runUntrustedAction($action);
    }

    /**#@-*/


    /**#@+ @access public */

    function& startSpecialView($name)
    {
        if (strcasecmp($name, 'MODULES') != 0) {
            return parent::startSpecialView($name);
        }

        $view =& TIP_Modules_View::getInstance($this->data);
        $view->on_row->set(array(&$this, '_onModuleRow'));
        return $this->push($view);
    }

    /**
     * Get a stored privilege
     *
     * Returns the privilege stored for the $module and $user_id pair. If
     * $user_id is omitted, the current user is processed.
     *
     * This method returns the privilege descriptor only if it is explicitily
     * stored in the data, does not provide any fallback or default value.
     *
     * @param  string           $module_name  The requesting module name
     * @param  int              $user_id      The user id
     * @return TIP_PRIVILEGE...               The stored privilege
     */
    function getStoredPrivilege($module_name, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = TIP::getUserId();
        }

        if (is_null($user_id) || $user_id === false) {
            return TIP_PRIVILEGE_INVALID;
        }

        /* The internal query is based only on the user id. The query could be
         * filtered on both $module_name and $user_id, but filtering only by
         * user id allows the next requests, with the same user id but different
         * module (which are expected to be done), to be cached. */
        $view =& $this->startView($this->data->filter('_user', $user_id));
        if (is_null($view)) {
            return TIP_PRIVILEGE_INVALID;
        }

        $filter = create_function('$r', "return \$r['_module']=='$module_name';");
        $row = @end(array_filter($view->rows, $filter));
        $this->endView();

        return (int) array_search(@$row['privilege'], $this->_privileges);
    }

    /**#@-*/
}

return 'TIP_Privilege';

?>
