<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP specific renderers
 * @package TIP
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
            $renderer =& new HTML_Menu_TipRenderer();
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
     * @param  array|null $rules The array of rules to enable
     * @return Text_Wiki         The renderer
     */
    static public function &getWiki($rules = null)
    {
        static $renderer = null;
        static $all_rules = array(
            'Prefilter', 'Delimiter', 'Code', 'Function', 'Html', 'Raw',
            'Include', 'Embed', 'Anchor', 'Heading', 'Toc', 'Horiz', 'Break',
            'Blockquote', 'List', 'Deflist', 'Table', 'Image', 'Phplookup',
            'Center', 'Newline', 'Paragraph', 'Url', 'Freelink', 'Interwiki',
            'Wikilink', 'Colortext', 'Strong', 'Bold', 'Emphasis', 'Italic',
            'Underline', 'Tt', 'Superscript', 'Subscript', 'Revise', 'Tighten'
        );
        static $base_rules = array(
            'Prefilter', 'Break', 'Paragraph', 'Tighten'
        );

        if (is_null($renderer)) {
            require_once 'Text/Wiki.php';
            $renderer =& Text_Wiki::singleton('Default');
            $renderer->setFormatConf('Xhtml', 'charset', 'UTF-8');
            $renderer->setFormatConf('Xhtml', 'translate', HTML_SPECIALCHARS);
            $renderer->setRenderConf('Xhtml', 'toc', array(
                'title'    => '<h2>Indice</h2>',
                'div_id'   => 'idToc',
                'use_ul'   => true,
                'collapse' => false
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
            $renderer =& new HTML_QuickForm_Renderer_Tip();
        }

        return $renderer;
    }

    //}}}
}
?>
