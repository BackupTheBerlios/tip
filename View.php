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
    /**#@+ @access private */

    var $_row_index = 0;


    function _buildId(&$filter, &$data)
    {
        return (isset($data) ? $data->getId() . '/' : '') . $filter;
    }

    function _row_callback(&$row)
    {
        ++ $this->_row_index;
        $row['ROW']     = $this->_row_index;
        $row['ODDEVEN'] = ($this->_row_index & 1) > 0 ? 'odd' : 'even';
        $this->on_row->goWithArray(array(&$row));
    }

    /**#@-*/


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
     * @param TIP_Data &$data   A data object
     * @param string    $filter The filter conditions
     */
    function TIP_View($filter, &$data)
    {
        $this->TIP_Type();

        $this->_id     =  TIP_View::_buildId($filter, $data);
        $this->data    =& $data;
        $this->filter  =  $filter;
        $this->on_row  =& new TIP_Callback;
        $this->on_view =& new TIP_Callback;
        $this->summaries['COUNT'] = 0;
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
    var $summaries;

    /**
     * Row callback
     *
     * Called for every row added to $rows. The only argument passed to the
     * callback is a reference to the added row. Useful to add calculated
     * fields to every row.
     *
     * @var TIP_Callback
     */
    var $on_row;

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
    var $on_view;


    /**
     * Get a TIP_View instance
     *
     * Gets the previously defined $filter view or instantiates a new one and
     * returns it.
     *
     * @param string    $filter The filter to apply
     * @param TIP_Data &$data   A data object
     * @return TIP_View A reference to the view instance
     * @static
     */
    function& getInstance($filter, &$data)
    {
        $id = TIP_View::_buildId($filter, $data);
        $instance =& TIP_View::singleton($id);
        if (is_null($instance)) {
            $instance =& new TIP_View($filter, $data);
            TIP_View::singleton($id, array($id => &$instance));
        }
        return $instance;
    }



    /**
     * Populate the view
     *
     * Performs all the needed steps to fill $rows, call the callbacks, add
     * the standard summary values and so on.
     *
     * @param bool $refresh Forces the fillRows() call
     * @return bool true on success or false on errors
     */
    function populate($refresh = false)
    {
        if (! is_null($this->rows) && ! $refresh) {
            $this->rowUnset();
            return true;
        }

        $this->rows = null;
        if (! $this->fillRows()) {
            return false;
        } elseif (! is_array($this->rows)) {
            return true;
        }

        $this->_row_index = 0;
        array_walk($this->rows, array(&$this, '_row_callback'));
        $this->summaries['COUNT'] = $this->_row_index;
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


/**
 * A fields view
 *
 * A special view to traverse the field structure.
 *
 * @package TIP
 */
class TIP_Fields_View extends TIP_View
{
    /**#@+ @access protected */

    /**
     * Get the field list
     *
     * Fills the $rows property with the field structure as specified by
     * TIP_Data::getFields().
     *
     * @return bool true on success or false on errors
     */
    function fillRows()
    {
        $this->rows =& $this->data->getFields(true);
        if (is_null($this->rows)) {
            $this->rows = false;
            return false;
        }
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Fields_View instance.
     *
     * @param TIP_Data &$data A data object
     */
    function TIP_Fields_View(&$data)
    {
        $this->TIP_View('__FIELDS__', $data);
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Get a TIP_Fields_View instance
     *
     * Gets the previously defined fields view of $data or instantiates a new
     * one and returns it.
     *
     * @param TIP_Data &$data A data object
     * @return TIP_Fields_View A reference to the fields view instance
     * @static
     */
    function& getInstance(&$data)
    {
        $id = $data->getId();
        $instance =& TIP_Fields_View::singleton($id);
        if (is_null($instance)) {
            $instance =& new TIP_Fields_View($data);
            TIP_Fields_View::singleton($id, array($id => &$instance));
        }
        return $instance;
    }

    /**#@-*/
}


/**
 * A modules view
 *
 * A special view to traverse the configured modules.
 *
 * @package TIP
 */
class TIP_Modules_View extends TIP_View
{
    /**#@+ @access protected */

    /**
     * Get the configured modules
     *
     * Fills the $rows property with all the configured modules that are
     * subclass of TIP_Module.
     *
     * @return bool true on success or false on errors
     */
    function fillRows()
    {
        foreach (array_keys ($GLOBALS['cfg']) as $module_name) {
            $instance =& TIP_Module::getInstance($module_name, false);
            if (is_a($instance, 'TIP_Module')) {
                $this->rows[$module_name] = array('id' => $module_name);
            }
        }

        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Modules_View instance.
     */
    function TIP_Modules_View()
    {
        $fake_null = null;
        $this->TIP_View('__MODULES__', $fake_null);
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Get a TIP_Modules_View instance
     *
     * Gets the previously defined modules view instance or instantiates a new
     * one and returns it.
     *
     * @param TIP_Data &$data A data object (not used)
     * @return TIP_Modules_View A reference to the modules view instance
     * @static
     */
    function& getInstance(&$data)
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance =& new TIP_Modules_View;
        }
        return $instance;
    }

    /**#@-*/
}

/**
 * An array view
 *
 * A special view to traverse a defined array or rows.
 *
 * @package TIP
 */
class TIP_Array_View extends TIP_View
{
    /**#@+ @access private */

    var $_stored_rows = null;

    /**#@-*/

    
    /**#@+ @access protected */

    function fillRows()
    {
        $this->rows = $this->_stored_rows;
        return true;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Constructor
     *
     * Initializes a TIP_Array_View instance.
     */
    function TIP_Array_View(&$rows)
    {
        $fake_null = null;
        $this->TIP_View('__ARRAY__', $fake_null);
        $this->_stored_rows =& $rows;
    }

    /**#@-*/
}

?>
