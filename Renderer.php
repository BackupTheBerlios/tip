<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * Renderers collection
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
 */

/**
 * Collection of renderers used by TIP
 *
 * @package TIP 
 */
class TIP_Renderer
{
    //{{{ Static methods

    /**
     * Get the HTML_Menu_Renderer
     *
     * Singleton to get the HTML_Menu_Renderer instance properly configured for
     * the TIP system.
     *
     * @param  int                $levels Number of levels to keep online
     * @return HTML_Menu_Renderer         The renderer
     */
    static public function &getMenu($levels = null)
    {
        static $renderer = null;

        if (is_null($renderer)) {
            require_once 'HTML/Menu/TipRenderer.php';
            $renderer = new HTML_Menu_TipRenderer();
        }

        $renderer->setLevels($levels);
        return $renderer;
    }

    /**
     * Get the Text_Wiki renderer
     *
     * Singleton to get the Text_Wiki instance properly configured for the
     * TIP system. You can specify an array of rules to use in the $rules
     * array, or leave it undefined to use all the available rules.
     *
     * @param  array|null  $rules     The array of rules to enable
     * @param  string|null $toc_title TOC title or null to use a default value
     * @param  string|null $wiki_base Base URI for wiki links
     * @return Text_Wiki              The renderer
     */
    static public function &getWiki($rules = null, $toc_title = null, $wiki_base = '')
    {
        static $renderer = null;
        static $all_rules = array(
            'Prefilter', 'Delimiter', 'Code', 'Function', 'Html', 'Raw',
            'Include', 'Embed', 'Anchor', 'Heading', 'Toc', 'Horiz', 'Break',
            'Blockquote', 'List', 'Deflist', 'Table', 'Image', 'Phplookup',
            'Center', 'Paragraph', 'Url', 'Freelink', 'Interwiki',
            'Wikilink', 'Colortext', 'Strong', 'Bold', 'Emphasis', 'Italic',
            'Underline', 'Tt', 'Superscript', 'Subscript', 'Revise', 'Tighten'
        );
        static $base_rules = array(
            'Prefilter', 'Break', 'Paragraph', 'Tighten'
        );

        if (is_null($renderer)) {
            require_once 'Text/Wiki.php';
            isset($toc_title) || $toc_title = TIP::getLocale('index', 'wiki');
            $renderer =& Text_Wiki::singleton('Default');
            $renderer->disable = array();
            $renderer->setFormatConf('Xhtml', 'charset', 'UTF-8');
            /* According to the following comment:
             * http://php.net/manual/function.htmlentities.php#78509
             * "There's no sane reason to use htmlentities() instead of htmlspecialchars()"
             */
            $renderer->setFormatConf('Xhtml', 'translate', HTML_SPECIALCHARS);
            $renderer->setRenderConf('Xhtml', 'url', array(
                'target'   => '',
                'regexes'  => array('|http://picasaweb\.google\.com/lh/photo/.*|' => array('TIP_Renderer', 'picasa2Callback'))
            ));
            $renderer->setRenderConf('Xhtml', 'code', array(
                'css' => 'programlisting'
            ));

            $renderer->setRenderConf('Xhtml', 'toc', array(
                'title'    => '<p><strong>' . $toc_title . '</strong></p>',
                'div_id'   => 'idToc',
                'use_ul'   => true,
                'collapse' => false
            ));
            $renderer->setRenderConf('Xhtml', 'freelink', array(
                'pages'        => null,
                'view_url'     => $wiki_base,
                'new_text_pos' => null
            ));
        }

        if (is_array($rules)) {
            // Capitalize the $rules values
            $rules = array_map('ucfirst', array_map('strtolower', $rules));
            // Join the forced rules
            $rules = array_merge($rules, $base_rules);
            // Get the real rules to apply
            $rules = array_intersect($all_rules, $rules);
        } else {
            $rules = $base_rules;
        }

        $renderer->rules = $rules;
        return $renderer;
    }

    /**
     * Get the HTML_QuickForm_Renderer
     *
     * Singleton to get the HTML_QuickForm_Renderer instance properly
     * configured for the TIP system.
     *
     * @return HTML_QuickForm_Renderer The renderer
     */
    static public function &getForm()
    {
        static $renderer = null;

        if (is_null($renderer)) {
            require_once 'HTML/QuickForm/Renderer/Tip.php';
            $renderer = new HTML_QuickForm_Renderer_Tip();
        }

        return $renderer;
    }

    /**
     * Render to html the URI of a picasa photo
     *
     * Checks if there are registered picasa2 modules and
     * sequentially tries to render $uri by calling the
     * TIP_Picasa2::toHtml() method on every module found.
     *
     * @param  string       $uri The PicasaWeb uri
     * @return string|false      The string to render or false if not found
     */
    static public function picasa2Callback($uri)
    {
        global $cfg;
        foreach ($cfg as $id => $options) {
            if (end($options['type']) == 'picasa2') {
                $instance = TIP_Type::getInstance($id);
                $output = $instance->toHtml($uri);
                if (is_string($output))
                    return $output;
            }
        }
        return false;
    }

    //}}}
}
?>
