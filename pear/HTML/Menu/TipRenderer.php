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
    //{{{ properties

    /**
     * Text to use to connect different levels in row rendering
     * @var string
     */
    var $_glue = '::';

    /**
     * Number of indentation levels to keep online (null for all)
     * @var int
     */
    var $_levels = null;

    /**
     * Rows render mode
     *
     * How to render in rows (it works only for toArray() method).
     *
     * @var int
     */
    var $_array_mode = 2;

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
     * Internal name stack
     * @var array
     * @internal
     */
    var $_name_stack = array();

    //}}}
    //{{{ setGlue()

    /**
     * Set the glue used in rendering toArray()
     *
     * @param  string $glue The new glue
     * @access public
     */
    function setGlue($glue)
    {
        $this->_glue = $glue;
    } // end func setGlue

    //}}}
    //{{{ getGlue()

    /**
     * Get the glue used in rendering toArray()
     *
     * @return string The current glue
     * @access public
     */
    function getGlue()
    {
        return $this->_glue;
    } // end func getGlue

    //}}}
    //{{{ setLevels()

    /**
     * Set the levels to keep online
     *
     * @param  string $levels The level number
     * @access public
     */
    function setLevels($levels)
    {
        $this->_levels = $levels;
    } // end func setLevels

    //}}}
    //{{{ getLevels()

    /**
     * Get the levels to keep online
     *
     * @return string The current levels
     * @access public
     */
    function getLevels()
    {
        return $this->_levels;
    } // end func getLevels

    //}}}
    //{{{ setArrayMode()

    /**
     * Set the renderer mode of toArray()
     *
     * Available modes are:
     *
     * 0 = render none
     * 1 = render only container nodes
     * 2 = render only leaf nodes
     * 3 = render every node
     *
     * @param  int    $mode The new array mode
     * @access public
     */
    function setArrayMode($mode)
    {
        $this->_array_mode = $mode;
    } // end func setArrayMode

    //}}}
    //{{{ getArrayMode()

    /**
     * Get the current renderer mode of toArray()
     *
     * @return int    The current array mode
     * @access public
     */

    function getArrayMode()
    {
        return $this->_array_mode;
    } // end func getArrayMode

    //}}}
    //{{{ overriden virtual functions

    function setMenuType($type)
    {
        if ('tree' == $type || 'sitemap' == $type || 'rows' == $type) {
            $this->_menuType = $type;
            $this->_html = '';
            $this->_rows = array();
            $this->_name_stack = array();
        } else {
            require_once 'PEAR.php';
            return PEAR::raiseError("HTML_Menu_TipRenderer: unable to render '$type' type menu");
        }
    }

    function renderEntry($node, $level, $type)
    {
        $this->_name_stack[$level] = $node['title'];
        $name = implode($this->_glue, $this->_name_stack);
        $is_container = array_key_exists('sub', $node);

        // Array rendering
        if ($is_container && ($this->_array_mode & 1) ||
            !$is_container && ($this->_array_mode & 2)) {
            $this->_rows[] = $name;
        }

        // Check for maximum level
        $is_deep = isset($this->_levels) && $level >= $this->_levels;
        $is_active = $type != HTML_MENU_ENTRY_INACTIVE;
        if ($is_deep && $level > $this->_levels+1 ||
            $is_deep && $level > $this->_levels && !$is_active &&
            !end($this->_html[$level-1]['active'])) {
            return;
        }

        // Generate the XHTML content
        $content = $node['title'];
        $indent = str_repeat('    ', $level);
        isset($node['ITEMS']) && $content .= ' (' . $node['ITEMS'] . ')';
        isset($node['COUNT']) && $content = ' <var>' . $node['COUNT'] . ' </var>' . $content;
        $is_active && $content = '<strong>' . $content . '</strong>';

        if ($is_container) {
            // <em> for containers
            $content = '<em>' . $content . '</em>';
            $is_deep && !$is_active && $content = '<a href="' . $node['url'] . '">' . $content . '</a>';
        } else {
            // <span> for normal entries
            $content = '<a href="' . $node['url'] . '"><span>' . $content . '</span></a>';
        }

        $content = "\n$indent  <li>$content";

        // Close previous <li> for non-starting entries
        isset($this->_html[$level]) && $content = '</li>' . $content;

        if (isset($this->_html[$level])) {
            $this->_html[$level]['active'][] = $is_active;
            $this->_html[$level]['content'] .= $content;
        } else {
            $this->_html[$level] = array(
                'active'  => array($is_active),
                'content' => $content
            );
        }
    }

    function finishLevel($level)
    {
        if (isset($this->_html[$level])) {
            $is_active = in_array(true, $this->_html[$level]['active']);
            $content =& $this->_html[$level]['content'];
            unset($this->_html[$level]);
        } else {
            $content = '';
        }

        unset($this->_name_stack[$level]);
        if (empty($content)) {
            return;
        }

        // Close the last <li>
        $content .= '</li>';

        if ($level > 0) {
            $indent = str_repeat('    ', $level-1);
            $ul = $is_active ? '<ul class="opened">' : '<ul>';
            $this->_html[$level-1]['content'] .= "\n$indent    $ul$content\n$indent    </ul>\n$indent  ";
        } else {
            $this->_html =& $content;
        }
    }

    function toHtml()
    {
        return $this->_html;
    }

    function toArray()
    {
        return $this->_rows;
    }

    //}}}
}
?>
