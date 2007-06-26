<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Privilege definition file
 * @package TIP
 * @subpackage Module
 */


/**
 * The privilege manager
 *
 * TIP uses different security levels, defined in a top-down fashion: every
 * level allows the actions of the lower levels.
 *
 * Here's the privilege list, ordered from the highest to the lowest level:
 *
 * - manager: can perform actionEdit() on everything
 *
 * - admin: can perform actionDelete() on everything
 *
 * - trusted: can perform actionEdit() and actionDelete() on his owned content
 *
 * - untrusted: can perform actionAdd()
 *
 * - none: can perform actionView() and actionBrowse()
 *
 * For addictive actions, such as actionLogin() and actionLogout() in TIP_User,
 * you can directy check the source of the module.
 *
 * The privileges are stored in a "Module-User" way, so for every pair of
 * module-user there will be a specific privilege level. If a requested
 * module-user pair is not stored in the privilege database, the default
 * privilege level will be used.
 *
 * The default privileges SHOULD be specified in the config file
 * for every module in the 'default_privilege' and 'anonymous_privilege'
 * options. If not specified, the privilege defaults to the one of the
 * application module.
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Privilege extends TIP_Content
{
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Privilege instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }
 
    /**
     * Custom post construction method
     *
     * Overrides the default post-constructor method to avoid the
     * TIP::getPrivilege() call and the consequential mutual recursion.
     */
    protected function postConstructor()
    {
        $this->_privilege = $this->getPrivilege($this->id);
        $this->refreshPrivileges();
    }

    //}}}
    //{{{ Methods

    public function getPrivilege($module, $user = null)
    {
        if (is_null($user)) {
            $user = TIP::getUserId();
        }

        if ($user) {
            $privilege = $this->_getStoredPrivilege($module, $user);
            if ($privilege != TIP_PRIVILEGE_INVALID) {
                return $privilege;
            }
        }

        return TIP::getDefaultPrivilege($module, $user);
    }

    //}}}
    //{{{ Tags

    /**
     * Check if the current user is manager
     *
     * Expands to true if the current logged-in user is manager in the module
     * specified with $params, false otherwise.
     *
     * @param  string @params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function tagIsManager($params)
    {
        echo $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_MANAGER ? 'true' : 'false';
        return true;
    }

    /**
     * Check if the current user is administrator
     *
     * Expands to true if the current logged-in user is administrator in the
     * module specified with $params, false otherwise.
     *
     * @param  string @params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function tagIsAdmin($params)
    {
        echo $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_ADMIN ? 'true' : 'false';
        return true;
    }

    /**
     * Check if the current user is a trusted user
     *
     * Expands to true if the current logged-in user is trusted in the
     * module specified with $params, false otherwise.
     *
     * @param  string @params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function tagIsTrusted($params)
    {
        echo $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_TRUSTED ? 'true' : 'false';
        return true;
    }

    /**
     * Check if the current user is an untrusted user
     *
     * Expands to true if the current logged-in user is untrusted in the
     * module specified with $params, false otherwise.
     *
     * @param  string @params The parameter string
     * @return bool           true on success or false on errors
     */
    protected function tagIsUntrusted($params)
    {
        echo $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_UNTRUSTED ? 'true' : 'false';
        return true;
    }

    //}}}
    //{{{ Actions

    protected function runManagerAction($action)
    {
        switch ($action) {

        case 'restore':
            if (is_null($subject = $this->_getSubjectId())) {
                return false;
            }

            $filter = $this->data->filter('_user', $subject);
            if (!$this->data->deleteRows($filter)) {
                TIP::notifyError('delete');
                $this->appendToPage('edit.src');
                return false;
            }

            TIP::notifyInfo('done');
            $this->appendToPage('edit.src');
            return true;

        }

        return null;
    }

    protected function runAdminAction($action)
    {
        return null;
    }

    protected function runTrustedAction($action)
    {
        return null;
    }

    protected function runUntrustedAction($action)
    {
        switch ($action) {

        case 'edit':
            if (is_null($subject = $this->_getSubjectId())) {
                return false;
            }

            $this->appendToPage('edit.src');
            return true;

        case 'change':
            if (is_null($subject = $this->_getSubjectId())) {
                return false;
            } elseif (is_null($module = TIP::getGet('where', 'string'))) {
                TIP::warning('no subject module specified');
                TIP::notifyError('noparams');
                return false;
            } elseif (is_null($privilege = TIP::getGet('privilege', 'string')) || ($level = @array_search($privilege, $this->_privileges)) < TIP_PRIVILEGE_NONE) {
                TIP::warning('no subject privilege specified');
                TIP::notifyError('noparams');
                return false;
            }

            if ($level > $this->_maxSettableLevel($module)) {
                TIP::notifyError('denied');
                $done = false;
            } else {
                $old_row = null;
                $view =& $this->startDataView($this->data->filter('_user', $subject));
                if ($view) {
                    foreach ($view as $row) {
                        if ($module == $row['_module']) {
                            $old_row =& $row;
                            break;
                        }
                    }
                }

                $new_row['privilege'] = $privilege;
                $new_row['_user']     = $subject;
                $new_row['_module']   = (string) $module;

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
                            $rows =& $view->getProperty('rows');
                            $rows[$new_row['id']] = $new_row;
                        }
                    } else {
                        TIP::notifyError('insert');
                    }
                }

                if ($view) {
                    $this->endView();
                }
            }

            $this->appendToPage('edit.src');
            return $done;
        }

        return null;
    }

    protected function runAction($action)
    {
        return null;
    }

    //}}}
    //{{{ Internal properties

    private $_privileges = array(
        TIP_PRIVILEGE_INVALID   => '',
        TIP_PRIVILEGE_NONE      => 'none',
        TIP_PRIVILEGE_UNTRUSTED => 'untrusted',
        TIP_PRIVILEGE_TRUSTED   => 'trusted',
        TIP_PRIVILEGE_ADMIN     => 'admin',
        TIP_PRIVILEGE_MANAGER   => 'manager'
    );

    //}}}
    //{{{ Callbacks

    /**
     * 'on_row' callback for TIP_Modules_View
     *
     * Adds the following calculated fields to every module row:
     * - 'ACTIVE':        privilege level of the subject user for this module
     * - 'CAN_MANAGER':   true if the subject user is manager
     * - 'CAN_ADMIN':     true if the subject user is at least administrator
     * - 'CAN_TRUSTED':   true if the subject user is at least trusted
     * - 'CAN_UNTRUSTED': true if the subject user is at least untrusted
     * - 'CAN_NONE':      always true
     *
     * @param  array &$row The row as generated by TIP_Modules_View
     * @return bool        true to include the row in the view or false to skip it
     */
    public function _onModulesRow(&$row)
    {
        $module = $row['id'];
        $up_to_level = $this->_maxSettableLevel($module);

        // Modules where the subject user does not have any privilege
        // are not included in the view
        if ($up_to_level <= TIP_PRIVILEGE_NONE) {
            return false;
        }

        $row['ACTIVE'] = $this->getPrivilege($module, $this->keys['UID']);
        foreach ($this->_privileges as $id => $privilege) {
            $row['CAN_' . strtoupper($privilege)] = $id <= $up_to_level;
        }

        return true;
    }

    //}}}
    //{{{ Internal methods

    private function _getSubjectId()
    {
        if (!array_key_exists('UID', $this->keys)) {
            if (is_null($subject = TIP::getGet('user', 'int'))) {
                TIP::notifyError('noparams');
            } elseif ($this->_privilege < TIP_PRIVILEGE_MANAGER && $subject == TIP::getUserId()) {
                TIP::notifyError('denied');
                $subject = null;
            }
            $this->keys['UID'] = $subject;
        }

        return $this->keys['UID'];
    }

    private function _maxSettableLevel($module)
    {
        $user_level = $this->getPrivilege($module);
        $subject_level = $this->getPrivilege($module, $this->keys['UID']);

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

    /**
     * Get a stored privilege
     *
     * Returns the privilege stored for the $module and $user pair. If
     * $user is omitted, the current user is processed.
     *
     * This method returns the privilege descriptor only if it is explicitily
     * stored in the data, does not provide any fallback or default value.
     *
     * @param  string           $module  The requesting module
     * @param  int              $user    The user
     * @return TIP_PRIVILEGE...          The stored privilege
     */
    private function _getStoredPrivilege($module, $user)
    {
        /* The internal query is based only on the user id. The query could be
         * filtered on both $module and $user, but filtering only by
         * user id allows the next requests, with the same user id but different
         * module (which are expected to be done), to be cached. */
        $view =& $this->startDataView($this->data->filter('_user', $user));
        if (is_null($view)) {
            return TIP_PRIVILEGE_INVALID;
        }

        $filter = create_function('$r', 'return $r[\'_module\']==\'' . $module . '\';');
        $row = @end(array_filter($view->getProperty('rows'), $filter));
        $this->endView();

        return (int) array_search(@$row['privilege'], $this->_privileges);
    }

    //}}}
}
?>
