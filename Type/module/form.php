<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package    TIP
 * @subpackage Module
 */

/** Html_QuickForm PEAR package */
require_once 'HTML/QuickForm.php';

/** Html_QuickForm table-less renderer package */
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

    var $_content = null;
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
    var $_tabindex = 0;

    /**
     * Process callback
     *
     * Function to call while processing the data. It takes one argument:
     * an associative array of validated values.
     *
     * By default, if the value with the primary field key is not found in the
     * array this callback will add a new row to the $data object of the binded
     * module or will update an existing row if the primary key field is found.
     *
     * @var TIP_Callback
     */
    var $_on_process = null;


    function _addAutomaticDefaults()
    {
        if (array_key_exists('_creation', $this->_fields) &&
            @empty($this->_defaults['_creation'])) {
            $this->_defaults['_creation'] = TIP::formatDate('datetime_iso8601');
        }

        if (array_key_exists('_lasthit', $this->_fields) &&
            @empty($this->_defaults['_lasthit'])) {
            $this->_defaults['_lasthit'] = TIP::formatDate('datetime_iso8601');
        }

        if (array_key_exists('_user', $this->_fields) &&
            @empty($this->_defaults['_user'])) {
            $this->_defaults['_user'] = TIP::getUserId();
        }
    }

    function _ruleUnique($value, $options)
    {
        $form_register =& TIP_Type::singleton(array('module', 'form'));
        $var = array_keys($form_register);

        // The active form is the last registered form
        $form_id = end(array_keys($form_register));
        $form =& $form_register[$form_id];
        if ($form->_command != TIP_FORM_ACTION_ADD && $form->_command != TIP_FORM_ACTION_EDIT) {
            return true;
        }

        $data =& $form->_content->data;
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

    function _converterSet(&$row, $field)
    {
        $row[$field] = implode(',', array_keys($row[$field]));
    }

    function _converterCancel(&$row, $field)
    {
        $row[$field] = @$this->_defaults[$field];
    }

    function& _widgetText(&$field)
    {
        $id = $field['id'];
        $element =& $this->_addElement('text', $id, 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        return $element;
    }

    function& _widgetPassword(&$field)
    {
        $id = $field['id'];
        $element =& $this->_addElement('password', $id, 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        if ($this->_command == TIP_FORM_ACTION_ADD || $this->_command == TIP_FORM_ACTION_EDIT) {
            $reid = 're' . $id;
            $reelement =& $this->_addElement('password', $reid, 'expand');

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
        $label = $this->getLocale('label.' . $id);
        $items = array_flip($field['choices']);
        array_walk($items, array(&$this, 'localize'), array('label.', ''));

        if (count($field['choices']) > 3) {
            // On lot of available choices, use a select menu
            $element =& $this->_form->addElement('select', $id, $label, $items);
            $element->setAttribute('class', 'expand');
        } else {
            // On few available choices, use radio button
            $group = array();
            foreach ($items as $i_value => $i_label) {
                ++ $this->_tabindex;
                $item =& $this->_form->createElement('radio', $id, $label, $i_label, $i_value, array('tabindex' => $this->_tabindex));
                $group[] =& $item;
            }
            $element =& $this->_form->addElement('group', $id, $label, $group, null, false);
        }

        return $element;
    }

    function& _widgetSet(&$field)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $items = array_flip($field['choices']);
        $default = @explode(',', $this->_defaults[$id]);
        array_walk($items, array(&$this, 'localize'), array('label.', ''));

        // Reset the defaults (a comma separated list of flags that are set):
        // the $this->_defaults[$id] variable will be defined in the foreach
        // cycle in the proper HTML_QuickForm format
        unset($this->_defaults[$id]);

        $group = array();
        foreach ($items as $i_value => $i_label) {
            $this->_defaults[$id][$i_value] = in_array($i_value, $default);
            ++ $this->_tabindex;
            $item =& $this->_form->createElement('checkbox', $i_value, $label, $i_label, array('tabindex' => $this->_tabindex));
            $group[] =& $item;
        }

        $this->_addConverter($id, 'set');
        return $this->_form->addElement('group', $id, $label, $group);
    }

    function& _widgetTextArea(&$field)
    {
        HTML_QuickForm::registerElementType('wikiarea', 'HTML/QuickForm/wikiarea.php', 'HTML_QuickForm_wikiarea');

        $id = $field['id'];
        $element =& $this->_addElement('wikiarea', $id, 'expand');

        if (array_key_exists('wiki_rules', $field)) {
            $rules = explode(',', $field['wiki_rules']);
        } else {
            $rules = null;
        }
        $element->setWiki(TIP_Renderer::getWiki($rules));
        $element->setRows('10');

        return $element;
    }

    function& _widgetDate(&$field)
    {
        HTML_QuickForm::registerRule('date', 'callback', '_ruleDate', 'TIP_Form');

        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);

        // Set the date in a format suitable for HTML_QuickForm_date
        $iso8601 = @$this->_defaults[$id];
        $timestamp = empty($iso8601) ? time() : TIP::getTimestamp($iso8601, 'iso8601');
        $this->_defaults[$id] = $timestamp;

        $field_year = date('Y', $this->_defaults[$id]);
        $this_year = date('Y');

        // $min_year > $max_year, so the year list is properly sorted in reversed
        // order
        $options = array(
            'language' => $GLOBALS[TIP_MAIN]->getOption('locale'),
            'format'   => 'dFY',
            'minYear'  => $this_year+1,
            'maxYear'  => $field_year < $this_year-5 ? $field_year : $this_year-5
        );

        ++ $this->_tabindex;
        $element =& $this->_form->addElement('date', $id, $label, $options, array('tabindex' => $this->_tabindex));
        $this->_addRule($id, 'date');
        $this->_addConverter($id, 'ISO8601');
        return $element;
    }

    function& _widgetPicture(&$field)
    {
        HTML_QuickForm::registerElementType('picture', 'HTML/QuickForm/picture.php', 'HTML_QuickForm_picture');

        $id = $field['id'];

        $element =& $this->_addElement('picture', $id);
        $element->setBasePath(TIP::buildDataPath($this->_content->getId()));
        $element->setBaseUrl(TIP::buildDataUrl($this->_content->getId()));

        // Unload the picture, if requested
        $unload_id = 'unload_' . $id;
        if (array_key_exists($unload_id, $_POST)) {
            $element->setState(QF_PICTURE_TO_UNLOAD);
        } else {
            $unload_label = $this->getLocale('label.' . $unload_id);
            $unload_element = $this->_form->createElement('checkbox', $unload_id, $unload_label, $unload_label, array('tabindex' => $this->_tabindex));
            $element->setUnloadElement($unload_element);
        }

        return $element;
    }

    function& _widgetHierarchy(&$field)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $hierarchy_id = $this->_content->getId() . '_hierarchy';
        $hierarchy =& TIP_Type::getInstance($hierarchy_id);

        // Populate the option list, prepending an empty option
        $items = array('&#160;') + $hierarchy->getRows();

        ++ $this->_tabindex;
        return $this->_form->addElement('select', $id, $label, $items, array('tabindex' => $this->_tabindex, 'class' => 'expand'));
    }

    function& _addElement($type, $id, $class = false)
    {
        $label = $this->getLocale('label.' . $id);
        ++ $this->_tabindex;
        $attributes['tabindex'] = $this->_tabindex;
        if ($class) {
            $attributes['class'] = $class;
        }
        return $this->_form->addElement($type, $id, $label, $attributes);
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
        // Add the format as context to getLocale (in case the localized message
        // will embed any format field)
        if (is_array($format)) {
            $context = $format;
        } elseif (!empty($format)) {
            $context[0] = $format;
        } else {
            $context = null;
        }
            
        $message = $this->getLocale('rule.' . $type, $context);
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
                if ($this->_form->elementExists($id) && @$this->_fields[$id]['widget'] != 'upload') {
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
        $renderer =& TIP_Renderer::getForm();
        $renderer->addStopFieldsetElements('buttons');
        $this->_form->accept($renderer);
        echo $renderer->toHtml();
    }

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes a TIP_Form instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function __construct($id, $args)
    {
        parent::__construct($id);

        foreach ($args as $key => &$value) {
            $property = '_' . $key;
            $this->$property =& $value;
        }

        // Define some needed properties
        if (!isset($this->_command)) {
            $this->_command = $this->_action;
        }
        if (!isset($this->_referer)) {
            $this->_referer = TIP::getRefererURI();
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

        HTML_QuickForm::registerRule('unique', 'callback', '_ruleUnique', 'TIP_Form');
    }

    /**
     * Build a TIP_Form identifier
     *
     * $args must be an array with the following items:
     * - $args['content']: a reference to the content instance
     *
     * A form identifier is equal to the content identifier.
     *
     * @param  array  $args The constructor arguments
     * @return string       The form identifier
     */
    function buildId($args)
    {
        return $args['content']->getId();
    }

    /**
     * Get a localized text
     *
     * Overrides the default method, always using 'form' as prefix.
     *
     * @param  string $id      The identifier
     * @param  array  $context A context associative array
     * @param  bool   $cached  Whether to perform or not a cached read
     * @return string          The requested localized text
     */
    function getLocale($id, $context = null, $cached = true)
    {
        $text = TIP::getLocale($id, 'form', $context, $cached);
        if (empty($text)) {
            $text = 'form.' . $id;
            TIP::warning("localized text not found ($text)");
        }

        return $text;
    }

    /**#@-*/


    /**#@+ @access public */

    function run()
    {
        if (is_null($this->_fields)) {
            $this->_fields = $this->_content->data->getFields();
        }

        if ($this->_action == TIP_FORM_ACTION_ADD) {
            $this->_addAutomaticDefaults();
        }

        // The localized header text is defined by the content
        $header_label = $this->_content->getLocale('header.' . $this->_command);

        // Create the interface
        $this->_form =& new HTML_QuickForm_DHTMLRulesTableless($this->_content->getId());
        // XHTML compliance
        $this->_form->removeAttribute('name');
        $this->_form->addElement('header', 'header.' . $this->_command, $header_label);
        // The label element (default header object) is buggy at least in
        // Firefox, so I provide a decent alternative
        $this->_form->addElement('html', '<h1>' . $header_label . '</h1>');
        $this->_form->addElement('hidden', 'module', $this->_content->getId());
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

        // Perform uploads (if any)
        if (is_callable(array('HTML_QuickForm_picture', 'doUploads'))) {
            HTML_QuickForm_picture::doUploads($this->_form);
        }

        // Process the form
        $referer = $this->_referer;
        if ($valid === true) {
            if (@HTTP_Session2::get('form.to_process')) {
                if ($this->_form->isSubmitted()) {
                    $this->_form->process(array(&$this, '_convert'));
                } else {
                    $this->_onProcess($this->_defaults);
                }
                HTTP_Session2::set('form.to_process', null);
            }
            $buttons = TIP_FORM_BUTTON_CLOSE;
            $referer = HTTP_Session2::get('form.referer');
            $render = $this->_valid_render;
        } elseif ($valid === false) {
            HTTP_Session2::set('form.to_process', true);
            if (!$this->_form->isSubmitted()) {
                HTTP_Session2::set('form.referer', $referer);
            } else {
                $referer = HTTP_Session2::get('form.referer');
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
            $group[] =& $this->_form->createElement('submit', null, $this->getLocale('button.submit'), array('class' => 'command'));
        }
        if ($buttons & TIP_FORM_BUTTON_RESET) {
            $group[] =& $this->_form->createElement('reset', null, $this->getLocale('button.reset'), array('class' => 'command'));
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->_command == TIP_FORM_ACTION_DELETE) {
            $group[] =& $this->_form->createElement('link', 'delete', null, $_SERVER['REQUEST_URI'] . '&process=1', $this->getLocale('button.delete'));
        }
        if ($buttons & TIP_FORM_BUTTON_CANCEL) {
            $group[] =& $this->_form->createElement('link', 'cancel', null, $referer, $this->getLocale('button.cancel'));
        }
        if ($buttons & TIP_FORM_BUTTON_CLOSE) {
            $group[] =& $this->_form->createElement('link', 'close', null, $referer, $this->getLocale('button.close'));
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->_command != TIP_FORM_ACTION_DELETE) {
            $primary_id = $this->_content->data->getPrimaryKey();
            $primary_value = $this->_form->getElementValue($primary_id);
            $url = TIP::getScriptURI() . '?module=' . $this->_content->getId() . '&action=delete';
            $url .= '&' . $primary_id . '=' . urlencode($primary_value);
            $group[] =& $this->_form->createElement('link', 'delete', null, $url, $this->getLocale('button.delete'), array('class' => 'dangerous'));
        }

        // Add the tabindex property to the buttons
        foreach (array_keys($group) as $id) {
            ++ $this->_tabindex;
            $group[$id]->setAttribute ('tabindex', $this->_tabindex);
        }

        // Add the group of buttons to the form
        $element =& $this->_form->addElement('group', 'buttons', null, $group, '');
        $element->setAttribute('class', 'command');

        // Rendering
        if ($render == TIP_FORM_RENDER_HERE) {
            $this->_render();
        } elseif ($render == TIP_FORM_RENDER_IN_CONTENT) {
            $GLOBALS[TIP_MAIN]->appendCallback(array(&$this, '_render'));
        }

        return $valid;
    }

    function process($row)
    {
        switch ($this->_action) {

        case TIP_FORM_ACTION_ADD:
            $this->_content->data->putRow($row);
            TIP::notifyInfo('done');
            break;

        case TIP_FORM_ACTION_EDIT:
            $this->_content->data->updateRow($row);
            TIP::notifyInfo('done');
            break;

        case TIP_FORM_ACTION_DELETE:
            $id = $row[$this->_content->data->getPrimaryKey()];
            $this->_content->data->deleteRow($id);
            TIP::notifyInfo('done');
            break;
        }
    }

    /**#@-*/
}
?>
