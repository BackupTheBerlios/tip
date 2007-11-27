<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package    TIP
 * @subpackage PEAR
 */

/** Html_QuickForm_Renderer default PEAR package */
require_once 'HTML/QuickForm/Renderer.php';

/**
 * TIP specific QuickForm renderer
 *
 * @package    TIP
 * @subpackage PEAR
 */
class HTML_QuickForm_Renderer_Tip extends HTML_QuickForm_Renderer
{
    /**
     * The HTML of the form  
     * @var      string
     * @access   private
     */
    var $_html;

    /**
     * Header Template string
     * @var      string
     * @access   private
     */
    var $_headerTemplate = null;

    /**
     * Element template string
     * @var      string
     * @access   private
     */
    var $_elementTemplate = "\n  <div<!-- BEGIN error --> class=\"error\"<!-- END error -->>\n<!-- BEGIN label -->    <label><!-- BEGIN required --><strong><!-- END required -->{label}<!-- BEGIN required --></strong><!-- END required --></label><!-- END label --><!-- BEGIN error --><em>{error}</em><!-- END error -->\n    <div{required}>{element}</div>\n<!-- BEGIN comment -->    <small>{comment}</small>\n<!-- END comment -->  </div>";

    /**
     * Form template string
     * @var      string
     * @access   private
     */
    var $_formTemplate = "\n<form{attributes}>\n\n<div style=\"display: none\">\n{hidden}\n</div>\n{content}\n</form>\n";

    /**
     * Required Note template string
     * @var      string
     * @access   private
     */
    var $_requiredNoteTemplate = "\n  <strong><small>{requiredNote}</small></strong>";

    /**
     * Template used when opening a fieldset
     * @var      string
     * @access   private
     */
    var $_openFieldsetTemplate = "\n<fieldset{id}{attributes}>";

    /**
     * Template used when opening a hidden fieldset
     * (i.e. a fieldset that is opened when there is no header element)
     * @var      string
     * @access   private
     */
    var $_openHiddenFieldsetTemplate = "\n<fieldset class=\"hidden{class}\">";

    /**
     * Template used when closing a fieldset
     * @var      string
     * @access   private
     */
    var $_closeFieldsetTemplate = "\n</fieldset>\n";

    /**
     * How many fieldsets are open
     * @var      integer
     * @access   private
     */
    var $_fieldsetsOpen = 0;

    /**
     * Array of element names that indicate the end of a fieldset
     * (a new one will be opened when the next header element occurs)
     * @var      array
     * @access   private
     */
    var $_stopFieldsetElements = array();

    /**
     * Array containing the templates for customised elements
     * @var      array
     * @access   private
     */
    var $_templates = array();

    /**
     * Array containing the templates for group wraps.
     * 
     * These templates are wrapped around group elements and groups' own
     * templates wrap around them. This is set by setGroupTemplate().
     * 
     * @var      array
     * @access   private
     */
    var $_groupWraps = array();

    /**
     * Array containing the templates for elements within groups
     * @var      array
     * @access   private
     */
    var $_groupTemplates = array();

    /**
     * True if we are inside a group 
     * @var      bool
     * @access   private
     */
    var $_inGroup = false;

    /**
     * Array with HTML generated for group elements
     * @var      array
     * @access   private
     */
    var $_groupElements = array();

    /**
     * Template for an element inside a group
     * @var      string
     * @access   private
     */
    var $_groupElementTemplate = '';

    /**
     * HTML that wraps around the group elements
     * @var      string
     * @access   private
     */
    var $_groupWrap = '';

    /**
     * HTML for the current group
     * @var      string
     * @access   private
     */
    var $_groupTemplate = '';

    /**
     * Collected HTML of the hidden fields
     * @var      string
     * @access   private
     */
    var $_hiddenHtml = '';

    /**
     * Constructor
     *
     * @access public
     */
    function HTML_QuickForm_Renderer_Tableless()
    {
        $this->HTML_QuickForm_Renderer();
    } // end constructor

    /**
     * Returns the HTML generated for the form
     *
     * @access public
     * @return string
     */
    function toHtml()
    {
        return $this->_hiddenHtml . $this->_html;
    } // end func toHtml
    
    /**
     * Called when visiting a form, before processing any form elements
     *
     * @param    object      An HTML_QuickForm object being visited
     * @access   public
     * @return   void
     */
    function startForm(&$form)
    {
        $this->_fieldsetsOpen = 0;
        $this->_html = '';
        $this->_hiddenHtml = '';
    } // end func startForm

    /**
     * Called when visiting a form, after processing all form elements
     * Adds required note, form attributes, validation javascript and form content.
     * 
     * @param    object      An HTML_QuickForm object being visited
     * @access   public
     * @return   void
     */
    function finishForm(&$form)
    {
        // add a required note, if one is needed
        if (!empty($form->_required) && !$form->_freezeAll) {
            $requiredNote = $form->getRequiredNote();
            $this->_html .= str_replace('{requiredNote}', $requiredNote, $this->_requiredNoteTemplate);
        }
        // close the open fieldset
        if ($this->_fieldsetsOpen > 0) {
            $this->_html .= $this->_closeFieldsetTemplate;
            -- $this->_fieldsetsOpen;
        }
        // add form attributes and content
        $html = str_replace('{attributes}', $form->getAttributes(true), $this->_formTemplate);
        if (strpos($this->_formTemplate, '{hidden}')) {
            $html = str_replace('{hidden}', $this->_hiddenHtml, $html);
        } else {
            $this->_html .= $this->_hiddenHtml;
        }
        $this->_hiddenHtml = '';
        $this->_html = str_replace('{content}', $this->_html, $html);

        // add a validation script
        $script = $form->getValidationScript();
        empty($script) || $this->_html = $script . "\n" . $this->_html;
    } // end func finishForm

    /**
     * Called when visiting a header element
     *
     * @param    object     An HTML_QuickForm_header element being visited
     * @access   public
     * @return   void
     */
    function renderHeader(&$header)
    {
        $name = $header->getName();
        $id = empty($name) ? '' : ' id="' . $name . '"';
        if (!empty($name) && isset($this->_templates[$name])) {
            $header_html = str_replace('{header}', $header->toHtml(), $this->_templates[$name]);
        } else {
            $header_html = str_replace('{header}', $header->toHtml(), $this->_headerTemplate);
        }
        $attributes = $header->getAttributes();
        $strAttr = '';
        if (is_array($attributes)) {
            $charset = HTML_Common::charset();
            foreach ($attributes as $key => $value) {
                if ($key == 'name') {
                    continue;
                }
                $strAttr .= ' ' . $key . '="' . htmlspecialchars($value, ENT_COMPAT, $charset) . '"';
            }
        }
        if ($this->_fieldsetsOpen > 0) {
            $this->_html .= $this->_closeFieldsetTemplate;
            -- $this->_fieldsetsOpen;
        }
        $openFieldsetTemplate = str_replace('{id}', $id, $this->_openFieldsetTemplate);
        $openFieldsetTemplate = str_replace('{attributes}',
                                            $strAttr,
                                            $openFieldsetTemplate);
        $this->_html .= $openFieldsetTemplate . $header_html;
        ++ $this->_fieldsetsOpen;
    } // end func renderHeader

    /**
     * Renders an element Html
     * Called when visiting an element
     *
     * @param object     An HTML_QuickForm_element object being visited
     * @param bool       Whether an element is required
     * @param string     An error message associated with an element
     * @access public
     * @return void
     */
    function renderElement(&$element, $required, $error)
    {
        $name = $element->getName();
        // Instead of having a useless boolean, better to have something more
        $required = $required ? ' class="required"' : null;

        $this->_handleStopFieldsetElements($name);

        if (!$this->_inGroup) {
            $html = $this->_elementExpansion($element, $required, $error);
            $this->_html .= str_replace('{element}', $element->toHtml(), $html);
        } elseif (!empty($this->_groupElementTemplate)) {
            $html = $this->_tagExpansion(
                $this->_groupElementTemplate,
                array(
                    'label'    => $element->getLabel(),
                    'required' => $required
                ));
            $this->_groupElements[] = str_replace('{element}', $element->toHtml(), $html);
        } else {
            $this->_groupElements[] = $element->toHtml();
        }
    } // end func renderElement

    /**
     * Renders an hidden element
     * Called when visiting a hidden element
     * 
     * @param object     An HTML_QuickForm_hidden object being visited
     * @access public
     * @return void
     */
    function renderHidden(&$element)
    {
        if (!is_null($element->getAttribute('id'))) {
            $id = $element->getAttribute('id');
        } else {
            $id = $element->getName();
        }
        $html = $element->toHtml();
        if (!empty($id)) {
            $html = str_replace('name="' . $id,
                                'id="' . $id . '" name="' . $id,
                                $html);
        }
        $this->_hiddenHtml .= $html . "\n";
    } // end func renderHidden

    /**
     * Called when visiting a raw HTML/text pseudo-element
     * 
     * @param  HTML_QuickForm_html   element being visited
     * @access public
     * @return void
     */
    function renderHtml(&$data)
    {
        $this->_html .= $data->toHtml();
    } // end func renderHtml

    /**
     * Called when visiting a group, before processing any group elements
     *
     * @param object     An HTML_QuickForm_group object being visited
     * @param bool       Whether a group is required
     * @param string     An error message associated with a group
     * @access public
     * @return void
     */
    function startGroup(&$group, $required, $error)
    {
        $name = $group->getName();
        $this->_handleStopFieldsetElements($name);
        $this->_groupTemplate        = $this->_elementExpansion($group, null, $error);
        $this->_groupElementTemplate = empty($this->_groupTemplates[$name])? '': $this->_groupTemplates[$name];
        $this->_groupWrap            = empty($this->_groupWraps[$name])? '': $this->_groupWraps[$name];
        $this->_groupElements        = array();
        $this->_inGroup              = true;
    } // end func startGroup

    /**
     * Called when visiting a group, after processing all group elements
     *
     * @param    object      An HTML_QuickForm_group object being visited
     * @access   public
     * @return   void
     */
    function finishGroup(&$group)
    {
        $separator = $group->_separator;
        if (is_array($separator)) {
            $count = count($separator);
            $html  = '';
            for ($i = 0; $i < count($this->_groupElements); $i++) {
                $html .= (0 == $i? '': $separator[($i - 1) % $count]) . $this->_groupElements[$i];
            }
        } else {
            if (is_null($separator)) {
                $separator = '&nbsp;';
            }
            $html = implode((string)$separator, $this->_groupElements);
        }
        if (!empty($this->_groupWrap)) {
            $html = str_replace('{content}', $html, $this->_groupWrap);
        }
        if (!is_null($group->getAttribute('id'))) {
            $id = $group->getAttribute('id');
        } else {
            $id = $group->getName();
        }
        $groupTemplate = $this->_groupTemplate;

        $this->_html   .= str_replace('{element}', $html, $groupTemplate);
        $this->_inGroup = false;
    } // end func finishGroup

    /**
     * Sets element template 
     *
     * @param       string      The HTML surrounding an element 
     * @param       string      (optional) Name of the element to apply template for
     * @access      public
     * @return      void
     */
    function setElementTemplate($html, $element = null)
    {
        if (is_null($element)) {
            $this->_elementTemplate = $html;
        } else {
            $this->_templates[$element] = $html;
        }
    } // end func setElementTemplate


    /**
     * Sets template for a group wrapper 
     * 
     * This template is contained within a group-as-element template 
     * set via setTemplate() and contains group's element templates, set
     * via setGroupElementTemplate()
     *
     * @param       string      The HTML surrounding group elements
     * @param       string      Name of the group to apply template for
     * @access      public
     * @return      void
     */
    function setGroupTemplate($html, $group)
    {
        $this->_groupWraps[$group] = $html;
    } // end func setGroupTemplate

    /**
     * Sets element template for elements within a group
     *
     * @param       string      The HTML surrounding an element 
     * @param       string      Name of the group to apply template for
     * @access      public
     * @return      void
     */
    function setGroupElementTemplate($html, $group)
    {
        $this->_groupTemplates[$group] = $html;
    } // end func setGroupElementTemplate

    /**
     * Sets header template
     *
     * @param       string      The HTML surrounding the header 
     * @access      public
     * @return      void
     */
    function setHeaderTemplate($html)
    {
        $this->_headerTemplate = $html;
    } // end func setHeaderTemplate

    /**
     * Sets form template 
     *
     * @param     string    The HTML surrounding the form tags 
     * @access    public
     * @return    void
     */
    function setFormTemplate($html)
    {
        $this->_formTemplate = $html;
    } // end func setFormTemplate

    /**
     * Sets the note indicating required fields template
     *
     * @param       string      The HTML surrounding the required note 
     * @access      public
     * @return      void
     */
    function setRequiredNoteTemplate($html)
    {
        $this->_requiredNoteTemplate = $html;
    } // end func setRequiredNoteTemplate

    /**
     * Sets the template used when opening a fieldset
     *
     * @param       string      The HTML used when opening a fieldset
     * @access      public
     * @return      void
     */
    function setOpenFieldsetTemplate($html)
    {
        $this->_openFieldsetTemplate = $html;
    } // end func setOpenFieldsetTemplate

    /**
     * Sets the template used when opening a hidden fieldset
     * (i.e. a fieldset that is opened when there is no header element)
     *
     * @param       string      The HTML used when opening a hidden fieldset
     * @access      public
     * @return      void
     */
    function setOpenHiddenFieldsetTemplate($html)
    {
        $this->_openHiddenFieldsetTemplate = $html;
    } // end func setOpenHiddenFieldsetTemplate

    /**
     * Sets the template used when closing a fieldset
     *
     * @param       string      The HTML used when closing a fieldset
     * @access      public
     * @return      void
     */
    function setCloseFieldsetTemplate($html)
    {
        $this->_closeFieldsetTemplate = $html;
    } // end func setCloseFieldsetTemplate

    /**
     * Clears all the HTML out of the templates that surround notes, elements, etc.
     * Useful when you want to use addData() to create a completely custom form look
     *
     * @access  public
     * @return  void
     */
    function clearAllTemplates()
    {
        $this->setElementTemplate('{element}');
        $this->setFormTemplate("\n\t<form{attributes}>{content}\n\t</form>\n");
        $this->setRequiredNoteTemplate('');
        $this->setOpenFieldsetTemplate('');
        $this->setOpenHiddenFieldsetTemplate('');
        $this->setCloseFieldsetTemplate('');
        $this->_templates = array();
    } // end func clearAllTemplates

    /**
     * Adds one or more element names that indicate the end of a fieldset
     * (a new one will be opened when a the next header element occurs)
     *
     * @param       mixed      Element name(s) (as array or string)
     * @param       string     (optional) Class name for the fieldset(s)
     * @access      public
     * @return      void
     */
    function addStopFieldsetElements($element, $class = '')
    {
        if (is_array($element)) {
            $elements = array();
            foreach ($element as $name) {
                $elements[$name] = $class;
            }
            $this->_stopFieldsetElements = array_merge($this->_stopFieldsetElements,
                                                       $elements);
        } else {
            $this->_stopFieldsetElements[$element] = $class;
        }
    } // end func addStopFieldsetElements

    /**
     * Expands a whole element template
     *
     * @param  object &$element  An HTML_QuickForm_element object being visited
     * @param  string  $required The substitution of {required} or null
     * @param  string  $error    An error message associated with an element
     * @return string            Html for $element
     * @access private
     */
    function _elementExpansion(&$element, $required, $error)
    {
        $name = $element->getName();
        $labels = $element->getLabel();
        $html = isset($this->_templates[$name]) ? $this->_templates[$name] : $this->_elementTemplate;

        if (is_array($labels)) {
            $label = array_shift($labels);
            foreach($labels as $key => $value) {
                $tags["label_$key"] = $value;
            }
        } else {
            $label = $labels;
            $tags = array();
        }

        $tags += array(
            'label'    => $label,
            'required' => $required,
            'error'    => $error,
            'comment'  => $element->getComment()
        );

        return $this->_tagExpansion(
            isset($this->_templates[$name]) ? $this->_templates[$name] : $this->_elementTemplate,
            $tags);
    } // end func _elementExpansion

    /**
     * Low level expansion method
     *
     * @param  string $html Template html
     * @param  array  $tags Array of Tag => Value to substitute
     * @return string       Html for element
     * @access private
     */
    function _tagExpansion($html, $tags)
    {
        foreach ($tags as $tag => $value) {
            $str_needle[] = '{' . $tag . '}';
            if (is_null($value)) {
                $str_value[] = '';
                $preg_needle[] = "/<!-- BEGIN $tag -->.*<!-- END $tag -->/isU";
                $preg_value[] = '';
            } else {
                $str_value[] = $value;
                $str_needle[] = "<!-- BEGIN $tag -->";
                $str_value[] = '';
                $str_needle[] = "<!-- END $tag -->";
                $str_value[] = '';
            }
        }

        isset($preg_needle) && $html = preg_replace($preg_needle, $preg_value, $html);
        isset($str_needle) && $html = str_replace($str_needle, $str_value, $html);
        return $html;
    } // end func _tagExpansion

    /**
     * Handle element/group names that indicate the end of a group
     *
     * @param string     The name of the element or group
     * @access private
     * @return void
     */
    function _handleStopFieldsetElements($element)
    {
        // if the element/group name indicates the end of a fieldset, close
        // the fieldset
        if (   array_key_exists($element, $this->_stopFieldsetElements)
            && $this->_fieldsetsOpen > 0
           ) {
            $this->_html .= $this->_closeFieldsetTemplate;
            -- $this->_fieldsetsOpen;
        }
        // if no fieldset was opened, we need to open a hidden one here to get
        // XHTML validity
        if ($this->_fieldsetsOpen === 0) {
            $replace = '';
            if (   array_key_exists($element, $this->_stopFieldsetElements)
                && $this->_stopFieldsetElements[$element] != ''
               ) {
                $replace = ' ' . $this->_stopFieldsetElements[$element];
            }
            $this->_html .= str_replace('{class}', $replace,
                                        $this->_openHiddenFieldsetTemplate);
            ++ $this->_fieldsetsOpen;
        }
    } // end func _handleStopFieldsetElements

} // end class HTML_QuickForm_Renderer_Tip
?>
