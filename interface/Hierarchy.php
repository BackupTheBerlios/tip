<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Hierarchy definition file
 *
 * @package TIP
 */

/** HTML_TreeMenu PEAR package */
require_once 'HTML/Menu.php';

/**
 * A TIP_Block with a hierarchy as data model
 *
 * @package TIP
 */
class TIP_Hierarchy extends TIP_Block
{
    /**#@+ @access private */

    var $_master_id = null;
    var $_model = null;
    var $_tree = null;

    var $_action = 'browse';
    var $_icon = null;
    var $_closed_icon = null;
    var $_opened_icon = null;


    function _onView(&$view)
    {
        if (isset($this->_model)) {
            return true;
        }

        $view->summaries['TOTAL_COUNT'] = 0;
        $total_count =& $view->summaries['TOTAL_COUNT'];
        $base_url = TIP::getRootUrl() .
            '?module=' . $this->_master_id .
            '&amp;action=' . $this->_action .
            '&amp;group=';

        $this->_tree  = array();
        $nodes = array();
        foreach ($view->rows as $id => $node) {
            if (array_key_exists($id, $nodes)) {
                $node['sub'] = @$nodes[$id]['sub'];
                $node['CLASS'] = @$nodes[$id]['CLASS'];
                if (isset($nodes[$id]['COUNT'])) {
                    $node['COUNT'] = $nodes[$id]['COUNT'];
                }
            } else {
                $node['CLASS'] = 'item';
            }

            $nodes[$id] =& $node;
            $node['url'] = $base_url . $id;
            if (array_key_exists('_count', $node)) {
                $count = $node['_count'];
                $node['COUNT'] = @$node['COUNT'] + $count;
                $total_count += $count;
            }

            if ($parent_id = @$node['parent']) {
                $last_id = $id;
                do {
                    if (!array_key_exists($parent_id, $nodes)) {
                        $nodes[$parent_id] = array();
                    }
                    $parent =& $nodes[$parent_id];
                    $parent['sub'][$last_id] =& $nodes[$last_id];
                    $parent['CLASS'] = 'folder';
                    if (isset($parent['COUNT'])) {
                        $parent['COUNT'] += $count;
                    }
                    $last_id = $parent_id;
                } while ($parent_id = @$parent['parent']);
            } else {
                $this->_tree[$id] =& $node;
            }

            unset($node);
        }

        $this->_model =& new HTML_Menu($this->_tree);
        if (isset($this->keys['CID'])) {
            $this->_model->forceCurrentIndex($this->keys['CID']);
        }

        return true;
    }

    function _buildRows($nodes, $prefix)
    {
        foreach ($nodes as $id => $node) {
            if (array_key_exists('sub', $node)) {
                $new_prefix = $prefix;
                $new_prefix[] = $node['title'];
                $this->_buildRows($node['sub'], $new_prefix);
            } else {
                $item = $prefix;
                $item[] = $node['title'];
                $GLOBALS['_TIP_ARRAY'][$id] = $item;
            }
        }
    }

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes an implementation of a TIP_Hierarchy interface.
     *
     * @param string $block_id The id of the master block
     */
    function TIP_Hierarchy($block_id)
    {
        // There is a singleton for every master block
        $this->_id = strtolower($block_id) . '_hierarchy';
        $this->_master_id = $block_id;
        $this->TIP_Block();
    }

    function getOption($option)
    {
        return @$GLOBALS['cfg'][$this->_master_id]['hierarchy'][$option];
    }

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_view->set(array(&$this, '_onView'));
        return $this->push($view);
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Echo the hierarchy of the master block
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

    function setCurrent($id)
    {
        $this->keys['CID'] = $id;
    }

    function& getModel()
    {
        if (is_null($this->_model)) {
            // Generate the model by starting a view
            $filter = $this->data->order('order');
            $this->startView($filter) && $this->endView();
        }

        return $this->_model;
    }

    function& getRows($glue = '::')
    {
        if (is_null($this->getModel())) {
            $fake_rows = array();
            return $fake_rows;
        }

        $GLOBALS['_TIP_ARRAY'] = array();
        $this->_buildRows($this->_tree, array());
        $rows =& $GLOBALS['_TIP_ARRAY'];
        unset($GLOBALS['_TIP_ARRAY']);

        foreach (array_keys($rows) as $id) {
            $rows[$id] = implode($glue, $rows[$id]);
        }

        return $rows;
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

        if (is_null($this->getModel())) {
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
