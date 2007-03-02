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
 * @package  TIP
 * @tutorial TIP/Module.pkg#TIP_Block
 */
class TIP_Block extends TIP_Module
{
    /**#@+ @access private */

    var $_view_stack = array ();


    function _addEdit($row, $is_add, $auto_render, $queued)
    {
        $form =& TIP_Module::getInstance('form');
        $form->setForm($this, $row, $is_add);

        if (!$form->make() || is_null($processed = $form->process())) {
            return null;
        }

        if (!$processed || $auto_render) {
            if ($queued) {
                $GLOBALS[TIP_MAIN_MODULE]->appendCallback($form->callback('render'));
            } else {
                $form->render();
            }
        }

        return $processed;
    }

    function _viewRow($row)
    {
        $form =& TIP_Module::getInstance('form');
        $form->setForm($this, $row);

        if (!$form->make($this, $row) || !$form->view()) {
            return false;
        }

        $GLOBALS[TIP_MAIN_MODULE]->appendCallback($form->callback('render'));
        return true;
    }

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Block instance.
     *
     * The data path is read from <code>$cfg[getId()]['data_path']</code>.
     * If not specified, it defaults to
     * <code>$cfg['application']['data_path'] . getId()</code>.
     *
     * The data engine is read from <code>$cfg[getId()]['data_engine']</code>.
     * If not specified, it defaults to
     * <code>$cfg['application']['data_engine']</code>.
     */
    function TIP_Block()
    {
        $this->TIP_Module();
        if (is_null($options = $this->getDataOptions())) {
            return;
        }

        $this->data =& TIP_Data::getInstance($options);
    }

    function getDataOptions()
    {
        if (is_null($path = $this->getOption('data_path'))) {
            if (is_null($path = TIP::getOption('application', 'data_path'))) {
                return null;
            }
            $path .= $this->getId();
        }

        if (is_null($engine = $this->getOption('data_engine'))) {
            if (is_null($engine = TIP::getOption('application', 'data_engine'))) {
                return null;
            }
        }

        $joins = $this->getOption('data_joins');
        $fieldset = @$this->getOption('data_fieldset');
        return compact('path', 'engine', 'joins', 'fieldset');
    }

    /**
     * Push a view
     *
     * Pushes a view object in the stack of this module. You can restore the
     * previous view calling pop().
     *
     * @param TIP_View &$view     The view to push
     * @param bool      $populate Whether to populate or not the view
     * @return TIP_View|null The pushed view on success or null on errors
     */
    function &push(&$view, $populate = true)
    {
        if (!$populate || $view->populate()) {
            $this->_view_stack[count($this->_view_stack)] =& $view;
            $this->view =& $view;
            $result =& $view;
        } else {
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
     * Generates an add form
     */
    function commandAddRow($params)
    {
        return true;
        return !is_null($this->addRow());
    }

    /**
     * Echo an uploaded URL
     *
     * Shortcut for the often used upload url. The upload URL is under the
     * directory "data", relative to the site root URL.
     */
    function commandUploadUrl($params)
    {
        echo TIP::buildUrl('data', $this->getId(), $params);
        return true;
    }

    /**
     * Wikize the content of the field specified in $params
     *
     * The value is parsed and rendered by the TIP::getWiki() instance
     * accordling to the wiki rules defined in the 'wiki_rules' option of the
     * field structure.
     */
    function commandWiki($params)
    {
        $value = $this->getField($params);
        if (is_null($value)) {
            TIP::error("no field found ($params)");
            return false;
        }

        $fields =& $this->data->getFields();
        $field =& $fields[$params];
        if (array_key_exists('wiki_rules', $field)) {
            $wiki_rules = explode(',', $field['wiki_rules']);
        } else {
            $wiki_rules = null;
        }
        $wiki =& TIP::getWiki($wiki_rules);
        echo $wiki->transform($value, 'Xhtml');
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
        if (! isset($this->view)) {
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
     * Gets a field content from the current row of the current view.
     *
     * @param string $id The field id
     * @return mixed|null The requested field content or null on errors
     */
    function getField($id)
    {
        if (!isset($this->view)) {
            return null;
        }

        $row =& $this->view->rowCurrent();
        return @$row[$id];
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
     * - Try to get the field in the current row throught getField().
     *
     * - If the field is not found but the current view is a special view
     *   (that is, it is a subclass of the standard TIP_View object), it
     *   scans the view stack for the last view that was of TIP_View type and
     *   checks if $id is present as a field in the current row.
     *   This because the special views are considered "weaks", that is their
     *   content is not built by real data fields.
     *
     * - Summary value of the current view throught getSummary().
     *
     * - Chain-up the parent method TIP_Module::getItem().
     *
     * The first succesful search operation will stop the sequence.
     *
     * @param string $id The item id
     * @return mixed|null The content of the requested item or null if not found
     */
    function getItem($id)
    {
        $value = $this->getField($id);
        if (isset($value)) {
            return $value;
        }

        if (@is_subclass_of($this->view, 'TIP_View')) {
            // Find the last non-special view
            $stack =& $this->_view_stack;
            end($stack);
            do {
                prev($stack);
                $id = key($stack);
            } while (isset($id) && is_subclass_of($stack[$id], 'TIP_View'));

            if (isset($id)) {
                $row = @current($stack[$id]->rows);
                $value = @$row[$id];
                if (isset($value)) {
                    return $value;
                }
            }
        }

        $value = $this->getSummary($id);
        if (isset($value)) {
            return $value;
        }

        return parent::getItem($id);
    }

    function insertInContent($file)
    {
        if (empty($this->view)) {
            return parent::insertToContent($file);
        }

        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildModulePath($file);
        }

        $application =& $GLOBALS[TIP_MAIN_MODULE];
        $application->prependCallback($this->callback('pop'));
        $application->prependCallback($this->callback('run', array($file)));
        $application->prependCallback($this->callback('push', array(&$this->view, false)));
        return true;
    }

    function appendToContent($file)
    {
        if (empty($this->view)) {
            return parent::appendToContent($file);
        }

        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildModulePath($file);
        }

        $application =& $GLOBALS[TIP_MAIN_MODULE];
        $application->appendCallback($this->callback('push', array(&$this->view, false)));
        $application->appendCallback($this->callback('run', array($file)));
        $application->appendCallback($this->callback('pop'));
        return true;
    }

    /**
     * Add a new row
     *
     * Generates an empty form and adds a new row with the user provided values,
     * if the form is properly validated.
     *
     * You can specify automatic fields providing an associative array in $row.
     *
     * @param array $row         The row default values
     * @param bool  $auto_render Whether to render or not a freezed view of
     *                           the form if it has been processed
     * @return bool|null true if the form has been processed, false if the form
     *                   must be processed or null on errors
     */
    function addRow($row = array(), $auto_render = true, $queued = true)
    {
        return $this->_addEdit($row, true, $auto_render, $queued);
    }

    /**
     * Edit a row
     *
     * Generates a form, fills it with the default data specified in the $row
     * associative array and updates the row with the user provided values, if
     * the form is properly validated.
     *
     * If $row is not specified, the current row will be used as default one.
     * On no default row, the function will fail.
     *
     * @param array|null $row         The row to edit or null for the current row
     * @param bool       $auto_render Whether to render or not a freezed view of
     *                                the form if it has been processed
     * @return bool|null true if the form has been processed, false if the form
     *                   must be processed or null on errors
     */
    function editRow($row = null, $auto_render = true, $queued = true)
    {
        if (is_null($row)) {
            if (!isset($this->view) || is_null($row =& $this->view->rowCurrent())) {
                $id = $this->data->getId();
                TIP::error("no current row to edit ($id)");
                return null;
            }
        }

        return $this->_addEdit($row, false, $auto_render, $queued);
    }

    /**
     * View a row
     *
     * Very similar to TIP_Block::editRow(), but do not allow to the user to
     * change form nor to update the data. The goal of viewing a row is 
     * achieved in an elegant way by freezing the HTML_QuickForm object of
     * the block.
     *
     * @param array|null $row The row to edit or null to view the current row
     * @return bool true on success or false on errors
     */
    function viewRow($row = null)
    {
        if (is_null($row)) {
            if (!isset($this->view) || is_null($row =& $this->view->rowCurrent())) {
                $id = $this->data->getId();
                TIP::error("no current row to view ($id)");
                return false;
            }
        }

        return $this->_viewRow($row);
    }

    /**#@-*/


    /**#@+ @access public */

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
            TIP::error("special view does not exist ($class_name)");
            return $fake_null;
        }

        $getInstance = $class_name . '::getInstance';
        $instance =& $getInstance($this->data);
        return $this->push($instance);
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
            TIP::error("'endView()' requested without a previous 'startView()' or 'startSpecialView()' call");
            return false;
        }

        return true;
    }

    /**#@-*/
}

?>
