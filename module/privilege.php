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
 * The default privileges COULD be specified in the configure file
 * (logic/config.php) for every module in the 'default_privilege' and
 * 'anonymous_privilege' options. If not specified, the privilege defaults
 * to the one of the 'application' module.
 *
 * @final
 * @package TIP
 * @subpackage Module
 */
class TIP_Privilege extends TIP_Block
{
    /**#@+ @access private */

    var $_privileges = array('none', 'untrusted', 'trusted', 'admin', 'manager');
    var $_subject_id = null;


    function _getAvailablePrivileges(&$module, $from, $to)
    {
        $available_privileges = array();
        $store = false;

        foreach ($this->_privileges as $privilege) {
            $store = $store || $privilege == $from;
            if ($store && $module->getLocale(strtoupper($privilege) . '_HELP')) {
                $available_privileges[] = $privilege;
            }

            if ($privilege == $to) {
                break;
            }
        }

        return $available_privileges;
    }

    function _getSubjectId()
    {
        if (! is_null($this->_subject_id)) {
            return $this->_subject_id;
        }

        $this->_subject_id = TIP::getGet('user', 'int');
        if (is_null($this->_subject_id)) {
            TIP::notifyError('E_NOTSPECIFIED');
            return null;
        } elseif ($this->_subject_id == TIP::getUserId()) {
            TIP::notifyError('E_DENIED');
            $this->_subject_id = null;
            return null;
        }
        return $this->_subject_id;
    }

    function _onModuleRow(&$row)
    {
        $module =& TIP_Module::getInstance ($row['id']);
        $from = $module->getOption('default_privilege');
        if (is_null($from)) {
            $from = TIP::getOption('application', 'default_privilege');
        }
        $to = $this->keys['IS_MANAGER'] ? 'manager' : TIP::getPrivilege($module);
        $available = $this->_getAvailablePrivileges($module, $from, $to);

        if (count($available) <= 1) {
            return false;
        }

        $row['active'] = TIP::getPrivilege ($module, $this->_subject_id);
        foreach ($this->_privileges as $privilege) {
            $row['can_' . $privilege] = in_array ($privilege, $available);
        }

        return true;
    }

    /**#@-*/


    /**#@+ @access protected */

    function runAdminAction ($action)
    {
        switch ($action) {
            /**
             * \adminaction <b>Edit</b>\n
             * Requests a privilege change. You must specify in $_GET['user'] the
             * user id.
             */
        case 'edit':
            $subject_id = $this->_getSubjectId();
            if (is_null($subject_id)) {
                return false;
            }

            $this->AppendToContent ('edit.src');
            return true;

            /**
             * \adminaction <b>DoEdit</b>\n
             * Changes the privileges of a user. You must specify in $_GET['user']
             * the user id, in $_GET['where'] the module name and in
             * $_GET['privilege'] the new privilege descriptor.
             */
        case 'doedit':
            $subject_id = $this->_getSubjectId();
            if (is_null($subject_id)) {
                return false;
            }

            $module_name = TIP::getGet('where', 'string');
            $privilege = TIP::getGet('privilege', 'string');
            if (empty($module_name) || empty($privilege)) {
                TIP::notifyError('E_NOTSPECIFIED');
                $this->appendToContent('edit.src');
                return true;
            }

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
                $done = $this->data->updateRow($new_row, $old_row);
                if ($done) {
                    TIP::notifyInfo('I_DONE');
                    $old_row = $new_row;
                } else {
                    TIP::notifyError('E_DATA_UPDATE');
                }
            } else {
                $done = $this->data->putRow($new_row);
                if ($done) {
                    TIP::notifyInfo('I_DONE');
                    if ($view) {
                        $view->rows[$new_row['id']] = $new_row;
                    }
                } else {
                    TIP::notifyError('E_DATA_INSERT');
                }
            }

            if ($view) {
                $this->endView();
            }

            $this->appendToContent('edit.src');
            return $done;

            /**
             * \adminaction <b>Restore</b>\n
             * Restores all the privileges of a user to their defaults. You must
             * specify in $_GET['user'] the user id.
             */
        case 'restore':
            $subject_id = $this->_getSubjectId();
            if (is_null($subject_id)) {
                return false;
            }

            $filter = $this->data->filter('_user', $subject_id);
            if (! $this->data->deleteRows($filter)) {
                TIP::notifyError('E_DATA_DELETE');
                $this->appendToContent('edit.src');
                return false;
            }

            TIP::notifyInfo('I_DONE');
            $this->appendToContent('edit.src');
            return true;
        }

        return parent::runAdminAction($action);
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
     * @param TIP_Module &$module  The requesting module
     * @param int         $user_id The user id
     * @return string|null The stored privilege or null if no stored privileges
     *                     were found
     */
    function getStoredPrivilege(&$module, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = TIP::getUserId();
        }

        if (is_null($user_id) || $user_id === false) {
            return null;
        }

        /* The internal query is based only on the user id. The query could be
         * <tt>"WHERE `_user`=$UsedId AND `module`='$module_name'"</tt>, but
         * filtering only by user id allows the next requests, with the same user id
         * but different module (which are expected to be done), to be cached. */
        $view =& $this->startView($this->data->filter('_user', $user_id));
        if (is_null($view))
            return null;

        $filter = create_function('$r', 'return $r[\'_module\']==\'' . $module->getId() . '\';');
        $row = @end(array_filter($view->rows, $filter));
        $this->endView();

        return @$row['privilege'];
    }

    /**#@-*/
}

return 'TIP_Privilege';

?>
