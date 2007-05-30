<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

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
    private $_html = null;
    private $_rows = null;


    protected function __construct($id)
    {
        parent::__construct($id);
    }

    private function _render()
    {
        if (is_null($this->_html)) {
            $this->startView() && $this->endView();
        }

        return !empty($this->_html);
    }

    public function _onView(&$view)
    {
        $rows = $view->getRows();
        if (empty($rows)) {
            $this->_html = '';
            $this->_rows = array();
            return true;
        }

        // By default, counting is enable if the '_count' field is present
        $count_on = array_key_exists('_count', reset($rows));
        if ($count_on) {
            $total_count = 0;
        }

        $base_action = TIP::getScriptURI();
        $action = $this->getOption('action');
        if ($action) {
            // Action specified: prepend the root URL
            $action = TIP::buildUrl($action);
        } else {
            // No action specified: construct the default action (browse)
            $id = $this->getId();
            $action = $base_action . '?module=' . substr($id, 0, strrpos($id, '_')) . '&amp;action=browse&amp;group=';
        }

        $primary_key = $this->data->getPrimaryKey();
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
            if ($count_on) {
                isset($row['COUNT']) || $row['COUNT'] = 0;
                $count = $row['_count'];
                $row['COUNT'] += $count;
                $total_count += $count;
            }

            if ($row['parent']) {
                while ($parent_id = $row['parent']) {
                    $parent =& $rows[$parent_id];
                    $parent['CLASS'] = 'folder';
                    $parent['sub'][$row[$primary_key]] =& $row;
                    if ($count_on) {
                        isset($parent['COUNT']) || $parent['COUNT'] = 0;
                        $parent['COUNT'] += $count;
                    }
                    $row =& $parent;
                }
            } else {
                $tree[$id] =& $row;
            }
        }

        if ($count_on) {
            $view->setSummary('TOTAL_COUNT', $total_count);
        }

        require_once 'HTML/Menu.php';
        $model =& new HTML_Menu($tree);
        $model->forceCurrentUrl(htmlspecialchars(TIP::getRequestURI()));

        $renderer =& TIP_Renderer::getMenu($this->getId());
        $model->render($renderer, 'sitemap');
        $this->_html = $renderer->toHtml();
        $this->_rows = $renderer->toArray();
        return true;
    }

    /**
     * Echo the hierarchy
     *
     * Outputs the DHTML hierarchy of this instance.
     *
     * @param  string $params Not used
     * @return bool           true on success or false on errors
     */
    protected function commandShow($params)
    {
        if (!$this->_render()) {
            return false;
        }

        echo $this->_html;
        return true;
    }

    public function& startView($filter = null)
    {
        $model_filter = $this->data->order('order');

        if (empty($filter)) {
            // Force an empty select to be sorted by the order field, so this
            // query can also be used to build the hierarchy model
            $filter = $model_filter;
        } elseif ($filter != $model_filter) {
            // No way to use this query to build the model
            return parent::startView($filter);
        }

        return parent::startView($filter, array('on_view' => array(&$this, '_onView')));
    }

    public function updateCount($id, $offset)
    {
        $view =& $this->startView($this->data->rowFilter($id));
        if (is_null($view)) {
            TIP::error("unable to get row $id on data " . $this->data->getId());
            return false;
        }
        $row =& $view->current();
        $this->endView();
        if (is_null($row)) {
            TIP::error("row $id not found in " . $this->data->getId());
            return false;
        }
        $old_row = $row;
        $row['_count'] += $offset;
        if (!$this->data->updateRow($row, $old_row)) {
            TIP::error("no way to update comments counter on row $id in " . $this->data->getId());
            return false;
        }

        return true;
    }

    /**
     * Render a DHTML hierarchy
     *
     * Renders this hierarchy in a DHTML form.
     *
     * @return true on success or false on errors
     */
    public function &getHtml()
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
     * @return array The hierarchy content as array of strings
     */
    public function &getRows()
    {
        $this->_render();
        return $this->_rows;
    }
}
?>
