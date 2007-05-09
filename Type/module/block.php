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

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Block instance.
     *
     * The data path is defined by the 'data_path' option of the block.
     * If not specified, it defaults to the 'data_path' option of the
     * main module with the getId() of this block appended.
     *
     * The data engine is defined by the 'data_engine' option of the block.
     * If not specified, it defaults to the 'data_engine' option of the
     * main module.
     *
     * @param mixed $id  Identifier of this instance
     */
    function TIP_Block($id)
    {
        $this->TIP_Module($id);
        if (is_null($options = $this->getDataOptions())) {
            return;
        }

        $this->data =& TIP_Type::singleton(array('data'), $options);
    }

    function getDataOptions()
    {
        if (is_null($path = $this->getOption('data_path'))) {
            $path = @$GLOBALS[TIP_MAIN]->getOption('data_path') . $this->getId();
        }

        if (is_null($engine_name = $this->getOption('data_engine'))) {
            if (is_null($engine_name = $GLOBALS[TIP_MAIN]->getOption('data_engine'))) {
                return null;
            }
        } 

        if (is_null($engine =& TIP_Type::getInstance($engine_name))) {
            return null;
        }

        return array(
            'engine'    => &$engine,
            'path'      =>  $path,
            'joins'     => @$this->getOption('data_joins'),
            'fieldset'  => @$this->getOption('data_fieldset')
        );
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
     * Echo an uploaded URL
     *
     * Shortcut for the often used data url.
     */
    function commandDataUrl($params)
    {
        echo TIP::buildDataUrl($this->getId(), $params);
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
     * Get a specific row
     *
     * Gets a reference to a specific row. If $id is not specified, the current
     * row is assumed.
     *
     * This is an high level method that raises errors and notify them to the
     * user if the row is not found: use it only when the row content is
     * absolutely needed.
     *
     * The method starts (and ends) a view to find a row, so every further
     * requests will be cached.
     *
     * @param  mixed      $id       The row id
     * @param  bool       $end_view Wheter to end the view or not
     * @return array|null           The requested row or null on errors
     */
    function& getRow($id = null, $end_view = true)
    {
        $row = null;

        if (isset($id)) {
            $view =& $this->startView($this->data->rowFilter($id));
            if (!$view) {
                TIP::notifyError('select');
                return $row;
            }

            $row =& $view->rowReset();
            if ($end_view || is_null($row)) {
                $this->endView();
            }
        } elseif (isset($this->view)) {
            $row =& $this->view->rowCurrent();
        }

        if (is_null($row)) {
            TIP::warning("'$id' not found in " . $this->data->getId());
            TIP::notifyError('notfound');
        }

        return $row;
    }

    /**
     * Check if the row is owned by the current user
     *
     * Checks if the content of $field of row identified by $id is equal to
     * the current user id, that is if $id is owned by the current user.
     *
     * This is an high level method that raises errors and notify them to the
     * user on any errors: use it only when error notify is needed.
     *
     * If there are no errors but the row is not owned by the current user,
     * the 'denied' notify error is raised.
     *
     * @param  mixed  $id    The row id
     * @param  string $field The name of the field containing the user id
     * @return bool          true if $id is owned by the current user or false
     *                       on errors
     */
    function rowOwner($id = null, $field = '_user')
    {
        if (is_null($row =& $this->getRow($id))) {
            return false;
        }

        if ($row[$field] != TIP::getUserId()) {
            TIP::notifyError('denied');
            return false;
        }

        return true;
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
                $view_id = key($stack);
            } while (isset($view_id) && is_subclass_of($stack[$view_id], 'TIP_View'));

            if (isset($view_id)) {
                $row = @current($stack[$view_id]->rows);
                $value = @$row[$id];
                if (!is_null($value)) {
                    return $value;
                }
            }
        }

        $value = $this->getSummary($id);
        if (!is_null($value)) {
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
            $file = $this->buildSourcePath($this->getId(), $file);
        }

        $main =& $GLOBALS[TIP_MAIN];
        $main->prependCallback($this->callback('pop'));
        $main->prependCallback($this->callback('run', array($file)));
        $main->prependCallback($this->callback('push', array(&$this->view, false)));
        return true;
    }

    function appendToContent($file)
    {
        if (empty($this->view)) {
            return parent::appendToContent($file);
        }

        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildSourcePath($this->getId(), $file);
        }

        $main =& $GLOBALS[TIP_MAIN];
        $main->appendCallback($this->callback('push', array(&$this->view, false)));
        $main->appendCallback($this->callback('run', array($file)));
        $main->appendCallback($this->callback('pop'));
        return true;
    }

    /**
     * Form management
     *
     * Generates a form with the specified $options and executes $action on it.
     *
     * The default values of the form (that usually must be present in
     * $options['defaults']) can also be specified by providing a row $id. If
     * both are provided, the two arrays are merged, with the one in
     * $options['defaults'] with higher priority. Before merging, if $action is
     * TIP_FORM_ACTION_ADD, the primary key on the read row is stripped.
     *
     * If $action is not TIP_FORM_ACTION_ADD and either $id or
     * $options['defaults'] are not provided, the current row is assumed as
     * default values. If there is not current row, an error is raised.
     *
     * @param  TIP_FORM_ACTION_ADD|TIP_FORM_ACTION_EDIT|TIP_FORM_ACTION_VIEW|TIP_FORM_ACTION_DELETE $action The action
     * @param  array|null $id      A row id to use as default
     * @param  array      $options The options to pass to the form
     * @return bool|null           true if the form has been processed, false if
     *                             the form must be processed or null on errors
     */
    function form($action, $id = null, $options = array())
    {
        // Define the 'defaults' option
        if ($action != TIP_FORM_ACTION_ADD || isset($id)) {
            if (is_null($row = $this->getRow($id))) {
                return null;
            }

            if ($action == TIP_FORM_ACTION_ADD) {
                unset($row[$this->data->getPrimaryKey()]);
            }

            if (@is_array($options['defaults'])) {
                $options['defaults'] = array_merge($row, $options['defaults']);
            } else {
                $options['defaults'] =& $row;
            }
        }

        $options['block'] =& $this;
        $options['action'] = $action;
        $form =& TIP_Type::singleton(array('module', 'form'), $options);
        return $form->run();
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
     * @param  string        $filter  The filter conditions
     * @param  array         $options The constructor arguments to pass to the
     *                                TIP_View instance
     * @return TIP_View|null          The view instance or null on errors
     */
    function& startView($filter, $options = array())
    {
        $options['data'] =& $this->data;
        $options['filter'] = $filter;
        return $this->push(TIP_Type::singleton(array('view'), $options));
    }

    /**
     * Start a special view
     *
     * Starts a view trying to instantiate the class named TIP_{$type}_View.
     * All the startView() advices also applies to startSpecialView().
     * If $id is not specified, it defaults to the id of the data binded to
     * this block.
     *
     * @param  string        $type    The special view type
     * @param  array         $options The constructor arguments to pass to the
     *                                TIP_View derived instance
     * @return TIP_View|null          The view instance or null on errors
     */
    function& startSpecialView($type, $options = array())
    {
        $options['data'] =& $this->data;
        $view =& TIP_Type::singleton(array('view', $type . '_view'), $options);
        if (is_null($view)) {
            TIP::error("special view does not exist ($type)");
        } else {
            $this->push($view);
        }

        return $view;
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
