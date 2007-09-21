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
    /**
     * Menu identifier
     * @var string
     */
    var $_id = null;

    /**
     * Text to use to connect different levels in row rendering
     * @var array
     */
    var $_glue = '::';

    /**
     * Generated HTML for the menu
     * @var string
     */
    var $_html = '';

    /**
     * Generated rows for the menu
     * @var string
     */
    var $_rows = array();

    /**
     * Row id
     * @var int
     * @internal
     */
    var $_row_id = 0;

    /**
     * Internal name stack
     * @var array
     * @internal
     */
    var $_name_stack = array();

    /**
     * Internal id stack
     * @var array
     * @internal
     */
    var $_id_stack = array();


    function setId($id)
    {
        $this->_id = $id;
    }

    function getId()
    {
        return $this->_id;
    }

    function setGlue($glue)
    {
        $this->_glue = $glue;
    }

    function getGlue()
    {
        return $this->_glue;
    }

    function setMenuType($type)
    {
        static $dummy_id = 0;

        if ('tree' == $type || 'sitemap' == $type || 'rows' == $type) {
            $this->_menuType = $type;
            $this->_html = '';
            $this->_rows = array();
            $this->_name_stack = array();
            $this->_id_stack = array();
            isset($this->_id) || $this->_id = $type . (++ $dummy_id);
        } else {
            require_once 'PEAR.php';
            return PEAR::raiseError("HTML_Menu_TipRenderer: unable to render '$type' type menu");
        }
    }

    function renderEntry($node, $level, $type)
    {
        static $dummy_id = 0;
        $is_active = $type != HTML_MENU_ENTRY_INACTIVE;
        $is_container = array_key_exists('sub', $node);
        $this->_row_id = isset($node['id']) ? $node['id'] : 'd' . ++$dummy_id;
        $this->_name_stack[$level] = $node['title'];
        $this->_id_stack[$level] = $this->_row_id;
        $name = implode($this->_glue, $this->_name_stack);

        if ($is_container) {
            $class = $is_active ? 'folder_active_open' : 'folder';
            $href = 'javascript:switchHierarchy(\'' . $this->_id . '_' . $this->_row_id . '\')';
        } else {
            $class = $is_active ? 'active' : null;
            $href = $node['url'];
            $this->_rows[$this->_row_id] =& $name;
        }

        $content = "\n" . str_repeat('  ', $level) . '<a ';
        if (isset($class)) {
            $content .= 'class="' . $class .'" ';
        }
        $content .= 'href="' . $href .'">';
        if (isset($node['COUNT'])) {
            $content .= '<var>' . $node['COUNT'] . '</var>';
        }
        $content .= $node['title'];
        if (isset($node['ITEMS'])) {
            $content .= ' (' . $node['ITEMS'] . ')';
        }
        $content .= '</a>';

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
        unset($this->_name_stack[$level]);
        unset($this->_id_stack[$level]);

        if ($level > 0) {
            $attributes = 'id="' . $this->_id . '_' . end($this->_id_stack) . '"';
            if ($is_active) {
                $attributes .= ' class="active"';
            }
            $this->_html[$level-1]['content'] .= "<div $attributes>$content\n</div>";
        } else {
            $attributes = 'class="hierarchy" id="' . $this->_id . '"';
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

   /**
    * Returns the array of rows generated for the menu
    *
    * @access public
    * @return array
    */
    function toArray()
    {
        return $this->_rows;
    }
}
?>
