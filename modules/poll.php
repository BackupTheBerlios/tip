<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Poll definition file
 * @package Modules
 **/


/**
 * Poll module
 *
 * @package Modules
 **/
class TIP_Poll extends TIP_Block
{
    /**#@+ @private */

    function TIP_Poll()
    {
        $this->TIP_Block();

        $view =& $this->startView('ORDER BY ' . $this->data->engine->prepareName('date') . ' DESC LIMIT 1');
        $view->rowReset();
        // No endView() call to retain this row as default row
    }

    function onRow(&$row)
    {
        $total = $row['votes1']+$row['votes2']+$row['votes3']+$row['votes4']+$row['votes5']+$row['votes6'];

        $row['TOTAL'] = $total;
        $row['PERCENT1'] = round ($row['votes1'] * 100.0 / $total);
        $row['PERCENT2'] = round ($row['votes2'] * 100.0 / $total);
        $row['PERCENT3'] = round ($row['votes3'] * 100.0 / $total);
        $row['PERCENT4'] = round ($row['votes4'] * 100.0 / $total);
        $row['PERCENT5'] = round ($row['votes5'] * 100.0 / $total);
        $row['PERCENT6'] = round ($row['votes6'] * 100.0 / $total);

        return true;
    }

    /**#@-*/


    /**#@+ @protected */

    function runAction($action)
    {
        switch ($action) {
            /**
             * \action <b>edit</b>\n
             * Vote request. You must specify the answer code in the
             * $_GET['answer'] field.
             **/
        case 'edit':
            $answer = TIP::getGet('answer', 'int');
            if (is_null($answer)) {
                TIP::error('E_NOTSPECIFIED');
                return false;
            }

            $row =& $this->GetCurrentRow ();
            $votes = 'votes' . $answer;
            if (! array_key_exists($votes, $row)) {
                TIP::error('E_INVALID');
                return false;
            }

            setcookie ('plvoting', 'true');
            $this->appendToContent ('vote.src');
            return true;

            /**
             * \action <b>doedit</b>\n
             * Vote operation. You must specify the answer code in the
             * $_GET['answer'] field.
             **/
        case 'doedit':
            if (! array_key_exists('plvoting', $_COOKIE)) {
                TIP::error ('E_COOKIEOFF');
                return false;
            }
            setcookie ('plvoting', '');

            if (array_key_exists('plvoted', $_COOKIE)) {
                TIP::error ('E_PL_DOUBLE');
                return false;
            }

            $answer = TIP::getGet('answer', 'int');
            if (is_null($answer)) {
                TIP::error ('E_NOTSPECIFIED');
                return false;
            }

            $row =& $this->GetCurrentRow ();
            $old_row = $row;
            $votes = "votes$_GET[answer]";
            if (! array_key_exists ($votes, $row))
            {
                TIP::error ('E_INVALID');
                return false;
            }

            ++ $row[$votes];
            $this->onRow($row);
            $this->data->updateRow($old_row, $row, $this);
            setcookie ('plvoted', 'true', strtotime($this->getOption('expiration')));

        case 'browse':
            $this->appendToContent ('browse.src');
            return true;
        }

        return parent::runAction ($action);
    }

    /**#@-*/


    /**#@+ @access public */

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_row->set(array(&$this, 'onRow'));
        return $this->push($view);
    }

    /**#@-*/
}

return new TIP_Poll;

?>
