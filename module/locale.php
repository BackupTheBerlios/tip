<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Locale definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Locale module
 *
 * Provides a basic interface to the localization of short texts (less than
 * 256 bytes).
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Locale extends TIP_Block
{
    /**#@+ @access private */

    var $_locale = null;


    function _onRow(&$row)
    {
        $row['MESSAGE'] = $row[$this->_locale];
    }

    /**#@-*/


    /**#@+ @access protected */

    function TIP_Locale()
    {
        // The data stuff is initialized here, so the call to $this->TIP_Block
        // will be skipped
        $this->TIP_Module();

        $this->_locale = TIP::getOption('application', 'locale');

        if (is_null($data_path = $this->guessDataPath()) ||
            is_null($data_engine = $this->guessDataEngine())) {
            return;
        }

        $this->data =& TIP_Data::getInstance($data_path, $data_engine, array('id', $this->_locale));
    }

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_row->set(array(&$this, '_onRow'));
        return $this->push($view);
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Get a localized text
     *
     * Given an $id and a $module, retrieves the localized text (in the current
     * locale) binded to "$module.$id". This means the data object of
     * TIP_Locale must have a series of rows with module.id values as the
     * primary key.
     *
     * Usually the locale query is cached, that is, to avoid multiple queries,
     * a single request from a module gets all the localized text of this
     * module. If you are sure the other localized strings are not used (such
     * as for the TIP_Notify module) you can disable the cache by passig false
     * to the $cached argument.
     *
     * The $context associative array contains a series of key=>value pairs
     * that can be substituted in the localized text. The get() method will
     * search in the localized text for any key enclosed by '|' and will put 
     * the corresponding value. For instance, if there is a 'size'=>200 in
     * the $context array, the text 'Max allowed size is |size|...' will
     * expand to 'Max allowed size is 200...'.
     *
     * @param string $id      The text identifier
     * @param string $module  The name of the caller module
     * @param array  $context The context associative array
     * @param bool   $cached  Whether to perform or not a cached read
     * @return string The requested localized text or $id on errors
     */
    function get($id, $module, $context = null, $cached = true)
    {
        $row_id = $module . '.' . $id;

        if ($cached) {
            $filter = $this->data->filter('id', $module . '.%', 'LIKE');
            $view =& $this->startView($filter);
            if (is_null($view)) {
                return $id;
            }

            $rows =& $this->view->rows;
            if (array_key_exists($row_id, $rows)) {
                $row =& $rows[$row_id];
            } else {
                $row = null;
            }

            $this->endView();
        } else {
            $row =& $this->data->getRow($row_id);
        }

        if (is_null($row)) {
            TIP::warning("Localized text not found ($row_id)");
            return $id;
        }

        $text = @$row[$this->_locale];
        if (is_null($context) || strpos($text, '|') === false) {
            return $text;
        }

        // There are some embedded keys to expand ...
        $token = explode('|', $text);
        foreach ($token as $n => $value) {
            // Odd tokens are keys
            if ($n & 1) {
                $token[$n] = @$context[$value];
            }
        }
        return implode($token);
    }

    /**#@-*/
}

return 'TIP_Locale';

?>