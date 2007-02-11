<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Block definition file
 * @package TIP
 */

/**
 * The root of data based modules
 *
 * This class mainly adds a data management infrastructure to the TIP_Module
 * class, allowing a full interaction between TIP_Source and TIP_Data using
 * the TIP_View interface.
 *
 * @abstract
 * @package TIP
 * @tutorial Module.pkg#TIP_Block
 */
class TIP_Block extends TIP_Module
{
    /**#@+ @access private */

    var $_view_stack = array ();

    /**#@-*/


    /**#@+ @access protected */

    /**
     * The data context
     *
     * Contains a reference to the data from which this module will get
     * informations. See the TIP_Data class for details on what is it.
     *
     * @var TIP_Data
     */
    var $data = null;


    /**
     * Constructor
     *
     * Initializes a TIP_Block instance.
     *
     * The data path is read from <code>$cfg[getName()]['data_path']</code>.
     * If not specified, it defaults to
     * <code>$cfg['application']['data_path'] . $getName()</code>.
     *
     * The data engine is read from <code>$cfg[getName()]['data_engine']</code>.
     * If not specified, it defaults to
     * <code>$cfg['application']['data_engine']</code>.
     */
    function TIP_Block()
    {
        $this->TIP_Module();

        $data_path = $this->getOption('data_path');
        if (is_null($data_path)) {
            $data_path = TIP::getOption('application', 'data_path') . $this->getName();
        }

        $data_engine = $this->getOption('data_engine');
        if (is_null($data_engine)) {
            $data_engine = TIP::getOption('application', 'data_engine');
        }

        $this->data =& TIP_Data::getInstance($data_path, $data_engine);
    }

    /**
     * Push a view
     *
     * Pushes a view object in the stack of this module. You can restore the
     * previous view calling pop().
     *
     * @param TIP_View &$view The view to push
     * @return TIP_View|null The pushed view on success or null on errors
     */
    function &push(&$view)
    {
        if ($view->populate()) {
            $this->_view_stack[count($this->_view_stack)] =& $view;
            $this->view =& $view;
            $result =& $view;
        } else {
            $this->setError($view->resetError());
            $result = null;
        }
        return $result;
    }

    /**
     * Pop a view
     *
     * Pops a view object from the stack of this module. This operation restores
     * the previously active view.
     *
     * @return TIP_View|null|false The previous view on success, null if the
     *                             view stack is empty or false on errors
     */
    function &pop()
    {
        unset($this->view);
        $count = count($this->_view_stack);

        if ($count > 0) {
            unset($this->_view_stack[$count-1]);
            if ($count > 1) {
                $result =& $this->_view_stack[$count-2];
                $this->view =& $result;
            } else {
                $result = null;
            }
        } else {
            $result = false;
        }

        return $result;
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Echo the hierarchy of a block
     *
     * Outputs the DHTML hierarchy of a block.
     *
     * @uses TIP_Hierarchy::toHtml()
     */
    function commandDhtmlHierarchy($params)
    {
        $hierarchy =& TIP_Hierarchy::getInstance($this);
        $hierarchy->toDhtml();
        return true;
    }

    /**#@-*/

    /**
     * Get the current rows
     *
     * Gets a reference to the rows of the current view.
     *
     * @return array|null The array of rows or null on errors
     */
    function& getCurrentRows()
    {
        if (is_object($this->view)) {
            $rows =& $this->view->rows;
        } else {
            $rows = null;
        }
        return $rows;
    }

    /**
     * Get the current row
     *
     * Gets a reference to the row pointed by the internal cursor.
     *
     * @return array|null The current row or null on errors
     */
    function& getCurrentRow()
    {
        if (is_null($this->view)) {
            $fake_null = null;
            return $fake_null;
        }

        return $this->view->rowCurrent();
    }

    /**
     * Get a specified row
     *
     * Gets a reference to a specific row. This function does not move the
     * internal cursor.
     *
     * @param mixed $id The row id
     * @return array|null The current row or null on errors
     */
    function& getRow($id)
    {
        if (@array_key_exists ($id, $this->view->rows)) {
            $row =& $this->view->rows[$id];
        } else {
            $row = null;
        }

        return $row;
    }

    /**
     * Get a field value
     *
     * Gets a field content from the current row. If the field exists but its
     * content is null, the value is converted in an empty string to avoid
     * confusion between error and valid null value.
     *
     * If the field is not found but the current view is a special view
     * (that is, it is a subclass of the standard TIP_View object), this method
     * scans the view stack for the last view that was of TIP_View type and
     * checks if $id is present in its fields before returning null.
     * This because the special views are considered "weaks", that is their
     * content is not made by real data fields.
     *
     * @param string $id The field id
     * @return mixed|null The requested field content or null on errors
     */
    function getField($id)
    {
        if (is_null($this->view)) {
            return null;
        }

        $row =& $this->view->rowCurrent();
        $value = @$row[$id];

        if (is_null($value) && is_subclass_of($this->view, 'TIP_View')) {
            $cnt = count($this->_view_stack)-1;

            // Find the last non-special view
            do {
                if ($cnt <= 0) {
                    return null;
                }
                $view =& $this->_view_stack[-- $cnt];
            } while (is_subclass_of($view, 'TIP_View'));

            $row = @current($view->rows);
            $value = @$row[$id];
        }

        return $value;
    }

    /**
     * Get a summary value
     *
     * Gets the content of a summary value from the current view.
     *
     * @param string $id The summary id
     * @return mixed|null The requested summary content or null on errors
     */
    function getSummary($id)
    {
        return @$this->view->summaries[$id];
    }

    /**
     * Return the content of a generic item
     *
     * Gets the content of a generic item. This implementation adds the field
     * feature to the TIP_Module::getItem() method.
     *
     * Getting an item performs some search operations with this priority:
     *
     * - Current row fields
     * - Summary values of the current view
     * - Module keys
     * - Application module keys
     *
     * The first succesful search operation will stop the sequence.
     *
     * @param string $id The item id
     * @return mixed|null The content of the requested item or null if not found
     */
    function getItem($id)
    {
        $value = $this->getField($id);
        if (! is_null($value))
            return $value;

        $value = $this->getSummary($id);
        if (! is_null($value))
            return $value;

        return parent::getItem($id);
    }

    /**
     * Validates the posts
     *
     * Checks if the posts contain valid data, accordling to the data source of
     * the module.
     *
     * @return bool true on success or false on errors
     * @todo Check if the PEAR Validate package can substitute this too simple
     *       interface
     */
    function validatePosts ()
    {
	if (! $this->startSpecialView ('Fields')) {
	    $this->logWarning ('No data to validate');
	    return true;
	}

	$result = true;
	while ($this->nextRow ()) {
	    $row =& $this->getCurrentRow ();
	    if (! array_key_exists ('importance', $row))
		continue;

	    $id =& $row['id'];
	    $label = $this->getLocale ($id . '_label');
	    $value = tip::getPost ($id, $row['type']);
	    if ($row['importance'] == 1 && empty ($value)) {
		TIP::error ('E_VL_REQUIRED', " ($label)");
		$result = false;
		break;
	    }
	    if ($row['mode'] == 'secret') {
		$re_label = $this->getLocale ('re' . $id . '_label');
		$re_value = TIP::getPost ("re$Id", $row['type']);
		if ($row['importance'] == 1 && empty ($re_value)) {
		    TIP::error ('E_VL_REQUIRED', " ($re_label)");
		    $result = false;
		    break;
		} elseif ($value != $re_value) {
		    TIP::error ('E_VL_DIFFER', " ($re_label)");
		    $result = false;
		    break;
		}
	    }
	    $length = @$row['length'];
	    if ($length > 0 && @strlen ($value) > $length) {
		TIP::error ('E_VL_LENGTH', " ($label)");
		$result = false;
		break;
	    }
	    $validator = @$row['validator'];
	    if (is_object ($validator) && ! $validator->go (array (&$row, &$value))) {
		$result = false;
		break;
	    }
	}

	$this->endView ();
	return $result;
    }

    /**
     * Stores the posts
     *
     * Stores the posts content in the specified row, accordling to the data
     * source of the module. This method complements validatePosts() to manage
     * the user modules: usually you must validate the posts and after store
     * them in some place for further data operations (update or insert).
     *
     * @param array &$destination Where to store the posts
     * @return bool true on success or false on errors
     */
    function storePosts (&$destination)
    {
	if (! is_array ($destination)) {
	    $this->logWarning ('Invalid destination to store data');
	    return true;
	}
	if (! $this->startSpecialView ('Fields')) {
	    $this->logWarning ('No data to store');
	    return true;
	}

	$result = true;
	while ($this->nextRow ()) {
	    $row =& $this->getCurrentRow ();
	    if (array_key_exists ('importance', $row)) {
		$id =& $row['id'];
		$value = TIP::getPost ($id, 'string');
		if (strlen ($value) == 0 && $row['can_be_null'])
		    $destination[$id] = null;
		elseif (settype ($value, $row['type']))
		    $destination[$id] = $value;
		else
		    $this->logWarning ("Unable to cast '$value' to '$row[type]'");
	    }
	}

	$this->endView ();
	return $result;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * The current view
     *
     * A reference to the current view or null if there are no current views.
     *
     * @var TIP_View
     */
    var $view = null;

    /**
     * Start a view
     *
     * Starts a filtered view. Starting a view pushes it in the internal view
     * stack and makes it the current view, accessible throught the
     * TIP_Block::$view property.
     *
     * @param string $filter The filter conditions
     * @return TIP_View|null The view instance or null on errors
     */
    function& startView($filter)
    {
        return $this->push(TIP_View::getInstance($filter, $this->data));
    }

    /**
     * Start a special view
     *
     * Starts a view trying to instantiate the class named TIP_{$name}_View.
     * All the startView() advices also applies to startSpecialView().
     *
     * @param string $name The name of the special view
     * @return TIP_View|null The view instance or null on errors
     */
    function& startSpecialView($name)
    {
        $class_name = TIP_PREFIX . $name . '_View';
        if (! class_exists($class_name)) {
            $fake_null = null;
            $this->setError("Special view does not exist ($class_name)");
            return $fake_null;
        }

        $getInstance = $class_name . '::getInstance';
        $instance = $getInstance($this->data);
        return $this->push($instance);
        return $this->push(call_user_func_array(array($class_name, 'getInstance'), array(&$this->data)));
    }

    /**
     * Ends a view
     *
     * Ends the current view. Ending a view means the previously active view
     * in the internal stack is made current.
     *
     * Usually, you always have to close all views. Anyway, in some situations,
     * is useful to have the base view ever active (so called default view)
     * where all commands of a TIP_Block refers if no views were started.
     * In any case, you can't have more endView() than start[Special]View().
     *
     * @return bool true on success or false on errors
     */
    function endView()
    {
        if ($this->pop() === FALSE) {
            $this->setError("'endView()' requested without a previous 'startView()' or 'startSpecialView()' call");
            return false;
        }

        return true;
    }

    /**#@-*/
}

?>
