<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Hierarchy definition file
 *
 * @package TIP
 */

/** TreeMenu PEAR package */
require_once 'HTML/TreeMenu.php';

/**
 * A TIP_Block with a hierarchy as data model
 *
 * @package TIP
 */
class TIP_Hierarchy extends TIP_Block
{
    /**#@+ @access protected */

    var $master = null;
    var $model = null;


    /**
     * Constructor
     *
     * Initializes a TIP_Hierarchy instance. This class provides a pseudo
     * block that manages a hierarchy of a real block module.
     *
     * The data path is read from
     * <code>$cfg[$master->getName()]['hierarchy_path']</code>.
     * If not specified, it defaults to
     * <code>$cfg[$master->getName()]['data_path'] . '_hierarchy'</code>.
     *
     * The data engine is the same of the master module.
     *
     * @param TIP_Block &$master The master module
     */
    function TIP_Hierarchy(&$master)
    {
        $this->TIP_Module ();

        $this->master =& $master;
        $this->model =& new HTML_TreeMenu ();

        $data_path = $master->getOption('hierarchy_path');
        if (is_null($data_path)) {
            $data_path = $master->data->path . '_hierarchy';
        }

        $data_engine = $master->data->engine->getName();
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
                $this->model->addItem($tree_node);
            }
        }

        return true;
    }

    /**#@-*/


    function TIP_Block()
    {
        $this->TIP_Module();

        $data_path = $this->getOption('data_path');
        if (is_null($data_path)) {
            $data_path = TIP::getOption('application', 'data_path') . $this->getName();
        }

        $data_engine = $this->getOption('data_engine');
        if (is_null($data_engine)) {
            $data_engine = TIP::getOption('application', 'data_engine');
        }

        $this->data =& TIP_Data::getInstance($data_path, $data_engine);
    }


    /**#@+ @access public */

    var $icon = 'generic.png';
    var $folder_icon = 'folder.png';
    var $folder_expanded_icon = 'folder-expanded.png';


    /**
     * Get a hierarchy instance
     *
     * Gets the singleton hierarchy instance of a block.
     *
     * A module is instantiated by includind its logic file found in the
     * 'logic_module_root' directory (relative to 'logic_root').
     *
     * To improve consistency, the $module name is always converted lowercase.
     * This means also the logic file name must be lowecase.
     *
     * @param TIP_Block $master The master block
     * @return TIP_Hierarchy A reference to the TIP_Hierarchy binded to $master
     * @static
     */
    function& getInstance(&$master)
    {
        $id = $master->getName();
        $instance =& TIP_Hierarchy::singleton($id);
        if (is_null($instance)) {
            $instance =& TIP_Hierarchy::singleton($id, new TIP_Hierarchy($master));
        }

        return $instance;
    }

    function toDhtml()
    {
        $this->startView('');
        $path_to_images = TIP::buildSourceURL ('shared', 'icons');
        $menu =& new HTML_TreeMenu_DHTML($this->model, array('images' => $path_to_images, 'defaultClass' => 'hierarchy'));
        $menu->printMenu();
        $this->endView();
    }

    /**#@-*/
}

?>
