<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Locale definition file
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
 * Locale module
 *
 * Provides a basic interface to the localization of short texts (less than
 * 256 bytes).
 *
 * @package TIP
 */
class TIP_Locale extends TIP_Content
{
    //{{{ Properties

    /**
     * The locale identification string (such as 'en' or 'it')
     * @var string
     */
    protected $locale = null;

    //}}}
    //{{{ Constructor/destructor

    static protected function checkOptions(&$options)
    {
        if (!isset($options['locale'])) {
            return false;
        }

        // The 'data' option must be defined before calling parent::checkOptions()
        // so I can force the 'fieldset' option on the 'data' object
        isset($options['data']) || $options['data'] = $options['id'];
        if (is_string($options['data'])) {
            $options['data'] = array(
                'path'     => TIP_Application::getGlobal('data_prefix') . $options['data'],
                'fieldset' => array('id', $options['locale'])
            );
        }
        return parent::checkOptions($options);
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Locale instance.
     *
     * $options inherits the TIP_Content properties, and add the following:
     * - $options['locale']: the locale identification string (required)
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Methods

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
    public function get($id, $prefix, $context, $cached)
    {
        $row_id = $prefix . '.' . $id;
        if (array_key_exists($row_id, $this->_cache)) {
            // Localized text found in the TIP_Locale cache
            $row =& $this->_cache[$row_id];
        } elseif ($cached) {
            $filter = $this->data->filter('id', $prefix . '.%', 'LIKE');
            if (is_null($view =& $this->startDataView($filter))) {
                TIP::error("no way to get localized text ($row_id)");
                return null;
            }

            $this->_cache += $view->getProperty('rows');
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

        $text = $row[$this->locale];
        if (is_null($context) || strpos($text, '|') === false) {
            return $text;
        }

        // There are some embedded keys to expand ...
        $token = explode('|', $text);
        foreach ($token as $n => $value) {
            // Odd tokens are keys
            if ($n & 1) {
                $token[$n] = array_key_exists($value, $context) ? $context[$value] : '';
            }
        }
        return implode($token);
    }

    //}}}
    //{{{ Internal properties

    /**
     * An internal cache to avoid using the TIP_Data_View interface
     * @var array
     * @internal
     */
    private $_cache = array();

    //}}}
    //{{{ Callbacks

    /**
     * 'on_row' callback for TIP_Data_View
     *
     * Adds the following calculated fields to every data row:
     * - 'MESSAGE': a reference to the proper localized message
     *
     * @param  array &$row The row as generated by TIP_Data_View
     * @return bool        always true
     */
    public function _onDataRow(&$row)
    {
        $row['MESSAGE'] = $row[$this->locale];

        // Chain-up the parent callback
        return parent::_onDataRow($row);
    }

    //}}}
}
?>
