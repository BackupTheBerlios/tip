<?php

/**
 * TipTreeModule definition file
 * @package tip
 **/

require_once ('HTML/TreeMenu.php');

/**
 * Module with a hierarchy as data model
 **/
class TipTreeModule extends tipModule
{
  /**#@+
   * @access protected
   **/

  var $icon = 'generic.png';
  var $folder_icon = 'folder.png';
  var $folder_expanded_icon = 'folder-expanded.png';
  var $model;


  function RunCommand ($Command, &$Params)
  {
    global $APPLICATION;

    switch ($Command)
      {
      case 'menu':
	$path_to_images = $this->sourceURL ('shared', 'icons');
	if ($Params) 
	  $this->icon = $Params;
	$menu =& new HTML_TreeMenu_DHTML ($this->model, array('images' => $path_to_images, 'defaultClass' => 'tree'));
	$menu->printMenu ();
	return TRUE;
      }

    return parent::RunCommand ($Command, $Params);
  }

  function StartView ($query)
  {
    $view =& new tipView ($this, $query);
    $view->ON_ROWS->Set (array (&$this, 'onRows'));
    return $this->Push ($view);
  }
  
  function TipTreeModule ()
  {
    $this->tipModule ();
    $this->model =& new HTML_TreeMenu ();
  }

  /**#@-*/


  /**#@+
   * @access private
   **/

  function onRows (&$view)
  {
    $rows =& $view->ROWS;
    $view->SUMMARY_FIELDS['TOTAL_COUNT'] = 0;
    $total_count =& $view->SUMMARY_FIELDS['TOTAL_COUNT'];

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
	    if (! @array_key_exists ($parent_id, $rows)
		|| ! @is_a ($rows[$parent_id]['TREE_NODE'], 'HTML_TreeNode'))
	      return FALSE;

	    $rows[$parent_id]['COUNT'] += $count;
	    $parent_tree_node =& $rows[$parent_id]['TREE_NODE'];
	    $parent_tree_node->addItem ($tree_node);
	    $parent_tree_node->icon =& $this->folder_icon;
	    $parent_tree_node->expandedIcon =& $this->folder_expanded_icon;
	    $parent_tree_node->expanded = FALSE;
	    $parent_tree_node->link = '';
	} else {
	    $this->model->addItem ($tree_node);
	}
    }

    return TRUE;
  }

  /**#@-*/
}

?>
