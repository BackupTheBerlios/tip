<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * @package    TIP
 * @subpackage PEAR
 */

/** Html_QuickForm_Renderer default PEAR package */
require_once 'HTML/QuickForm/Renderer.php';

/**
 * TIP specific QuickForm renderer
 *
 * Return an array in the following form:
 *
 * <pre>
 *  array(
 *      'section1'             => array(
 *          'object'           => HTML_QuickForm_header,
 *          'hidden'           => boolean,
 *          'elements'         => array(
 *              'element11'    => array(
 *                  'object'   => HTML_QuickForm_element,
 *                  'group'    => boolean,
 *                  'required' => boolean,
 *                  'error'    => string
 *              ),
 *              'element12'    => array(
 *                  'object'   => HTML_QuickForm_element,
 *                  'group'    => boolean,
 *                  'required' => boolean,
 *                  'error'    => string
 *              ),
 *              ...
 *          )
 *      ),
 *      'section2'             => array(
 *          'object'           => HTML_QuickForm_header,
 *          'hidden'           => boolean,
 *          'elements'         => array(
 *              'element21'    => array(
 *                  'object'   => HTML_QuickForm_element,
 *                  'group'    => boolean,
 *                  'required' => boolean,
 *                  'error'    => string
 *              ),
 *              'element22'    => array(
 *                  'object'   => HTML_QuickForm_element,
 *                  'group'    => boolean,
 *                  'required' => boolean,
 *                  'error'    => string
 *              ),
 *              ...
 *          )
 *      ),
 *      ...
 *  );
 * </pre>
 *
 * @package    TIP
 * @subpackage PEAR
 */
class HTML_QuickForm_Renderer_Tip extends HTML_QuickForm_Renderer
{
    //{{{ Constructor/destructor

    function HTML_QuickForm_Renderer_Tip()
    {
        $this->HTML_QuickForm_Renderer();
    }

    //}}}
    //{{{ HTML_QuickForm_Renderer implementation

    /**
     * Called when visiting a form, before processing any form elements
     *
     * @param    object      An HTML_QuickForm object being visited
     * @access   public
     * @return   void
     */
    function startForm(&$form)
    {
        $this->_array = array();
        $this->_elements = null;
        $this->_in_group = false;
    }

    /**
     * Called when visiting a header element
     * @param  HTML_QuickForm_header &$header The header to be rendered
     * @access public
     */
    function renderHeader(&$header)
    {
        $name = $header->getName();
        isset($this->_array[$name]) || $this->_array[$name] = array(
            'elements' => array(),
            'hidden'   => false
        );
        $this->_array[$name]['object'] =& $header;
        $this->_elements =& $this->_array[$name]['elements'];
    }

    /**
     * Called when visiting a group, before processing any group elements
     * @param  HTML_QuickForm_group &$group    The group to be rendered
     * @param  bool                  $required Whether a group is required
     * @param  string                $error    The group error message
     * @access public
     */
    function startGroup(&$group, $required, $error)
    {
        $this->renderElement($group, $required, $error, true);
        $this->_in_group = true;
    }

    /**
     * Called when visiting a group, after processing all group elements
     * @param  HTML_QuickForm_group &$group The rendered group
     * @access public
     */
    function finishGroup(&$group)
    {
        $this->_in_group = false;
    }

    /**
     * Render an element
     * @param  HTML_QuickForm_element &$element  The element to be rendered
     * @param  bool                    $required Whether an element is required
     * @param  string                  $error    The error message
     * @param  bool                    $group    Whether the element is a group
     * @access public
     */
    function renderElement(&$element, $required, $error, $group = false)
    {
        // Don't render elements inside groups
        if ($this->_in_group) {
            return;
        }

        if ($element->getType() == 'date') {
            // Dirty hack to set the separator (there's no public API, fuck)
            $element->_separator = '&nbsp;';
            // The date is a group
            $group = true;
        }

        $name = $element->getName();
        if ($name) {
            $element->setAttribute('id', $name);
        }

        isset($this->_elements) || $this->_elements =& $this->_getHiddenElements();
        $this->_elements[$element->getName()] = array(
            'object'   => &$element,
            'group'    =>  $group,
            'required' =>  $required,
            'error'    =>  $error ? $error : ''
        );
    }

    /**
     * Render a hidden element
     * @param  HTML_QuickForm_hidden &$hidden The hidden element
     * @access public
     */
    function renderHidden(&$hidden)
    {
        $old_elements =& $this->_elements;
        $this->_elements =& $this->_getHiddenElements();
        $this->renderElement($hidden, false, null);
        unset($this->_elements);
        $this->_elements =& $old_elements;
    }

    /**
     * Called when visiting a raw HTML/text pseudo-element
     * @param  HTML_QuickForm_html &$html The html element to render
     * @access public
     */
    function renderHtml(&$html)
    {
        $this->renderElement($html, false, null);
    }

    /**
     * Returns the array generated for the form
     * @return array
     */
    function toArray()
    {
        return $this->_array;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The rendered array
     * @var array
     * @internal
     */
    var $_array = null;

    /**
     * A reference to the current element array
     * @var array
     * @internal
     */
    var $_elements = null;

    /**
     * Whether the current element is inside (true) or outside (false) a group
     * @var bool
     * @internal
     */
    var $_in_group = false;

    //}}}
    //{{{ Internal methods

    function &_getHiddenElements()
    {
        isset($this->_array['hidden']) || $this->_array['hidden'] = array(
            'object'   => null,
            'hidden'   => true,
            'elements' => array()
        );
        return $this->_array['hidden']['elements'];
    }

    //}}}
}
?>
