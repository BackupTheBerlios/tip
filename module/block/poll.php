<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Poll definition file
 * @package TIP
 * @subpackage Module
 */


/**
 * Poll module
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Poll extends TIP_Block
{
    /**#@+ @access private */

    function _onRow(&$row)
    {
        $total = $row['votes1']+$row['votes2']+$row['votes3']+$row['votes4']+$row['votes5']+$row['votes6'];

        $row['TOTAL'] = $total;
        if ($total == 0) {
            $total = 1;
        }

        $row['PERCENT1'] = round ($row['votes1'] * 100.0 / $total);
        $row['PERCENT2'] = round ($row['votes2'] * 100.0 / $total);
        $row['PERCENT3'] = round ($row['votes3'] * 100.0 / $total);
        $row['PERCENT4'] = round ($row['votes4'] * 100.0 / $total);
        $row['PERCENT5'] = round ($row['votes5'] * 100.0 / $total);
        $row['PERCENT6'] = round ($row['votes6'] * 100.0 / $total);

        return true;
    }

    function _stripVotes($field)
    {
        return substr($field['id'], 0, 4) != 'vote';
    }

    /**#@-*/


    /**#@+ @access protected */

    function TIP_Poll($id)
    {
        $this->TIP_Block($id);
    }

    function runManagerAction($action)
    {
        switch ($action) {

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }

            return !is_null($this->form(TIP_FORM_ACTION_DELETE, $id));
        }

        return parent::runManagerAction($action);
    }

    function runAdminAction($action)
    {
        switch ($action) {

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }

            return !is_null($this->form(TIP_FORM_ACTION_EDIT, $id));
        }

        return parent::runAdminAction($action);
    }

    function runTrustedAction($action)
    {
        switch ($action) {

        case 'add':
            $fields = array_filter($this->data->getFields(), array(&$this, '_stripVotes'));
            return !is_null($this->form(TIP_FORM_ACTION_ADD, null, array('fields' => $fields)));
        }

        return parent::runTrustedAction($action);
    }

    function runUntrustedAction($action)
    {
        switch ($action) {

        case 'set':
            if (is_null($id = TIP::getGet('id', 'int')) && is_null($id = TIP::getPost('id', 'integer')) ||
                is_null($answer_id = TIP::getGet('answer', 'int'))) {
                TIP::notifyError('noparams');
                return false;
            }

            $expiration = @HTTP_Session::getLocal('expiration');
            $voting = @HTTP_Session::getLocal('voting');
            if ($voting && time() < $expiration) {
                TIP::notifyError('doublevote');
                return false;
            }

            $view =& $this->startView($this->data->rowFilter($id));
            if (is_null($view)) {
                TIP::notifyError('select');
                return false;
            } elseif (!$view->rowReset()) {
                TIP::notifyError('notfound');
                $this->endView();
                return false;
            }

            $answer_label = $this->getField('answer' . $answer_id);
            if (empty($answer_label)) {
                TIP::notifyError('wrongparams');
                $this->endView();
                return false;
            }

            if (@TIP::getGet('process', 'int') == 1) {
                if (!$voting) {
                    TIP::notifyError('nocookies');
                    $this->endView();
                    return false;
                }
                $row =& $this->view->rowCurrent();
                $old_row = $row;
                ++ $row['votes' . $answer_id];

                $this->_onRow($row);
                $this->data->updateRow($row, $old_row);
                HTTP_Session::setLocal('voting', false);
                HTTP_Session::setLocal('expiration', strtotime($this->getOption('expiration')));
                $this->appendToContent('view.src');
            } else {
                HTTP_Session::setLocal('voting', true);
                $this->appendToContent('vote.src');
            }

            $this->endView();
            return true;

        case 'view':
            $id = @TIP::getGet('id', 'int');
            if (!$id) {
                TIP::notifyError('noparams');
                return false;
            }

            $view =& $this->startView($this->data->rowFilter($id));
            if (is_null($view)) {
                TIP::notifyError('select');
                return false;
            } elseif (!$view->rowReset()) {
                TIP::notifyError('notfound');
                $this->endView();
                return false;
            }

            $this->appendToContent('view.src');
            $id && $this->endView();
            return true;

        case 'browse':
            $this->appendToContent('browse.src');
            return true;
        }

        return parent::runUntrustedAction($action);
    }

    /**#@-*/


    /**#@+ @access public */

    function& startView($filter)
    {
        return TIP_Block::startView($filter, array('on_row' => array(&$this, '_onRow')));
    }

    /**#@-*/
}
?>
