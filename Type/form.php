<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Form definition file
 * @package TIP
 */

/** Html_QuickForm PEAR package */
require_once 'HTML/QuickForm.php';

/**
 * Form generator
 *
 * Manages all the forms generated by the TIP system using the QuickForm PEAR
 * package.
 *
 * @package    TIP
 * @subpackage Module
 * @tutorial   TIP/Module/TIP_Form.cls
 */
class TIP_Form extends TIP_Module
{
    //{{{ Properties

    /**
     * A reference to the master content instance
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The type of action this form must perform
     * @var TIP_FORM_ACTION_...
     */
    protected $action = null;

    /**
     * Action name
     *
     * Action identifier to use in localizing the title.
     *
     * For basic actions, such as 'add', 'delete' and 'edit', you can leave it
     * null: the value will default to the content of the 'action' property.
     *
     * @var string
     */
    protected $action_id = null;

    /**
     * A list of field ids to be used by the form: leave null for full automatic
     * field management
     *
     * @var array|null
     */
    protected $fields = null;

    /**
     * A sum of TIP_FORM_BUTTON_... constants: leave it null to use
     * default buttons regardling the requested action
     *
     * @var int
     */
    protected $buttons = null;

    /**
     * An associative array of default element values
     * @var array
     */
    protected $defaults = null;

    /**
     * Validation type, as described in HTML_QuickForm
     * @var string
     */
    protected $validation = 'client';

    /**
     * Validation callback
     *
     * Function to call before processing the data. It takes one argument:
     * the associative array of values to validate. This callback must return
     * true to process the record, or false to skip the processing.
     *
     * This callback can be useful to provide additional validations (other
     * than the ones provided by HTML_QuickForm) that need complex tasks.
     *
     * @var callback
     */
    protected $validator = null;

    /**
     * Process callback
     *
     * Function to call while processing the data. It takes one argument:
     * the associative array of validated values. This callback must return
     * true to process the record, or false to skip the processing.
     *
     * By default, if the value with the primary field key is not found in the
     * array this callback will add a new row to the data object of the master
     * module or will update an existing row if the primary key field is found.
     *
     * @var callback
     */
    protected $on_process = null;

    /**
     * The render mode for not-validated form
     * @var TIP_FORM_RENDER_...
     */
    protected $invalid_render = TIP_FORM_RENDER_IN_PAGE;

    /**
     * The render mode for validated form
     * @var TIP_FORM_RENDER_...
     */
    protected $valid_render = TIP_FORM_RENDER_IN_PAGE;

    /**
     * The url where turn back
     *
     * Leaves it null to use the default referer. If the action is processed,
     * all occurrences of '%lastid%' inside this string will be replaced by the
     * last id (if any).
     *
     * @var string|null
     */
    protected $referer = null;

    /**
     * The url where go on: leave it null to use the referer as default value
     *
     * Leaves it null to use the referer as default value. If the action is
     * processed, all occurrences of '%lastid%' inside this string will be
     * replaced by the last id (if any).
     *
     * @var string|null
     */
    protected $follower = null;

    //}}}
    //{{{ Constructor/destructor

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['master'], $options['action'])) {
            return false;
        }

        // Default values
        $options['id'] = $options['master']->getProperty('id');
        isset($options['action_id']) || $options['action_id'] = $options['action'];
        isset($options['referer']) || $options['referer'] = TIP::getRefererURI();
        isset($options['follower']) || $options['follower'] = $options['referer'];
        if (!isset($options['buttons'])) {
            switch ($options['action']) {

            case TIP_FORM_ACTION_ADD:
            case TIP_FORM_ACTION_EDIT:
                $options['buttons'] = TIP_FORM_BUTTON_SUBMIT|TIP_FORM_BUTTON_CANCEL;
                break;

            case TIP_FORM_ACTION_VIEW:
                $options['buttons'] = TIP_FORM_BUTTON_CLOSE;
                break;

            case TIP_FORM_ACTION_DELETE:
                $options['buttons'] = TIP_FORM_BUTTON_DELETE|TIP_FORM_BUTTON_CANCEL;
                break;

            default:
                $options['buttons'] = TIP_FORM_BUTTON_CLOSE;
            }
        }

        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Form instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
        HTML_QuickForm::registerRule('unique', 'callback', '_ruleUnique', 'TIP_Form');
        $this->_data =& $this->master->getProperty('data');
    }

    //}}}
    //{{{ Methods

    /**
     * Get a localized text
     *
     * Gets a localized text using 'form' as prefix. Furthermore, it logs a
     * warning message if the label is not found.
     *
     * @param  string $id      The identifier
     * @param  array  $context A context associative array
     * @param  bool   $cached  Whether to perform or not a cached read
     * @return string          The requested localized text
     */
    protected function getLocale($id, $context = null, $cached = true)
    {
        if (is_null($text = TIP::getLocale($id, 'form', $context, $cached))) {
            TIP::warning("localized text not found (form.$id)");
            $text = '';
        }

        return $text;
    }

    /**
     * Run the form
     *
     * Executes the requested action, accordling to the properties values set
     * in the constructor.
     *
     * @return bool|null true if the action is performed, false if not
     *                   or null on errors
     */
    public function run()
    {
        isset($this->fields) || $this->fields = $this->_data->getFields();

        if ($this->action == TIP_FORM_ACTION_ADD) {
            $this->_addAutomaticDefaults();
        }

        // The localized header text is defined by the content
        $header_label = $this->master->getLocale('header.' . $this->action_id);

        // Create the interface
        require_once 'HTML/QuickForm/DHTMLRulesTableless.php';
        $this->_form =& new HTML_QuickForm_DHTMLRulesTableless($this->id);

        // XHTML compliance
        $this->_form->removeAttribute('name');

        // The label element (default header object) is buggy at least on
        // Firefox, so I provide a decent alternative (a static <h1> element)
        $this->_form->addElement('html', '<h1>' . $header_label . '</h1>');
        $this->_form->addElement('header', 'header.' . $this->action_id, $header_label);
        $this->_form->addElement('hidden', 'module', $this->id);
        $this->_form->addElement('hidden', 'action', $this->action_id);
        array_walk(array_keys($this->fields), array(&$this, '_addWidget'));

        // Set the default content
        if (is_array($this->defaults)) {
            $defaults =& $this->defaults;
        } else {
            $defaults = array_map(create_function('&$f', 'return $f["default"];'), $this->fields);
        }
        $this->_form->setDefaults($defaults);

        // Validate the form
        $valid = $this->_validate();

        // Perform uploads (if any)
        if (is_callable(array('HTML_QuickForm_picture', 'doUploads'))) {
            HTML_QuickForm_picture::doUploads($this->_form);
        }

        // Process the form
        if ($valid === true) {
            if (HTTP_Session2::get($this->id . '.process')) {
                if ($this->_form->isSubmitted()) {
                    $this->_form->process(array(&$this, '_convert'));
                } else {
                    $this->_process($this->defaults);
                }
                HTTP_Session2::set($this->id . '.process', null);

                $last_id = $this->_data->getLastId();
                if (isset($last_id)) {
                    $this->referer = str_replace('%lastid%', $last_id, $this->referer);
                    $this->follower = str_replace('%lastid%', $this->_data->getLastId(), $this->follower);
                }
            }
            $buttons = TIP_FORM_BUTTON_CLOSE;
            $render = $this->valid_render;
        } elseif ($valid === false) {
            HTTP_Session2::set($this->id . '.process', true);
            $render = $this->invalid_render;
        } else {
            $render = $this->valid_render;
        }

        if ($render == TIP_FORM_RENDER_NOTHING) {
            return $valid;
        }

        // Define buttons if $this->buttons is not set
        if (!isset($buttons)) {
            $buttons = $this->buttons;
        }

        // Add buttons
        $group = array();
        if ($buttons & TIP_FORM_BUTTON_SUBMIT) {
            $group[] =& $this->_form->createElement('submit', null, $this->getLocale('button.submit'), array('class' => 'ok'));
        }
        if ($buttons & TIP_FORM_BUTTON_RESET) {
            $group[] =& $this->_form->createElement('reset', null, $this->getLocale('button.reset'), array('class' => 'restore'));
        }
        if ($buttons & TIP_FORM_BUTTON_OK) {
            $group[] =& $this->_form->createElement('link', 'ok', null, $_SERVER['REQUEST_URI'] . '&process=1', $this->getLocale('button.ok'), array('class' => 'ok'));
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->action_id == TIP_FORM_ACTION_DELETE) {
            $group[] =& $this->_form->createElement('link', 'delete', null, $_SERVER['REQUEST_URI'] . '&process=1', $this->getLocale('button.delete'), array('class' => 'delete'));
        }
        if ($buttons & TIP_FORM_BUTTON_CANCEL) {
            $group[] =& $this->_form->createElement('link', 'cancel', null, $this->referer, $this->getLocale('button.cancel'), array('class' => 'cancel'));
        }
        if ($buttons & TIP_FORM_BUTTON_CLOSE) {
            $group[] =& $this->_form->createElement('link', 'close', null, $this->follower, $this->getLocale('button.close'), array('class' => 'close'));
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->action_id != TIP_FORM_ACTION_DELETE) {
            $primary_key = $this->_data->getProperty('primary_key');
            $url = TIP::getScriptURI() . '?module=' . $this->id .
                '&action=delete&' .
                $primary_key . '=' . urlencode($this->_form->getElementValue($primary_key));
            $group[] =& $this->_form->createElement('link', 'delete', null, $url, $this->getLocale('button.delete'), array('class' => 'delete'));
        }

        // Add the tabindex property to the buttons
        foreach (array_keys($group) as $id) {
            ++ $this->_tabindex;
            $group[$id]->setAttribute ('tabindex', $this->_tabindex);
        }

        // Add the group of buttons to the form
        $element =& $this->_form->addElement('group', 'buttons', null, $group, ' ');

        // Rendering
        if ($render == TIP_FORM_RENDER_HERE) {
            $this->_render();
        } elseif ($render == TIP_FORM_RENDER_IN_PAGE) {
            TIP_Application::appendCallback(array(&$this, '_render'));
        }

        return $valid;
    }

    //}}}
    //{{{ Internal properties

    /**
     * A reference to the TIP_Data object of the master module
     * @var TIP_Data
     * @internal
     */
    private $_data = null;

    /**
     * A reference to the form object
     * @var HTML_QuickForm
     * @internal
     */
    private $_form = null;

    /**
     * An associative array of element_id => callback that convert the
     * submitted values in usable form before the processing
     *
     * @var array
     * @internal
     */
    private $_converter = array();

    /**
     * The internal used tabindex counter
     * @var int
     * @internal
     */
    private $_tabindex = 0;

    //}}}
    //{{{ Callbacks

    public function _ruleUnique($value, $options)
    {
        $form_register =& TIP_Type::singleton('form');
        $var = array_keys($form_register);

        // The active form is the last registered form
        $form_id = end(array_keys($form_register));
        $form =& $form_register[$form_id];
        if ($form->action_id != TIP_FORM_ACTION_ADD && $form->action_id != TIP_FORM_ACTION_EDIT) {
            return true;
        }

        $filter = $form->_data->filter($options, $value);
        $rows =& $form->_data->getRows($filter);
        $valid = empty($rows);

        if (!$valid && count($rows) < 2) {
            // Check if the row edited by this form has the same primary key of
            // the found row
            $primary_key = $form->_data->getProperty('primary_key');
            $valid = @array_key_exists($form->defaults[$primary_key], $rows);
        }

        return $valid;
    }

    public function _ruleDate($value)
    {
        list($day, $month, $year) = array_values($value);
        return checkdate($month, $day, $year);
    }

    private function _converterTimestamp(&$row, $field)
    {
        list($day, $month, $year) = array_values($row[$field]);
        $row[$field] = mktime(0, 0, 0, $month, $day, $year);
    }

    private function _converterISO8601(&$row, $field)
    {
        list($day, $month, $year) = array_values($row[$field]);
        $row[$field] = sprintf('%04d%02d%02d', $year, $month, $day);
    }

    private function _converterSet(&$row, $field)
    {
        if (@is_array($row[$field])) {
            $row[$field] = implode(',', array_keys($row[$field]));
        }
    }

    private function _converterCancel(&$row, $field)
    {
        $row[$field] = @$this->defaults[$field];
    }

    public function _convert(&$row)
    {
        foreach ($this->_converter as $field => $type) {
            $method = '_converter' . $type;
            $this->$method($row, $field);
        }

        $this->_process($row);
    }

    public function _render()
    {
        $renderer =& TIP_Renderer::getForm();
        $this->_form->accept($renderer);
        echo $renderer->toHtml();
    }

    //}}}
    //{{{ Internal methods

    private function _addAutomaticDefaults()
    {
        if (array_key_exists('_creation', $this->fields) &&
            @empty($this->defaults['_creation'])) {
            $this->defaults['_creation'] = TIP::formatDate('datetime_iso8601');
        }

        if (array_key_exists('_lasthit', $this->fields) &&
            @empty($this->defaults['_lasthit'])) {
            $this->defaults['_lasthit'] = TIP::formatDate('datetime_iso8601');
        }

        if (array_key_exists('_user', $this->fields) &&
            @empty($this->defaults['_user'])) {
            $this->defaults['_user'] = TIP::getUserId();
        }
    }

    private function& _addElement($type, $id, $class = false)
    {
        $label = $this->getLocale('label.' . $id);
        ++ $this->_tabindex;
        $attributes['tabindex'] = $this->_tabindex;
        if ($class) {
            $attributes['class'] = $class;
        }
        return $this->_form->addElement($type, $id, $label, $attributes);
    }

    private function _addWidget($id)
    {
        if (substr($id, 0, 1) == '_' || $this->fields[$id]['automatic']) {
            // By default, fields starting with '_' and automatic fields
            // cannot be edited, so are included as hidden (if defined)
            if (@array_key_exists($id, $this->defaults)) {
                $this->_form->addElement('hidden', $id, $this->defaults[$id]);
            }
        } else {
            $method = '_widget' . @$this->fields[$id]['widget'];
            if (!method_exists($this, $method)) {
                $method = '_widgetText';
            }
            $element =& $this->$method($this->fields[$id]);
        }
    }

    private function _addRule($id, $type, $format = '')
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
        $result = $this->_form->addRule($id, $message, $type, $format, $this->validation);
    }

    private function _addGuessedRules($id)
    {
        if (@$this->fields[$id]['category'] == 'required') {
            $this->_addRule($id, 'required');
        }
        if (is_numeric($this->fields[$id]['default'])) {
            $this->_addRule($id, 'numeric');
        }
    }

    private function _addCustomRules($id)
    {
        $text = @$this->fields[$id]['rules'];
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

    private function _addConverter($id, $type)
    {
        $this->_converter[$id] = $type;
    }

    private function _validate()
    {
        // Validate
        switch ($this->action) {

        case TIP_FORM_ACTION_ADD:
        case TIP_FORM_ACTION_EDIT:
            foreach (array_keys($this->fields) as $id) {
                if ($this->_form->elementExists($id) && @$this->fields[$id]['widget'] != 'upload') {
                    $this->_addGuessedRules($id);
                    $this->_addCustomRules($id);
                }
            }

            isset($this->validator) && $this->_form->addFormRule($this->validator);
            $this->_form->applyFilter('__ALL__', array('TIP', 'extendedTrim'));
            if ($this->_form->validate()) {
                $this->_form->freeze();
                return true;
            }
            $this->_form->setRequiredNote($this->getLocale('required_note'));
            return false;

        case TIP_FORM_ACTION_DELETE:
        case TIP_FORM_ACTION_CUSTOM:
            $this->_form->freeze();
            return TIP::getGet('process', 'int') == 1;
        }

        $this->_form->freeze();
        return null;
    }

    private function _process(&$row)
    {
        $processed = false;

        if (is_null($this->on_process) || call_user_func_array($this->on_process, array(&$row, &$this->defaults))) {
            switch ($this->action) {

            case TIP_FORM_ACTION_ADD:
                $processed = $this->_data->putRow($row);
                break;

            case TIP_FORM_ACTION_EDIT:
                $processed = $this->_data->updateRow($row);
                break;

            case TIP_FORM_ACTION_DELETE:
                $processed = $this->_data->deleteRow($row[$this->_data->getProperty('primary_key')]);
                break;

            case TIP_FORM_ACTION_CUSTOM:
                $processed = true;
                break;
            }

            if (!$processed) {
                // Error in processing the row
                TIP::notifyError('fatal');
                return false;
            }
        } else {
            // Callback returned false: no processing needed
            return false;
        }

        TIP::notifyInfo('done');
        return true;
    }

    private function &_widgetText(&$field)
    {
        $id = $field['id'];
        $element =& $this->_addElement('text', $id, 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        return $element;
    }

    private function &_widgetPassword(&$field)
    {
        $id = $field['id'];
        $element =& $this->_addElement('password', $id, 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        if ($this->action_id == TIP_FORM_ACTION_ADD || $this->action_id == TIP_FORM_ACTION_EDIT) {
            $reid = 're' . $id;
            $reelement =& $this->_addElement('password', $reid, 'expand');

            // The repetition field must have the same features of the original,
            // so the field structure is copyed
            if (!array_key_exists($reid, $this->fields)) {
                $this->fields[$reid] = $field;
            }

            $this->_addRule(array($reid, $id), 'compare');
            if (@array_key_exists($id, $this->defaults) && !array_key_exists($reid, $this->defaults)) {
                $this->defaults[$reid] = $this->defaults[$id];
            }
        }

        return $element;
    }

    private function &_widgetEnum(&$field)
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

    private function &_widgetSet(&$field)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $items = array_flip($field['choices']);
        $default = @explode(',', $this->defaults[$id]);
        array_walk($items, array(&$this, 'localize'), array('label.', ''));

        // Reset the defaults (a comma separated list of flags that are set):
        // the $this->defaults[$id] variable will be defined in the foreach
        // cycle in the proper HTML_QuickForm format
        unset($this->defaults[$id]);

        $group = array();
        foreach ($items as $i_value => $i_label) {
            $this->defaults[$id][$i_value] = in_array($i_value, $default);
            ++ $this->_tabindex;
            $item =& $this->_form->createElement('checkbox', $i_value, $label, $i_label, array('tabindex' => $this->_tabindex));
            $group[] =& $item;
        }

        $this->_addConverter($id, 'set');
        return $this->_form->addElement('group', $id, $label, $group);
    }

    private function &_widgetTextArea(&$field)
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

    private function &_widgetDate(&$field)
    {
        HTML_QuickForm::registerRule('date', 'callback', '_ruleDate', 'TIP_Form');

        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);

        // Set the date in a format suitable for HTML_QuickForm_date
        $iso8601 = @$this->defaults[$id];
        $timestamp = empty($iso8601) ? time() : TIP::getTimestamp($iso8601, 'iso8601');
        $this->defaults[$id] = $timestamp;

        $field_year = date('Y', $this->defaults[$id]);
        $this_year = date('Y');

        // $min_year > $max_year, so the year list is properly sorted in reversed
        // order
        $options = array(
            'language' => TIP::getLocaleId(),
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

    private function &_widgetPicture(&$field)
    {
        HTML_QuickForm::registerElementType('picture', 'HTML/QuickForm/picture.php', 'HTML_QuickForm_picture');

        $id = $field['id'];

        $element =& $this->_addElement('picture', $id);
        $element->setBasePath(TIP::buildUploadPath($this->id));
        $element->setBaseURL(TIP::buildUploadURL($this->id));

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

    private function &_widgetHierarchy(&$field)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $hierarchy_id = $this->id . '_hierarchy';
        $hierarchy =& TIP_Type::getInstance($hierarchy_id);

        // Populate the option list, prepending an empty option
        $items = array(' ' => '&#160;') + $hierarchy->toRows();

        ++ $this->_tabindex;
        return $this->_form->addElement('select', $id, $label, $items, array('tabindex' => $this->_tabindex, 'class' => 'expand'));
    }

    //}}}
}
?>
