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
class TIP_Locale extends TIP_Content
{
    /**#@+ @access private */

    var $_locale = null;
    var $_cache = array();


    function _onRow(&$row)
    {
        $row['MESSAGE'] = $row[$this->_locale];
        return true;
    }

    /**#@-*/


    /**#@+ @access protected */

    function __construct($id)
    {
        parent::__construct($id);
    }

    function getDataOptions()
    {
        $this->_locale = $GLOBALS[TIP_MAIN]->getOption('locale');
        $options = parent::getDataOptions();
        $options['fieldset'] = array('id', $this->_locale);
        return $options;
    }

    function& startView($filter)
    {
        return parent::startView($filter, array('on_row' => array(&$this, '_onRow')));
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Get a localized text
     *
     * Given an $id and a $prefix, retrieves the localized text (in the locale
     * specified by the 'locale' option of the main TIP_Application) binded to
     * "$prefix.$id". This means the data object of TIP_Locale must have a
     * series of rows with prefix.id values as the primary key.
     *
     * The choice of splitting the key in $prefix and $id allows to perform
     * a sort of cached read, that is, to avoid multiple queries, a single
     * request for a specified prefix gets all the id of this prefix.
     * If you are sure the other id are not used (such as for the TIP_Notify
     * module) you can disable the cache by passig false to $cached.
     *
     * The $context associative array contains a series of key=>value pairs
     * that can be substituted in the localized text. The get() method will
     * search in the localized text for any key enclosed by '|' and will put 
     * the corresponding value. For instance, if there is a 'size'=>200 in
     * the $context array, the text 'Max allowed size is |size|...' will
     * expand to 'Max allowed size is 200...'.
     *
     * @param  string      $id      The identifier
     * @param  string      $prefix  The prefix
     * @param  array       $context A context associative array
     * @param  bool        $cached  Whether to perform or not a cached read
     * @return string|null          The localized text or null if not found
     */
    function get($id, $prefix, $context, $cached)
    {
        $row_id = $prefix . '.' . $id;
        if (array_key_exists($row_id, $this->_cache)) {
            // Localized text found in the TIP_Locale cache
            $row =& $this->_cache[$row_id];
        } elseif ($cached) {
            $filter = $this->data->filter('id', $prefix . '.%', 'LIKE');
            $view =& $this->startView($filter);
            if (is_null($view)) {
                TIP::error("no way to get localized text ($row_id)");
                return null;
            }

            $this->_cache += $this->view->rows;
            $this->endView();

            if (array_key_exists($row_id, $this->_cache)) {
                $row =& $this->_cache[$row_id];
            } else {
                // $row_id not found
                $this->_cache[$row_id] = null;
                return null;
            }
        } else {
            $row =& $this->data->getRow($row_id);
            if (is_null($this->_cache[$row_id] = $row)) {
                // $row_id not found
                return null;
            }
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
?>
