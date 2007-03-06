<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 */

/**
 * Pseudo-block to add comments
 *
 * @package TIP
 */
class TIP_Comments extends TIP_Block
{
    /**#@+ access private */

    var $_master_id = null;
    var $_parent_id = null;


    function _updateCounter($offset)
    {
        $master =& TIP_Module::getInstance($this->_master_id);
        $id = $this->_parent_id;
        $view =& $master->startView($master->data->rowFilter($id));
        if (is_null($view)) {
            TIP::error("unable to get row $id on data " . $master->data->getId());
            return false;
        }
        $row =& $view->rowReset();
        $master->endView();
        if (is_null($row)) {
            TIP::error("row $id not found on data " . $master->data->getId());
            return false;
        }
        $old_row = $row;
        $row['_comments'] += $offset;
        if (!$master->data->updateRow($row, $old_row)) {
            TIP::error("no way to update comments counter on row $id for data " . $master->data->getId());
            return false;
        }

        return true;
    }

    function _onAdd(&$form, &$row)
    {
        if ($this->_updateCounter(+1)) {
            $form->process($row);
        }
    }

    function _onDelete(&$form, &$row)
    {
        $this->_parent_id = $row['_parent'];
        if ($this->_updateCounter(-1)) {
            $form->process($row);
        }
    }

    /**#@-*/


    /**#@+ access protected */

    /**
     * Constructor
     *
     * Initialize an implementation of the TIP_Comments interface.
     *
     * @param string $block_id The id of the master block
     */
    function TIP_Comments($block_id)
    {
        // There is a singleton for every master block
        $this->_id = strtolower($block_id) . '_comments';
        $this->_master_id = $block_id;
        $this->TIP_Block();
    }

    function getOption($option)
    {
        return @$GLOBALS['cfg'][$this->_master_id]['comments'][$option];
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
        $this->_parent_id = (int) $params;
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
            if (is_null($this->_parent_id)) {
                $this->_parent_id = TIP::getPost('_parent', 'int');
            }

            if (is_null($this->_parent_id)) {
                $this->_parent_id = TIP::getGet('id', 'int');
            }

            if (is_null($this->_parent_id)) {
                TIP::error('no parent id specified');
                return null;
            }

            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'referer'        => $_SERVER['REQUEST_URI'],
                'buttons'        => TIP_FORM_BUTTON_SUBMIT,
                'invalid_render' => TIP_FORM_RENDER_HERE,
                'valid_render'   => TIP_FORM_RENDER_IN_CONTENT,
                'defaults'       => array(
                    '_creation'  => TIP::formatDate('datetime_iso8601'),
                    '_user'      => TIP::getUserId(),
                    '_parent'    => $this->_parent_id
                ),
                'on_process' => array(&$this, '_onAdd')
            ));

            return !is_null($processed);
        }

        return null;
    }

    /**#@-*/


    /**#@+ @access public */

    function parentRemoved($id)
    {
        $filter = $this->data->filter('_parent', $id);
        return $this->data->deleteRows($filter);
    }

    /**#@-*/
}

?>
