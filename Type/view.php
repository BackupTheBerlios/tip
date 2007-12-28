<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_View definition file
 *
 * @package TIP
 */

/**
 * View abstraction
 *
 * The TIP_View is a rows data model.
 *
 * @package TIP
 */
abstract class TIP_View extends TIP_Type implements Iterator
{
    //{{{ Properties

    /**
     * A reference to the TIP_Data object the view will apply
     * @var TIP_Data
     */
    protected $data = null;

    /**
     * The filter conditions
     *
     * The filter is defined in the TIP_View() constructor. The exact format
     * of this string is data engine dependent: see the documentation of the
     * data engine used by the data object for further informations.
     *
     * @var string
     */
    protected $filter = null;

    /**
     * The fields to get
     *
     * A subset of fields to get with this view. Leave it undefined to get
     * all the fields.
     *
     * @var array
     */
    protected $fields = null;

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
    protected $rows = null;

    /**
     * A list of summary values
     *
     * Here must be present the values of summary operations done on the
     * 'rows' property, such as totals and counts.
     *
     * The following values are always present in 'summary':
     *
     * - 'COUNT', the number of rows in the 'rows' property
     *
     * @var array
     */
    protected $summary = null;

    /**
     * Row callback
     *
     * Called for every row added to the 'rows' property. The only argument
     * passed to the callback is a reference to the added row. Useful to add
     * calculated fields to every row.
     *
     * The custom callback must return true to include the processed row in
     * the 'rows' property.
     *
     * @var callback
     */
    protected $on_row = null;

    /**
     * View callback
     *
     * Called at the end of the population of the 'rows' property. A reference
     * to the current view is passed as argument to the callback. Useful to add
     * summary values or perform general operations on the whole data view.
     *
     * The custom callback must return true to validate the view or false to
     * invalidate it.
     *
     * @var callback
     */
    protected $on_view = null;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_View instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
        is_null($this->rows) && $this->fillRows();
    }

    protected function postConstructor()
    {
        if (!is_array($this->rows)) {
            return;
        }

        $filtered_rows = array();
        $n_row = 0;
        foreach ($this->rows as $id => &$row) {
            if (!$this->on_row || call_user_func_array($this->on_row, array(&$row))) {
                ++ $n_row;
                $row['ROW']     = $n_row;
                $row['ODDEVEN'] = ($n_row & 1) > 0 ? 'odd' : 'even';
                $filtered_rows[$id] =& $row;
            }
        }

        $this->rows =& $filtered_rows;
        $this->summary['COUNT'] = $n_row;
        if (isset($this->on_view) && !call_user_func_array($this->on_view, array(&$this))) {
            // 'on_view' callback returned false: invalidate the view
            $this->rows = null;
        }
    }

    //}}}
    //{{{ Methods
 
    /**
     * Check for a valid view
     *
     * Checks if this view instance contains valid data, that is if the query
     * was performed succesfully.
     *
     * @return bool true if the view is valid or false otherwise
     */
    public function isValid()
    {
        return is_array($this->rows);
    }

    /**
     * The number of rows in this view
     * @return int The number of rows or -1 on errors
     */
    public function nRows()
    {
        return is_array($this->rows) ? count($this->rows) : -1;
    }

    /**
     * Get a field value from the current row
     *
     * @param  mixed      $field The field id
     * @return mixed|null        The requested field value or null on errors
     */
    public function getField($field)
    {
        $row =& $this->current();
        return @$row[$field];
    }

    /**
     * Get a summary field value
     *
     * @param  mixed      $field The summary field id
     * @return mixed|null        The requested field value or null on errors
     */
    public function getSummary($field)
    {
        return @$this->summary[$field];
    }

    /**
     * Set a summary field value
     *
     * @param  mixed      $field The summary field id
     * @param  mixed      $value The new summary field value
     */
    public function setSummary($field, $value)
    {
        $this->summary[$field] = $value;
    }

    //}}}
    //{{{ Interface

    abstract protected function fillRows();

    //}}}
    //{{{ Iterator implementation

    /**
     * Set the internal cursor to the first row
     *
     * @return bool true on success, false on errors
     */
    public function rewind()
    {
        return is_array($this->rows) && reset($this->rows) !== false;
    }

    /**
     * Get the current row
     *
     * @return array|null The current row or null on errors
     */
    public function current()
    {
        $row = current($this->rows);
        return is_array($row) ? $row : null;
    }

    /**
     * Get the id of the current row
     *
     * @return mixed|null The current key or null on errors
     */
    public function key()
    {
        return key($this->rows);
    }

    /**
     * Set the cursor to the next row
     *
     * @return bool true on success, false on errors
     */
    public function next()
    {
        return next($this->rows) !== false;
    }

    /**
     * Check if the current row is valid
     *
     * @return bool true on success, false on errors
     */
    public function valid()
    {
        return !is_null(key($this->rows));
    }

    //}}}
}
?>
