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
        $this->_name_stack[$level] = $node['title'];
        $name = implode($this->_glue, $this->_name_stack);
        $is_active = $type != HTML_MENU_ENTRY_INACTIVE;

        $content = $node['title'];
        if (isset($node['ITEMS'])) {
            $content .= ' (' . $node['ITEMS'] . ')';
        }
        if (isset($node['COUNT'])) {
            $content .= ' <var>' . $node['COUNT'] . '</var>';
        }

        if ($is_active) {
            $content = '<strong>' . $content . '</strong>';
        }

        if (array_key_exists('sub', $node)) {
            // Cointainer node
            $ul = $is_active ? '<ul class="opened">' : '<ul>';
            $content = '<li><em>' . $content . '</em>' . $ul;
        } else {
            // Leaf node
            $content = '<li><a href="' . $node['url'] . '">' . $content . '</a></li>';
        }

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
            $this->_html[$level-1]['content'] .= $content . '</ul></li>';
        } else {
            $this->_html =& $content;
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
