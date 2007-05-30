<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */

/**
 * Comment module
 *
 * @package TIP
 */
class TIP_Comments extends TIP_Content
{
    /**#@+ access private */

    var $_master = null;
    var $_slave_field = null;
    var $_master_id = null;


    function _updateCounter($offset)
    {
        $id = $this->_master_id;
        $view =& $this->_master->startView($this->_master->data->rowFilter($id));
        if (is_null($view)) {
            TIP::warning("unable to get row $id on data " . $this->_master->data->getId());
            TIP::notifyError('select');
            return false;
        }

        $row =& $view->current();
        $this->_master->endView();

        if (is_null($row)) {
            TIP::error("row $id not found in " . $this->_master->data->getId());
            TIP::notifyError('notfound');
            return false;
        }
        $row =& $this->_master->getRow($this->_master_id);

        $old_row = $row;
        $row['_comments'] += $offset;
        if (!$this->_master->data->updateRow($row, $old_row)) {
            TIP::error("no way to update comments counter on row $id in " . $this->_master->data->getId());
            return false;
        }

        return true;
    }

    function _onAdd(&$form, &$row)
    {
        if ($this->_updateCounter(+1)) {
            $form->process($row);
        }

        // Update _comments, if the user module exists
        $user =& $GLOBALS[TIP_MAIN]->getSharedModule('user');
        if (is_object($user) && !is_null($count = $user->getLoggedField('_comments'))) {
            $user->setLoggedField('_comments', $count+1);
        }
    }

    function _onDelete(&$form, &$row)
    {
        $this->_master_id = $row[$this->_slave_field];
        if ($this->_updateCounter(-1)) {
            $form->process($row);
        }

        // Update _deleted_comments, if the user module exists
        $user =& $GLOBALS[TIP_MAIN]->getSharedModule('user');
        if (is_object($user) && !is_null($count = $user->getLoggedField('_deleted_comments'))) {
            $user->setLoggedField('_deleted_comments', $count+1);
        }
    }

    /**#@-*/


    /**#@+ access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Comments instance.
     *
     * @param string $id The instance identifier
     */
    function __construct($id)
    {
        parent::__construct($id);

        $this->_master =& TIP_Type::getInstance($this->getOption('master_module'));
        $this->_slave_field = $this->getOption('slave_field');
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Add a comments form
     */
    function commandAdd($params)
    {
        $this->_master_id = (int) $params;
        return $this->callAction('add');
    }

    /**#@-*/


    function runManagerAction($action)
    {
        switch ($action) {
        case 'edit':
            if (is_null($id = TIP::getPost('id', 'int')) && is_null($id = TIP::getGet('id', 'int'))) {
                TIP::error('no id specified');
                return false;
            }

            $processed = $this->form(TIP_FORM_ACTION_EDIT, $id);
            return !is_null($processed);
        }

        return null;
    }

    function runAdminAction($action)
    {
        switch ($action) {
        case 'delete':
            $id = TIP::getGet('id', 'int');
            if (empty($id)) {
                TIP::error('no comment specified');
                return false;
            }

            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete')
            ));
            return !is_null($processed);
        }

        return null;
    }

    function runTrustedAction($action)
    {
        switch ($action) {
        case 'add':
            if (is_null($this->_master_id)) {
                $this->_master_id = TIP::getPost($this->_slave_field, 'int');
            }

            if (is_null($this->_master_id)) {
                $this->_master_id = TIP::getGet($this->_slave_field, 'int');
            }

            if (is_null($this->_master_id)) {
                TIP::error('no parent id specified');
                return null;
            }

            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'referer'               => $_SERVER['REQUEST_URI'],
                'buttons'               => TIP_FORM_BUTTON_SUBMIT,
                'invalid_render'        => TIP_FORM_RENDER_HERE,
                'valid_render'          => TIP_FORM_RENDER_IN_CONTENT,
                'defaults'              => array(
                    '_creation'         => TIP::formatDate('datetime_iso8601'),
                    '_user'             => TIP::getUserId(),
                    $this->_slave_field => $this->_master_id
                ),
                'on_process'            => array(&$this, '_onAdd')
            ));

            return !is_null($processed);
        }

        return parent::runTrustedAction($action);
    }

    /**#@-*/


    /**#@+ @access public */

    function parentRemoved($id)
    {
        $filter = $this->data->filter($this->_slave_field, $id);
        return $this->data->deleteRows($filter);
    }

    /**#@-*/
}
?>
