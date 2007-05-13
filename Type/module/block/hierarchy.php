<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Hierarchy definition file
 *
 * @package TIP
 */

/** HTML_Menu PEAR package */
require_once 'HTML/Menu.php';

/**
 * A TIP_Block with a hierarchy as data model
 *
 * @package TIP
 */
class TIP_Hierarchy extends TIP_Block
{
    /**#@+ @access private */

    var $_model = null;
    var $_tree = null;


    function _onView(&$view)
    {
        $rows = $view->rows;
        if (!is_array($rows)) {
            // No rows
            return true;
        }

        // By default, counting is enable if the '_count' field is present
        $count_on = array_key_exists('_count', reset($rows));
        if ($count_on) {
            $total_count =& $view->summaries['TOTAL_COUNT'];
            $total_count = 0;
        }

        $base_action = TIP::getScriptURI() . '?';
        $action = $this->getOption('action');
        if ($action) {
            // Action specified: prepend the root URL
            $action = TIP::buildUrl($action);
        } else {
            // No action specified: construct the default action (browse)
            $id = $this->getId();
            $action = $base_action . 'module=' . substr($id, 0, strrpos($id, '_')) . '&amp;action=browse&amp;group=';
        }

        $primary_key = $this->data->getPrimaryKey();
        $this->_tree  = array();
        foreach (array_keys($rows) as $id) {
            $row =& $rows[$id];
            isset($row['CLASS']) || $row['CLASS'] = 'item';
            isset($row['url']) || $row['url'] = @$row['action'] ? $base_action . $row['action'] : $action . $id;
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
                $this->_tree[$id] =& $row;
            }
        }

        $this->_model =& new HTML_Menu($this->_tree);
        $this->_model->forceCurrentUrl(htmlentities(TIP::getRequestURI()));
        return true;
    }

    function _buildRows($nodes, $parents = array())
    {
        foreach ($nodes as $id => $node) {
            $hierarchy = $parents;
            $hierarchy[] = $node['title'];
            if (@is_array($node['sub'])) {
                $this->_buildRows($node['sub'], $hierarchy);
            } else {
                $GLOBALS['_TIP_ARRAY'][$id] = $hierarchy;
            }
        }
    }

    /**#@-*/


    /**#@+ @access protected */

    function TIP_Hierarchy($id)
    {
        $this->TIP_Block($id);
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Echo the hierarchy of the master block
     *
     * $params is the base URL of the action to execute when selecting an item:
     * TIP_Hierarchy will append the id of the item to this URL. Leave it empty
     * to provide the default 'browse' action on the guessed master module.
     *
     * Outputs the DHTML hierarchy of the specified block.
     */
    function commandShow($params)
    {
        return $this->show();
    }

    /**#@-*/

    /**#@-*/


    /**#@+ @access public */

    function& startView($filter = null)
    {
        $model_filter = $this->data->order('order');

        if (empty($filter)) {
            // Force an empty select to be sorted by the order field, so this
            // query can also be used to build the hierarchy model
            $filter = $model_filter;
        } elseif ($filter != $model_filter) {
            // No way to use this query to build the model
            return TIP_Block::startView($filter);
        }

        return TIP_Block::startView($filter, array('on_view' => array(&$this, '_onView')));
    }

    /**
     * Get the hierarchy rows
     *
     * Builds an array of rows from this hierarchy. Useful to automatically
     * define the options of a <select> item in a TIP_Form instance.
     *
     * @param  string $glue The glue to join nested levels
     * @return array        The hierarchy content as array of strings
     */
    function& getRows($glue = '::')
    {
        // Force the model population
        $this->_model || $this->startView() && $this->endView();
        if (!$this->_model) {
            $fake_rows = array();
            return $fake_rows;
        }

        $GLOBALS['_TIP_ARRAY'] = array();
        $this->_buildRows($this->_tree);
        $rows =& $GLOBALS['_TIP_ARRAY'];
        unset($GLOBALS['_TIP_ARRAY']);

        foreach (array_keys($rows) as $id) {
            $rows[$id] = implode($glue, $rows[$id]);
        }

        return $rows;
    }

    function updateCount($id, $offset)
    {
        $view =& $this->startView($this->data->rowFilter($id));
        if (is_null($view)) {
            TIP::error("unable to get row $id on data " . $this->data->getId());
            return false;
        }
        $row =& $view->rowReset();
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
    function show()
    {
        static $renderer = false;

        $this->_model || $this->startView() && $this->endView();
        if (!$this->_model) {
            return false;
        }

        // The renderer is unique for all the TIP_Hierarchy instances
        if (!$renderer) {
            require_once 'HTML/Menu/TipRenderer.php';
            $renderer =& new HTML_Menu_TipRenderer();
        }

        $renderer->setId($this->getId());
        $this->_model->render($renderer, 'sitemap');
        echo $renderer->toHtml();
        return true;
    }

    /**#@-*/
}
?>
