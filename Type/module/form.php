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
    var $_tabindex = 0;

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
        $label = $this->_block->getLocale($id . '_label');
        $items = array_flip($field['choices']);
        $default = @explode(',', $this->_defaults[$id]);
        unset($this->_defaults[$id]);
        array_walk($items, array(&$this->_block, 'localize'), $id . '_label');

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
        $unload_id = 'unload_' . $id;
        $element =& $this->_addElement('picture', $id);
        $element->setBasePath(TIP::buildDataPath($this->_block->getId()));
        $element->setBaseUrl(TIP::buildDataUrl($this->_block->getId()));

        if (!@is_null(TIP::getPost($unload_id, 'string'))) {
            $element->unloadPicture();
        } else {
            $unload_label = $this->_block->getLocale($unload_id . '_label');
            ++ $this->_tabindex;
            $unload =& $this->_form->createElement('checkbox', $unload_id, $unload_label, $unload_label, array('tabindex' => $this->_tabindex));
            $element->setUnloadElement($unload);
        }

        return $element;
    }

    function& _widgetHierarchy(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');

        $hierarchy_id = $this->_block->getId() . '_hierarchy';
        $hierarchy =& TIP_Type::getInstance($hierarchy_id);
        $items =& $hierarchy->getRows();

        // Prepend an empty (default) option to the row list
        $items = array('', '&nbsp;') + $items;

        ++ $this->_tabindex;
        return $this->_form->addElement('select', $id, $label, $items, array('tabindex' => $this->_tabindex, 'class' => 'expand'));
    }

    function& _addElement($type, $id)
    {
        $label = $this->_block->getLocale($id . '_label');
        ++ $this->_tabindex;
        return $this->_form->addElement($type, $id, $label, array('tabindex' => $this->_tabindex));
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
     * Initializes a TIP_Form instance.
     *
     * @param string $id   The instance identifier
     * @param array  $args The constructor arguments, as described in buildId()
     */
    function TIP_Form($id, $args)
    {
        $this->TIP_Module($id);

        // TODO: scan instead the form register
        $GLOBALS['_TIP_FORM'] =& $this;

        foreach (array_keys($args) as $name) {
            $property = '_' . $name;
            $this->$property =& $args[$name];
        }

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
     * $args can have all the items specified in TIP_View::buildId(), but the
     * 'filter' and 'data' arguments are not used.
     *
     * $args must be an array with the following items:
     * - $args['data']: a reference to a TIP_Data object
     * - $args['filter']: the filter to apply
     * - $args['on_row']: callback to run for every row
     * - $args['on_view']: callback to run when populated
     *
     * @param  array  $args The constructor arguments
     * @return string       The data identifier
     * The returned identifier is constant because the modules view is only one.
     *
     * @return '__MODULES__' The data identifier
     */
    function buildId($args)
    {
        return $args['block']->getId();
    }

    /**#@-*/


    /**#@+ @access public */

    function run()
    {
        if (is_null($this->_fields)) {
            $this->_fields = $this->_block->data->getFields();
        }

        if ($this->_action == TIP_FORM_ACTION_ADD) {
            $this->_addAutomaticDefaults();
        }

        $header_label = $this->_block->getLocale($this->_command . '_header_label');

        // Create the interface
        $this->_form =& new HTML_QuickForm_DHTMLRulesTableless($this->_block->getId());
        // XHTML compliance
        $this->_form->removeAttribute('name');
        $this->_form->addElement('header', $this->_command . '_header', $header_label);
        // The label element (default header object) is buggy at least in
        // Firefox, so I provide a decent alternative
        $this->_form->addElement('html', '<h1>' . $header_label . '</h1>');
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

        // Perform uploads (if any)
        if (is_callable(array('HTML_QuickForm_picture', 'doUploads'))) {
            HTML_QuickForm_picture::doUploads($this->_form);
        }

        // Process the form
        $referer = $this->_referer;
        if ($valid === true) {
            if (@HTTP_Session::get('form.to_process')) {
                if ($this->_form->isSubmitted()) {
                    $this->_form->process(array(&$this, '_convert'));
                } else {
                    $this->_onProcess($this->_defaults);
                }
                HTTP_Session::set('form.to_process', null);
            }
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
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->_command == TIP_FORM_ACTION_DELETE) {
            $url = $_SERVER['REQUEST_URI'] . '&process=1';
            $group[] =& $this->_form->createElement('link', 'delete', null, $url, $this->getLocale('delete'));
        }
        if ($buttons & TIP_FORM_BUTTON_CANCEL) {
            $group[] =& $this->_form->createElement('link', 'cancel', null, $referer, $this->getLocale('cancel'));
        }
        if ($buttons & TIP_FORM_BUTTON_CLOSE) {
            $group[] =& $this->_form->createElement('link', 'close', null, $referer, $this->getLocale('close'));
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->_command != TIP_FORM_ACTION_DELETE) {
            $primary_id = $this->_block->data->getPrimaryKey();
            $primary_value = $this->_form->getElementValue($primary_id);
            $url = TIP::getScriptURI() . '?module=' . $this->_block->getId() . '&action=delete';
            $url .= '&' . $primary_id . '=' . urlencode($primary_value);
            $group[] =& $this->_form->createElement('link', 'delete', null, $url, $this->getLocale('delete'), array('class' => 'dangerous'));
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
            $GLOBALS[TIP_MAIN]->appendCallback($this->callback('_render'));
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
