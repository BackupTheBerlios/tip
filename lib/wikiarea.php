<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

require_once 'HTML/QuickForm/textarea.php';

/**
 * HTML class for a Text_Wiki based type field
 * 
 * @author       Nicola Fontana <ntd@users.sourceforge.net>
 * @version      1.0
 * @since        PHP4.3.0
 * @access       public
 */
class HTML_QuickForm_wikiarea extends HTML_QuickForm_textarea
{
    /**#@+ @access private */

    // {{{ properties

    /**
     * Text_Wiki instance to use
     * @var   Text_Wiki
     * @since 1.0
     */
    var $_wiki = null;
    
    /**
     * Text_Wiki renderer to use
     * @var   string
     * @since 1.0
     */
    var $_renderer = 'Xhtml';
    
    // }}}

    /**#@-*/


    /**#@+ @access public */

    // {{{ constructor
        
    /**
     * Class constructor
     * 
     * @param  string $elementName  Input field name attribute
     * @param  mixed  $elementLabel Label(s) for a field
     * @param  mixed  $attributes   Either a typical HTML attribute string or an associative array
     * @since  1.0
     */
    function HTML_QuickForm_wikiarea($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'wikiarea';
    } //end constructor
    
    // }}}
    // {{{ setWiki()

    /**
     * Sets a custom Text_Wiki instance
     * 
     * @param Text_Wiki &$wiki The new Text_Wiki instance
     * @since 1.0
     */
    function setWiki(&$wiki)
    {
        $this->_wiki =& $wiki;
    } //end func setWiki
    
    // }}}
    // {{{ getWiki()

    /**
     * Returns the active Text_Wiki instance
     * 
     * @since  1.0
     * @return Text_Wiki
     */
    function& getWiki()
    {
        return $this->_wiki;
    } //end func getWiki

    // }}}
    // {{{ setRenderer()

    /**
     * Sets a non-default Text_Wiki renderer
     * 
     * @param string $renderer The new Text_Wiki renderer
     * @since 1.0
     */
    function setRenderer($renderer)
    {
        $this->_renderer = $renderer;
    } //end func setRenderer
    
    // }}}
    // {{{ getRenderer()

    /**
     * Returns the active Text_Wiki renderer
     * 
     * @since  1.0
     * @return string
     */
    function getRenderer()
    {
        return $this->_renderer;
    } //end func getRenderer

    // }}}
    // {{{ getFrozenHtml()

    /**
     * Returns the frozen value of field using the specified Text_Wiki renderer
     * 
     * @since  1.0
     * @return string
     */
    function getFrozenHtml()
    {
        if (isset($this->_wiki)) {
            // Use the user provided Text_Wiki instance
            $wiki =& $this->_wiki;
        } else {
            // Use the 'Default' Text_Wiki instance
            require_once 'Text/Wiki.php';
            $wiki =& Text_Wiki::singleton('Default');
        }

        $value = $this->getValue();
        $html = $wiki->transform($value, $this->_renderer);
        if (empty($html)) {
            $html = nl2br($value)."\n";
        }
        $start_tag = '<div ' . $this->getAttributes(true) . '>';
        $end_tag = '</div>';
        return $start_tag . $html . $end_tag . $this->_getPersistantData();
    } //end func getFrozenHtml

    // }}}

    /**#@-*/

} //end class HTML_QuickForm_textarea

?>
