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


    function TIP_Comments($block_id)
    {
        // There is a singleton for every master block
        $this->_id = $this->_buildId($block_id);
        $this->_master_id = $block_id;
        $this->TIP_Block();
    }

    function _buildId($block_id)
    {
        return strtolower($block_id) . '_' . TIP_COMMENTS_POSTFIX;
    }

    /**#@-*/


    /**#@+ access protected */

    function getOption($option)
    {
        return @$GLOBALS['cfg'][$this->_master_id][TIP_COMMENTS_POSTFIX][$option];
    }

    function runManagerAction($action)
    {
        switch ($action) {
        case 'edit':
            if (is_null($id = TIP::getPost('_parent', 'int')) && is_null($id = TIP::getGet('id', 'int'))) {
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

            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id);
            return !is_null($processed);
        }

        return null;
    }

    function runTrustedAction($action)
    {
        switch ($action) {
        case 'add':
            if (is_null($id = TIP::getPost('_parent', 'int')) && is_null($id = TIP::getGet('id', 'int'))) {
                TIP::error('no parent id specified');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'referer'        => $_SERVER['REQUEST_URI'],
                'buttons'        => TIP_FORM_BUTTON_SUBMIT,
                'invalid_render' => TIP_FORM_RENDER_HERE,
                'valid_render'   => TIP_FORM_RENDER_IN_CONTENT,
                'defaults'       => array(
                    '_creation'  => TIP::formatDate('datetime_iso8601'),
                    '_user'      => TIP::getUserId(),
                    '_parent'    => $id,
                )
            ));
            if ($processed) {
            }
            return !is_null($processed);
        }

        return null;
    }

    /**#@-*/
}

?>
