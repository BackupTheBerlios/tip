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

    var $_block = null;
    var $_model = null;


    /**
     * Constructor
     *
     * Initializes a TIP_Hierarchy instance. This class provides a pseudo
     * block that manages a hierarchy of a real block, called master.
     *
     * The data path is read from
     * <code>$cfg[masterblockname]['hierarchy_path']</code>.
     * If not specified, it defaults to
     * <code>$cfg[masterblockname]['data_path'] . '_hierarchy'</code>.
     *
     * The data engine is the same used by the master block.
     *
     * @param TIP_Block &$block The master block
     */
    function TIP_Hierarchy(&$block)
    {
        /* The data engine is initialized here, so no needs to call the
         * TIP_Block constructor */
        $this->TIP_Block ();

        $this->_block =& $block;
        $this->_model =& new HTML_TreeMenu ();

        if (is_null($data_path = $block->getOption('hierarchy_path')) &&
            is_null($data_path = $block->data->path . '_hierarchy') ||
            is_null($data_engine = $block->getOption('data_engine')) &&
            is_null($data_engine = TIP::getOption('application', 'data_engine'))) {
            return;
        }

        $this->data =& TIP_Data::getInstance($data_path, $data_engine);
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

        foreach (array_keys ($rows) as $id) {
            $row =& $rows[$id];
            $parent_id = @$row['parent'];
            $tree_node =& new HTML_TreeNode (array ('text' => $row['title']));
            $tree_node->icon =& $this->icon;
            $tree_node->setOption ('link', 'http://www.bresciapoint.local/');
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

    var $icon = 'generic.png';
    var $folder_icon = 'folder.png';
    var $folder_expanded_icon = 'folder-expanded.png';


    /**
     * Get a hierarchy instance
     *
     * Gets the singleton hierarchy instance of a block. Every block can have
     * only one hierarchy block.
     *
     * @param TIP_Block $block The master block
     * @return TIP_Hierarchy A reference to the TIP_Hierarchy binded to $block
     * @static
     */
    function& getInstance(&$block)
    {
        $id = $block->getName();
        $instance =& TIP_Hierarchy::singleton($id);
        if (is_null($instance)) {
            $instance =& TIP_Hierarchy::singleton($id, new TIP_Hierarchy($block));
        }

        return $instance;
    }

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
        $options['jsObjectName'] = $this->_block->getName();
        $menu =& new HTML_TreeMenu_DHTML($this->_model, $options);
        $menu->printMenu();
        $this->endView();
    }

    /**#@-*/
}

?>
