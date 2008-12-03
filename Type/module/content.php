<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Content definition file
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
 * The root of data based modules
 *
 * This class mainly adds a data management infrastructure to TIP_Module,
 * allowing for a full interaction between TIP_Template and TIP_Data
 * throught the use of TIP_Data_View instances.
 *
 * @package  TIP
 */
class TIP_Content extends TIP_Module
{
    //{{{ Properties

    /**
     * Contains a reference to the binded TIP_Data object
     * @var TIP_Data
     */
    protected $data = null;

    /**
     * The file to run on view actions
     * @var string
     */
    protected $view_template = 'view';

    /**
     * The file to run on browse actions
     * @var string
     */
    protected $browse_template = 'browse';

    /**
     * The file to run at the beginning of a 'pager' tag
     *
     * This template must reside in the application directory.
     *
     * @var string
     */
    protected $pager_pre_template = 'pager_before';

    /**
     * The file to run for every row on the 'pager' tag
     * @var string
     */
    protected $pager_template = 'row';

    /**
     * The file to run on empty result set
     *
     * This template must reside in the application directory.
     *
     * @var string
     */
    protected $pager_empty_template = 'pager_empty';

    /**
     * The file to run at the end of a 'pager' tag
     *
     * This template must reside in the application directory.
     *
     * @var string
     */
    protected $pager_post_template = 'pager_after';

    /**
     * The file to run to generate the atom feed
     * @var string
     */
    protected $atom_template = 'atom.xml';

    /**
     * The field containing the creation datetime
     * @var string
     */
    protected $creation_field = '_creation';

    /**
     * The field containing to owner user id
     * @var string
     */
    protected $owner_field = '_user';

    /**
     * The default field to use for browse actions
     * @var string
     */
    protected $browse_field = '_parent';

    /**
     * The field containing the last edit datetime
     * @var string
     */
    protected $last_edit_field = '_edit_on';

    /**
     * The field containing the last editor id
     * @var string
     */
    protected $editor_field = '_edit_by';

    /**
     * The field containing the counter of edit performed a row
     * @var string
     */
    protected $edits_field = '_edit_count';

    /**
     * The field containing the counter of actionView
     * @var string
     */
    protected $hits_field = '_hits';

    /**
     * The field specifying the title for rendering operations
     * @var string
     */
    protected $title_field = 'title';

    /**
     * The field specifying the tooltip for rendering operations
     * @var string|null
     */
    protected $tooltip_field = null;

    /**
     * The field containing the date of the last actionView
     * @var string
     */
    protected $last_hit_field = '_lasthit';

    /**
     * The search field(s) to be scanned
     * @var string|array
     */
    protected $search_field = null;

    /**
     * Statistic user fields
     *
     * An associative array of field ids in TIP_User to increment whenever a
     * callback action in this module has been called.
     *
     * @var array
     */
    protected $user_statistic = array(
        '_onAdd'    => '_submits',
        '_onEdit'   => '_edited_submits',
        '_onDelete' => '_deleted_submits',
        '_onView'   => '_viewed_submits'
    );

    /**
     * Default browse conditions
     *
     * An array of "field id" => value used as base conditions for
     * any browse action.
     *
     * @var array
     */
    protected $default_conditions = array();

    /**
     * Default order field
     * @var string
     */
    protected $default_order = null;

    /**
     * Browsable fields
     *
     * An array of field ids enabled in the 'browse' action, specified for
     * each privilege.
     *
     * @var array
     */
    protected $browsable_fields = array(
        TIP_PRIVILEGE_NONE  => array('group', '_parent', '_user'),
        TIP_PRIVILEGE_ADMIN => array('__ALL__')
    );

    /**
     * Explicit options to pass to the form
     *
     * @var array
     */
    protected $form_options = null;

    /**
     * The type of the id field: any valid settype() type is allowed
     * @var string
     */
    protected $id_type = 'integer';

    /**
     * Enable expiration time for row ownership
     *
     * If set, the isOwner() and related methods will return true **only**
     * if the request is done before this time is elapsed after the creation
     * of the row. Obviously, this means the "creation_field" property must
     * be set and work properly.
     *
     * Any value accepted by the strtotime() function is valid.
     *
     * @var string
     */
    protected $ownership_expiration = null;

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        TIP::arrayDefault($options, 'data', $options['id']);
        if (is_string($options['data'])) {
            $options['data'] =& TIP_Type::singleton(array(
                'type' => array('data'),
                'path' => $options['data']
            ));
        } elseif (@is_array($options['data'])) {
            TIP::arrayDefault($options['data'], 'type', array('data'));
            TIP::arrayDefault($options['data'], 'path', $options['id']);
            $options['data'] =& TIP_Type::singleton($options['data']);
        }

        return $options['data'] instanceof TIP_Data;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Content instance.
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
     * Get the active TIP_Data instance
     *
     * @return TIP_Data The active TIP_Data instance
     */
    public function &getData()
    {
        return $this->data;
    }

    /**
     * Get the current view
     *
     * @return TIP_View|null The current view or null
     */
    public function &getCurrentView()
    {
        return $this->_view;
    }

    /**
     * Get a field value
     *
     * Gets a field from the current row of the current view.
     *
     * @param  string     $id The field id
     * @return mixed|null     The field value or null on errors
     */
    public function getField($id)
    {
        return isset($this->_view) ? $this->_view->getField($id) : null;
    }

    /**
     * Get a summary value
     *
     * Gets a summary value from the current view.
     *
     * @param string      $id The summary id
     * @return mixed|null     The summary value or null on errors
     */
    public function getSummary($id)
    {
        return isset($this->_view) ? $this->_view->getSummary($id) : null;
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
     * - If the field is not found but the current view is not a TIP_Data_View
     *   (that is, it is not a TIP_Data_View object), it scans the view stack
     *   for the last view that was of TIP_Data_View type and checks if $id is
     *   present as a field in the current row.
     *   This because views others than TIP_Data_View are considered "weaks",
     *   that is their values are not built from real data fields.
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
    public function getItem($id)
    {
        if (!is_null($value = $this->getField($id))) {
            return $value;
        }

        if (isset($this->_view) && !$this->_view instanceof TIP_Data_View) {
            // Find the last TIP_Data_View
            $stack =& $this->_views;
            end($stack);
            do {
                prev($stack);
                $view_id = key($stack);
            } while (isset($view_id) && !$stack[$view_id] instanceof TIP_Data_View);

            if (isset($view_id) && !is_null($value = $stack[$view_id]->getField($id))) {
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
    protected function form($action, $id = null, $options = array())
    {
        // Define the 'defaults' option
        if ($action != TIP_FORM_ACTION_ADD || isset($id)) {
            if (is_null($row = $this->fromRow($id))) {
                return null;
            }

            if ($action == TIP_FORM_ACTION_ADD) {
                unset($row[$this->getData()->getProperty('primary_key')]);
            }

            if (@is_array($options['defaults'])) {
                $options['defaults'] = array_merge($row, $options['defaults']);
            } else {
                $options['defaults'] =& $row;
            }
        }

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = $action;
        return TIP_Type::singleton($options)->run();
    }

    /**
     * Push a view
     *
     * Pushes a view object in the stack of this module. You can restore the
     * previous view calling pop().
     *
     * @param  TIP_View     &$view The view to push
     * @return TIP_View|null       The pushed view on success or null on errors
     */
    public function &push(&$view)
    {
        if ($view->isValid()) {
            $this->_views[count($this->_views)] =& $view;
            $this->_view =& $view;
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
    public function &pop()
    {
        unset($this->_view);
        $count = count($this->_views);

        if ($count > 0) {
            unset($this->_views[$count-1]);
            if ($count > 1) {
                $result =& $this->_views[$count-2];
                $this->_view =& $result;
            } else {
                $result = null;
            }
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * Start a generic view
     *
     * Starts a view trying to instantiate the class named "TIP_{$type}_View".
     *
     * Furthermore, some options are automatically set to the following
     * defaults if not explicitely specified in the $options array:
     * - $options['data']    = the TIP_Data object of this module
     * - $options['fields']  = the 'subset' property value
     * - $options['on_row']  = the '_on{$type}Row' callback, if it is defined
     * - $options['on_view'] = the '_on{$type}View' callback, if it is defined
     *
     * @param  string        $type    The view type
     * @param  array         $options The constructor arguments to pass to the
     *                                TIP_View derived instance
     * @return TIP_View|null          The view instance or null on errors
     */
    public function &startView($type, $options = array())
    {
        $options['type'] = array('view', strtolower($type) . '_view');
        TIP::arrayDefault($options, 'data', $this->getData());
        TIP::arrayDefault($options, 'fields', $this->_subset);
        $callback = array(&$this, '_on' . $type . 'Row');
        is_callable($callback) && TIP::arrayDefault($options, 'on_row', $callback);
        $callback = array(&$this, '_on' . $type . 'View');
        is_callable($callback) && TIP::arrayDefault($options, 'on_view', $callback);

        if (is_null($view =& TIP_Type::singleton($options))) {
            TIP::error("view type does not exist ($type)");
            return $view;
        }

        return $this->push($view);
    }

    /**
     * Start a data view
     *
     * Creates a TIP_Data_View instance, providing also an easy way
     * to specify the filter instead of setting $options['filter'].
     *
     * The main difference between this function and using startView()
     * directly is that here "default_conditions" and "default_order"
     * properties are automatically applied on global queries (that is,
     * whenever $filter is empty). In this case, the model used by the
     * rendering operations is populated too.
     *
     * See startView() for further details.
     *
     * @param  string            $filter  The filter conditions
     * @param  array             $options The constructor arguments to pass
     *                                    to the TIP_Data_View instance
     * @return TIP_Data_View|null         The view instance or null on errors
     */
    public function &startDataView($filter = null, $options = array())
    {
        if (!isset($options['data'])) {
            $options['data'] =& $this->getData();
        }
        $data =& $options['data'];

        $model_filter = '';
        if (!empty($this->default_conditions)) {
            foreach ($this->default_conditions as $id => $value) {
                if (empty($model_filter)) {
                    $model_filter = $data->filter($id, $value);
                } else {
                    $model_filter .= $data()->addFilter('AND', $id, $value);
                }
            }
        }
        $model_filter .= $data->order($this->default_order);

        // Apply default conditions and order if $filter is empty
        empty($filter) && $filter = $model_filter;

        if ($filter == $model_filter) {
            // This query can be used to build the model
            if (isset($options['on_view'])) {
                $old_callback = $options['on_view'];
            } else {
                $old_callback = array(&$this, '_onDataView');
            }

            $callback = array(&$this, '_createModel');
            if (is_callable($old_callback)) {
                $this->_on_view_callbacks = array($old_callback, $callback);
                $options['on_view'] = array($this, '_onViewHook');
            } else {
                $options['on_view'] = $callback;
            }
        }

        $options['filter'] = $filter;
        return $this->startView('Data', $options);
    }

    /**
     * Ends a view
     *
     * Ends the current view. Ending a view means the previously active view
     * in the internal stack is made current.
     *
     * Usually, you always have to close all views. Anyway, in some situations,
     * is useful to have the base view ever active (so called default view)
     * where all tags of a TIP_Content refers if no views were started.
     * In any case, you can't have more endView() than start[Data]View().
     *
     * @return bool true on success or false on errors
     */
    public function endView()
    {
        if ($this->pop() === false) {
            TIP::error("'endView()' requested without a previous 'startView()' or 'startDataView()' call");
            return false;
        }

        return true;
    }

    /**
     * Get a specific GET value
     *
     * If $id is not specified, it defaults to the primary key of the binded
     * data.
     *
     * This is an high level method that notify errors to the user if $id is
     * not found.
     *
     * @param  mixed      $id The get id
     * @return mixed|null     The get value or null if not found
     */
    public function fromGet($id = null, $type = 'integer')
    {
        isset($id) || $id = $this->data->getProperty('primary_key');
        if (is_null($value = TIP::getGet($id, $type))) {
            TIP::warning("GET not found ($id)");
            TIP::notifyError('noparams');
        }

        return $value;
    }

    /**
     * Get a specific GET or POST value
     *
     * If $id is not specified, it defaults to the primary key of the binded
     * data.
     *
     * This is an high level method that notify errors to the user if $id is
     * not found.
     *
     * @param  mixed      $id The get/post id
     * @return mixed|null     The get/post value or null if not found
     */
    public function fromGetOrPost($id = null, $type = 'integer')
    {
        if (is_null($id)) {
            $id = 'id';
            $type = $this->id_type;
        }

        if (is_null($value = TIP::getGet($id, $type)) &&
            is_null($value = TIP::getPost($id, $type))) {
            TIP::warning("GET or POST not found ($id)");
            TIP::notifyError('noparams');
        }

        return $value;
    }

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
     * @param  bool       $end_view Whether to end the view or not
     * @return array|null           The row or null on errors
     */
    public function &fromRow($id = null, $end_view = true)
    {
        $row = null;
        $start_view = isset($id);

        if ($start_view) {
            if (is_null($view =& $this->startDataView($this->getData()->rowFilter($id)))) {
                TIP::notifyError('select');
                return $row;
            }
        } elseif (isset($this->_view)) {
            $view =& $this->_view;
            $id = $view->key();
        }

        if (isset($view)) {
            // Use the 'rows' property instead of current() to get a real
            // reference to the row stored inside the view
            $rows =& $view->getProperty('rows');
            array_key_exists($id, $rows) && $row =& $rows[$id];
        }

        if (is_null($row)) {
            TIP::warning("row not found ($id)");
            TIP::notifyError('notfound');
        }

        $start_view && ($end_view || is_null($row)) && $this->endView();
        return $row;
    }

    /**
     * Check if a row is owned by the current user
     *
     * Checks if the owner field in the row identified by $id is equal to the
     * current user id, that is if $id is owned by the current user.
     *
     * This is an high level method that raises errors and notify them to the
     * user on any errors: use it only when error notify is needed.
     *
     * If there are no errors but the row is not owned by the current user,
     * the 'denied' error is notified.
     *
     * @param  mixed $id The row id
     * @return bool      true if owned by the current user or false on errors
     */
    public function isOwner($id = null)
    {
        if (is_null($row =& $this->fromRow($id))) {
            return false;
        }

        if ($this->_isOwner($row) === false) {
            TIP::warning("not an owned row ($id)");
            TIP::notifyError('denied');
            return false;
        }

        return true;
    }

    /**
     * Check if a row is not owned by the current user
     *
     * Similar to isOwner(), but works in the reverse way: check if the row
     * identified by $id is NOT owned by the current user.
     *
     * @param  mixed $id The row id
     * @return bool      true if not owned by the current user or false on errors
     */
    public function isNotOwner($id = null)
    {
        if (is_null($row =& $this->fromRow($id))) {
            return false;
        }

        if ($this->_isOwner($row) === true) {
            TIP::warning("owned row ($id)");
            TIP::notifyError('denied');
            return false;
        }

        return true;
    }

    /**
     * Shortcut to build a filter on the owned field
     * @param  mixed $user The user id
     * @return string      The requested filter in the proper engine format
     */
    public function filterOwnedBy($user)
    {
        return $this->getData()->filter($this->owner_field, $user);
    }

    /**
     * Update the magic fields
     *
     * Fills the magic fields (if found and not set) with their magic
     * values.
     *
     * @param array &$row The row to check
     */
    public function setMagicFields(&$row)
    {
        TIP::arrayDefault($row, $this->creation_field, TIP::formatDate('datetime_sql'));
        TIP::arrayDefault($row, $this->owner_field, TIP::getUserId());
    }

    /**
     * Render to XHTML
     *
     * @param  string      $action The action template string
     * @return string|null         The rendered HTML or null on errors
     */
    public function toHtml($action = null)
    {
        if (is_null($renderer = $this->_getRenderer($action))) {
            return null;
        }

        return $renderer->toHtml();
    }

    /**
     * Render to rows
     *
     * Builds an array of rows from this content data. Useful to
     * automatically define the options of a <select> item for a
     * TIP_Form instance.
     *
     * @param  string     $action The action template string
     * @return array|null         The rendered rows or null on errors
     */
    public function toRows($action = null)
    {
        if (is_null($renderer = $this->_getRenderer($action))) {
            return null;
        }

        return $renderer->toArray();
    }

    /**
     * Render specific rows
     *
     * Works in the same way of toRows(), but rendering only the
     * rows with the id specified in the $ids array.
     *
     * @param  mixed|array $ids    The id/ids of the rows to render
     * @param  string      $action The action template string
     * @return array|null          The rendered rows or null on errors
     */
    public function toRow($ids, $action = null)
    {
        is_array($ids) || $ids = array($id);
        if (empty($ids)) {
            return null;
        }

        $rows = array();

        foreach ($ids as $id) {
            // Render the rows one per one
            $row =& $this->data->getRow($id);
            $this->_rowToArray($row, $id);
            $rows[$id] = $row['title'];
            unset($row);
        }

        return $rows;
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param  string      $params Parameters of the tag
     * @return string|null         The string result or null
     */

    /**
     * Htmlize the first defined request
     *
     * Overrides the default tagHtml() providing highlighting on
     * requests of search fields.
     *
     * @param boolean $raise_error Whether to generate an error
     */
    protected function tagHtml($params, $raise_error = false)
    {
        $requests = explode(',', $params);
        $value = $this->getValidRequest($requests);
        if ($raise_error && is_null($value)) {
            TIP::error("no valid request found ($params)");
            return null;
        }

        $result = TIP::toHtml($value);
        if (empty($this->_search_tokens)) {
            // No search active
            return $result;
        }

        foreach ($requests as &$request) {
            if (array_key_exists($request, $this->search_field) ||
                in_array($request, $this->search_field)) {
                $result = str_ireplace($this->_search_tokens, $this->_search_spans, $result);
                break;
            }
        }

        return $result;
    }

    /**
     * Fields to use in the next queries
     *
     * Changes the field subset in SELECT queries. $params must be a comma
     * separated list of field ids. If $params is empty the default
     * fieldset will be used.
     */
    protected function tagSubset($params)
    {
        $this->_subset = empty($params) ? null : explode(',', $params);
        return '';
    }

    /**
     * Wikize the field specified in $params
     *
     * The value of the field with $params id is parsed and rendered by the
     * Text_Wiki renderer accordling to the wiki rules defined in the
     * widget args of this field.
     */
    protected function tagWiki($params)
    {
        $value = $this->getField($params);
        if (is_null($value)) {
            TIP::error("no field found ($params)");
            return null;
        }

        $fields =& $this->data->getFields();

        if (!array_key_exists($params, $fields)) {
            // Field not found $this->data: it is probably a joined field
            $rules = null;
        } else {
            $field =& $fields[$params];

            // Get the wiki rules
            if (array_key_exists('widget_args', $field)) {
                $rules = explode(',', $field['widget_args']);
            } elseif (array_key_exists('wiki_rules', $field)) {
                // DEPRECATED: now use widget args instead of "wiki_rules" option
                $rules = explode(',', $field['wiki_rules']);
            } else {
                $rules = null;
            }
        }

        $wiki_base = TIP::buildActionUri($this->id, 'view');
        $renderer =& TIP_Renderer::getWiki($rules, null, $wiki_base);
        $renderer->setRenderConf('Xhtml', 'Image', 'base', TIP::buildDataUri($this->id) . '/');

        $result = $renderer->transform($value);
        if (PEAR::isError($result)) {
            return 'ERROR: ' . $result->getMessage();
        }

        return $result;
    }

    /**
     * Get a partial content of a wiki field
     *
     * $params must be a string in the form "field_id[,len[,wordlen]]", where
     * id is the id of a wiki field, len is the number of characters to be
     * echoed and wordlen is the maximum length of a single word.
     * len defaults to 100 while wordlen defaults to 25.
     */
    protected function tagPartialWiki($params)
    {
        @list($field_id, $max, $max_word) = explode(',', $params);
        if (empty($field_id) || is_null($value = $this->getField($field_id))) {
            TIP::error("no valid field found ($params)");
            return null;
        }

        $fields =& $this->data->getFields();

        if (!array_key_exists($field_id, $fields)) {
            // Field not found $this->data: it is probably a joined field
            $rules = null;
        } else {
            $field =& $fields[$field_id];

            // Get the wiki rules
            if (array_key_exists('widget_args', $field)) {
                $rules = explode(',', $field['widget_args']);
            } elseif (array_key_exists('wiki_rules', $field)) {
                // DEPRECATED: now use "widget_args" instead of "wiki_rules" option
                $rules = explode(',', $field['wiki_rules']);
            } else {
                $rules = null;
            }
        }

        $max > 0 || $max = 100;
        $max_word > 0 || $max_word = 25;
        $text = TIP_Renderer::getWiki($rules)->transform($value, 'Plain');

        // Ellipsize the words too big
        $text_len = -1; // Do not consider the first space delimiter
        $token_list = array();
        $token = strtok($text, " \n\t");
        while ($text_len < $max && $token !== false) {
            $token_len = mb_strlen($token);
            if ($token_len > $max_word) {
                $token = mb_substr($token, 0, $max_word-3) . '...';
                $token_len = $max_word;
            }

            $text_len += $token_len+1;
            $token_list[] = $token;
            $token = strtok(" \n\t");
        }

        $text_len > 0 || $text_len = 0;
        $text = implode(' ', $token_list);
        $text_len < $max || $text = mb_substr($text, 0, $max-3) . '...';
        return TIP::toHtml($text);
    }

    /**
     * Return a link to the atom feeder
     *
     * In $params you can specify a query to perform, a date field or leave it
     * blank to use the default behavior (that is use the $creation_field).
     *
     * The default behaviour is to keep the last three days or
     * (for empty result set) the last ten records.
     */
    protected function tagAtom($params)
    {
        $failed = TIP_Application::getGlobal('fatal_uri');

        $template =& TIP_Type::singleton(array(
            'type' => array('template'),
            'path' => array($this->id, $this->atom_template)
        ));
        if (!$template) {
            return $failed;
        }

        // Check for cache presence
        if (!is_null($uri = $this->engine->getCacheUri($template))) {
            return $uri;
        }

        // Check for usable cache path
        $path = $this->engine->buildCachePath($template);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            return $failed;
        }

        // To make life easier, $params will be a query if contains a space
        if (strpos($params, ' ') !== false) {
            // $params is a query
            if (is_null($view =& $this->startDataView($params, array('fields' => null)))) {
                return $failed;
            }
        } else {
            // $params is a field or it is empty
            $date_field = empty($params) ? $this->creation_field : $params;

            // Check for the last 3 days interval
            $query = $this->data->filter($date_field, array('NOW() - INTERVAL 3 DAY'), '>');
            if (is_null($view =& $this->startDataView($query, array('fields' => null)))) {
                return $failed;
            }

            if ($view->nRows() <= 0) {
                // Empty result set: get the last 10 rows
                $this->endView();
                $query = $this->data->order($date_field, true);
                $query .= $this->data->limit(10);
                if (is_null($view =& $this->startDataView($query, array('fields' => null)))) {
                    return $failed;
                }
            }
        }

        if ($view->nRows() <= 0) {
            $this->endView();
            return $failed;
        }

        ob_start();
        if (!$template->run($this) || !$this->endView()) {
            ob_end_clean();
            return $failed;
        }

        // Store the cached atom feed
        if (!file_put_contents($path, ob_get_clean(), LOCK_EX)) {
            return $failed;
        }

        return $this->engine->getCacheUri($template);
    }

    /**
     * Browse the content throught a cronology interface
     *
     * Renders the whole data content in a cronology tree. The options must be
     * specified in the $params argument as a comma-separated list of field ids
     * in the following sequence:
     *
     * date_field,title_field,tooltip_field,count_field,base_action
     *
     * All the fields are optionals, in which case the TIP_Cronology default
     * value is used.
     */
    protected function tagCronology($params)
    {
        @list(
            $options['date_field'],
            $options['title_field'],
            $options['tooltip_field'],
            $options['count_field'],
            $options['base_action'],
            $options['levels']
        ) = explode(',', $params);

        // Delete null values
        $options = array_filter($options, 'is_string');
        $id = $this->id . '_cronology';
        if (empty($options['date_field'])) {
            unset($options['date_field']);
        } else {
            $id .= $options['date_field'];
        }

        // Merge with the configuration options
        $options['id'] = $id;
        array_key_exists($id, $GLOBALS['cfg']) && $options += $GLOBALS['cfg'][$id];

        // Set required defaults
        TIP::arrayDefault($options, 'type', array('cronology'));
        TIP::arrayDefault($options, 'master', $this);
        TIP::arrayDefault($options, 'date_field', $this->creation_field);

        return TIP_Type::singleton($options)->toHtml();
    }

    /**
     * Perform the browse query throught a pager
     *
     * $params is a string in the form "quanto,query_adds".
     *
     * The quanto is the number of rows per page: leave it undefined to disable
     * the pager. In query_adds you can specify additional SQL commands to
     * append to the query, such as ORDER clauses.
     *
     * This function checks if there is a row more than what specified in the
     * quanto: this provides a simple way to know whether the 'NEXT' button
     * must be rendered or not.
     */
    protected function tagPager($params)
    {
        if (is_null($this->_pager_conditions)) {
            TIP::error('no active browse action');
            return null;
        }

        @list($quanto, $query_template) = explode(',', $params, 2);
        $quanto = (int) $quanto;
        $pager = $quanto > 0;

        if (empty($this->_pager_conditions)) {
        } elseif (is_array($this->_pager_conditions)) {
            $conditions = array();
            foreach ($this->_pager_conditions as $id => $value) {
                $conditions[] = $this->getData()->addFilter('', $id, $value);
            }
            $filter = 'WHERE (' . implode(' AND ', $conditions) . ')';
        } elseif (empty($this->search_field)) {
            $filter = $this->_pager_conditions;
        } else {
            is_string($this->search_field) && $this->search_field = explode(',', $this->search_field);
            $this->_search_tokens = explode(' ', $this->_pager_conditions);
            $pattern = '%' . implode('%', $this->_search_tokens) . '%';
            $conditions = array();
            foreach ($this->search_field as $id) {
                $conditions[] = $this->getData()->addFilter('', $id, $pattern, 'LIKE');
            }
            $filter = 'WHERE (' . implode(' OR ', $conditions) . ')';
        }

        if (isset($filter)) {
            $filter .= ' ' . $query_template;
        } else {
            $filter = $query_template;
        }

        if ($pager) {
            $offset = TIP::getGet('pg_offset', 'int');
            $offset > 0 || $offset = 0;
            $filter .= $this->getData()->limit($quanto+1, $offset);
        } else {
            $offset = 0;
        }

        if (is_null($view = $this->startDataView($filter))) {
            TIP::notifyError('select');
            $this->_search_tokens = null;
            return null;
        }

        if (!empty($this->_search_tokens)) {
            $this->_search_spans = array();
            foreach ($this->_search_tokens as &$token) {
                $this->_search_spans[] = '<span class="highlight">' . $token . '</span>';
            }
        }

        ob_start();
        if (!$view->isValid()) {
            $this->tryRun(array($main_id, $this->pager_empty_template));
        } else {
            $main_id = TIP_Application::getGlobal('id');
            $partial = $pager && $view->nRows() == $quanto+1;
            if ($partial) {
                // Remove the trailing row from the view
                $rows =& $view->getProperty('rows');
                array_splice($rows, $quanto);
            }

            if ($pager) {
                if ($offset > 0) {
                    $this->keys['PREV'] = TIP::modifyActionUri(
                        null, null, null,
                        array('pg_offset' => $offset-$quanto > 0 ? $offset-$quanto : 0)
                    );
                }
                if ($partial) {
                    $this->keys['NEXT'] = TIP::modifyActionUri(
                        null, null, null,
                        array('pg_offset' => $offset+$quanto)
                    );
                }
                $pager = $partial || $offset > 0;
            }

            // Pager rendering BEFORE the rows
            $pager && $this->tryRun(array($main_id, $this->pager_pre_template));

            // Rows rendering
            $empty = true;
            $path = array($this->id, $this->pager_template);
            foreach ($view as $row) {
                $this->run($path);
                $empty = false;
            }

            // Empty result set
            $empty && $this->tryRun(array($main_id, $this->pager_empty_template));

            // Pager rendering AFTER the rows
            $pager && $this->tryRun(array($main_id, $this->pager_post_template));
        }

        $this->endView();
        $this->_search_tokens = null;
        $this->_search_spans = null;
        return ob_get_clean();
    }

    /**
     * Echo the rendered XHTML
     *
     * Renders the whole TIP_Content rows in XHTML. In $params there
     * should be the template action string to use to build the links.
     */
    protected function tagShow($params)
    {
        return $this->toHtml($params);
    }

    /**
     * View the current row content
     *
     * Shows the current row using a standard frozen form. If $params is
     * specified, the row with $params as primary key is shown instead.
     */
    protected function tagView($params)
    {
        if (is_null($row = $this->fromRow($params == '' ? null : $params))) {
            return null;
        }

        if (@is_array($this->form_options['view'])) {
            $options = $this->form_options['view'];
        }

        TIP::arrayDefault($options, 'buttons', 0);
        TIP::arrayDefault($options, 'valid_render', TIP_FORM_RENDER_HERE);
        TIP::arrayDefault($options, 'invalid_render', TIP_FORM_RENDER_HERE);
        $options['defaults'] =& $row;
        $options['type'] = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_VIEW;

        $form =& TIP_Type::singleton($options);
        $form->validate();
        $form->process();

        ob_start();
        $form->render(false);
        return ob_get_clean();
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Generates and executes a TIP_Form instance to add a new row.
     * The $id argument can be used to duplicate an existing row.
     * If left null, an empty row is used as default.
     *
     * If no $options are specified, the default behaviour is to render the
     * form in the page and to try to call actionView() on the result when the
     * form is validated.
     *
     * Notice also that $options['on_process'], if not specified, will be set
     * to the _onAdd() default callback.
     *
     * @param  mixed $id      The identifier of the row to duplicate
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionAdd($id = null, $options = array())
    {
        $primary_key = $this->data->getProperty('primary_key');

        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['add'])) {
            $options = array_merge($this->form_options['add'], $options);
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onAdd'));
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '') . '{' . $primary_key . '}');

        $processed = $this->form(TIP_FORM_ACTION_ADD, $id, $options);
        if (is_null($processed)) {
            return false;
        } elseif (!$processed) {
            return true;
        }

        // Form validate: if 'valid_render' is set to nothing, try to
        // call actionView() on the newly appended row
        if (@$options['valid_render'] == TIP_FORM_RENDER_NOTHING) {
            return $this->actionView($this->data->getLastId());
        }

        return true;
    }

    /**
     * Perform an edit action
     *
     * Generates and executes a TIP_Form instance to edit a row.
     *
     * If no $options are specified, the default behaviour is to render both
     * valid and invalid form in the page.
     *
     * Notice also that $options['on_process'], if not specified, will be set
     * to the _onEdit() default callback.
     *
     * @param  mixed $id      The identifier of the row to edit
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionEdit($id, $options = array())
    {
        if (@is_array($this->form_options['edit'])) {
            $options = array_merge($this->form_options['edit'], $options);
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onEdit'));

        return !is_null($this->form(TIP_FORM_ACTION_EDIT, $id, $options));
    }

    /**
     * Perform a delete action
     *
     * Generates and executes a TIP_Form instance to delete a row.
     *
     * If no $options are specified, the default behaviour is to render both
     * valid and invalid form in the page.
     *
     * Notice also that $options['on_process'], if not specified, will be set
     * to the _onDelete() default callback.
     *
     * @param  mixed $id      The identifier of the row to delete
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionDelete($id, $options = array())
    {
        if (@is_array($this->form_options['delete'])) {
            $options = array_merge($this->form_options['delete'], $options);
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onDelete'));

        return !is_null($this->form(TIP_FORM_ACTION_DELETE, $id, $options));
    }

    /**
     * Perform a view action
     *
     * Runs the file identified by the 'view_template' property for the
     * specified row. The rendered result is appended to the page.
     *
     * @param  mixed $id The identifier of the row to view
     * @return bool      true on success or false on errors
     */
    protected function actionView($id)
    {
        // If no view template defined, simply does nothing (without errors)
        if (empty($this->view_template)) {
            return true;
        }

        if (is_null($row =& $this->fromRow($id, false)) || !$this->_onView($row)) {
            return false;
        }

        $this->appendToPage($this->view_template);
        $this->endView();
        return true;
    }

    /**
     * Perform a browse action
     *
     * In $conditions, you must specify an associative array of
     * 'field_id' => 'value' to impose for this browse action. Only equal
     * conditions are allowed.
     *
     * @param  array &$conditions The browse conditions
     * @return bool               true on success or false on errors
     */
    protected function actionBrowse(&$conditions)
    {
        // If no browse template defined, simply does nothing (without errors)
        if (empty($this->browse_template)) {
            return true;
        }

        $this->_pager_conditions =& $conditions;
        $this->appendToPage($this->browse_template);
        return true;
    }

    /**
     * Perform a search action
     *
     * @param  string $pattern The search pattern
     * @return bool            true on success or false on errors
     */
    protected function actionSearch($pattern)
    {
        // If no browse template defined, simply does nothing (without errors)
        if (empty($this->browse_template)) {
            return true;
        }

        $this->_pager_conditions = trim($pattern);
        $this->appendToPage($this->browse_template);
        return true;
    }

    protected function runManagerAction($action)
    {
        switch ($action) {

        case 'edit':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->actionEdit($id);
        }

        return null;
    }

    protected function runAdminAction($action)
    {
        switch ($action) {

        case 'delete':
            return
                !is_null($id = $this->fromGet(null, $this->id_type)) &&
                $this->actionDelete($id);
        }

        return null;
    }

    protected function runTrustedAction($action)
    {
        switch ($action) {

        case 'edit':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->isOwner($id) &&
                $this->actionEdit($id);

        case 'delete':
            return
                !is_null($id = $this->fromGet(null, $this->id_type)) &&
                $this->isOwner($id) &&
                $this->actionDelete($id);
        }

        return null;
    }

    protected function runUntrustedAction($action)
    {
        switch ($action) {

        case 'add':
            return $this->actionAdd();
        }

        return null;
    }

    protected function runAction($action)
    {
        switch ($action) {

        case 'view':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->actionView($id);

        case 'browse':
            $conditions = $this->default_conditions;

            // Merge all browsable fields for this privilege level
            $browsable = array();
            for ($n = $this->privilege; $n > TIP_PRIVILEGE_INVALID; --$n) {
                if (array_key_exists($n, $this->browsable_fields)) {
                    $browsable = array_merge($browsable, $this->browsable_fields[$n]);
                }
            }

            // Build a query for every GETS matching the $browsable array
            // and which has a corrispondence in the data structure
            $fields = $this->data->getFields();
            foreach ($browsable as $id) {
                $get = $id == $this->browse_field ? 'id' : $id;
                if (array_key_exists($get, $_GET) &&
                    !is_null($type = $this->data->getFieldType($id))) {
                    $conditions[$id] = TIP::getGet($get, $type);
                }
            }

            // Global browsing is enabled only if there is the special
            // '__ALL__' id in the browsable fields
            if (empty($conditions) && !in_array('__ALL__', $browsable)) {
                TIP::notifyError('denied');
                return false;
            }

            return $this->actionBrowse($conditions);

        case 'search':
            return
                !is_null($pattern = $this->fromGetOrPost('id', 'string')) &&
                $this->actionSearch($pattern);
        }

        return null;
    }

    //}}}
    //{{{ Callbacks

    /**#@+
     * This callback is transaction protected to avoid data corruptions.
     *
     * @param  array      &$row     The subject row
     * @param  array|null  $old_row The old row or null on no old row
     * @return bool                 true on success or false on error
     */

    /**
     * Overridable add callback
     *
     * Called by a TIP_Form instance to process the add action.
     * The default handler updates the "magic" fields, inserts a
     * new row in the $data object and calls the _onMasterAdd signal
     * for every configured module that has this module as 'master'.
     *
     * The new primary key of the inserted row is updated in $row
     * **before** calling the _onMasterAdd() signals, so it is
     * available from these callbacks. No other changes are made to
     * $row, so it could contain merged data.
     */
    public function _onAdd(&$row, $old_row)
    {
        $this->setMagicFields($row);

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        // Work on a copy of $row because putRow() is destructive
        $new_row = $row;

        // Process the row
        $done = $this->data->putRow($new_row) &&
            ($row = array_merge($row, $new_row)) &&
            $this->_onDbAction('Add', $row, $old_row);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**
     * Overridable edit callback
     *
     * Called by a TIP_Form instance to process the edit action.
     * The default handler updates the "magic" fields, updates the
     * row in the $data object and calls the _onMasterEdit signal
     * for every configured module that has this module as 'master'.
     */
    public function _onEdit(&$row, $old_row)
    {
        TIP::arrayDefault($row, $this->last_edit_field, TIP::formatDate('datetime_sql'));
        TIP::arrayDefault($row, $this->editor_field, TIP::getUserId());
        isset($this->edits_field) &&
            array_key_exists($this->edits_field, $row) &&
            ++ $row[$this->edits_field];

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        // Process the row
        $done = $this->data->updateRow($row, $old_row) &&
            $this->_onDbAction('Edit', $row, $old_row);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**
     * Overridable delete callback
     *
     * Called by a TIP_Form instance to process the delete action.
     * The default handler deletes the row and calls the
     * _onMasterDelete() signal for every configured module that has
     * this module as 'master'.
     */
    public function _onDelete(&$row, $old_row)
    {
        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        // Process the row
        $done = $this->data->deleteRow($row) &&
            $this->_onDbAction('Delete', $row, $old_row);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**#@-*/

    /**
     * Overridable 'view' callback
     *
     * Called by actionView() before performing the 'view' action.
     * The default handler updates 'hits_field' and 'last_hit_field', if they
     * are present.
     *
     * @param  array &$row The data row to view
     * @return bool        true on success, false on errors
     */
    public function _onView(&$row)
    {
        $old_row = $row;

        TIP::arrayDefault($row, $this->last_hit_field, TIP::formatDate('datetime_sql'));
        isset($this->hits_field) &&
            array_key_exists($this->hits_field, $row) &&
            ++ $row[$this->hits_field];

        // Update user statistics, if the user module exists
        if (!is_null($user =& TIP_Application::getSharedModule('user'))) {
            $user->increment($this->user_statistic['_onView']);
        }

        return $this->data->updateRow($row, $old_row);
    }

    /**
     * 'on_row' callback for TIP_Data_View
     *
     * Adds the following calculated fields to every data row:
     * - 'IS_OWNER': true if the current user owns the row or false otherwise
     *
     * @param  array &$row The row as generated by TIP_Data_View
     * @return bool        true to continue, false to stop
     */
    public function _onDataRow(&$row)
    {
        $row['IS_OWNER'] = $this->_isOwner($row) ? true : false;
        return true;
    }
 
    /**
     * 'on_view' callback helper
     *
     * Calls the sequence of on_view callbacks registered in the
     * $_on_view_callbacks array.
     *
     * All the callbacks are executed also if someone of them returns
     * false but the returned value is still the logical AND of all
     * the collected results.
     *
     * @param  TIP_Data_View &$view The view
     * @return bool                 true to continue, false to stop
     */
    public function _onViewHook(&$view)
    {
        $done = true;
        foreach ($this->_on_view_callbacks as $callback) {
            if (!call_user_func_array($callback, array(&$view))) {
                $done = false;
            }
        }

        return $done;
    }

    /**
     * 'on_view' callback for global TIP_Data_View queries
     *
     * Called whenever a global query runs: this callback automatically
     * populates the model used by rendering operations (toRows() and
     * toHtml()).
     *
     * @param  TIP_Data_View &$view The view
     * @return bool                 true to continue, false to stop
     */
    public function _createModel(&$view)
    {
        if (!is_null($this->_model)) {
            // Model yet created
            return true;
        }

        // Map the custom fields to HTML_Menu id
        $this->_model =& $view->getProperty('rows');
        foreach ($this->_model as $id => &$row) {
            $this->_rowToArray($row, $id);
        }

        return true;
    }

    //}}}
    //{{{ Internal properties

    /**
     * Fields to use in SELECT queries (null for all fields)
     * @var array
     * @internal
     */
    private $_subset = null;

    /**
     * The stack of performed views
     * @var array
     * @internal
     */
    private $_views = array();

    /**
     * A reference to the current view or null for no current views
     * @var TIP_View
     * @internal
     */
    private $_view = null;

    /**
     * The pager conditions, as specified by actionBrowse() or actionSearch()
     * @var array|string
     * @internal
     */
    protected $_pager_conditions = null;

    /**
     * List of tokens to search for
     * @var array|null
     * @internal
     */
    private $_search_tokens = null;

    /**
     * List of spans to be substituted to the tokens
     * @var array|null
     * @internal
     */
    private $_search_spans = null;

    /**
     * The model used by rendering operations
     * @var array
     * @internal
     */
    protected $_model = null;

    /**
     * An array of view callbacks to be called in sequence
     * @var array
     * @internal
     */
    private $_on_view_callbacks = null;

    //}}}
    //{{{ Internal methods

    /**
     * General db action manager
     *
     * Internal method used by _onAdd(), _onEdit() and _onDelete().
     *
     * @param  string      $action  'Add', 'Edit' or 'Delete'
     * @param  array      &$row     The subject row
     * @param  array|null  $old_row The old row or null on no old row
     * @return bool                 true on success or false on error
     * @internal
     */
    private function _onDbAction($action, &$row, $old_row)
    {
        // Dispatch the signal to all children modules
        $callback = create_function('$a', 'return @$a[\'master\'] == \'' . $this->id . '\';');
        if (is_array($children = array_filter($GLOBALS['cfg'], $callback))) {
            $method = '_onMaster' . $action;
            foreach (array_keys($children) as $child_id) {
                $child = TIP_Type::getInstance($child_id);
                if (method_exists($child, $method) &&
                    !$child->$method($row, $old_row)) {
                    return false;
                }
            }
        }

        // Update user statistics, if the user module exists
        if (!is_null($field = @$this->user_statistic['_on' . $action]) &&
            !is_null($user =& TIP_Application::getSharedModule('user'))) {
            $user->increment($field);
        }

        // Remove the feed, if it exists
        if (!is_null($template =& TIP_Type::singleton(array(
               'type' => array('template'),
               'path' => array($this->id, $this->atom_template)
           ))) &&
           !is_null($path = $this->engine->getCachePath($template)) &&
           file_exists($path)) {
           unlink($path);
        }

        return true;
    }

    /**
     * Get the data rows and return a renderer ready to be used
     *
     * @param  string                     $action The action template string
     * @return HTML_Menu_TipRenderer|null         The renderer or
     *                                            null on errors
     * @internal
     */
    protected function &_getRenderer($action)
    {
        if (is_null($this->_model)) {
            $this->startDataView() && $this->endView();
        }

        if (is_null($this->_model)) {
            $fake_null = null;
            return $fake_null;
        }

        // Work on a copy
        $model = $this->_model;
        foreach ($model as $id => &$row) {
            if (isset($row['url'])) {
                // Explicit action set
                continue;
            }

            $url = array_key_exists('action', $row) ? $row['action'] : $action;
            if (empty($url)) {
                // No action specified
                $row['url'] = null;
                continue;
            }

            $url = str_replace('-id-', $id, $url);
            $row['url'] = TIP::buildActionUriFromTag($url, $this->id);
        }

        require_once 'HTML/Menu.php';
        $menu = new HTML_Menu($model);
        empty($action) || $menu->forceCurrentUrl(TIP::getRequestUri());
        $renderer =& TIP_Renderer::getMenu();
        $menu->render($renderer, 'sitemap');
        return $renderer;
    }

    /**
     * Check if a row is owned by a user
     *
     * Abstracts the //owned by// operation. It works properly also on
     * missing info and fully supports the "ownership_expiration" property.
     *
     * @param  array   &$row  The subject row
     * @param  int|null $user A user id or null to use the logged user
     * @return bool           true if $row is owned by $user, false if it is
     *                        not owned, null on errors
     * @internal
     */
    private function _isOwner(&$row, $user = null)
    {
        if (!array_key_exists($this->owner_field, $row)) {
            // No owner field found: error
            return null;
        }

        isset($user) || $user = TIP::getUserId();
        if ($row[$this->owner_field] != $user) {
            // Owner and user do not match: return "not owned row" condition
            return false;
        }

        if ($this->privilege == TIP_PRIVILEGE_MANAGER ||
            $this->privilege == TIP_PRIVILEGE_ADMIN ||
            is_null($this->ownership_expiration)) {
            // Ownership does not expire: return "owned row" condition
            return true;
        }

        if (!array_key_exists($this->creation_field, $row)) {
            // Ownership expiration set but creation field not found: error
            return null;
        }

        // Check if the ownership expired
        $creation = TIP::getTimestamp($row[$this->creation_field], 'sql');
        if (is_null($creation)) {
            // TIP::getTimestamp() failed: error
            return null;
        }

        $expiration = strtotime($this->ownership_expiration, $creation);
        if ($expiration === false) {
            // strtotime() failed (wrong expiration format?): error
            return null;
        }

        // The row is owned only if now is before the expiration time
        return time() < $expiration;
    }

    /**
     * Render a row to an array
     *
     * Renders $row as required by toArray(), that is append to the row
     * data some missing info (the {{id}}, {{title}} and {{tooltip}} fields).
     *
     * @param  array   &$row  The subject row
     * @param  mixed    $id   The id of the row
     * @internal
     */
    private function _rowToArray(&$row, $id)
    {
        isset($row['id']) || $row['id'] = $id;

        // Use the custom "title_field" or
        // leave the default $row['title'] untouched
        if (isset($this->title_field) && $this->title_field != 'title') {
            $row['title'] = TIP::pickElement($this->title_field, $row);
        }

        // Try to set the custom tooltip field
        if (isset($this->tooltip_field) && $this->tooltip_field != 'tooltip') {
            $row['tooltip'] = TIP::pickElement($this->tooltip_field, $row);
        }
    }

    //}}}
}
?>
