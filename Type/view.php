<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_View definition file
 *
 * @package TIP
 */

/**
 * A data view
 *
 * Management of the data views mainly used by the TIP_Block.
 * This class provides a way to bind summuries values to the view (such as
 * totals or subtotals) and some callbacks to customize the view.
 * Specifically, the $on_row callback is used to add some custom fields
 * (also called calculated fields) to every row while the $on_view callback
 * can be used to fill the $_summary values with every kind of aggregate
 * function you desire.
 *
 * Notice after starting a view there is no current row, so trying to retrieve
 * some data will fail. You must use a cursor movement method to initially set
 * the cursor position.
 *
 * @package TIP
 */
class TIP_View extends TIP_Type implements Iterator
{
    /**
     * Row callback
     *
     * Called for every row added to $_rows. The only argument passed to the
     * callback is a reference to the added row. Useful to add calculated
     * fields to every row.
     *
     * @var TIP_Callback
     */
    private $_on_row = null;

    /**
     * View callback
     *
     * Called at the end of the population of $_rows. A reference to the current
     * view is passed as argument to the callback. Useful to add summary values
     * or perform general operations on the whole data of the view.
     *
     * The custom callback must return true to validate the view or false to
     * invalidate it.
     *
     * @var TIP_Callback
     */
    private $_on_view = null;

    /**
     * The data object
     *
     * A reference to the TIP_Data object the view will apply.
     *
     * @var TIP_Data
     */
    protected $_data = null;

    /**
     * The filter conditions
     *
     * The filter is defined in the TIP_View() constructor. The exact format
     * of this string is data engine dependent: see the documentation of the
     * data engine used by the data object for further informations.
     *
     * @var string
     */
    protected $_filter = null;

    /**
     * The list of rows
     *
     * This list should be filled in the construction process. It is null if
     * not yet filled or false on errors.
     *
     * The format of the row depends on the type of the view. In TIP_View, the
     * format is the one used also in TIP_Data. Furthermore, some common
     * fields are added to every row (also for the special views):
     *
     * - 'ROW', the row number starting from 1
     * - 'ODDEVEN', a switching text: 'odd' for odd and 'even' for even rows
     *
     * @var array|null
     */
    protected $_rows = null;

    /**
     * A list of summary values
     *
     * Here must be present the values of summary operations done on the
     * $rows array, such as totals and counts.
     *
     * The following values are always present in $_summary:
     *
     * - 'COUNT', the number of rows in the $rows property
     *
     * @var array
     */
    protected $_summary = null;


    /**
     * Constructor
     *
     * Initializes a TIP_View instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    protected function __construct($id, $args)
    {
        parent::__construct($id);

        foreach ($args as $key => &$value) {
            $property = '_' . $key;
            $this->$property =& $value;
        }
    }

    protected function postConstructor()
    {
        $this->_rows =& $this->_data->getRows($this->_filter);
        $this->onPopulated();
    }

    /**
     * Build a TIP_View identifier
     *
     * $args must be an array with the following items (all are optionals):
     * - $args['data']: a reference to a TIP_Data object
     * - $args['filter']: the filter to apply
     * - $args['on_row']: callback to run for every row
     * - $args['on_view']: callback to run when populated
     *
     * @param  array  $args The constructor arguments
     * @return string       The data identifier
     */
    protected function buildId($args)
    {
        $id = $args['data']->getId();
        if (array_key_exists('filter', $args)) {
            $id .= ':' . $args['filter'];
        }
        return $id;
    }

    protected function onPopulated()
    {
        $this->_summary = null;
        if (!is_array($this->_rows)) {
            return false;
        }

        $filtered_rows = array();
        $n_row = 0;
        foreach ($this->_rows as $id => &$row) {
            if (!$this->_on_row || call_user_func_array($this->_on_row, array(&$row))) {
                ++ $n_row;
                $row['ROW']     = $n_row;
                $row['ODDEVEN'] = ($n_row & 1) > 0 ? 'odd' : 'even';
                $filtered_rows[$id] =& $row;
            }
        }

        $this->_rows =& $filtered_rows;
        $this->_summary['COUNT'] = $n_row;
        return !$this->_on_view || call_user_func_array($this->_on_view, array(&$this));
    }

    public function isValid()
    {
        return is_array($this->_rows);
    }

    public function &getRows()
    {
        return $this->_rows;
    }

    public function getField($id)
    {
        $row =& $this->current();
        return @$row[$id];
    }

    public function getSummary($id)
    {
        return @$this->_summary[$id];
    }

    public function setSummary($id, $value)
    {
        $this->_summary[$id] = $value;
    }

    /**
     * Set the internal cursor to the first row
     *
     * @return bool true on success, false on errors
     */
    public function rewind()
    {
        return reset($this->_rows) !== false;
    }

    /**
     * Get the current row
     *
     * @return array|null The current row or null on errors
     */
    public function current()
    {
        $row = current($this->_rows);
        return is_array($row) ? $row : null;
    }

    /**
     * Get the id of the current row
     *
     * @return mixed|null The current key or null on errors
     */
    public function key()
    {
        return key($this->_rows);
    }

    /**
     * Set the cursor to the next row
     *
     * @return bool true on success, false on errors
     */
    public function next()
    {
        return next($this->_rows) !== false;
    }

    /**
     * Check if the current row is valid
     *
     * @return bool true on success, false on errors
     */
    public function valid()
    {
        return !is_null(key($this->_rows));
    }
}
?>
