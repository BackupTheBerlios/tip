<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Hierarchy definition file
 *
 * @package TIP
 */

/** HTML_TreeMenu PEAR package */
require_once 'HTML/TreeMenu.php';

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


    function _populateModel(&$view)
    {
        if (isset($this->_model)) {
            return true;
        }

        $this->_model =& new HTML_TreeMenu();
        $rows =& $view->rows;
        $view->summaries['TOTAL_COUNT'] = 0;
        $total_count =& $view->summaries['TOTAL_COUNT'];
        $module = $this->getId();
        $action = $this->action;
        $base_url = TIP::buildUrl("index.php?module=$module&amp;action=$action&amp;id=");

        foreach (array_keys($rows) as $id) {
            $row =& $rows[$id];
            $parent_id = @$row['parent'];
            $options = array('text' => $row['title'], 'link' => $base_url.$id, 'icon' => $this->icon);
            $tree_node =& new HTML_TreeNode($options);
            $count = @$row['_count'];
            $total_count += $count;
            $row['COUNT'] = $count;
            $row['TREE_NODE'] =& $tree_node;

            if ($parent_id) {
                if (! @is_a ($rows[$parent_id]['TREE_NODE'], 'HTML_TreeNode')) {
                    return false;
                }

                $rows[$parent_id]['COUNT'] += $count;
                $parent_tree_node =& $rows[$parent_id]['TREE_NODE'];
                $parent_tree_node->addItem($tree_node);
                $parent_tree_node->icon =& $this->folder_icon;
                $parent_tree_node->expandedIcon =& $this->folder_expanded_icon;
                $parent_tree_node->expanded = false;
                $parent_tree_node->link = '';
            } else {
                $this->_model->addItem($tree_node);
            }
        }

        return true;
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
        return $this->toDhtml();
    }

    /**#@-*/

    /**#@-*/


    /**#@+ @access public */

    var $action = 'browse';
    var $icon = 'generic.png';
    var $folder_icon = 'folder.png';
    var $folder_expanded_icon = 'folder-expanded.png';

    /**
     * Render a DHTML hierarchy
     *
     * Renders this hierarchy in a DHTML form.
     */
    function toDhtml()
    {
        $view =& $this->startView('');
        if (is_null($view)) {
            return false;
        }
        $result = $this->_populateModel($view);
        $this->endView();

        if (!$result) {
            return false;
        }

        $options['images'] = TIP::buildSourceURL('shared', 'icons');
        $options['defaultClass'] = 'hierarchy';
        $options['jsObjectName'] = $this->getId();
        $menu =& new HTML_TreeMenu_DHTML($this->_model, $options);
        $menu->printMenu();
    }

    /**#@-*/
}

?>
