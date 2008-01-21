<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

require_once 'HTML/QuickForm/textarea.php';

/**
 * HTML class for a Text_Wiki based type field
 * 
 * @author  Nicola Fontana <ntd@entidi.it>
 * @version 1.0
 * @access  public
 */
class HTML_QuickForm_wikiarea extends HTML_QuickForm_textarea
{
    //{{{ Properties

    /**
     * Text_Wiki instance to use
     * @var    Text_Wiki
     * @access private
     * @since  1.0
     */
    var $_wiki = null;
    
    /**
     * Text_Wiki renderer to use
     * @var    string
     * @access private
     * @since  1.0
     */
    var $_renderer = 'Xhtml';

    //}}}
    //{{{ Constructor/destructor
        
    /**
     * Class constructor
     * 
     * @param  string $elementName  Input field name attribute
     * @param  mixed  $elementLabel Label(s) for a field
     * @param  mixed  $attributes   Either a typical HTML attribute string or an associative array
     * @access public
     * @since  1.0
     */
    function HTML_QuickForm_wikiarea($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_textarea::HTML_QuickForm_textarea($elementName, $elementLabel, $attributes);
    }
    
    //}}}
    //{{{ Methods

    /**
     * Sets a custom Text_Wiki instance
     * 
     * @param  Text_Wiki &$wiki The new Text_Wiki instance
     * @access public
     * @since  1.0
     */
    function setWiki(&$wiki)
    {
        $this->_wiki =& $wiki;
    }
    
    /**
     * Returns the active Text_Wiki instance
     * @return Text_Wiki
     * @access public
     * @since  1.0
     */
    function& getWiki()
    {
        return $this->_wiki;
    }

    /**
     * Sets a non-default Text_Wiki renderer
     * @param  string $renderer The Text_Wiki renderer to use
     * @access public
     * @since  1.0
     */
    function setRenderer($renderer)
    {
        $this->_renderer = $renderer;
    }
    
    /**
     * Return the active Text_Wiki renderer
     * @return string The text wiki renderer
     * @access public
     * @since  1.0
     */
    function getRenderer()
    {
        return $this->_renderer;
    }

    //}}}
    //{{{ Implementation

    /**
     * Return the frozen value of field using the specified Text_Wiki renderer
     * @return string
     * @since  1.0
     * @access public
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
        return $html . $this->_getPersistantData();
    }

    //}}}
}
?>
