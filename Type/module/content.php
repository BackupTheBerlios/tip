<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Content definition file
 * @package TIP
 */

/**
 * The root of data based modules
 *
 * This class mainly adds a data management infrastructure to TIP_Module,
 * allowing for a full interaction between TIP_Source and TIP_Data throught the
 * use of TIP_View instances.
 *
 * @package  TIP
 * @tutorial TIP/Module.pkg#TIP_Content
 */
class TIP_Content extends TIP_Module
{
    /**#@+ @access private */

    var $_view_stack = array ();


    function _onAdd(&$form, &$row)
    {
        // Update the counters, if the hierarchy module exists
        $hierarchy_id = $this->getId() . '_hierarchy';
        if (array_key_exists($hierarchy_id, $GLOBALS['cfg'])) {
            $hierarchy =& TIP_Type::getInstance($hierarchy_id);
            $hierarchy->updateCount($row['group'], +1);
        }

        // Update _submits, if the user module exists
        $user =& $GLOBALS[TIP_MAIN]->getSharedModule('user');
        if (is_object($user) && !is_null($counts = $user->getLoggedField('_submits'))) {
            $user->setLoggedField('_submits', $counts+1);
        }

        $form->process($row);
    }

    function _onDelete(&$form, &$row)
    {
        $id = $row[$this->data->getPrimaryKey()];
        if (empty($id)) {
            TIP::error('no primary key found');
            return;
        }

        // Update the counters, if the hierarchy module exists
        $hierarchy_id = $this->getId() . '_hierarchy';
        if (array_key_exists($hierarchy_id, $GLOBALS['cfg'])) {
            $hierarchy =& TIP_Type::getInstance($hierarchy_id);
            $hierarchy->updateCount($row['group'], -1);
        }

        // Remove the comments, if the comment module exists
        $comments_id = $this->getId() . '_comments';
        if (array_key_exists($comments_id, $GLOBALS['cfg'])) {
            $comments =& TIP_Type::getInstance($comments_id);
            if (!$comments->parentRemoved($id)) {
                return;
            }
        }

        // Update _deleted_submits, if the user module exists
        $user =& $GLOBALS[TIP_MAIN]->getSharedModule('user');
        if (is_object($user) && !is_null($count = $user->getLoggedField('_deleted_submits'))) {
            $user->setLoggedField('_deleted_submits', $count+1);
        }


        $form->process($row);
    }

    /**#@-*/


    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Content instance.
     *
     * The data path is defined by the 'data_path' option of this module.
     * If not specified, it defaults to the 'data_path' option of the
     * main module with the getId() of this module appended.
     *
     * The data engine is defined by the 'data_engine' option of this module.
     * If not specified, it defaults to the 'data_engine' option of the
     * main module.
     *
     * @param mixed $id Identifier of this instance
     */
    protected function __construct($id)
    {
        parent::__construct($id);
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

    /**#@+
     * @param  string    $action The action name
     * @return bool|null         true on action executed, false on action error or
     *                           null on action not found
     */

    protected function runManagerAction($action)
    {
        switch ($action) {

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_EDIT, $id);
            return !is_null($processed);

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::warning('no id specified');
                TIP::notifyError('noparams');
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete'))
            );
            return !is_null($processed);
        }

        return parent::runManagerAction($action);
    }

    protected function runAdminAction($action)
    {
        switch ($action) {

        case 'delete':
            if (is_null($id = TIP::getGet('id', 'integer'))) {
                TIP::warning('no id specified');
                TIP::notifyError('noparams');
                return false;
            }
            if (!$this->rowOwner($id)) {
                return false;
            }
            $processed = $this->form(TIP_FORM_ACTION_DELETE, $id, array(
                'on_process' => array(&$this, '_onDelete'))
            );
            return !is_null($processed);
        }

        return parent::runAdminAction($action);
    }

    protected function runTrustedAction($action)
    {
        switch ($action) {

        case 'add':
            $processed = $this->form(TIP_FORM_ACTION_ADD, null, array(
                'on_process'   => array(&$this, '_onAdd'),
                'valid_render' => TIP_FORM_RENDER_NOTHING
            ));

            if ($processed) {
                $id = $this->data->getLastId();
                if (empty($id) || is_null($this->getRow($id, false))) {
                    return false;
                }

                $this->appendToPage('view.src');
                $this->endView();
            }
            return !is_null($processed);

        case 'edit':
            if (is_null($id = TIP::getGet('id', 'integer')) && is_null($id = TIP::getPost('id', 'integer'))) {
                TIP::error('no id specified');
                return false;
            }

            if (!$this->rowOwner($id)) {
                return false;
            }

            $processed = $this->form(TIP_FORM_ACTION_EDIT, $id);
            return !is_null($processed);
        }

        return parent::runTrustedAction($action);
    }

    protected function runUntrustedAction($action)
    {
        switch ($action) {

        case 'view':
            $id = TIP::getGet('id', 'integer');
            if (is_null($id)) {
                TIP::notifyError('noparams');
                return false;
            }

            $filter = $this->data->rowFilter($id);
            if (!$this->startView($filter)) {
                TIP::notifyError('select');
                return false;
            }

            $row =& $this->view->current();
            if (!$row) {
                TIP::notifyError('notfound');
                $this->endView();
                return false;
            }

            if (array_key_exists('_public', $row) && !$row['_public']) {
                TIP::notifyError('denied');
                $this->endView();
                return false;
            }

            $this->appendToPage('view.src');
            $this->endView();

            if (array_key_exists('_hits', $row)) {
                $old_row = $row;
                $row['_hits'] += 1;
                $row['_lasthit'] = TIP::formatDate('datetime_iso8601');
                $this->data->updateRow($row, $old_row);
            }

            return true;

        case 'browse':
            $filter = array();

            $user = TIP::getGet('user', 'integer');
            if ($user) {
                $filter[] = $this->data->filter('_user', $user);
            }

            $group = TIP::getGet('group', 'integer');
            if ($group) {
                $filter[] = $this->data->filter('group', $group);
            }

            if (!$this->startView(implode(' AND ', $filter))) {
                TIP::notifyError('select');
                return false;
            }

            $this->appendToPage('browse.src');
            $this->endView();
            return true;
        }

        return parent::runUntrustedAction($action);
    }

    /**#@-*/

    /**
     * Push a view
     *
     * Pushes a view object in the stack of this module. You can restore the
     * previous view calling pop().
     *
     * @param  TIP_View     &$view The view to push
     * @return TIP_View|null       The pushed view on success or null on errors
     */
    function &push(&$view)
    {
        if ($view->isValid()) {
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
     * @param      string       $params The parameter string
     * @return     bool                 true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Echo an uploaded URL
     *
     * Shortcut for the often used data url.
     */
    protected function commandDataUrl($params)
    {
        echo TIP::buildDataUrl($this->getId(), $params);
        return true;
    }

    /**
     * Wikize the field specified in $params
     *
     * The value is parsed and rendered by the Text_Wiki renderer accordling to
     * the wiki rules defined in the 'wiki_rules' option of the field.
     */
    protected function commandWiki($params)
    {
        $value = $this->getField($params);
        if (is_null($value)) {
            TIP::error("no field found ($params)");
            return false;
        }

        $fields =& $this->data->getFields();
        $field =& $fields[$params];
        $rules = isset($field['wiki_rules']) ? explode(',', $field['wiki_rules']) : null;

        echo TIP_Renderer::getWiki($rules)->transform($value);
        return true;
    }

    /**#@-*/

    /**
     * Get a specific row
     *
     * Gets a reference to a specific row. If $id is not specified, the current
     * row is assumed.
     *
     * This is an high level method that notify errors to the user if the row
     * is not found.
     *
     * The method starts (and ends) a view to find a row, so every further
     * requests will be cached.
     *
     * @param  mixed      $id       The row id
     * @param  bool       $end_view Wheter to end the view or not
     * @return array|null           The row or null on errors
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

            $row =& $view->current();
            if ($end_view || is_null($row)) {
                $this->endView();
            }
        } elseif (isset($this->view)) {
            $row =& $this->view->current();
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
     * Checks if the $field in the $id row is equal to the current user id,
     * that is if $id is owned by the current user.
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
     * Gets a field from the current row of the current view.
     *
     * @param  string     $id The field id
     * @return mixed|null     The field value or null on errors
     */
    function getField($id)
    {
        return isset($this->view) ? $this->view->getField($id) : null;
    }

    /**
     * Get a summary value
     *
     * Gets a summary value from the current view.
     *
     * @param string      $id The summary id
     * @return mixed|null     The summary value or null on errors
     */
    function getSummary($id)
    {
        return isset($this->view) ? $this->view->getSummary($id) : null;
    }

    /**
     * Return the value of a generic item
     *
     * Gets the value of a generic item. This implementation adds the field
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
     *   values are not built from real data fields.
     *
     * - Summary value of the current view throught getSummary().
     *
     * - Chain-up the parent method TIP_Module::getItem().
     *
     * The first succesful search operation will stop the sequence.
     *
     * @param  string     $id The item id
     * @return mixed|null     The value of the item or null if not found
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

            if (isset($stack[$view_id]) && !is_null($value = $stack[$view_id]->getField($id))) {
                return $value;
            }
        }

        $value = $this->getSummary($id);
        if (!is_null($value)) {
            return $value;
        }

        return parent::getItem($id);
    }

    /**
     * Prepend a source file to the page
     *
     * Overrides TIP_Module::insertInPage() storing also the current view (if
     * present) in the callback.
     *
     * @param  string $file The source file
     * @return bool         true on success or false on errors
     */
    function insertInPage($file)
    {
        if (empty($this->view)) {
            return parent::insertInPage($file);
        }

        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildSourcePath($this->getId(), $file);
        }

        $main =& $GLOBALS[TIP_MAIN];
        $main->prependCallback(array(&$this, 'pop'));
        $main->prependCallback(array(&$this, 'run'),  array($file));
        $main->prependCallback(array(&$this, 'push'), array(&$this->view, false));
        return true;
    }

    /**
     * Append a source file to the page
     *
     * Overrides TIP_Module::appendToPage() storing also the current view (if
     * present) in the callback.
     *
     * @param  string $file The source file
     * @return bool         true on success or false on errors
     */
    function appendToPage($file)
    {
        if (empty($this->view)) {
            return parent::appendToPage($file);
        }

        if (strpos($file, DIRECTORY_SEPARATOR) === false) {
            $file = $this->buildSourcePath($this->getId(), $file);
        }

        $main =& $GLOBALS[TIP_MAIN];
        $main->appendCallback(array(&$this, 'push'), array(&$this->view, false));
        $main->appendCallback(array(&$this, 'run'),  array($file));
        $main->appendCallback(array(&$this, 'pop'));
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
     * @param  TIP_FORM_ACTION_... $action  The action
     * @param  array|null          $id      A row id to use as default
     * @param  array               $options The options to pass to the form
     * @return bool|null                    true if the form has been processed,
     *                                      false if the form must be processed
     *                                      or null on errors
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

        $options['content'] =& $this;
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
     * stack and makes it the current view.
     *
     * @param  string        $filter  The filter conditions
     * @param  array         $options The constructor arguments to pass to the
     *                                TIP_View instance
     * @return TIP_View|null          The view instance or null on errors
     */
    public function &startView($filter, $options = array())
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
     *
     * @param  string        $type    The special view type
     * @param  array         $options The constructor arguments to pass to the
     *                                TIP_View derived instance
     * @return TIP_View|null          The view instance or null on errors
     */
    public function &startSpecialView($type, $options = array())
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
     * where all commands of a TIP_Content refers if no views were started.
     * In any case, you can't have more endView() than start[Special]View().
     *
     * @return bool true on success or false on errors
     */
    public function endView()
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
