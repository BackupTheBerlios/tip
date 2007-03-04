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
    /**#@+ @access protected */

    var $_model = null;


    /**
     * Constructor
     *
     * Initializes a TIP_Hierarchy instance. This class provides a pseudo
     * block that manages a hierarchy of a real block, called master.
     *
     * The data engine is the same used by the master block.
     *
     * @param string $block_id The id of the master block
     */
    function TIP_Hierarchy($block_id)
    {
        // The data engine is initialized as for $block_id but with the overriden
        // getDataOptions() method
        $this->_id = $block_id;
        $this->TIP_Block();
        $this->_model =& new HTML_TreeMenu();
    }

    /**
     * Overrides the data options
     *
     * The data path is defined by the 'hiearchy_path' option of the master
     * block. If not specified, it defaults to the 'data_path' of the master
     * block with '_hierarchy' appended.
     *
     * @return array The array of data options
     */
    function getDataOptions()
    {
        if (is_null($options = parent::getDataOptions())) {
            return null;
        } elseif ($path = $this->getOption('hierarchy_path')) {
            $options['path'] = $path;
        } else {
            $options['path'] .= '_hierarchy';
        }

        return $options;
    }

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_view->set(array(&$this, 'onView'));
        return $this->push($view);
    }

    function onView(&$view)
    {
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
        $this->startView('');
        $options['images'] = TIP::buildSourceURL('shared', 'icons');
        $options['defaultClass'] = 'hierarchy';
        $options['jsObjectName'] = $this->getId();
        $menu =& new HTML_TreeMenu_DHTML($this->_model, $options);
        $menu->printMenu();
        $this->endView();
    }

    /**#@-*/
}

?>
