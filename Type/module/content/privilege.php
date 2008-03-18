<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Privilege definition file
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
        $this->privilege = $this->getPrivilege($this->id);
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

    /**#@+
     * @param      string       $params Parameters of the tag
     * @return     string|null          The string result or null
     * @subpackage SourceEngine
     */

    /**
     * Check if the current user is manager
     *
     * Expands to 'true' if the current logged-in user is manager in the module
     * specified with $params, 'false' otherwise.
     */
    protected function tagIsManager($params)
    {
        return $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_MANAGER ? 'true' : 'false';
    }

    /**
     * Check if the current user is administrator
     *
     * Expands to 'true' if the current logged-in user is administrator in the
     * module specified with $params, 'false' otherwise.
     */
    protected function tagIsAdmin($params)
    {
        return $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_ADMIN ? 'true' : 'false';
    }

    /**
     * Check if the current user is a trusted user
     *'
     * Expands to 'true' if the current logged-in user is trusted in the
     * module specified with $params, 'false' otherwise.
     */
    protected function tagIsTrusted($params)
    {
        return $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_TRUSTED ? 'true' : 'false';
    }

    /**
     * Check if the current user is an untrusted user
     *
     * Expands to 'true' if the current logged-in user is untrusted in the
     * module specified with $params, 'false' otherwise.
     */
    protected function tagIsUntrusted($params)
    {
        return $this->getPrivilege(strtolower($params)) >= TIP_PRIVILEGE_UNTRUSTED ? 'true' : 'false';
    }

    /**
     * Get the privilege description
     *
     * Expands to the specified privilege description, in the current locale.
     * In $params you must specify the privilege as 'module_id,privilege',
     * where privilege must be manager|admin|trusted|untrusted|none.
     */
    protected function tagPrivilegeDescription($params)
    {
        global $cfg;
        list($module, $privilege) = explode(',', $params);
        $prefixes = array_reverse($cfg[$module]['type']);
        isset($cfg[$module]['locale_prefix']) && array_unshift($prefixes, $cfg[$module]['locale_prefix']);

        foreach ($prefixes as $prefix) {
            $description = TIP::getLocale($privilege, $prefix);
            if (!empty($description)) {
                return TIP::toHtml($description);
            }
        }

        TIP::warning("localized privilege description not found ($params)");
        return null;
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    /**
     * Perform a change action
     *
     * Changes the privilege level for the given user on the specified module.
     *
     * @param  int    $user      The user to modify
     * @param  string $module    A module name
     * @param  string $privilege The new privilege level
     * @return bool              true on success or false on errors
     */
    protected function actionChange($user, $module, $privilege)
    {
        $level = @array_search($privilege, $this->_privileges);
        if ($level < TIP_PRIVILEGE_NONE || $level > $this->_maxSettableLevel($module)) {
            TIP::notifyError('denied');
            $done = false;
        } else {
            $old_row = null;
            if (!is_null($view =& $this->startDataView($this->filterOwnedBy($user)))) {
                $rows =& $view->getProperty('rows');
                foreach ($rows as &$row) {
                    if ($module == $row['_module']) {
                        $old_row =& $row;
                        unset($row);
                        break;
                    }
                }
            }

            $row = $old_row;
            $row['privilege'] = $privilege;
            $row[$this->owner_field] = $user;
            $row['_module'] = (string) $module;

            if ($old_row) {
                // Previous stored privilege: it needs update
                if ($done = $this->data->updateRow($row, $old_row)) {
                    TIP::notifyInfo('done');
                    $old_row = $row;
                } else {
                    TIP::notifyError('update');
                }
            } else {
                // New stored privilege
                if ($done = $this->data->putRow($row)) {
                    TIP::notifyInfo('done');
                    if ($view) {
                        $rows =& $view->getProperty('rows');
                        $rows[$row['id']] = $row;
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

    protected function runManagerAction($action)
    {
        switch ($action) {

        case 'restore':
            if (is_null($user = $this->_getUser())) {
                return false;
            }

            if (!$this->data->deleteRows($this->filterOwnedBy($user))) {
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
            return
                !is_null($user = $this->_getUser()) &&
                $this->appendToPage('edit.src');

        case 'change':
            return
                !is_null($user = $this->_getUser()) &&
                !is_null($module = $this->fromGet('where', 'string')) &&
                !is_null($privilege = $this->fromGet('privilege', 'string')) &&
                $this->actionChange($user, $module, $privilege);
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
     * - 'ACTIVE':        privilege level of the user for this module
     * - 'CAN_MANAGER':   true if the user is manager
     * - 'CAN_ADMIN':     true if the user is at least administrator
     * - 'CAN_TRUSTED':   true if the user is at least trusted
     * - 'CAN_UNTRUSTED': true if the user is at least untrusted
     * - 'CAN_NONE':      always true
     *
     * @param  array &$row The row as generated by TIP_Modules_View
     * @return bool        true to include the row in the view or false to skip it
     */
    public function _onModulesRow(&$row)
    {
        $module = $row['id'];
        $user = $this->keys['UID'];
        $lowest_level = $this->privilege < TIP_PRIVILEGE_ADMIN ? TIP::getDefaultPrivilege($module, $user) : TIP_PRIVILEGE_NONE;
        $up_to_level = $this->_maxSettableLevel($module);

        // Modules where I cannot change the user level are not included
        if ($up_to_level <= $lowest_level) {
            return false;
        }

        $row['ACTIVE'] = $this->getPrivilege($module, $user);
        foreach ($this->_privileges as $id => $privilege) {
            $row['CAN_' . strtoupper($privilege)] = $id <= $up_to_level;
        }

        return true;
    }

    //}}}
    //{{{ Internal methods

    private function _getUser()
    {
        if (!array_key_exists('UID', $this->keys)) {
            if (!is_null($user = $this->fromGet('id')) && $this->privilege < TIP_PRIVILEGE_MANAGER && $user == TIP::getUserId()) {
                TIP::notifyError('denied');
                $user = null;
            }
            $this->keys['UID'] = $user;
        }

        return $this->keys['UID'];
    }

    private function _maxSettableLevel($module)
    {
        $my_level = $this->getPrivilege($module);
        $user_level = $this->getPrivilege($module, $this->keys['UID']);

        switch ($this->privilege) {

        case TIP_PRIVILEGE_MANAGER:
            // The manager of the privilege module can do anything
            return TIP_PRIVILEGE_MANAGER;

        case TIP_PRIVILEGE_ADMIN:
            // The administrator can modify anything up to his level
            return $my_level < $user_level ?
                TIP_PRIVILEGE_INVALID : $my_level;

        case TIP_PRIVILEGE_TRUSTED:
            // The trusted can modify anything up to his level only in the
            // modules where he has at least the administrator level
            return $my_level < TIP_PRIVILEGE_ADMIN || $my_level < $user_level ?
                TIP_PRIVILEGE_INVALID : $my_level;

        case TIP_PRIVILEGE_UNTRUSTED:
            // The untrusted can modify the privileges up to
            // TIP_PRIVILEGE_TRUSTED and only in the modules where he has at
            // least the administrator level
            return $my_level < TIP_PRIVILEGE_ADMIN || $my_level <= $user_level ?
                TIP_PRIVILEGE_INVALID : TIP_PRIVILEGE_TRUSTED;
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
        if (is_null($view =& $this->startDataView($this->filterOwnedBy($user)))) {
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
