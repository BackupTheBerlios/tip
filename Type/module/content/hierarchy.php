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
     * The reference to the master module
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The field in "master" to join to the primary key of this hierarchy
     * @var string
     */
    protected $master_field = 'group';

    /**
     * The field in this hierarchy that specifies the parent of a row
     * @var string
     */
    protected $parent_field = 'parent';

    /**
     * The field in "master" that keeps track of the number of children rows
     * @var string
     */
    protected $count_field = '_count';

    /**
     * The action to be used by the renderer
     * @var string
     * @deprecated
     */
    protected $action = null;

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
        if (!is_null($this->_model)) {
            return true;
        }

        $this->_model = array();
        $rows = $view->getProperty('rows');
        if (empty($rows)) {
            return true;
        }

        // By default, counting is enable if the "count_field" property is set
        isset($this->count_field) && $total_count = 0;
        $primary_key = $this->data->getProperty('primary_key');
        foreach (array_keys($rows) as $id) {
            $row =& $rows[$id];
            isset($row['id']) || $row['id'] = $id;
            isset($row['CLASS']) || $row['CLASS'] = 'item';

            // Use the custom "title_field" or
            // leave the default $row['title'] untouched
            if (isset($this->title_field) && @$this->title_field != 'title') {
                $row['title'] = TIP::pickElement($this->title_field, $row);
            }

            // Try to set the custom tooltip field
            if (isset($this->tooltip_field) && @$this->tooltip_field != 'tooltip') {
                $row['tooltip'] = TIP::pickElement($this->tooltip_field, $row);
            }

            if (isset($this->count_field)) {
                $count = @$row[$this->count_field];
                isset($row['COUNT']) || $row['COUNT'] = 0;
                $row['COUNT'] += $count;
                $total_count += $count;
            }

            if (isset($this->parent_field) && $row[$this->parent_field]) {
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
                $this->_model[$id] =& $row;
            }
        }

        if (isset($total_count)) {
            $view->setSummary('TOTAL_COUNT', $total_count);
        }

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
        $model_filter = '';
        if (!empty($this->default_conditions)) {
            foreach ($this->default_conditions as $id => $value) {
                if (empty($model_filter)) {
                    $model_filter = $this->data->filter($id, $value);
                } else {
                    $model_filter .= $this->data->addFilter('AND', $id, $value);
                }
            }
        }

        $model_filter .= $this->data->order($this->order_field);

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
     * Get the hierarchy rows
     *
     * Builds an array of rows from this hierarchy. Useful to automatically
     * define the options of a <select> item in a TIP_Form instance.
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
        } elseif (is_null($renderer = $this->_getRenderer())) {
            return null;
        }

        $rows =& $renderer->toArray();
        if (!array_key_exists($params, $rows)) {
            TIP::error("row not found ($params)");
            return null;
        }

        return $rows[$params];
    }

    /**
     * Echo the hierarchy
     *
     * Outputs the XHTML hierarchy of this instance.
     */
    protected function tagShow($params)
    {
        // Backward compatibility
        empty($params) && $params = $this->action;

        if (is_null($renderer = $this->_getRenderer($params))) {
            return null;
        }

        if ($renderer->isCurrentContainer()) {
            // If the current row is a container, don't index this page
            TIP_Application::setRobots(false, null);
        }

        return $renderer->toHtml();
    }

    /**#@-*/

    //}}}
    //{{{ Internal properties

    /**
     * The model for rows rendering
     * @var string
     * @internal
     */
    private $_model = null;

    //}}}
    //{{{ Internal methods

    private function &_getRenderer($action)
    {
        if (is_null($this->_model)) {
            $this->startDataView() && $this->endView();
        }

        if (is_null($this->_model)) {
            return null;
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
            $row['url'] = TIP::buildActionUriFromTag($url, (string) $module);
        }

        require_once 'HTML/Menu.php';
        $menu = new HTML_Menu($model);
        empty($action) || $menu->forceCurrentUrl(TIP::getRequestUri());
        $renderer =& TIP_Renderer::getMenu($this->levels);
        $menu->render($renderer, 'sitemap');
        return $renderer;
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
