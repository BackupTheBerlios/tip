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
 * can be used to fill the $summaries values with every kind of aggregate
 * function you desire.
 *
 * Notice after starting a view there is no current row, so trying to retrieve
 * some data will fail. You must use a cursor movement method to initially set
 * the cursor position.
 *
 * @package TIP
 */
class TIP_View extends TIP_Type
{
    /**#@+ @access protected */

    /**
     * The data object
     *
     * A reference to the TIP_Data object the view will apply.
     *
     * @var TIP_Data
     */
    var $data = null;

    /**
     * The filter conditions
     *
     * The filter is defined in the TIP_View() constructor. The exact format
     * of this string is data engine dependent: see the documentation of the
     * data engine used by the data object for further informations.
     *
     * @var string
     */
    var $filter = null;


    /**
     * Constructor
     *
     * Initializes a TIP_View instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function __construct($id, $args)
    {
        parent::__construct($id);

        if (array_key_exists('data', $args)) {
            $this->data =& $args['data'];
        }

        $this->filter = @$args['filter'];
        $this->on_row =& new TIP_Callback(array_key_exists('on_row', $args) ? $args['on_row'] : true);
        $this->on_view =& new TIP_Callback(array_key_exists('on_view', $args) ? $args['on_view'] : true);
        $this->summaries['COUNT'] = 0;
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
    function buildId($args)
    {
        return $args['data']->getId() . ':' . $args['filter'];
    }

    /**
     * Execute the query
     *
     * Fills the $rows by executing a read query with the specified filter
     * conditions.
     *
     * This method is usually overriden by the special views to perform
     * different operations.
     *
     * @return bool true on success or false on errors
     */
    function fillRows()
    {
        $this->rows =& $this->data->getRows($this->filter);
        if (is_null($this->rows)) {
            $this->rows = false;
            return false;
        }
        return true;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * The list of rows
     *
     * This list is filled by the populate() method. It is null if not yet
     * filled or false on errors.
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
    var $rows = null;

    /**
     * A list of summary values
     *
     * Here must be present the values of summary operations done on the
     * $rows array, such as totals and counts.
     *
     * The following values are always present in $summaries:
     *
     * - 'COUNT', the number of rows in the $rows property
     *
     * @var array
     */
    var $summaries = array();

    /**
     * Row callback
     *
     * Called for every row added to $rows. The only argument passed to the
     * callback is a reference to the added row. Useful to add calculated
     * fields to every row.
     *
     * @var TIP_Callback
     */
    var $on_row = null;

    /**
     * View callback
     *
     * Called at the end of the population of $rows. A reference to the current
     * view is passed as argument to the callback. Useful to add summary values
     * or perform general operations on the whole data of the view.
     *
     * The custom callback must return true to validate the view or false to
     * invalidate it.
     *
     * @var TIP_Callback
     */
    var $on_view = null;


    /**
     * Populate the view
     *
     * Performs all the needed steps to fill $rows, call the callbacks, add
     * the standard summary values and so on.
     *
     * @param  bool $refresh Forces the fillRows() call
     * @return bool          true on success or false on errors
     */
    function populate($refresh = false)
    {
        if (!is_null($this->rows) && !$refresh) {
            $this->rowUnset();
            return true;
        }

        $this->rows = null;
        if (!$this->fillRows()) {
            return false;
        } elseif (!is_array($this->rows)) {
            return true;
        }

        $n_row = 0;
        foreach (array_keys($this->rows) as $id) {
            $row =& $this->rows[$id];
            if (!$this->on_row->goWithArray(array(&$row))) {
                // If the user callback returns false, remove the row
                array_splice($this->rows, $n_row, 1);
            } else {
                ++ $n_row;
                $row['ROW']     = $n_row;
                $row['ODDEVEN'] = ($n_row & 1) > 0 ? 'odd' : 'even';
            }
            unset($row);
        }

        $this->summaries['COUNT'] = $n_row;
        return $this->on_view->goWithArray(array(&$this)) && $this->rowUnset();
    }

    /**
     * Unsets the cursor
     *
     * Sets the internal cursor to an undefined row.
     *
     * @return bool true
     */
    function rowUnset()
    {
        @end($this->rows);
        @next($this->rows);
        return true;
    }


    /**#@+ @return array|null The new selected row or null on errors */

    /**
     * Get the current row
     *
     * Returns a reference to the current row. This function hangs if there
     * is no current row.
     */
    function& rowCurrent()
    {
        $key = @key($this->rows);
        if (is_null($key)) {
            // Hoping the undefined key will be null for every php versions
            return $key;
        }

        return $this->rows[$key];
    }

    /**
     * Reset the cursor
     *
     * Resets (set to the first row) the internal cursor. This function hangs
     * if there are no rows.
     */
    function& rowReset()
    {
        @reset($this->rows);
        return $this->rowCurrent();
    }

    /**
     * Move the cursor to the end
     *
     * Moves the internal cursor to the last row. This function hangs if there
     * are no rows.
     */
    function& rowEnd()
    {
        @end($this->rows);
        return $this->rowCurrent();
    }

    /**
     * Sets the cursor to the previous row
     *
     * Decrements the cursor so it referes to the previous row. If the cursor
     * is in undefined position and $rewind is true, this function moves it to
     * the last row (same as cursorEnd()). If the cursor is on the first row,
     * returns false.
     *
     * @param bool $rewind If the cursor must go to the last row when unset
     */
    function& rowPrevious($rewind = true)
    {
        if (is_null(@key($this->rows))) {
            if ($rewind) {
                @end($this->rows);
            }
        } else {
            @prev($this->rows);
        }

        return $this->rowCurrent();
    }

    /**
     * Sets the cursor to the next row
     *
     * Increments the cursor so it referes to the next row. If the cursor is
     * in undefined position and $rewind is true, this function moves it to the
     * first row (same as cursorReset()). If there are no more rows, returns
     * false.
     *
     * @param bool $rewind If the cursor must go to the first row when unset
     */
    function& rowNext($rewind = true)
    {
        if (is_null(@key($this->rows))) {
            if ($rewind) {
                @reset($this->rows);
            }
        } else {
            @next($this->rows);
        }

        return $this->rowCurrent();
    }

    /**#@-*/

    /**#@-*/
}
?>
