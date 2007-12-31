<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Hierarchy definition file
 *
 * @package TIP
 */

/**
 * A content module with a hierarchy as data model
 *
 * @package TIP
 */
class TIP_Hierarchy extends TIP_Content
{
    //{{{ Properties

    /**
     * A reference to the master module
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The field in 'master' to join to the primary key of this hierarchy
     * @var string
     */
    protected $master_field = 'group';

    /**
     * The field specifying the parent of a row
     * @var string
     */
    protected $parent_field = 'parent';

    /**
     * The field that forces a specified order
     * @var string
     */
    protected $order_field = 'order';

    /**
     * The field that forces a specified order
     * @var string
     */
    protected $count_field = '_count';

    /**
     * Maximum number of levels to keep online
     * @var int
     */
    protected $levels = null;

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['master'])) {
            return false;
        }

        if (is_string($options['master'])) {
            $options['master'] =& TIP_Type::getInstance($options['master']);
        } elseif (is_array($options['master'])) {
            $options['master'] =& TIP_Type::singleton($options['master']);
        }

        isset($options['view_source']) || $options['view_source'] = null;
        return $options['master'] instanceof TIP_Content;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Hierarchy instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Callbacks

    public function _createModel(&$view)
    {
        $rows = $view->getProperty('rows');
        if (empty($rows)) {
            $this->_html = '';
            $this->_rows = array();
            return true;
        }

        // By default, counting is enable if the 'count_field' property is set
        if (isset($this->count_field)) {
            $total_count = 0;
            $request_url = htmlspecialchars(TIP::getRequestURI(), ENT_QUOTES, 'UTF-8');
        }

        $base_action = TIP::getScriptURI();
        $action = $this->getOption('action');
        if ($action) {
            // Action specified: prepend the root URL
            $action = TIP::buildUrl($action);
        } else {
            // No action specified: construct the default action (browse)
            $action = $base_action . '?module=' . $this->master . '&amp;action=browse&amp;' . $this->master_field . '=';
        }

        $primary_key = $this->data->getProperty('primary_key');
        $tree = array();
        foreach (array_keys($rows) as $id) {
            $row =& $rows[$id];
            isset($row['CLASS']) || $row['CLASS'] = 'item';
            if (!isset($row['url'])) {
                if (isset($row['action'])) {
                    $row['url'] = $base_action;
                    if ($row['action']) {
                        $row['url'] .= '?' . $row['action'];
                    }
                } else {
                    $row['url'] = $action . $id;
                }
            }
            if (isset($this->count_field)) {
                $count = @$row[$this->count_field];
                isset($row['COUNT']) || $row['COUNT'] = 0;
                $row['COUNT'] += $count;
                $total_count += $count;
            }

            if ($row[$this->parent_field]) {
                while ($parent_id = $row[$this->parent_field]) {
                    $parent =& $rows[$parent_id];
                    $parent['CLASS'] = 'folder';
                    $parent['sub'][$row[$primary_key]] =& $row;
                    if (isset($count)) {
                        isset($parent['COUNT']) || $parent['COUNT'] = 0;
                        $parent['COUNT'] += $count;
                    }
                    $row =& $parent;
                }
            } else {
                $tree[$id] =& $row;
            }
        }

        if (isset($total_count)) {
            $view->setSummary('TOTAL_COUNT', $total_count);
        }

        require_once 'HTML/Menu.php';
        $model =& new HTML_Menu($tree);
        $model->forceCurrentUrl(htmlspecialchars(TIP::getRequestURI()));
        $renderer =& TIP_Renderer::getMenu($this->levels);
        $model->render($renderer, 'sitemap');
        $this->_html = $renderer->toHtml();
        $this->_rows = $renderer->toArray();
        return true;
    }

    public function _onMasterAdd(&$row)
    {
        // Update the counter only if the public flag is on (or does not exist)
        $public = $this->master->getProperty('public_field');
        if (!isset($public, $row[$public]) || stripos($row[$public], 'yes') !== false) {
            $this->_updateCount($row[$this->master_field], +1);
        }
        return true;
    }

    public function _onMasterDelete(&$row)
    {
        // Update the counter only if the public flag is on (or does not exist)
        $public = $this->master->getProperty('public_field');
        if (!isset($public, $row[$public]) || stripos($row[$public], 'yes') !== false) {
            $this->_updateCount($row[$this->master_field], -1);
        }
        return true;
    }

    public function _onMasterEdit(&$row, &$old_row)
    {
        if (is_array($old_row)) {
            // Check for the public flag
            $public = $this->master->getProperty('public_field');
            $old_public = !isset($public, $old_row[$public]) || $old_row[$public] == 'yes';
            $new_public = isset($row, $row[$public]) ? $row[$public] == 'yes' : $old_public;

            // Check for the parent
            $parent = $this->master_field;
            $old_parent = @$old_row[$parent];
            $new_parent = isset($row[$parent]) ? $row[$parent] : $old_parent;

            // If something rilevant has changed, update the counters
            if ($old_public != $new_public || $old_parent != $new_parent) {
                $old_public && $this->_updateCount($old_parent, -1);
                $new_public && $this->_updateCount($new_parent, +1);
            }
        }

        return true;
    }

    //}}}
    //{{{ Methods

    private function _render()
    {
        if (is_null($this->_html)) {
            $this->startDataView() && $this->endView();
        }

        return !empty($this->_html);
    }

    /**
     * Start a data view
     *
     * Overrides the default method to force global queries (that is with
     * empty $filter) to be sorted by 'order_field', so the query can also be
     * used to build the hierarchy model.
     *
     * @param  string|null       $filter  The filter conditions
     * @param  array             $options The constructor arguments to pass
     *                                    to the TIP_Data_View instance
     * @return TIP_Data_View|null         The view instance or null on errors
     */
    public function &startDataView($filter = null)
    {
        $model_filter = $this->data->order($this->order_field);

        if (empty($filter)) {
            $filter = $model_filter;
        } elseif ($filter != $model_filter) {
            // No way to use this query to build the model
            return parent::startDataView($filter);
        }

        return parent::startDataView($filter, array('on_view' => array(&$this, '_createModel')));
    }

    /**
     * Render a DHTML hierarchy
     *
     * Renders this hierarchy in a DHTML form.
     *
     * @return string|null The rendered HTML or null on errors
     */
    public function &toHtml()
    {
        $this->_render();
        return $this->_html;
    }

    /**
     * Get the hierarchy rows
     *
     * Builds an array of rows from this hierarchy. Useful to automatically
     * define the options of a <select> item in a TIP_Form instance.
     *
     * @return array|null The rendered rows or null on errors
     */
    public function &toRows()
    {
        $this->_render();
        return $this->_rows;
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param      string       $params Parameters of the tag
     * @return     string|null          The string result or null
     * @subpackage SourceEngine
     */

    /**
     * Echo a row descriptor
     *
     * Outputs the full path (as generated by the row renderer) of the
     * row with $params id.
     */
    protected function tagDescriptor($params)
    {
        if (empty($params)) {
            TIP::error('no row specified');
            return null;
        } elseif (!$this->_render()) {
            return null;
        }

        if (!array_key_exists($params, $this->_rows)) {
            TIP::error("row not found ($params)");
            return null;
        }

        return $this->_rows[$params];
    }

    /**
     * Echo the hierarchy
     *
     * Outputs the XHTML hierarchy of this instance.
     */
    protected function tagShow($params)
    {
        if (!$this->_render()) {
            return null;
        }

        return $this->_html;
    }

    /**#@-*/

    //}}}
    //{{{ Internal properties

    /**
     * The generated html content
     * @var string
     * @internal
     */
    private $_html = null;

    /**
     * The generated rows content
     * @var array
     * @internal
     */
    private $_rows = null;

    //}}}
    //{{{ Internal methods

    private function _updateCount($id, $offset)
    {
        if (empty($this->count_field)) {
            return true;
        }

        // Global query (probably cached)
        if (is_null($view =& $this->startDataView())) {
            TIP::notifyError('select');
            return false;
        }
        $rows =& $view->getProperty('rows');
        $this->endView();

        if (!isset($rows[$id])) {
            TIP::warning("row not found ($id)");
            TIP::notifyError('notfound');
            return false;
        }

        $old_row =& $rows[$id];
        $row[$this->count_field] = $old_row[$this->count_field] + $offset;
        if (!$this->data->updateRow($row, $old_row)) {
            TIP::notifyError('update');
            return false;
        }

        $old_row[$this->count_field] += $offset;
        return true;
    }

    //}}}
}
?>
