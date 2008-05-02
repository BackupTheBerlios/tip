<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Form definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
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
     * The file to run on form rendering
     * @var string
     */
    protected $form_source = 'form.src';

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
     * Wheter to include a captcha element in the form
     * @var boolean
     */
    protected $captcha = false;

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
    protected $validation = 'server';

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
     * The uri where turn back
     *
     * Leaves it null to use the default referer. If the action is processed,
     * all occurrences of '-lastid-' inside this string will be replaced by the
     * last id (if any).
     *
     * @var string|null
     */
    protected $referer = null;

    /**
     * The uri where go on: leave it null to use the referer as default value
     *
     * Leaves it null to use the referer as default value. If the action is
     * processed, all occurrences of '-lastid-' inside this string will be
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
        $options['locale_prefix'] = 'form';
        isset($options['action_id']) || $options['action_id'] = $options['action'];
        isset($options['referer']) || $options['referer'] = TIP::getRefererUri();
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
        $this->keys['HEADER'] = $this->master->getLocale('header.' . $this->action_id);

        // Create the interface
        $this->_form =& new HTML_QuickForm('__tip_' . $this->id, 'post', $_SERVER['REQUEST_URI']);

        // XHTML compliance
        $this->_form->removeAttribute('name');

        $this->_form->addElement('hidden', 'module', $this->id);
        $this->_form->addElement('hidden', 'action', $this->action_id);
        $this->_form->addElement('header', '__set_' . $this->id, 'data');
        array_walk(array_keys($this->fields), array(&$this, '_addWidget'));
        if ($this->captcha) {
            $this->_addCaptcha();
        }

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
        if (is_callable(array('HTML_QuickForm_attachment', 'doUploads'))) {
            HTML_QuickForm_attachment::doUploads($this->_form);
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
                isset($last_id) || $last_id = '';
                $this->referer = str_replace('-lastid-', $last_id, $this->referer);
                $this->follower = str_replace('-lastid-', $last_id, $this->follower);
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
        isset($buttons) || $buttons = $this->buttons;

        // Add buttons
        $group = array();
        if ($buttons & TIP_FORM_BUTTON_SUBMIT) {
            $element =& $this->_form->createElement('submit', null, $this->getLocale('button.submit'), array('class' => 'ok'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_RESET) {
            $element =& $this->_form->createElement('reset', null, $this->getLocale('button.reset'), array('class' => 'restore'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_OK) {
            $uri = TIP::modifyActionUri(null, null, null, array('process' => 1));
            $element =& $this->_form->createElement('link', 'ok', null, $uri, $this->getLocale('button.ok'), array('class' => 'ok'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->action_id == TIP_FORM_ACTION_DELETE) {
            $uri = TIP::modifyActionUri(null, null, null, array('process' => 1));
            $element =& $this->_form->createElement('link', 'delete', null, $uri, $this->getLocale('button.delete'), array('class' => 'delete'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_CANCEL) {
            $element =& $this->_form->createElement('link', 'cancel', null, $this->referer, $this->getLocale('button.cancel'), array('class' => 'cancel'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_CLOSE) {
            $element =& $this->_form->createElement('link', 'close', null, $this->follower, $this->getLocale('button.close'), array('class' => 'close'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }
        if ($buttons & TIP_FORM_BUTTON_DELETE && $this->action_id != TIP_FORM_ACTION_DELETE) {
            $primary_key = $this->_data->getProperty('primary_key');
            $uri = TIP::buildActionUri($this->id, 'delete', $this->_form->getElementValue($primary_key));
            $element =& $this->_form->createElement('link', 'delete', null, $uri, $this->getLocale('button.delete'), array('class' => 'delete'));
            $element->removeAttribute('name');
            $group[] =& $element;
        }

        // Add the tabindex property to the buttons
        foreach (array_keys($group) as $id) {
            ++ $this->_tabindex;
            $group[$id]->setAttribute('tabindex', $this->_tabindex);
        }

        // Add the group of buttons to the form
        $element =& $this->_form->addElement('group', 'buttons', null, $group, ' ', false);

        // Rendering
        if (!$this->_render($render)) {
            return null;
        }

        return $valid;
    }

    /**
     * Start a form view
     *
     * The available form views are:
     * - 'SECTION' to browse throught the form sections
     * - 'ELEMENT' to browse throught the elements of the current section
     *
     * @param  string        $type The view type
     * @return TIP_View|null       The view instance or null on errors
     */
    public function &startView($type)
    {
        switch ($type) {

        case 'SECTION':
            if (!is_array($this->_array)) {
                return null;
            }

            $this->_section_view =& TIP_Type::singleton(array(
                'type' => array('view', 'array_view'),
                'id'   => 'SECTION',
                'rows' => &$this->_array
            ), true);

            return $this->_section_view;

        case 'ELEMENT':
            if (is_null($this->_section_view) ||
                is_null($s_id = $this->_section_view->key())) {
                return null;
            }

            $this->_element_view =& TIP_Type::singleton(array(
                'type' => array('view', 'array_view'),
                'id'   => 'ELEMENT',
                'rows' => &$this->_array[$s_id]['elements']
            ), true);
            return $this->_element_view;
        }

        return null;
    }

    /**
     * End the current view
     * @return bool true on success or false on errors
     */
    public function endView()
    {
        if (isset($this->_element_view)) {
            $this->_element_view = null;
        } else {
            $this->_section_view = null;
        }
        return true;
    }

    /**
     * Return the value of a generic item
     *
     * Gets the value of a generic item. This implementation adds form
     * specific features to the TIP_Module::getItem() method, such as
     * the ability to get information from the current element or from
     * the current section.
     *
     * @param  string     $id The item id
     * @return mixed|null     The value of the item or null if not found
     */
    public function getItem($id)
    {
        if (isset($this->_element_view)
            && !is_null($value = $this->_element_view->getField($id))) {
            return $value;
        }

        if (isset($this->_section_view)
            && !is_null($value = $this->_section_view->getField($id))) {
            return $value;
        }

        return parent::getItem($id);
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param      string       $params Parameters of the tag
     * @return     string|null          The string result or null
     * @subpackage SourceEngine
     */

    protected function tagHidden($params)
    {
        $hidden = isset($this->_section_view) &&
            !is_null($s_id = $this->_section_view->key()) &&
            $header =& $this->_array[$s_id]['hidden'];
        return $hidden ? 'true' : 'false';
    }

    protected function tagForm($params)
    {
        $form =& $this->_form;
        return method_exists($form, $params) ? $form->$params() : null;
    }

    protected function tagSection($params)
    {
        if (!isset($this->_section_view) ||
            is_null($section_id = $this->_section_view->key())) {
            return null;
        }

        $section =& $this->_array[$section_id]['object'];
        return method_exists($section, $params) ? $section->$params() : null;
    }

    protected function tagElement($params)
    {
        if (!isset($this->_element_view) ||
            is_null($element_id = $this->_element_view->key())) {
            return null;
        }

        $element_rows =& $this->_element_view->getProperty('rows');
        $element =& $element_rows[$element_id]['object'];
        return method_exists($element, $params) ? $element->$params() : null;
    }

    /**#@-*/

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

    /**
     * The rendered array used by the renderer
     * @var array
     * @internal
     */
    private $_array = null;

    /**
     * The temporary section view to use
     * @var array
     * @internal
     */
    private $_section_view = null;

    /**
     * The temporary element view to use
     * @var array
     * @internal
     */
    private $_element_view = null;

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

    private function _converterSqlDate(&$row, $field)
    {
        list($day, $month, $year) = array_values($row[$field]);
        $row[$field] = sprintf('%04d-%02d-%02d', $year, $month, $day);
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

    public function _render($mode)
    {
        if ($mode == TIP_FORM_RENDER_NOTHING) {
            return true;
        }

        // Initialize the source instance
        $source =& TIP_Type::singleton(array(
            'type' => array('source'),
            'path' => array(TIP_Application::getGlobal('id'), $this->form_source)
        ));
        if (!$source) {
            TIP::error("form template not found ($this->form_source)");
            return false;
        }

        // Some global keys
        $this->keys['ATTRIBUTES'] = $this->_form->getAttributes(true);
        $this->keys['REQUIREDNOTE'] = $this->_form->getRequiredNote();

        // Populate the array (if not yet done)
        if (is_null($this->_array)) {
            $renderer =& TIP_Renderer::getForm();
            $this->_form->accept($renderer);
            $this->_array = $renderer->toArray();
        }

        // Call the renderer
        if ($mode == TIP_FORM_RENDER_IN_PAGE) {
            $content =& TIP_Application::getGlobal('content');
            ob_start();
            $done = $source->run($this);
            $content .= ob_get_clean();
        } else {
            $done = $source->run($this);
        }

        return $done;
    }

    //}}}
    //{{{ Internal methods

    private function _addAutomaticDefaults()
    {
        if (array_key_exists('_creation', $this->fields) &&
            @empty($this->defaults['_creation'])) {
            $this->defaults['_creation'] = TIP::formatDate('datetime_sql');
        }

        if (array_key_exists('_lasthit', $this->fields) &&
            @empty($this->defaults['_lasthit'])) {
            $this->defaults['_lasthit'] = TIP::formatDate('datetime_sql');
        }

        if (array_key_exists('_user', $this->fields) &&
            @empty($this->defaults['_user'])) {
            $this->defaults['_user'] = TIP::getUserId();
        }
    }

    private function& _addElement($type, $id, $attributes = false)
    {
        $label = $this->getLocale('label.' . $id);
        $comment = TIP::getLocale('comment.' . $id, $this->locale_prefix);
        if (is_string($attributes)) {
            $attributes = array('class' => $attributes);
        }
        $attributes['tabindex'] = ++ $this->_tabindex;

        $element =& $this->_form->addElement($type, $id, $label, $attributes);
        $element->setComment($comment);
        return $element;
    }

    private function _addCaptcha()
    {
        HTML_QuickForm::registerElementType('captcha', 'HTML/QuickForm/captcha.php', 'HTML_QuickForm_captcha');

        $id = 'captchanw';
        $element =& $this->_addElement('captcha', $id, array('size' => 6, 'maxlength' => 6));
        $element->setLocale(TIP::getLocaleId());
        $this->_addRule($id, 'required');
        $this->_addRule($id, 'captcha');
    }

    private function _addWidget($id)
    {
        $field =& $this->fields[$id];

        if (substr($id, 0, 1) == '_' || $field['automatic']) {
            // By default, fields starting with '_' and automatic fields
            // cannot be edited, so are included as hidden (if defined)
            if (@array_key_exists($id, $this->defaults)) {
                $this->_form->addElement('hidden', $id, $this->defaults[$id]);
            }
        } else {
            $method = '_widget' . @$field['widget'];
            if (!method_exists($this, $method)) {
                $method = '_widgetText';
            }
            $element =& $this->$method($field, @$field['widget_args']);
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
        if (PEAR::isError($result)) {
            TIP::error($result->getMessage());
        }
    }

    private function _addGuessedRules($id)
    {
        $widget = strtolower(@$this->fields[$id]['widget']);
        $is_upload = in_array($widget, array('attachment', 'picture', 'thumbnail'));

        if (@$this->fields[$id]['category'] == 'required') {
            $this->_addRule($id, 'required');
            $is_upload && $this->_addRule($id, 'requiredupload');
        }

        if ($is_upload) {
            $this->_addRule($id, 'uploaded');
        } elseif (is_numeric($this->fields[$id]['default'])) {
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

    /**
     * Configure an attachment based element
     *
     * This code can be shared by every HTML_QuickForm_attachment based element.
     *
     * @param  HTML_QuickForm_element &$element The element to configure
     * @param  string                  $args    The widget args
     * @return HTML_QuickForm_element           The configured element
     */
    private function &_configAttachment(&$element, $args)
    {
        // Common base path and uri
        $element->setBasePath(TIP::buildDataPath($this->id));
        $element->setBaseUrl(TIP::buildDataUri($this->id));

        // Unload the element data, if needed
        $unload_id = 'unload_' . $element->getName();
        if ($this->action == TIP_FORM_ACTION_DELETE && $this->_toProcess() ||
            array_key_exists($unload_id, $_POST)) {
            $element->setState(QF_ATTACHMENT_TO_UNLOAD);
        } else {
            // Add the unload element
            $unload_label = $this->getLocale('label.' . $unload_id);
            $unload_element = $this->_form->createElement('checkbox', $unload_id, $unload_label, $unload_label, array('tabindex' => $this->_tabindex));
            $element->setUnloadElement($unload_element);
        }

        return $element;
    }

    /**
     * Configure a picture based element
     *
     * This code can be shared by every HTML_QuickForm_picture based element.
     *
     * @param  HTML_QuickForm_element &$element The element to configure
     * @param  array                   $range   The allowed range, as returned
     *                                          by _getPictureRange()
     * @param  string                  $args    The widget args
     * @return HTML_QuickForm_element           The configured element
     */
    private function &_configPicture(&$element, $range, $args)
    {
        // A picture is an attachment derived element
        $this->_configAttachment($element, $args);

        // Set the autoresize feature, if requested
        $element->setAutoresize($args && strpos($args, 'autoresize') !== false);

        // Variable substitution in the element comment, if needed
        $comment = $element->getComment();
        if ($comment && strpos($comment, '|0|') !== false) {
            foreach ($range as $n => $value) {
                $comment = str_replace('|'.$n.'|', $value, $comment);
            }
            $element->setComment($comment);
        }

        return $element;
    }

    /**
     * Get the range of allowed picture size
     *
     * Analyzes the field rules ('minpicturesize' and 'maxpicturesize')
     * to get the range of the allowed picture size.
     *
     * @param  array &$field The field to analyze
     * @return array         The size range in the form
     *                       array(min_width,min_height,max_width,max_height)
     */
    private function _getPictureRange(&$field)
    {
        $range = array();

        if (preg_match('/minpicturesize\(([0-9]+) *([0-9]+)\)/', $field['rules'], $match)) {
            $range[0] = $match[1];
            $range[1] = $match[2];
        } else {
            $range[0] = $range[1] = 0;
        }

        if (preg_match('/maxpicturesize\(([0-9]+) *([0-9]+)\)/', $field['rules'], $match)) {
            $range[2] = $match[1];
            $range[3] = $match[2];
        } else {
            $range[2] = $range[3] = '&infin;';
        }

        return $range;
    }

    private function _toProcess()
    {
        return TIP::getGet('process', 'int') == 1;
    }

    private function _validate()
    {
        // Validate
        switch ($this->action) {

        case TIP_FORM_ACTION_ADD:
        case TIP_FORM_ACTION_EDIT:
            foreach (array_keys($this->fields) as $id) {
                if ($this->_form->elementExists($id)) {
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
            return $this->_toProcess();
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

    private function &_widgetText(&$field, $args)
    {
        $id = $field['id'];
        $element =& $this->_addElement('text', $id, 'expand');

        if (@$field['length'] > 0) {
            $element->setMaxLength($field['length']);
            $this->_addRule($id, 'maxlength', $field['length']);
        }

        return $element;
    }

    private function &_widgetPassword(&$field, $args)
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

    private function &_widgetEnum(&$field, $args)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $comment = TIP::getLocale('comment.' . $id, $this->locale_prefix);
        $items = array();
        foreach ($field['choices'] as $choice) {
            $items[$choice] = $this->getLocale('label.' . $choice);
        }

        if (count($items) > 3) {
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

        $element->setComment($comment);
        return $element;
    }

    private function &_widgetSet(&$field, $args)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $comment = TIP::getLocale('comment.' . $id, $this->locale_prefix);
        $default = @explode(',', $this->defaults[$id]);
        $items = array();
        foreach ($field['choices'] as $choice) {
            $items[$choice] = $this->getLocale('label.' . $choice);
        }

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
        $element =& $this->_form->addElement('group', $id, $label, $group);
        $element->setComment($comment);
        return $element;
    }

    private function &_widgetTextArea(&$field, $args)
    {
        HTML_QuickForm::registerElementType('wikiarea', 'HTML/QuickForm/wikiarea.php', 'HTML_QuickForm_wikiarea');

        $id = $field['id'];
        $element =& $this->_addElement('wikiarea', $id, 'expand');

        if (!empty($args)) {
            $rules = explode(',', $args);
        } elseif (array_key_exists('wiki_rules', $field)) {
            // DEPRECATED: use the "wiki_rules" option instead of widget args
            $rules = explode(',', $field['wiki_rules']);
        } else {
            $rules = null;
        }
        $element->setWiki(TIP_Renderer::getWiki($rules));
        $element->setCols('30');
        $element->setRows('10');

        return $element;
    }

    private function &_widgetDate(&$field, $args)
    {
        HTML_QuickForm::registerRule('date', 'callback', '_ruleDate', 'TIP_Form');

        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $comment = TIP::getLocale('comment.' . $id, $this->locale_prefix);

        // Set the date in a format suitable for HTML_QuickForm_date
        $sql_date = @$this->defaults[$id];
        $timestamp = empty($sql_date) ? time() : TIP::getTimestamp($sql_date, 'sql');
        $this->defaults[$id] = $timestamp;

        $field_year = date('Y', $this->defaults[$id]);
        $this_year = date('Y');

        // $min_year > $max_year, so the year list is properly sorted in reversed
        // order
        $options = array(
            'language' => substr(TIP::getLocaleId(), 0, 2),
            'format'   => 'dFY',
            'minYear'  => $this_year+1,
            'maxYear'  => $field_year < $this_year-5 ? $field_year : $this_year-5
        );

        ++ $this->_tabindex;
        $element =& $this->_form->addElement('date', $id, $label, $options, array('tabindex' => $this->_tabindex));
        $element->setComment($comment);
        $this->_addRule($id, 'date');
        $this->_addConverter($id, 'SqlDate');
        return $element;
    }

    private function &_widgetAttachment(&$field, $args)
    {
        HTML_QuickForm::registerElementType('attachment', 'HTML/QuickForm/attachment.php', 'HTML_QuickForm_attachment');
        $element =& $this->_addElement('attachment', $field['id']);
        return $this->_configAttachment($element, $args);
    }

    private function &_widgetPicture(&$field, $args)
    {
        HTML_QuickForm::registerElementType('picture', 'HTML/QuickForm/picture.php', 'HTML_QuickForm_picture');
        $element =& $this->_addElement('picture', $field['id']);
        $range = $this->_getPictureRange($field);
        return $this->_configPicture($element, $range, $args);
    }

    private function &_widgetThumbnail(&$field, $args)
    {
        HTML_QuickForm::registerElementType('thumbnail', 'HTML/QuickForm/thumbnail.php', 'HTML_QuickForm_thumbnail');

        // Leave the default thumbnail path/url, that is
        // the base ones with 'thumbnail' appended
        $element =& $this->_addElement('thumbnail', $field['id']);
        $range = $this->_getPictureRange($field);

        // If set, the thumbnail size is equal to the minimum size
        if ($range[0] > 0 && $range[1] > 0) {
            $element->setThumbnailSize($range[0], $range[1]);
        }

        return $this->_configPicture($element, $range, $args);
    }

    private function &_widgetHierarchy(&$field, $args)
    {
        $id = $field['id'];
        $label = $this->getLocale('label.' . $id);
        $comment = TIP::getLocale('comment.' . $id, $this->locale_prefix);

        if (empty($args)) {
            // Try to get the hierarchy master module from the $cfg array
            global $cfg;
            foreach ($cfg as $module_id => &$options) {
                if (isset($options['master']) && $options['master'] == $this->id) {
                    $hierarchy_id = $module_id;
                    break;
                }
            }
        } else {
            // Explicitely defined in the widget args
            $hierarchy_id = $args;
        }

        // On master field not found, build a default one
        // by appending '_hierarchy' to this module id
        isset($hierarchy_id) || $hierarchy_id = $this->id . '_hierarchy';

        $hierarchy =& TIP_Type::getInstance($hierarchy_id);

        // Populate the option list, prepending an empty option
        $items = array(' ' => '&#160;') + $hierarchy->toRows();

        ++ $this->_tabindex;
        $element =& $this->_form->addElement('select', $id, $label, $items, array('tabindex' => $this->_tabindex, 'class' => 'expand'));
        $element->setComment($comment);
        return $element;
    }

    //}}}
}
?>
