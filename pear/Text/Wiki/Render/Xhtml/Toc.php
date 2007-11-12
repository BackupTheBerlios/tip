<?php
// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
/**
 * Toc rule end renderer for Xhtml
 *
 * PHP versions 4 and 5
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    CVS: $Id: Toc.php,v 1.9 2005/07/30 08:03:29 toggg Exp $
 * @link       http://pear.php.net/package/Text_Wiki
 */

/**
 * This class inserts a table of content in XHTML.
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Text_Wiki
 */
class Text_Wiki_Render_Xhtml_Toc extends Text_Wiki_Render {

    var $conf = array(
        'css_list' => null,
        'css_item' => null,
        'title' => '<strong>Table of Contents</strong>',
        'div_id' => 'toc',
        'use_ul' => false,
        'base_url' => '',
        'collapse' => true
    );

    var $min = 2;

    /**
    *
    * Renders a token into text matching the requested format.
    *
    * @access public
    *
    * @param array $options The "options" portion of the token (second
    * element).
    *
    * @return string The text rendered from the token options.
    *
    */

    function token($options)
    {
        // Keep track of the last level for <ul> nesting
        static $last_level = -1;

        // type, id, level, count, attr
        extract($options);

        $use_ul = $this->getConf('use_ul');

        switch ($type) {

        case 'list_start':
            $css = $this->getConf('css_list');
            $html = '';

            // collapse div within a table?
            if ($this->getConf('collapse')) {
                $html .= '<table border="0" cellspacing="0" cellpadding="0">';
                $html .= "<tr><td>\n";
            }

            // add the div, class, and id
            $html .= '<div';
            if ($css) {
                $html .= " class=\"$css\"";
            }

            $div_id = $this->getConf('div_id');
            if ($div_id) {
                $html .= " id=\"$div_id\"";
            }

            // add the title, and done
            $html .= '>';
            $html .= $this->getConf('title');

            $level = $this->min-1;
            break;

        case 'list_end':
            $html = '';
            if ($use_ul) {
                // Close all pending levels
                $last_level >= $this->min && $html .= '</li>';
                for ($l = $last_level; $l >= $this->min; --$l) {
                    $l_indent = str_repeat('    ', $l-$this->min);
                    $html .= "\n$l_indent  </ul>";
                    $l > $this->min && $html .= "\n$l_indent</li>";
                }

            }

            $html .= "\n</div>";

            if ($this->getConf('collapse')) {
                $html .= "\n</td></tr></table>";
            }

            $html .= "\n\n";
            break;

        case 'item_start':
            $indent = str_repeat('    ', $level-$this->min+1);

            if ($use_ul) {
                $html = '';
                if ($level > $last_level) {
                    // Nesting
                    for ($l = $last_level; $l < $level; ++$l) {
                        $l_indent = str_repeat('    ', $l-$this->min+1);
                        $html .= "\n$l_indent  <ul>\n$l_indent    <li>";
                    }
                } elseif ($level < $last_level) {
                    // Unnesting
                    $html .= '</li>';
                    for ($l = $last_level; $l > $level; --$l) {
                        $l_indent = str_repeat('    ', $l-$this->min);
                        $html .= "\n$l_indent  </ul>\n$l_indent</li>";
                    }
                    $html .= "\n$indent<li>";
                } else {
                    // Same level
                    $html .= "</li>\n$indent<li>";
                }
            } else {
                $css = $this->getConf('css_item');
                $pad = ($level-$this->min);

                $html = "\n$indent<div";
                isset($css) && $html .= ' class="' . $css . '"';
                $html .= ' style="margin-left: ' . $pad . 'em;">';
            }

            $html .= '<a href="' . $this->getConf('base_url') . '#' . $id . '">';
            break;

        case 'item_end':
            $html = '</a>';
            $use_ul || $html .= '</div>';
            break;
        }

        $last_level = $level;
        return $html;
    }
}
?>
