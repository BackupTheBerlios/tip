<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package    TIP
 * @subpackage Module
 */

/**
 * Html_QuickForm includes
 */
require_once 'HTML/QuickForm.php';
require_once 'HTML/QuickForm/DHTMLRulesTableless.php';

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

    var $_block = null;
    var $_fields = null;
    var $_form = null;
    var $_action = null;
    var $_command = null;
    var $_validator = null;
    var $_converter = array();
    var $_defaults = null;
    var $_validation = 'client';
    var $_referer = null;
    var $_buttons = null;
    var $_invalid_render = TIP_FORM_RENDER_IN_CONTENT;
    var $_valid_render = TIP_FORM_RENDER_IN_CONTENT;

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
    var $_on_process = null;


    function _getValidTmpFile($struct)
    {
        if (isset($struct['error']) && $struct['error'] != UPLOAD_ERR_OK) {
            return null;
        }
        $tmp_name = @$struct['tmp_name'];
        if (empty($tmp_name) || $tmp_name == 'none') {
            return null;
        }
        return $tmp_name;
    }

    function _ruleUnique($value, $options)
    {
        $form =& $GLOBALS['_TIP_FORM'];
        if ($form->_command != TIP_FORM_ACTION_ADD && $form->_command != TIP_FORM_ACTION_EDIT) {
            return true;
        }

        $data =& $form->_block->data;
        $filter = $data->filter($options, $value);
        $rows =& $data->getRows($filter);
        $valid = empty($rows);

        if (!$valid && count($rows) < 2) {
            // Check if the row edited by this form has the same primary key of
            // the found row
            $primary_key = $data->getPrimaryKey();
            $valid = @array_key_exists($form->_defaults[$primary_key], $rows);
        }

        return $valid;
    }

    function _ruleDate($value)
    {
        list($day, $month, $year) = array_values($value);
        return checkdate($month, $day, $year);
    }

    function _ruleMinImageSize($value, $options)
    {
        if (is_null($tmp_file = TIP_Form::_getValidTmpFile($value))) {
            // Yet invalid or no uploaded file found
            return true;
        }

        list($min_width, $min_height) = $options;
        list($width, $height) = getimagesize($tmp_file);
        if (empty($width) || empty($height)) {
            // getimagesize() failed to get the size
            return false;
        }

        return $width >= $min_width && $height >= $min_height;
    }

    function _ruleMaxImageSize($value, $options)
    {
        if (is_null($tmp_file = TIP_Form::_getValidTmpFile($value))) {
            // Yet invalid or no uploaded file found
            return true;
        }

        list($max_width, $max_height) = $options;
        list($width, $height) = getimagesize($tmp_file);
        if (empty($width) || empty($height)) {
            // getimagesize() failed to get the size
            return false;
        }

        return $width <= $max_width && $height <= $max_height;
    }

    function _converterTimestamp(&$row, $field)
    {
        list($day, $month, $year) = array_values($row[$field]);
        $row[$field] = mktime(0, 0, 0, $month, $day, $year);
    }

    function _converterISO8601(&$row, $field)
    {
        list($day, $month, $year) = array_values($row[$field]);
        $row[$field] = sprintf('%04d%02d%02d', $year, $month, $day);
    }

    function _converterCancel(&$row, $field)
    {
        $row[$field] = @$this->_defaults[$field];
    }

    function _converterUpload(&$row, $field)
    {
        $value =& $row[$field];
        if (is_null($tmp_file = TIP_Form::_getValidTmpFile($value))) {
            // Yet invalid or no uploaded file found
            $this->_converterCancel($row, $field);
            return;
        }

        $extension = substr($value['type'], strpos($value['type'], '/')+1);
        if (empty($extension)) {
            $extension = 'jpeg';
        }

        $path = TIP::buildDataPath($this->_block->getId());
        $id = @$row[$this->_block->data->getPrimaryKey()];
        $error = true;

        for (;;) {
            if (empty($id)) {
                // Here there is a race condition, but I want $file with the
                // specific extension provided by the mime type to avoid user
                // agents pitfalls
                $file = tempnam($path, 'tmp');
                if (empty($file) || !rename($file, $file . '.' . $extension)) {
                    break;
                }
                $file .= '.' . $extension;
                $name = basename($file);
            } else {
                // If this is a yet stored row, using the $id will make the
                // job a lot safer and cleaner
                $name = $id . '.' . $extension;
                $file = $path . DIRECTORY_SEPARATOR . $name;
            }

            $error = !move_uploaded_file($tmp_file, $file);
            break;
        }

        if ($error) {
            TIP::notifyError('upload');
            $this->_converterCancel($row, $field);
            return;
        }

        $old_name = @$this->_defaults[$field];
        $value = $name;

        if (!empty($old_name) && $old_name != $name) {
            unlink($path . DIRECTORY_SEPARATOR . $old_name);
        }
    }

    function& _widgetText(&$field)
    {
        $id = $field['id'];
        $element =& $this->_addElement('text', $id);
        $element->setAttribute('class', 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        return $element;
    }

    function& _widgetPassword(&$field)
    {
        $id = $field['id'];
        $element =& $this->_addElement('password', $id);
        $element->setAttribute('class', 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        if ($this->_command == TIP_FORM_ACTION_ADD || $this->_command == TIP_FORM_ACTION_EDIT) {
            $reid = 're' . $id;
            $reelement =& $this->_addElement('password', $reid);
            $reelement->setAttribute('class', 'expand');

            // The repetition field must have the same features of the original,
            // so the field structure is copyed
            if (!array_key_exists($reid, $this->_fields)) {
                $this->_fields[$reid] = $field;
            }

            $this->_addRule(array($reid, $id), 'compare');
            if (@array_key_exists($id, $this->_defaults) && !array_key_exists($reid, $this->_defaults)) {
                $this->_defaults[$reid] = $this->_defaults[$id];
            }
        }

        return $element;
    }

    function& _widgetEnum(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        $items = array_flip($field['choices']);
        array_walk($items, array(&$this->_block, 'localize'), array($id . '_', '_label'));

        if (count($field['choices']) > 3) {
            // On lot of available choices, use a select menu
            $element =& $this->_form->addElement('select', $id, $label, $items);
            $element->setAttribute('class', 'expand');
        } else {
            // On few available choices, use radio button
            $group = array();
            foreach ($items as $i_value => $i_label) {
                $item =& $this->_form->createElement('radio', $id, $label, $i_label, $i_value);
                $group[] =& $item;
            }
            $element =& $this->_form->addElement('group', $id, $label, $group, null, false);
        }

        return $element;
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
        HTML_QuickForm::registerElementType('wikiarea', 'HTML/QuickForm/wikiarea.php', 'HTML_QuickForm_wikiarea');

        $id = $field['id'];
        $element =& $this->_addElement('wikiarea', $id);
        $element->setAttribute('class', 'expand');

        if (array_key_exists('wiki_rules', $field)) {
            $wiki_rules = explode(',', $field['wiki_rules']);
        } else {
            $wiki_rules = null;
        }
        $element->setWiki(TIP::getWiki($wiki_rules));
        $element->setRows('10');

        return $element;
    }

    function& _widgetDate(&$field)
    {
        HTML_QuickForm::registerRule('date', 'callback', '_ruleDate', 'TIP_Form');

        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');

        // Set the date in a format suitable for HTML_QuickForm_date
        $iso8601 = @$this->_defaults[$id];
        $timestamp = empty($iso8601) ? time() : TIP::getTimestamp($iso8601, 'iso8601');
        $this->_defaults[$id] = $timestamp;

        $field_year = date('Y', $this->_defaults[$id]);
        $this_year = date('Y');

        // $min_year > $max_year so the year list is properly sorted in reversed order
        $options = array(
            'language' => TIP::getOption('application', 'locale'),
            'format'   => 'dFY',
            'minYear'  => $this_year+1,
            'maxYear'  => $field_year < $this_year-5 ? $field_year : $this_year-5
        );

        $element =& $this->_form->addElement('date', $id, $label, $options);
        $this->_addRule($id, 'date');
        $this->_addConverter($id, 'ISO8601');
        return $element;
    }

    function& _widgetFile(&$field)
    {
        HTML_QuickForm::registerRule('minimagesize', 'callback', '_ruleMinImageSize', 'TIP_Form');
        HTML_QuickForm::registerRule('maximagesize', 'callback', '_ruleMaxImageSize', 'TIP_Form');

        $id = $field['id'];
        $element =& $this->_addElement('file', $id);

        $this->_addConverter($id, 'upload');
        return $element;
    }

    function& _widgetHierarchy(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');

        $hierarchy_id = $this->_block->getId() . '_hierarchy';
        $hierarchy =& TIP_Module::getInstance($hierarchy_id);
        $items =& $hierarchy->getRows();

        $element =& $this->_form->addElement('select', $id, $label);
        $element->addOption('', '');
        $element->loadArray($items);
        $element->setAttribute('class', 'expand');

        return $element;
    }

    function& _addElement($type, $id)
    {
        $label = $this->_block->getLocale($id . '_label');
        return $this->_form->addElement($type, $id, $label);
    }

    function _addWidget($id)
    {
        if (substr($id, 0, 1) == '_' || $this->_fields[$id]['automatic']) {
            // By default, fields starting with '_' and automatic fields
            // cannot be edited, so are included as hidden (if defined)
            if (@array_key_exists($id, $this->_defaults)) {
                $this->_form->addElement('hidden', $id, $this->_defaults[$id]);
            }
        } else {
            $method = '_widget' . @$this->_fields[$id]['widget'];
            if (!method_exists($this, $method)) {
                $method = '_widgetText';
            }
            $element =& $this->$method($this->_fields[$id]);
        }
    }

    function _addRule($id, $type, $format = '')
    {
        // Add the format as context to getLocale (in case the message will
        // embed them)
        if (is_array($format)) {
            $context = $format;
        } elseif (!empty($format)) {
            $context[0] = $format;
        } else {
            $context = null;
        }
            
        $message = $this->getLocale($type, $context);
        $this->_form->addRule($id, $message, $type, $format, $this->_validation);
    }

    function _addGuessedRules($id)
    {
        if (is_numeric($this->_fields[$id]['default'])) {
            $this->_addRule($id, 'numeric');
        }
        if (@$this->_fields[$id]['category'] == 'required') {
            $this->_addRule($id, 'required');
        }
    }

    function _addCustomRules($id)
    {
        $text = @$this->_fields[$id]['rules'];
        if (empty($text)) {
            return;
        }

        $rules = explode(',', $text);
        foreach ($rules as $rule) {
            $open_brace = strpos($rule, '(');
            if ($open_brace === false) {
                $type = $rule;
                $format = '';
            } else {
                $close_brace = strrpos($rule, ')');
                if ($close_brace === false || $close_brace < $open_brace) {
                    TIP::warning("invalid custom rule for field $id ($rule)");
                    continue;
                }
                $type = substr($rule, 0, $open_brace);
                $format = substr($rule, $open_brace+1, $close_brace-$open_brace-1);
                if (strpos($format, ' ')) {
                    $format = explode(' ', $format);
                }
            }
            $this->_addRule($id, $type, $format);
        }
    }

    function _addConverter($id, $type)
    {
        $this->_converter[$id] = $type;
    }

    function _validate()
    {
        // Validate
        switch ($this->_action) {

        case TIP_FORM_ACTION_ADD:
        case TIP_FORM_ACTION_EDIT:
            foreach (array_keys($this->_fields) as $id) {
                if ($this->_form->elementExists($id)) {
                    $this->_addGuessedRules($id);
                    $this->_addCustomRules($id);
                }
            }

            if (isset($this->_validator)) {
                $this->_form->addFormRule($this->_validator);
            }

            $this->_form->applyFilter('__ALL__', 'trim');
            if ($this->_form->validate()) {
                $this->_form->freeze();
                return true;
            }
            $this->_form->setRequiredNote($this->getLocale('required_note'));
            return false;

        case TIP_FORM_ACTION_DELETE:
            $this->_form->freeze();
            return TIP::getGet('process', 'int') == 1;
        }

        $this->_form->freeze();
        return null;
    }

    function _convert(&$row)
    {
        foreach ($this->_converter as $field => $type) {
            $method = '_converter' . $type;
            $this->$method($row, $field);
        }

        $this->_onProcess($row);
    }

    function _onProcess(&$row)
    {
        if (isset($this->_on_process)) {
            call_user_func_array($this->_on_process, array(&$this, &$row));
        } else {
            $this->process($row);
        }
    }

    function _render()
    {
        require_once 'HTML/QuickForm/Renderer/Tableless.php';
        $renderer =& new HTML_QuickForm_Renderer_Tableless();
        $renderer->addStopFieldsetElements('buttons');
        $this->_form->accept($renderer);
        echo $renderer->toHtml();
    }

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes an implementation of a TIP_Form interface.
     *
     * @param string $block_id The id of the master block
     */
    function TIP_Form($block_id)
    {
        // There is a singleton for every master block
        $this->_id = $block_id . '_form';
        $this->_block =& TIP_Module::getInstance($block_id);
        $this->TIP_Module();

        $GLOBALS['_TIP_FORM'] =& $this;

        HTML_QuickForm::registerRule('unique', 'callback', '_ruleUnique', 'TIP_Form');
    }

    /**
     * Get a localized text for the TIP_Form class
     *
     * Overrides the default localizator method because the form messages
     * are commons for all the instantiation of TIP_Form, so the call to
     * TIP::getLocale() must use the 'form' constant string instead of the
     * id of this object.
     *
     * @param string $id      The text identifier
     * @param array  $context The context associative array
     * @param bool   $cached  Whether to perform or not a cached read
     * @return string The requested localized text
     */
    function getLocale($id, $context = null, $cached = true)
    {
        return TIP::getLocale($id, 'form', $context, $cached);
    }

    /**#@-*/


    /**#@+ @access public */


    function setOptions($options)
    {
        foreach (array_keys($options) as $name) {
            $property = '_' . $name;
            $this->$property =& $options[$name];
        }

        if (!isset($this->_command)) {
            $this->_command = $this->_action;
        }
        if (!isset($this->_referer)) {
            $this->_referer = TIP::getReferer();
        }

        if (!isset($this->_buttons)) {
            switch ($this->_action) {

            case TIP_FORM_ACTION_ADD:
            case TIP_FORM_ACTION_EDIT:
                $this->_buttons = TIP_FORM_BUTTON_SUBMIT|TIP_FORM_BUTTON_CANCEL;
                break;

            case TIP_FORM_ACTION_VIEW:
                $this->_buttons = TIP_FORM_BUTTON_CLOSE;
                break;

            case TIP_FORM_ACTION_DELETE:
                $this->_buttons = TIP_FORM_BUTTON_DELETE|TIP_FORM_BUTTON_CANCEL;
                break;

            default:
                $this->_buttons = TIP_FORM_BUTTON_CLOSE;
            }
        }
    }

    function run()
    {
        if (is_null($this->_fields)) {
            $this->_fields = $this->_block->data->getFields();
        }

        // Create the interface
        $this->_form =& new HTML_QuickForm_DHTMLRulesTableless($this->_block->getId());
        $this->_form->removeAttribute('name'); // XHTML compliance
        $this->_addElement('header', $this->_command . '_header');
        $this->_form->addElement('hidden', 'module', $this->_block->getId());
        $this->_form->addElement('hidden', 'action', $this->_command);
        array_walk(array_keys($this->_fields), array(&$this, '_addWidget'));

        // Set the default content
        if (is_array($this->_defaults)) {
            $defaults =& $this->_defaults;
        } else {
            $defaults = array_map(create_function('&$f', 'return $f["default"];'), $this->_fields);
        }
        $this->_form->setDefaults($defaults);

        // Validate the form
        $valid = $this->_validate();
        $action = $this->_action;
        $referer = $this->_referer;

        // Process the form
        if ($valid === true) {
            if (@HTTP_Session::get('form.to_process')) {
                if ($this->_form->isSubmitted()) {
                    $this->_form->process(array(&$this, '_convert'));
                } else {
                    $this->_onProcess($this->_defaults);
                }
                HTTP_Session::set('form.to_process', null);
            }
            $action  = TIP_FORM_ACTION_VIEW;
            $buttons = TIP_FORM_BUTTON_CLOSE;
            $referer = HTTP_Session::get('form.referer');
            $render = $this->_valid_render;
        } elseif ($valid === false) {
            HTTP_Session::set('form.to_process', true);
            if (!$this->_form->isSubmitted()) {
                HTTP_Session::set('form.referer', $referer);
            } else {
                $referer = HTTP_Session::get('form.referer');
            }
            $render = $this->_invalid_render;
        } else {
            $render = $this->_valid_render;
        }

        if ($render == TIP_FORM_RENDER_NOTHING) {
            return $valid;
        }

        // Define buttons if $this->_buttons is not set
        if (!isset($buttons)) {
            $buttons = $this->_buttons;
        }

        // Add buttons
        $group = array();
        if ($buttons & TIP_FORM_BUTTON_SUBMIT) {
            $element =& $this->_form->createElement('submit', null, $this->getLocale('submit'));
            $element->setAttribute('class', 'command');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_RESET) {
            $element =& $this->_form->createElement('reset', null, $this->getLocale('reset'));
            $element->setAttribute('class', 'command');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE) {
            $url = $_SERVER['REQUEST_URI'] . '&process=1';
            $group[] =& $this->_form->createElement('link', 'delete', null, $url, $this->getLocale('delete'));
        }
        if ($buttons & TIP_FORM_BUTTON_CANCEL) {
            $group[] =& $this->_form->createElement('link', 'cancel', null, $referer, $this->getLocale('cancel'));
        }
        if ($buttons & TIP_FORM_BUTTON_CLOSE) {
            $group[] =& $this->_form->createElement('link', 'close', null, $referer, $this->getLocale('close'));
        }
        $element =& $this->_form->addElement('group', 'buttons', null, $group);
        $element->setAttribute('class', 'command');

        // Rendering
        if ($render == TIP_FORM_RENDER_HERE) {
            $this->_render();
        } elseif ($render == TIP_FORM_RENDER_IN_CONTENT) {
            $GLOBALS[TIP_MAIN_MODULE]->appendCallback($this->callback('_render'));
        }

        return $valid;
    }

    function process($row)
    {
        switch ($this->_action) {

        case TIP_FORM_ACTION_ADD:
            $this->_block->data->putRow($row);
            TIP::notifyInfo('done');
            break;

        case TIP_FORM_ACTION_EDIT:
            $this->_block->data->updateRow($row);
            TIP::notifyInfo('done');
            break;

        case TIP_FORM_ACTION_DELETE:
            $id = $row[$this->_block->data->getPrimaryKey()];
            $this->_block->data->deleteRow($id);
            TIP::notifyInfo('done');
            break;
        }
    }

    /**#@-*/
}

?>
