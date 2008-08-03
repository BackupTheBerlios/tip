<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Sponsor definition file
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
 * Sponsor module
 *
 * Provides a basic implementation for a sponsor module.
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Sponsor extends TIP_Content
{
    //{{{ Properties

    /**
     * The field of the destination URI of the sponsor
     * @var string
     */
    protected $uri_field = 'uri';

    /**
     * The field of the counter of times the sponsor was showed
     * @var string
     */
    protected $count_field = '_count';

    /**
     * The field of the counter of past actionView
     * @var string
     */
    protected $counted_field = '_submitted_count';

    /**
     * The field of the counter of past times the sponsor was showed
     * @var string
     */
    protected $hitted_field = '_submitted_hits';

    //}}}
    //{{{ Internal properties

    /**
     * Row of the current showed sponsor
     * @var array
     * @internal
     */
    private $_row = null;

    /**
     * Old content of $_row
     * @var array
     * @internal
     */
    private $_old_row = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Sponsor instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    protected function postConstructor()
    {
        parent::postConstructor();

        $filter = $this->data->order($this->count_field) . ' LIMIT 1';
        if (!is_null($view = $this->startDataView($filter)) && $view->rewind()) {
            $this->_row = $this->_old_row = $view->current();
            // Increment count field
            ++ $this->_row[$this->count_field];
        }
    }

    /**
     * Destructor
     * Updates the current sponsor statistics
     */
    function __destruct()
    {
        if (is_array($this->_row)) {
            $this->data->updateRow($this->_row, $this->_old_row);
            $this->endView();
        }
    }

    //}}}
    //{{{ Callbacks

    /**
     * 'on_row' callback for TIP_Data_View
     *
     * Adds the following calculated fields to every data row:
     * - 'COUNT': the difference between counts and submitted counts
     * - 'HITS':  the difference between hits and submitted hits
     *
     * @param  array &$row The row as generated by TIP_Data_View
     * @return bool        always true
     */
    public function _onDataRow(&$row)
    {
        $row['COUNT'] = $row[$this->count_field] - $row[$this->counted_field];
        $row['HITS'] = $row[$this->hits_field] - $row[$this->hitted_field];
        return true;
    }

    /**
     * Overridable 'add' callback
     *
     * Overrides the default 'add' callback setting 'count_field' and
     * 'counted_field' to the current sponsor count.
     *
     * @param  array &$row The data row to add
     * @return bool        true on success, false on errors
     */
    public function _onAdd(&$row)
    {
        $row[$this->count_field] = $this->_row[$this->count_field];
        $row[$this->counted_field] = $this->_row[$this->count_field];
        return parent::_onAdd($row);
    }

    //}}}
    //{{{ Actions

    /**
     * Perform a view action
     *
     * Overrides the default view providing a redirection to the sponsor site,
     * if the 'uri_field' is not empty. If the destination URI is not defined,
     * performs the default view action.
     *
     * @param  mixed $id The identifier of the row to view
     * @return bool      true on success or false on errors
     */
    protected function actionView($id)
    {
        if (is_null($row =& $this->fromRow($id, false)) || !$this->_onView($row)) {
            return false;
        }

        if (@array_key_exists($this->uri_field, $row) && !empty($row[$this->uri_field])) {
            // The URI of the sponsor site is defined: redirect the browser
            header('Location: ' . $row[$this->uri_field]);
            exit;
        }

        $this->appendToPage($this->view_template);
        $this->endView();
        return true;
    }

    //}}}
}
?>
