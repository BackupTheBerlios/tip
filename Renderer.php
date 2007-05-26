<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

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
    /**
     * Get the HTML_Menu_Renderer
     *
     * Singleton to get the HTML_Menu_Renderer instance properly configured for
     * the TIP system.
     *
     * @param  string             $id The menu identifier
     * @return HTML_Menu_Renderer     The renderer
     */
    static public function &getMenu($id = null)
    {
        static $renderer = null;

        if (is_null($renderer)) {
            require_once 'HTML/Menu/TipRenderer.php';
            $renderer =& new HTML_Menu_TipRenderer();
        }

        if (isset($id)) {
            $renderer->setId($id);
        }

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
            'Prefilter', 'Heading', 'Toc', 'Horiz', 'Break', 'Blockquote', 
            'List', 'Deflist', 'Table', 'Center', 'Paragraph', 'Url',
            'Strong', 'Emphasis', 'Revise', 'Tighten'
        );
        static $base_rules = array(
            'Prefilter', 'Break', 'Paragraph', 'Tighten'
        );

        if (is_null($renderer)) {
            require_once 'Text/Wiki.php';
            $renderer =& Text_Wiki::singleton('Default');
            $renderer->setFormatConf('Xhtml', 'charset', 'UTF-8');
            $renderer->setFormatConf('Xhtml', 'translate', HTML_SPECIALCHARS);
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
            require_once 'HTML/QuickForm/Renderer/Tableless.php';
            $renderer =& new HTML_QuickForm_Renderer_Tableless();
        }

        return $renderer;
    }
}
?>
