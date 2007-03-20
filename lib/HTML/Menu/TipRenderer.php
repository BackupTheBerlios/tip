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

    function _newSubmenuId($level)
    {
        ++ $level;
        isset($this->_submenu[$level]) || $this->_submenu[$level] = 0;
        ++ $this->_submenu[$level];
        return $this->_id . '_' . $level . '_' . $this->_submenu[$level];
    }

    function _getSubmenuId($level)
    {
        if (empty($this->_submenu[$level])) {
            return false;
        }
        return $this->_id . '_' . $level . '_' . $this->_submenu[$level];
    }


    function setId($id)
    {
        $this->_id = $id;
        $this->_submenu = null;
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
        $content = isset($node['COUNT']) ? "<var>$node[COUNT]</var>" : '';
        $content .= $node['title'];

        switch ($type) {

        case HTML_MENU_ENTRY_INACTIVE:
            if (array_key_exists('sub', $node)) {
                $id = $this->_newSubmenuId($level);
                @$this->_html[$level] .= "\n";
                $attributes = "class=\"folder\" href=\"javascript:switchHierarchy('$id')\"";
            } else {
                $attributes = "href=\"$node[url]\"";
            }
            $entry = "<a $attributes>$content</a>";
            break;

        case HTML_MENU_ENTRY_ACTIVEPATH:
            $entry = "<p class=\"folder_open\">$content</p>";
            break;

        case HTML_MENU_ENTRY_ACTIVE:
            $entry = "<p>$content</p>";
            break;
        }

        @$this->_html[$level] .= $entry;
    }

    function finishLevel($level)
    {
        $content = @$this->_html[$level];
        unset($this->_html[$level]);

        if ($level > 0) {
            $id = $this->_getSubmenuId($level);
            if (!$id) {
                $attributes = 'class="active"';
            } else {
                $attributes = "id=\"$id\"";
            }
            $this->_html[$level-1] .= "<div $attributes>\n$content</div>\n\n";
        } else {
            $attributes = 'class="hierarchy"';
            if (isset($this->_id)) {
                $attributes .= " id=\"$this->_id\"'";
            }
            $this->_html = "\n<div $attributes>\n$content</div>\n\n";
        }
    }

   /**
    * Returns the HTML generated for the menu
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
