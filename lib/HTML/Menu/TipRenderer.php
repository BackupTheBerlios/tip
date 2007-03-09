<?php

require_once 'HTML/Menu/Renderer.php';

/**
 * TIP specific menu renderer
 *
 * @package TIP
 */
class HTML_Menu_TipRenderer extends HTML_Menu_Renderer
{
    var $_id = null;
    var $_submenu = null;

    /**
     * Generated HTML for the menu
     * @var string
     */
    var $_html = '';

    function _pushSubmenu($level)
    {
        ++ $level;
        isset($this->_submenu[$level]) || $this->_submenu[$level] = 0;
        ++ $this->_submenu[$level];
        return $this->_id . '_' . $level . '_' . $this->_submenu[$level];
    }

    function _popSubmenu($level)
    {
        $id = $this->_id . '_' . $level . '_' . $this->_submenu[$level];
        //-- $this->_submenu[$level];
        return $id;
    }


    function setId($id)
    {
        $this->_id = $id;
        $this->_sub_id = 0;
    }

    function setMenuType($menuType)
    {
        if ('tree' == $menuType || 'sitemap' == $menuType) {
            $this->_menuType = $menuType;
        } else {
            require_once 'PEAR.php';
            return PEAR::raiseError("HTML_Menu_TipRenderer: unable to render '$menuType' type menu");
        }
    }


    function renderEntry($node, $level, $type)
    {
        switch ($type) {

        case HTML_MENU_ENTRY_INACTIVE:
            if (array_key_exists('sub', $node)) {
                $id = $this->_pushSubmenu($level);
                $callback = "javascript:switchHierarchy('$id')";
                $entry = '<a class="folder" href="' . $callback . '"><var>' . $node['COUNT'] . '</var>' . $node['title'] . '</a>';
            } else {
                $entry = "<a href=\"$node[url]\"><var>$node[COUNT]</var>$node[title]</a>";
            }
            break;

        case HTML_MENU_ENTRY_ACTIVEPATH:
            $entry = "<li class=\"folder_active\"><div>$node[COUNT]</div><strong>$node[title]</strong></li>";
            break;

        case HTML_MENU_ENTRY_ACTIVE:
            $entry = "<li class=\"item_active\"><div>$node[COUNT]</div><strong>$node[title]</strong></li>";
            break;
        }

        @$this->_html[$level] .= $entry;
    }

    function finishLevel($level)
    {
        if ($level > 0) {
            $id = $this->_popSubmenu($level);
            $this->_html[$level-1] .= "<div id=\"$id\">" . @$this->_html[$level] . '</div>';
        } else {
            $attributes = 'class="hierarchy"';
            if (isset($this->_id)) {
                $attributes .= ' id="' . $this->_id . '"';
            }
            $this->_html = '<div ' . $attributes . '>' . @$this->_html[0] . '</div>';
        }

        unset($this->_html[$level]);
    }

   /**
    * returns the HTML generated for the menu
    *
    * @access public
    * @return string
    */
    function toHtml()
    {
        return $this->_html;
    }
}

?>
