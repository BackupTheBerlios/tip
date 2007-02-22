<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package    TIP
 * @subpackage Module
 */

/**
 * Form generator
 *
 * Manages all the forms generated by the TIP system using the QuickForm PEAR
 * package.
 *
 * @final
 * @package    TIP
 * @subpackage Module
 * @tutorial   TIP/Module/TIP_Form.cls
 */
class TIP_Form extends TIP_Module
{
    /**#@+ @access private */

    var $_form = null;
    var $_validation = 'client';
    var $_block = null;
    var $_defaults = null;
    var $_fields = null;
    var $_is_add = false;


    function TIP_Form()
    {
        $this->TIP_Module();

        $this->on_process =& $this->callback('_onProcess');
    }

    function _customizations()
    {
        HTML_QuickForm::registerElementType('wikiarea', TIP::buildLogicPath('lib', 'wikiarea.php'), 'HTML_QuickForm_wikiarea');
    }

    function& _widgetText(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        return $this->_form->addElement('text', $id, $label, array('class'=>'expand'));
    }

    function& _widgetPassword(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        $element =& $this->_form->addElement('password', $id, $label, array('class'=>'expand'));

        $reid = 're' . $id;
        $relabel = $this->_block->getLocale($reid . '_label');
        $reelement =& $this->_form->addElement('password', $reid, $relabel, array('class'=>'expand'));
        if (!array_key_exists($reid, $this->_defaults)) {
            $this->_defaults[$reid] = $this->_defaults[$id];
        }

        $this->_addRule($reid, 'required');
        if ($field['length'] > 0) {
            $reelement->setMaxLength($field['length']);
        }
        $message = $this->getLocale('repeat');
        $this->_addRule(array($reid, $id), 'compare');

        return $element;
    }

    function& _widgetEmail(&$field)
    {
        $element =& $this->_widgetText($field);
        $this->_addRule($field['id'], 'email');
        return $element;
    }

    function& _widgetEnum(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        $items = array_flip($field['choices']);
        array_walk($items, array(&$this->_block, 'localize'), array($id . '_', '_label'));

        // On lot of available choices, use a select menu
        if (count($field['choices']) > 3) {
            return $this->_form->addElement('select', $id, $label, $items, array('class'=>'expand'));
        }

        // On few available choices, use radio button
        $group = array();
        foreach ($items as $i_value => $i_label) {
            $item =& $this->_form->createElement('radio', $id, $label, $i_label, $i_value);
            $group[] =& $item;
        }
        return $this->_form->addElement('group', $id, $label, $group, null, false);
    }

    function& _widgetSet(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        $items = array_flip($field['choices']);
        array_walk($items, array(&$this->_block, 'localize'), $id . '_label');

        $group = array();
        foreach ($items as $i_value => $i_label) {
            $item =& $this->_form->createElement('advcheckbox', $id, $label, $i_label);
            $group[] =& $item;
        }

        return $this->_form->addElement('group', $id, $label, $group);
    }

    function& _widgetTextArea(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        if (array_key_exists('wiki_rules', $field)) {
            $wiki_rules = explode(',', $field['wiki_rules']);
        } else {
            $wiki_rules = null;
        }
        $element =& $this->_form->addElement('wikiarea', $id, $label, array('class'=>'expand'));
        $element->setWiki(TIP::getWiki($wiki_rules));
        $element->setRows('10');
        return $element;
    }

    function _addRule($id, $type, $format = '')
    {
        $message = $this->getLocale($type);
        $this->_form->addRule($id, $message, $type, $format, $this->_validation);
    }

    function _addCustomRules($id, $text)
    {
        $rules = explode(',', $text);
        foreach ($rules as $rule) {
            $open_brace = strpos($text, '(');
            if ($open_brace === false) {
                $type = $text;
                $format = '';
            } else {
                $close_brace = strrpos($text, ')');
                if ($close_brace === false || $close_brace < $open_brace) {
                    $this->logWarning("invalid custom rule for field $id ($rule)");
                    continue;
                }
                $type = substr($text, 0, $open_brace);
                $format = substr($text, $open_brace+1, $close_brace-$open_brace-1);
            }
            $this->_addRule($id, $type, $format);
        }
    }

    function _onProcess($row)
    {
        if ($this->_is_add) {
            // Put operation
            $this->_block->data->putRow($row);
        } else {
            // Update operation
            $this->_block->data->updateRow($row);
        }
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * Process callback
     *
     * Function to call while processing the data. It takes one argument:
     * an associative array of validated values.
     *
     * By default, if the value with the primary field key is not found in the
     * array this callback will add a new row to the $data object of the binded
     * block or will update an existing row if the primary key field is found.
     *
     * @var TIP_Callback
     */
    var $on_process = null;


    /**
     * Set the TIP_Form
     *
     * Defines some needed setting.
     *
     * @param TIP_Block &$block      The requesting block
     * @param array      $row        The default values
     * @param bool       $is_add     Is this an add form?
     * @param string     $validation Validation mode ('client' | 'server')
     * @return bool true on success or false on errors
     */
    function setForm(&$block, $row = null, $is_add = null, $validation = null)
    {
        $this->_block =& $block;
        $this->_defaults = $row;

        if (isset($is_add)) {
            $this->_is_add = $is_add;
        }
        if (isset($validation)) {
            $this->_validation = $validation;
        }
    }

    /**
     * Create a generic form
     *
     * The form is only created as data structure: no echo operations are
     * performed in this step.
     *
     * @return bool true on success or false on errors
     */
    function make()
    {
        require_once 'HTML/QuickForm.php';
        require_once 'HTML/QuickForm/DHTMLRulesTableless.php';

        $this->_customizations();
        $this->_form =& new HTML_QuickForm_DHTMLRulesTableless($this->_block->getId());
        $this->_form->removeAttribute('name'); // XHTML compliance

        $application =& $GLOBALS[TIP_MAIN_MODULE];
        $this->_fields =& $this->_block->data->getFields();
        $primary_key = $this->_block->data->primary_key;

        $header = $this->_block->getLocale($this->_is_add ? 'add_header' : 'edit_header');
        $this->_form->addElement('header', 'PageHeader', $header);
        $this->_form->addElement('hidden', 'module', $this->_block->getId());
        $this->_form->addElement('hidden', 'action', $application->keys['ACTION']);

        foreach (array_keys($this->_fields) as $id) {
            $field =& $this->_fields[$id];

            if (substr($id, 0, 1) == '_' || $field['automatic']) {
                // By default, fields starting with '_' and automatic fields
                // cannot be edited, so are included as hidden (if defined)
                if (array_key_exists($id, $this->_defaults)) {
                    $this->_form->addElement('hidden', $id, $this->_defaults[$id]);
                }
                continue;
            }

            $method = '_widget' . @$field['widget'];
            if (! method_exists($this, $method)) {
                $method = '_widgetText';
            }

            $element =& $this->$method($field);
            if (is_null($element)) {
                continue;
            }

            $maxlength = $field['length'];
            if ($maxlength > 0) {
                if (method_exists($element, 'setMaxLength')) {
                    $element->setMaxLength($maxlength);
                }
                $this->_addRule($id, 'maxlength', $field['length']);
            }

            if (is_numeric($field['default'])) {
                $this->_addRule($id, 'numeric');
            }

            if (@$field['category'] == 'required') {
                $this->_addRule($id, 'required');
            }

            if (isset($field['rules'])) {
                $this->_addCustomRules($id, $field['rules']);
            }
        }

        $this->_form->applyFilter('__ALL__', 'trim');
        return true;
    }

    /**
     * Process the form
     *
     * @return bool true on success or false on errors
     */
    function process()
    {
        if ($this->_form->validate()) {
            if (@TIP::getSession('form.to_process')) {
                $this->_form->process(array(&$this->on_process, 'go'));
                TIP::setSession('form.to_process', null);
                TIP::info('I_DONE');
            }

            return $this->view(TIP::buildUrl('index.php'));
        } else {
            TIP::setSession('form.to_process', true);

            // Add reset and submit buttons
            $group[] = $this->_form->createElement('reset', null, $this->getLocale('reset'));
            $group[] = $this->_form->createElement('submit', null, $this->getLocale('submit'));
            $this->_form->addElement('group', 'buttons', null, $group);

            if (is_array($this->_defaults)) {
                // Set the default values from the given row
                $defaults =& $this->_defaults;
            } else {
                // Set the default values with the defaults from TIP_Data
                $defaults = array_map(create_function('&$f', 'return $f["default"];'), $this->_fields);
            }

            $this->_form->setDefaults($defaults);
        }

        return true;
    }

    /**
     * Prepare the form to be viewed
     *
     * @param string $referer The link where to turn back
     * @return bool true on success or false on errors
     */
    function view($referer = null)
    {
        if (is_null($referer) && is_null($referer = $_SERVER['HTTP_REFERER'])) {
            $referer = TIP::buildUrl('index.php');
        }

        // Add the 'Close' button
        $element =& $this->_form->addElement('link', 'buttons', null, $referer);
        $element->setAttribute('class', 'command');
        $element->setText($this->getLocale('close'));

        $this->_form->freeze();
        return true;
    }

    /**
     * Render the form
     *
     * This is the final step of the TIP_Form module: the rendering.
     *
     * @return bool true on success or false on errors
     */
    function render()
    {
        require_once 'HTML/QuickForm/Renderer/Tableless.php';

        $renderer =& new HTML_QuickForm_Renderer_Tableless();
        $renderer->addStopFieldsetElements('buttons');
        $this->_form->accept($renderer);

        echo $renderer->toHtml();
        return true;
    }

    /**#@-*/
}

return 'TIP_Form';

?>
