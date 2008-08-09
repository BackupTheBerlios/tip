<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Hierarchy definition file
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
 * A content module with a hierarchy as data model
 *
 * @package TIP
 */
class TIP_Hierarchy extends TIP_Content
{
    //{{{ Properties

    /**
     * An optional reference to the master module
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The default action (if not specified in the row)
     * @var string
     */
    protected $action = null;

    /**
     * The field in $master to join to the primary key of this hierarchy
     * @var string
     */
    protected $master_field = 'group';

    /**
     * The field specifying the parent of a row
     * @var string
     */
    protected $parent_field = 'parent';

    /**
     * The field specifying the title or null to use the default 'title' field
     * @var string
     */
    protected $title_field = 'title';

    /**
     * The field specifying the tooltip to use or null to try to use the
     * default 'tooltip' field
     * @var string
     */
    protected $tooltip_field = null;

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
     * Maximum number of levels to keep online (before enabling AJAX)
     *
     * Leave it null to not use AJAX at all. This means the whole tree is
     * generated on every page.
     *
     * @var int
     */
    protected $levels = null;

    /**
     * Set to true to automatically generate a self-reference as the
     * first child on every container
     * @var boolean
     */
    protected $self_reference = false;

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        if (@is_string($options['master'])) {
            $options['master'] =& TIP_Type::getInstance($options['master']);
        } elseif (@is_array($options['master'])) {
            $options['master'] =& TIP_Type::singleton($options['master']);
        }

        if (isset($options['master']) && !$options['master'] instanceof TIP_Content) {
            return false;
        }

        isset($options['action']) || $options['action'] = 'browse,-id-';
        return true;
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
        isset($this->count_field) && $total_count = 0;

        $primary_key = $this->data->getProperty('primary_key');
        $tree = array();
        if (isset($this->master)) {
            $module =& $this->master;
        } else {
            $module =& $this;
        }
        foreach (array_keys($rows) as $id) {
            $row =& $rows[$id];
            isset($row['id']) || $row['id'] = $id;
            isset($row['CLASS']) || $row['CLASS'] = 'item';

            // Use the custom title_field or
            // leave the default $row['title'] untouched
            isset($this->title_field) && $row['title'] = $row[$this->title_field];

            // Try to set the custom tooltip field
            if (isset($this->tooltip_field) && array_key_exists($this->tooltip_field, $row)) {
                $row['tooltip'] = $row[$this->tooltip_field];
            }

            if (!isset($row['url'])) {
                $action = @$row['action'];
                $action || $action = $this->action;
                $action = str_replace('-id-', $id, $action);
                $row['url'] = TIP::buildActionUriFromTag($action, (string) $module);
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
                    if (@$parent['CLASS'] != 'folder') {
                        if ($this->self_reference) {
                            $tmp = $parent;
                            $parent['sub']['SELF'] = $tmp;
                        }
                        $parent['CLASS'] = 'folder';
                    }
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
        $model->forceCurrentUrl(TIP::getRequestUri());
        $renderer =& TIP_Renderer::getMenu($this->levels);
        $model->render($renderer, 'sitemap');
        $this->_html = $renderer->toHtml();
        $this->_rows = $renderer->toArray();
        $this->_is_current_container = $renderer->isCurrentContainer();
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
     * Render as XHTML hierarchy
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
     * @param  string      $params Parameters of the tag
     * @return string|null         The string result or null
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

        if ($this->_is_current_container) {
            // If the current row is a container, don't index this page
            TIP_Application::setRobots(false, null);
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

    /**
     * Is the current row a container?
     * @var boolean
     * @internal
     */
    private $_is_current_container = false;

    //}}}
    //{{{ Internal methods

    private function _render()
    {
        if (is_null($this->_html)) {
            $this->startDataView() && $this->endView();
        }

        return !empty($this->_html);
    }

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
