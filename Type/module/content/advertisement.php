<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Advertisement definition file
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
 * Advertisement module
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Advertisement extends TIP_Content
{
    //{{{ Properties

    /**
     * The field with the boolean public flag
     * @var string
     */
    protected $public_field = '_public';

    /**
     * The field owning the expiration time
     * @var string
     */
    protected $expiration_field = '_expiration';

    /**
     * The default expiration time
     * @var string
     */
    protected $expiration = '+2 month';

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        isset($options['browsable_fields']) || $options['browsable_fields'] = array(
            TIP_PRIVILEGE_NONE    => array('group'),
            TIP_PRIVILEGE_TRUSTED => array('_user'),
            TIP_PRIVILEGE_ADMIN   => array('_check', '_public', '__ALL__')
        );
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Advertisement instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Callbacks

    /**
     * 'on_row' callback for TIP_Data_View
     *
     * Adds the following calculated fields to every data row:
     * - 'EXPIRED': true if the advertisement is expired
     *
     * @param  array &$row The row as generated by TIP_Data_View
     * @return bool        always true
     */
    public function _onDataRow(&$row)
    {
        $row['EXPIRED'] = TIP::getTimestamp($row[$this->expiration_field]) < time();
        if ($row['EXPIRED'] && @$row[$this->public_field] == 'yes') {
            // The advertisement is expired: update accordling
            $new_row = array($this->public_field => 'no');
            if ($this->_onDbAction('Edit', $new_row, $row) && !$this->data->updateRow($new_row, $row)) {
                TIP::notifyError('update');
            }
        }
        return true;
    }

    /**
     * 'add' callback
     *
     * Overrides the default callback setting the initial expiration value.
     *
     * @param  array &$row The data row to add
     * @return bool        true on success, false on errors
     */
    public function _onAdd(&$row)
    {
        isset($this->expiration_field) &&
            empty($row[$this->expiration_field]) &&
            $row[$this->expiration_field] = TIP::formatDate('datetime_sql', strtotime($this->expiration));
        return parent::_onAdd($row);
    }


    public function _onCheck(&$old_row)
    {
        $row['_check'] = 'yes';
        $row['_check_on'] = TIP::formatDate('datetime_sql');
        $row['_check_by'] = TIP::getUserId();

        if (!$this->data->updateRow($row, $old_row)) {
            TIP::notifyError('update');
            TIP::warning("unable to update a row ($id)");
            return false;
        }

        $signaled = $old_row['_user'];
        $user = TIP_Application::getSharedModule('user');

        // Update statistics of the signaling user
        if (!is_null($user)) {
            $user->increment('_checked');
        }

        // Update statistics of the signaled user
        if ($signaled && !is_null($user) &&
            !is_null($view = $user->startDataView($user->getProperty('data')->rowFilter($signaled)))) {
            $row = $view->current();
            $user->endView();
            if (!is_null($row)) {
                $old_row = $row;
                ++ $row['_own_checked'];
                $user->getProperty('data')->updateRow($row, $old_row);
            }
        }

        return true;
    }

    public function _onRestore(&$old_row)
    {
        $row['_check'] = 'no';
        if (!$this->data->updateRow($row, $old_row)) {
            TIP::notifyError('update');
            TIP::warning("unable to update a row ($id)");
            return false;
        }

        $signaling = $old_row['_check_by'];
        $signaled = $old_row['_user'];
        $user = TIP_Application::getSharedModule('user');

        // Update statistics of the signaling user
        if ($signaling && !is_null($user) &&
            !is_null($view = $user->startDataView($user->getProperty('data')->rowFilter($signaling)))) {
            $row = $view->current();
            $user->endView();
            if (!is_null($row)) {
                $old_row = $row;
                ++ $row['_unchecked'];
                $user->getProperty('data')->updateRow($row, $old_row);
            }
        }

        // Update statistics of the signaled user
        if ($signaled && !is_null($user) &&
            !is_null($view = $user->startDataView($user->getProperty('data')->rowFilter($signaled)))) {
            $row = $view->current();
            $user->endView();
            if (!is_null($row)) {
                $old_row = $row;
                ++ $row['_own_unchecked'];
                $user->getProperty('data')->updateRow($row, $old_row);
            }
        }

        return true;
    }

    public function _onRefresh(&$old_row)
    {
        if (isset($this->expiration_field, $this->expiration)) {
            $expiration = strtotime($this->expiration);
            if ($expiration === false) {
                return false;
            }
            $row[$this->expiration_field] = TIP::formatDate('datetime_sql', $expiration);
            isset($this->public_field) && $row[$this->public_field] = 'yes';
            if ($this->_onDbAction('Edit', $row, $old_row) && !$this->data->updateRow($row, $old_row)) {
                TIP::notifyError('update');
                return false;
            }
        }

        return true;
    }

    //}}}
    //{{{ Actions

    /**
     * Perform a browse action
     *
     * Overrides the default browse action, imposing the browsing of only
     * public advertisement for non-admin users.
     *
     * @param  array &$conditions The browse conditions
     * @return bool               true on success or false on errors
     */
    protected function actionBrowse(&$conditions)
    {
        if ($this->privilege < TIP_PRIVILEGE_ADMIN && !isset($conditions[$this->public_field])) {
            $conditions[$this->public_field] = 'yes';
        }
        return parent::actionBrowse($conditions);
    }

    protected function actionCheck($id, $options = null)
    {
        isset($options) || $options = array(
            'action_id'  => 'check',
            'buttons'    => TIP_FORM_BUTTON_OK|TIP_FORM_BUTTON_CANCEL,
            'on_process' => array(&$this, '_onCheck')
        );
        return !is_null($this->form(TIP_FORM_ACTION_CUSTOM, $id, $options));
    }

    protected function actionRestore($id, $options = null)
    {
        isset($options) || $options = array(
            'action_id'  => 'restore',
            'buttons'    => TIP_FORM_BUTTON_OK|TIP_FORM_BUTTON_CANCEL,
            'on_process' => array(&$this, '_onRestore')
        );
        return !is_null($this->form(TIP_FORM_ACTION_CUSTOM, $id, $options));
    }

    protected function actionRefresh($id, $options = null)
    {
        isset($options) || $options = array(
            'action_id'  => 'refresh',
            'buttons'    => TIP_FORM_BUTTON_OK|TIP_FORM_BUTTON_CANCEL,
            'on_process' => array(&$this, '_onRefresh')
        );
        return !is_null($this->form(TIP_FORM_ACTION_CUSTOM, $id, $options));
    }


    protected function runAdminAction($action)
    {
        switch ($action) {

        case 'restore':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->actionRestore($id);

        case 'refresh':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->actionRefresh($id);
        }

        return parent::runAdminAction($action);
    }

    protected function runTrustedAction($action)
    {
        switch ($action) {

        case 'check':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->isNotOwner($id) &&
                $this->actionCheck($id);

        case 'refresh':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->isOwner($id) &&
                $this->actionRefresh($id);
        }

        return parent::runTrustedAction($action);
    }

    //}}}
}
?>
