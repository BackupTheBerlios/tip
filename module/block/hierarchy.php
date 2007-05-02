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
        $total_count =& $view->summaries['TOTAL_COUNT'];
        $this->_tree  = array();
        $nodes = array();
        $total_count = 0;
        $action = $this->getOption('action');
        if ($action) {
            // Prepend the root URL
            $action = TIP::buildUrl($action);
        } else {
            // Provide a default action
            $id = $this->getId();
            $master = substr($id, 0, strrpos($id, '_'));
            $action = TIP::getScriptURI() . '?module=' . $master . '&action=browse&group=';
        }

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

            if (@empty($node['url'])) {
                if (@empty($node['action'])) {
                    // No action defined: build a default URL
                    $node['url'] = $action . $id;
                } else {
                    // Action specified: define the URL accordling
                    $node['url'] = TIP::getScriptURI() . '?' . $node['action'];
                }
            }

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
        $this->_model->forceCurrentUrl(htmlentities(TIP::getRequestURI()));
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
