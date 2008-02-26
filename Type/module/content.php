<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Content definition file
 * @package TIP
 */

/**
 * The root of data based modules
 *
 * This class mainly adds a data management infrastructure to TIP_Module,
 * allowing for a full interaction between TIP_Source and TIP_Data throught the
 * use of TIP_Data_View instances.
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
    protected $view_source = 'view.src';

    /**
     * The file to run on browse actions
     * @var string
     */
    protected $browse_source = 'browse.src';

    /**
     * The file to run at the beginning of a 'pager' tag
     * @var string
     */
    protected $pager_pre_source = 'pager_before.src';

    /**
     * The file to run for every row on the 'pager' tag
     * @var string
     */
    protected $pager_source = 'row.src';

    /**
     * The file to run at the end of a 'pager' tag
     * @var string
     */
    protected $pager_post_source = 'pager_after.src';

    /**
     * The file to run to generate the atom feed
     * @var string
     */
    protected $atom_source = 'atom.xml';

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
     * The field containing the date of the last actionView
     * @var string
     */
    protected $last_hit_field = '_lasthit';

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

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        isset($options['data']) || $options['data'] = $options['id'];
        if (is_string($options['data'])) {
            $options['data'] =& TIP_Type::singleton(array(
                'type' => array('data'),
                'path' => $options['data']
            ));
        } elseif (is_array($options['data'])) {
            isset($options['data']['type']) || $options['data']['type'] = array('data');
            isset($options['data']['path']) || $options['data']['path'] = $options['id'];
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
                unset($row[$this->data->getProperty('primary_key')]);
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
        array_key_exists('data', $options) || $options['data'] =& $this->data;
        array_key_exists('fields', $options) || $options['fields'] = $this->_subset;

        if (!array_key_exists('on_row', $options) &&
            method_exists($this, '_on' . $type . 'Row')) {
            $options['on_row'] = array(&$this, '_on' . $type . 'Row');
        }

        if (!array_key_exists('on_view', $options) &&
            method_exists($this, '_on' . $type . 'View')) {
            $options['on_view'] = array(&$this, '_on' . $type . 'View');
        }

        if (is_null($view =& TIP_Type::singleton($options))) {
            TIP::error("view type does not exist ($type)");
            return $view;
        }

        return $this->push($view);
    }

    /**
     * Start a data view
     *
     * A shortcut for often used TIP_Data_View calls. Also, it provides an
     * easy way to specify the filter instead of setting $options['filter'].
     *
     * See startView() for further details.
     *
     * @param  string            $filter  The filter conditions
     * @param  array             $options The constructor arguments to pass
     *                                    to the TIP_Data_View instance
     * @return TIP_Data_View|null         The view instance or null on errors
     */
    public function &startDataView($filter, $options = array())
    {
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
        isset($id) || $id = $this->data->getProperty('primary_key');
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
     * @param  bool       $end_view Wheter to end the view or not
     * @return array|null           The row or null on errors
     */
    public function &fromRow($id = null, $end_view = true)
    {
        $row = null;
        $start_view = isset($id);

        if ($start_view) {
            if (is_null($view =& $this->startDataView($this->data->rowFilter($id)))) {
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
            isset($rows[$id]) && $row =& $rows[$id];
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

        if (isset($row[$this->owner_field]) && $row[$this->owner_field] != TIP::getUserId()) {
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

        if (isset($row[$this->owner_field]) && $row[$this->owner_field] == TIP::getUserId()) {
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
        return $this->data->filter($this->owner_field, $user);
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param      string       $params Parameters of the tag
     * @return     string|null          The string result or null
     * @subpackage SourceEngine
     */

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
     * 'wiki_rules' option of this field.
     */
    protected function tagWiki($params)
    {
        $value = $this->getField($params);
        if (is_null($value)) {
            TIP::error("no field found ($params)");
            return null;
        }

        $fields =& $this->data->getFields();
        $field =& $fields[$params];
        $rules = isset($field['wiki_rules']) ? explode(',', $field['wiki_rules']) : null;

        $renderer =& TIP_Renderer::getWiki($rules);
        $renderer->setRenderConf('Xhtml', 'Image', 'base', TIP::buildDataUri($this->id) . '/');
        return $renderer->transform($value);
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

        $max > 0 || $max = 100;
        $max_word > 0 || $max_word = 25;
        $fields =& $this->data->getFields();
        $field =& $fields[$field_id];
        $rules = isset($field['wiki_rules']) ? explode(',', $field['wiki_rules']) : null;
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
     * In $params you can specify a query or leave it empty to use the default
     * query. The default behaviour is to keep the last three days or
     * (for empty result set) the last ten records.
     */
    protected function tagAtom($params)
    {
        $failed = TIP_Application::getGlobal('fatal_uri');

        $source =& TIP_Type::singleton(array(
            'type' => array('source'),
            'path' => array($this->id, $this->atom_source)
        ));
        if (!$source) {
            return $failed;
        }

        // Check for cache presence
        if (!is_null($uri = $this->engine->getCacheUri($source))) {
            return $uri;
        }

        empty($params) && $params = $this->data->filter($this->creation_field, array('NOW() - INTERVAL 3 DAY'), '>');
        if (is_null($view =& $this->startDataView($params, array('fields' => null)))) {
            return $failed;
        }

        if ($view->nRows() <= 0) {
            // Empty result set: get the last 10 rows
            $this->endView();
            $params = $this->data->order($this->creation_field, true);
            $params .= $this->data->limit(10);
            if (is_null($view =& $this->startDataView($params, array('fields' => null)))) {
                return $failed;
            }
        }

        if ($view->nRows() <= 0) {
            $this->endView();
            return $failed;
        }

        ob_start();
        $done = $source->run($this) && $this->endView();
        if (!$done) {
            ob_end_clean();
            return $failed;
        }

        // Store the cached atom feed
        $path = $this->engine->buildCachePath($source);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) ||
            !file_put_contents($path, ob_get_clean(), LOCK_EX)) {
            return $failed;
        }

        return $this->engine->getCacheUri($source);
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
        isset($GLOBALS['cfg'][$id]) && $options += $GLOBALS['cfg'][$id];

        // Set required defaults
        isset($options['type']) || $options['type'] = array('cronology');
        isset($options['master']) || $options['master'] =& $this;
        isset($options['date_field']) || $options['date_field'] = $this->creation_field;

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
        if (is_null($this->_browse_conditions)) {
            TIP::error('no active browse action');
            return null;
        }

        @list($quanto, $query_adds) = explode(',', $params);
        $quanto = (int) $quanto;
        $pager = $quanto > 0;

        if (empty($this->_browse_conditions)) {
            $filter = $query_adds;
        } else {
            $conditions = array();
            foreach ($this->_browse_conditions as $id => $value) {
                $conditions[] = $this->data->addFilter('', $id, $value);
            }
            $filter = 'WHERE ' . implode(' AND ', $conditions) . ' ' . $query_adds;
        }

        if ($pager) {
            $offset = TIP::getGet('pg_offset', 'int');
            $offset > 0 || $offset = 0;
            $filter .= $this->data->limit($quanto+1, $offset);
        } else {
            $offset = 0;
        }

        if (is_null($view = $this->startDataView($filter))) {
            TIP::notifyError('select');
            return null;
        }

        ob_start();
        if ($view->isValid()) {
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
                $pager = isset($this->keys['PREV']) || isset($this->keys['NEXT']);
            }

            // Pager rendering BEFORE the rows
            $pager && $this->tryRun(array($main_id, $this->pager_pre_source));

            // Rows rendering
            $path = array($this->id, $this->pager_source);
            foreach ($view as $row) {
                $this->run($path);
            }

            // Pager rendering AFTER the rows
            $pager && $this->tryRun(array($main_id, $this->pager_post_source));
        }

        $this->endView();
        return ob_get_clean();
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Generates and executes a TIP_Form instance to add a new row.
     *
     * If no $options are specified, the default behaviour is to render the
     * form in the page and to try to call actionView() on the result when the
     * form is validated.
     *
     * Notice also that $options['on_process'], if not specified, will be set
     * to the _onAdd() default callback.
     *
     * @param  array|null $options Options to pass to the form() call
     * @return bool                true on success or false on errors
     */
    protected function actionAdd($options = null)
    {
        if (isset($this->form_options['add'])) {
            $options = array_merge($this->form_options['add'], (array) $options);
        }

        isset($options['on_process']) || $options['on_process'] = array(&$this, '_onAdd');
        isset($options['follower']) || $options['follower'] = TIP::buildActionUri($this->id, 'view', '-lastid-');
        $processed = $this->form(TIP_FORM_ACTION_ADD, null, $options);
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
     * @param  mixed      $id      The identifier of the row to edit
     * @param  array|null $options Options to pass to the form() call
     * @return bool                true on success or false on errors
     */
    protected function actionEdit($id, $options = null)
    {
        if (isset($this->form_options['edit'])) {
            $options = array_merge($this->form_options['edit'], (array) $options);
        }

        isset($options) || $options = array();
        isset($options['on_process']) || $options['on_process'] = array(&$this, '_onEdit');
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
     * @param  mixed      $id      The identifier of the row to delete
     * @param  array|null $options Options to pass to the form() call
     * @return bool                true on success or false on errors
     */
    protected function actionDelete($id, $options = null)
    {
        if (isset($this->form_options['delete'])) {
            $options = array_merge($this->form_options['delete'], (array) $options);
        }

        isset($options) || $options = array();
        isset($options['on_process']) || $options['on_process'] = array(&$this, '_onDelete');
        return !is_null($this->form(TIP_FORM_ACTION_DELETE, $id, $options));
    }

    /**
     * Perform a view action
     *
     * Runs the file identified by the 'view_source' property for the
     * specified row. The rendered result is appended to the page.
     *
     * @param  mixed $id The identifier of the row to view
     * @return bool      true on success or false on errors
     */
    protected function actionView($id)
    {
        // If no view source defined, simply does nothing (without errors)
        if (empty($this->view_source)) {
            return true;
        }

        if (is_null($row =& $this->fromRow($id, false)) || !$this->_onView($row)) {
            return false;
        }

        $this->appendToPage($this->view_source);
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
        // If no browse source defined, simply does nothing (without errors)
        if (empty($this->browse_source)) {
            return true;
        }

        $this->_browse_conditions =& $conditions;
        $this->appendToPage($this->browse_source);
        return true;
    }

    protected function runManagerAction($action)
    {
        switch ($action) {

        case 'edit':
            return
                !is_null($id = $this->fromGetOrPost(null, $this->id_type)) &&
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
                !is_null($id = $this->fromGetOrPost(null, $this->id_type)) &&
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
                !is_null($id = $this->fromGetOrPost(null, $this->id_type)) &&
                $this->actionView($id);

        case 'browse':
            $conditions = array();

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
                if (array_key_exists($id, $fields) &&
                    !is_null($value = TIP::getGet($get, $fields[$id]['type']))) {
                    $conditions[$id] = $value;
                }
            }

            // Global browsing is enabled only if there is the special
            // '__ALL__' id in the browsable fields
            if (empty($conditions) && !in_array('__ALL__', $browsable)) {
                TIP::notifyError('denied');
                return false;
            }

            return $this->actionBrowse($conditions);
        }

        return null;
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
     * The browse conditions, as specified in actionBrowse()
     * @var array
     * @internal
     */
    private $_browse_conditions = null;

    //}}}
    //{{{ Callbacks

    /**
     * Overridable 'add' callback
     *
     * Called by a TIP_Form instance before performing the 'add' action.
     * The default handler calls the _onMasterAdd signal for every configured
     * module that has this module as 'master' and updates 'creation_field'
     * and 'owner_field', if they exists.
     *
     * @param  array &$row The subject row
     * @return bool        true on success, false on errors
     */
    public function _onAdd(&$row)
    {
        isset($this->creation_field) &&
            array_key_exists($this->creation_field, $row) &&
            empty($row[$this->creation_field]) &&
            $row[$this->creation_field] = TIP::formatDate('datetime_sql');
        isset($this->owner_field) &&
            array_key_exists($this->owner_field, $row) &&
            empty($row[$this->owner_field]) &&
            $row[$this->owner_field] = TIP::getUserId();

        return $this->_onDbAction('Add', $row, $row);
    }

    /**
     * Overridable 'edit' callback
     *
     * Called by a TIP_Form instance before performing the 'edit' action.
     * The default handler calls the _onMasterEdit signal for every configured
     * module that has this module as 'master' and updates 'editor_field',
     * 'last_edit_field' and 'edit_count', if they exist.
     *
     * @param  array &$row     The subject row
     * @param  array  $old_row The old row
     * @return bool            true on success, false on errors
     */
    public function _onEdit(&$row, $old_row = null)
    {
        isset($this->last_edit_field) &&
            array_key_exists($this->last_edit_field, $row) &&
            $row[$this->last_edit_field] = TIP::formatDate('datetime_sql');
        isset($this->editor_field) &&
            array_key_exists($this->editor_field, $row) &&
            $row[$this->editor_field] = TIP::getUserId();
        isset($this->edits_field) &&
            array_key_exists($this->edits_field, $row) &&
            ++ $row[$this->edits_field];

        return $this->_onDbAction('Edit', $row, $old_row);
    }

    /**
     * Overridable 'delete' callback
     *
     * Called by a TIP_Form instance before performing the 'delete' action.
     * The default handler calls the _onMasterDelete signal for every configured
     * module that has this module as 'master'.
     *
     * @param  array &$row The subject row
     * @return bool        true on success, false on errors
     */
    public function _onDelete(&$row)
    {
        return $this->_onDbAction('Delete', $row, $row);
    }

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

        isset($this->last_hit_field) &&
            array_key_exists($this->last_hit_field, $row) &&
            $row[$this->last_hit_field] = TIP::formatDate('datetime_sql');
        isset($this->hits_field) &&
            array_key_exists($this->hits_field, $row) &&
            ++ $row[$this->hits_field];

        // Update user statistics, if the user module exists
        if (!is_null($user =& TIP_Application::getSharedModule('user'))) {
            $user->increment($this->user_statistic['_onView']);
        }

        return $this->data->updateRow($row, $old_row);
    }

    //}}}
    //{{{ Internal methods

    /**
     * General db action manager
     *
     * Internal method used by _onAdd(), _onEdit() and _onDelete().
     *
     * @param  string $action 'Add', 'Edit' or 'Delete'
     * @param  array &$row    The subject row
     * @param  array &$data   Additional data to pass to the callbacks
     * @return bool           true on success or false on errors
     * @internal
     */
    protected function _onDbAction($action, &$row, &$data)
    {
        // Dispatch the signal to all children modules
        $callback = create_function('$a', 'return @$a[\'master\'] == \'' . $this->id . '\';');
        if (is_array($children = array_filter($GLOBALS['cfg'], $callback))) {
            $method = '_onMaster' . $action;
            foreach (array_keys($children) as $child_id) {
                $child = TIP_Type::getInstance($child_id);
                if (method_exists($child, $method) &&
                    !$child->$method($row, $data)) {
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
        if (!is_null($source =& TIP_Type::singleton(array(
               'type' => array('source'),
               'path' => array($this->id, $this->atom_source)
           ))) &&
           !is_null($path = $this->engine->getCachePath($source)) &&
           file_exists($path)) {
           unlink($path);
        }

        return true;
    }

    //}}}
}
?>
