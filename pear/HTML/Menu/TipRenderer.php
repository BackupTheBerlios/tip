<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package    TIP
 * @subpackage PEAR
 */

/** Html_Menu_Renderer PEAR package */
require_once 'HTML/Menu/Renderer.php';

/**
 * TIP specific menu renderer
 *
 * @package    TIP
 * @subpackage PEAR
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

    function _pushId($level)
    {
        if (isset($this->_submenu[$level])) {
            ++ $this->_submenu[$level];
        } else {
            $this->_submenu[$level] = 0;
        }
        return $this->_id . '_' . $level . '_' . $this->_submenu[$level];
    }

    function _popId($level)
    {
        if (!isset($this->_submenu[$level])) {
            return false;
        }
        if (isset($this->_submenu[$level+1])) {
            unset($this->_submenu[$level+1]);
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
        $is_active = $type != HTML_MENU_ENTRY_INACTIVE;
        $is_container = array_key_exists('sub', $node);

        if ($is_container) {
            $class = $is_active ? 'folder_active_open' : 'folder';
            $href = 'javascript:switchHierarchy(\'' . $this->_pushId($level) . '\')';
        } else {
            $class = $is_active ? 'active' : null;
            $href = $node['url'];
        }

        $content = "\n" . str_repeat('  ', $level) . '<a ';
        if (isset($class)) {
            $content .= 'class="' . $class .'" ';
        }
        $content .= 'href="' . $href .'">';
        if (isset($node['COUNT'])) {
            $content .= '<var>' . $node['COUNT'] . '</var>';
        }
        $content .= $node['title'] . '</a>';

        if (isset($this->_html[$level])) {
            $this->_html[$level]['active'] = $this->_html[$level]['active'] || $is_active;
            $this->_html[$level]['content'] .= $content;
        } else {
            $this->_html[$level] = array(
                'active'  => $is_active,
                'content' => $content
            );
        }
    }

    function finishLevel($level)
    {
        $is_active = $this->_html[$level]['active'];
        $content = $this->_html[$level]['content'];
        unset($this->_html[$level]);

        if ($level > 0) {
            $attributes = 'id="' . $this->_popId($level-1) . '"';
            if ($is_active) {
                $attributes .= ' class="active"';
            }
            $this->_html[$level-1]['content'] .= "<div $attributes>$content\n</div>";
        } else {
            $attributes = 'class="hierarchy"';
            if (isset($this->_id)) {
                $attributes .= ' id="' . $this->_id . '"';
            }
            $this->_html = "<div $attributes>$content\n</div>";
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
