<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Class definition file
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
 * @since     0.3.0
 */

/**
 * Class module
 *
 * @package TIP
 * @since   0.3.0
 */
class TIP_Class extends TIP_Content
{
    //{{{ Properties

    /**
     * The field to be used to identify the child module
     * @var string
     */
    protected $class_field = 'class';

    /**
     * The field in the child module to be joined to this class primary key
     * @var string
     */
    protected $master_field = '_master';

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Class instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
        $this->_current_data =& $this->data;
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param  string      $params Parameters of the tag
     * @return string|null         The string result or null on errors
     */

    /**
     * Overriden method including the child fields in the frozen form
     */
    protected function tagView($params)
    {
        $child =& $this->_getChildModule();
        if ($child === false ||
            is_null($row = $this->fromRow($params == '' ? null : $params))) {
            // Errors on child module
            return null;
        }

        if (@is_array($this->form_options['view'])) {
            $options = $this->form_options['view'];
        }

        TIP::arrayDefault($options, 'buttons', 0);
        TIP::arrayDefault($options, 'valid_render', TIP_FORM_RENDER_HERE);
        TIP::arrayDefault($options, 'invalid_render', TIP_FORM_RENDER_HERE);
        $options['defaults'] =& $row;
        $options['type'] = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_VIEW;

        $form =& TIP_Type::singleton($options);
        $form->validate();
        $child && $form->validateAlso($child);
        $form->process();

        ob_start();
        $form->render(false);
        return ob_get_clean();
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Overrides the default add action, chaining the child module
     * form if the class form validates.
     *
     * @param  mixed $id      The identifier of the row to duplicate (not used)
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionAdd($id = null, $options = array())
    {
        $primary_key = $this->data->getProperty('primary_key');

        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['add'])) {
            $options = array_merge($this->form_options['add'], $options);
        }

        // Populate "defaults" if $id is specified (= duplicate row)
        if (isset($id)) {
            if (is_null($row = $this->fromRow($id))) {
                return false;
            }

            if (@is_array($options['defaults'])) {
                $options['defaults'] = array_merge($row, $options['defaults']);
            } else {
                $options['defaults'] =& $row;
            }

            // Unset the primary_key: this is an add action
            unset($options['defaults'][$primary_key]);
        }

        $options['type'] = array('module', 'form');
        $options['master'] =& $this;

        TIP::arrayDefault($options, 'action', TIP_FORM_ACTION_ADD);
        TIP::arrayDefault($options, 'on_process', array(&$this, '_onAdd'));
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '') . '{' . $primary_key . '}');
        if (!is_null($class = TIP::getGetOrPost($this->class_field, 'string'))) {
            // Class selected: by default, freeze the form element
            TIP::arrayDefault($options, 'readonly', array($this->class_field));
        }

        $form =& TIP_Type::singleton($options);
        $valid = $form->validate();

        if (isset($id)) {
            // If $id is set, the child module is chained-up also on 
            // $valid==false: this module was already retrieved by fromRow()
            $child =& $this->_getChildModule();
        } elseif (!is_null($class)) {
            // "class_field" defined: get the specific child module
            $child =& $this->_getChildModule($class);
        } else {
            $child = null;
        }

        if ($child === false) {
            // Errors on child module
            return false;
        } elseif ($child) {
            // Child module found and valid: chain-up the child form
            $valid = $form->validateAlso($child);
        }

        if ($valid) {
            $form->process();
        }

        return $form->render($valid);
    }

    /**
     * Perform an edit action
     *
     * Overrides the default edit action, merging master and child
     * fields to build an unique form. The class field is frozen.
     *
     * @param  mixed $id      The identifier of the row to edit
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionEdit($id, $options = array())
    {
        $primary_key = $this->data->getProperty('primary_key');

        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['edit'])) {
            $options = array_merge($this->form_options['edit'], $options);
        }

        // Populate "defaults" with master and child values
        if (is_null($row = $this->fromRow($id))) {
            return false;
        } elseif (@is_array($options['defaults'])) {
            $options['defaults'] = array_merge($row, $options['defaults']);
        } else {
            $options['defaults'] =& $row;
        }

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;

        TIP::arrayDefault($options, 'action', TIP_FORM_ACTION_EDIT);
        TIP::arrayDefault($options, 'on_process', array(&$this, '_onEdit'));
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '') . '{' . $primary_key . '}');
        TIP::arrayDefault($options, 'readonly', array($this->class_field));

        $form =& TIP_Type::singleton($options);
        $valid = $form->validate();

        // On edit, the child form is chained-up also if $valid==false:
        // this module was already retrieved by fromRow()
        $child =& $this->_getChildModule();
        if ($child === false) {
            // Errors on child module
            return false;
        } elseif ($child) {
            // Child module found and valid: chain-up the child form
            $valid = $form->validateAlso($child);
        }

        if ($valid) {
            $form->process();
        }

        return $form->render($valid);
    }

    /**
     * Perform a delete action
     *
     * Overrides the default delete action by showing a merged form
     * between master and child data.
     *
     * @param  mixed $id      The identifier of the row to delete
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionDelete($id, $options = array())
    {
        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['delete'])) {
            $options = array_merge($this->form_options['delete'], $options);
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onDelete'));

        // Populate "defaults" with master and child values
        if (is_null($row = $this->fromRow($id))) {
            return false;
        }
        if (@is_array($options['defaults'])) {
            $options['defaults'] = array_merge($row, $options['defaults']);
        } else {
            $options['defaults'] =& $row;
        }

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_DELETE;

        $form =& TIP_Type::singleton($options);
        $valid = $form->validate();

        // On delete, the child form is chained-up also if $valid==false:
        // this module was already retrieved by fromRow()
        $child =& $this->_getChildModule();
        if ($child === false) {
            // Errors on child module
            return false;
        } elseif ($child) {
            // Child module found and valid: chain-up the child form
            $valid = $form->validateAlso($child);
        }

        if ($valid) {
            $form->process();
        }

        return $form->render($valid);
    }

    //}}}
    //{{{ Methods

    /**
     * Overrides the default getData() returning $_current_data
     *
     * @return TIP_Data The active TIP_Data instance
     */
    public function getData()
    {
        return $this->_current_data;
    }

    /**
     * Get a specific row
     *
     * Overrides the default method to merge also the child row fields
     * to the returned array.
     *
     * @param  mixed      $id       The row id
     * @param  bool       $end_view Whether to end the view or not
     * @return array|null           The row or null on errors
     */
    public function &fromRow($id = null, $end_view = true)
    {
        // Get the current "master" row
        if (is_null($row = parent::fromRow($id, $end_view))) {
            return $row;
        }

        // Try to get the child row, if possible: the class name is
        // retrieved from the post (if found) or from the current $row
        if (is_null($class = TIP::getPost($this->class_field, 'string'))) {
            $class = $row[$this->class_field];
        }
        $child =& $this->_getChildModule($class);
        if ($child === false) {
            $row = null;
        } elseif ($child && !is_null($child_row = $child->fromRow($id, $end_view))) {
            $row = array_merge($child_row, $row);
        }

        return $row;
    }

    //}}}
    //{{{ Callbacks

    /**#@+
     * This callback is transaction protected to avoid data corruptions.
     *
     * @param  array      &$row     The subject row
     * @param  array|null  $old_row The old row or null on no old row
     * @return bool                 true on success or false on error
     */

    /**
     * Process an add action
     *
     * Overrides the default add action inserting both master and child rows.
     */
    public function _onAdd(&$row, $old_row)
    {
        $child =& $this->_getChildModule();
        if (is_null($child)) {
            // No child module: chain-up the parent method
            return parent::_onAdd($row, $old_row);
        } elseif (!$child) {
            // An error occurred somewhere: do nothing
            return false;
        }

        $child_data =& $child->getProperty('data');
        $key = $this->data->getProperty('primary_key');
        $child_key = $child_data->getProperty('primary_key');

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        $done = parent::_onAdd($row, $old_row);
        if ($done) {
            // Work on a copy of $row because putRow() is destructive
            $new_row = $row;
            $done = !is_null($new_row[$child_key] = $new_row[$key]) &&
                $child_data->putRow($new_row) &&
                ($row = array_merge($row, $new_row));
        }
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**
     * Process an edit action
     *
     * Overrides the default edit action updating both master and child rows.
     */
    public function _onEdit(&$row, $old_row)
    {
        $child =& $this->_getChildModule();
        if (is_null($child)) {
            // No child module: chain-up the parent method
            return parent::_onEdit($row, $old_row);
        } elseif (!$child) {
            // An error occurred somewhere: do nothing
            return false;
        }

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        // The class_field MUST NOT be changed (only deleting allowed)
        unset($row[$this->class_field]);

        $done = parent::_onEdit($row, $old_row) &&
            $child->getProperty('data')->updateRow($row);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**
     * Process a delete action
     *
     * Overrides the default delete action erasing both master and child rows.
     */
    public function _onDelete(&$row, $old_row)
    {
        $child =& $this->_getChildModule();
        if (is_null($child)) {
            // No child module: chain-up the parent method
            return parent::_onDelete($row, $old_row);
        } elseif (!$child) {
            // An error occurred somewhere: do nothing
            return false;
        }

        $primary_key = $this->data->getProperty('primary_key');
        if (!array_key_exists($primary_key, $row) ||
            is_null($id = $row[$primary_key])) {
            TIP::warning("no primary key defined (field $primary_key)");
            return false;
        }

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        $done = parent::_onDelete($row, $old_row) &&
            $child->getProperty('data')->deleteRow($id);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**#@-*/

    //}}}
    //{{{ Internal methods

    /**
     * Get the child module
     *
     * Checks for the child module existence and caches the request.
     * If $class is not specified, no attempts are made to get the
     * child module: only the cache is returned or an error is raised.
     *
     * This method provides also a way to validate the data engine,
     * that **must** be shared between this module and the child one
     * to allow //transation protected// commits.
     *
     * @param  string|null            $class The class to use
     * @return TIP_Content|null|false        The requested child module,
     *                                       null if not needed or
     *                                       false on errors
     * @internal
     */
    private function &_getChildModule($class = null)
    {
        // The true value is used as "uncached" value
        static $child = true;

        // Check for cached result
        if ($child !== true) {
            return $child;
        }

        // Check for request without $class (no autodiscovering)
        if (is_null($class)) {
            TIP::error('No previous child request performed');
            $error = false;
            return $error;
        }

        // Check if the child module is required
        $child = TIP_Type::getInstance($this->id . '-' . $class, false);
        if (is_null($child)) {
            // No child module needed
            return $child;
        }

        // Get and check the child module
        $child_data = $child->getProperty('data');
        if (!$child_data instanceof TIP_Data) {
            TIP::error("the child module has no data (child = $class)");
            $child = false;
        } elseif ($child_data->getProperty('engine') != $this->data->getProperty('engine')) {
            TIP::error("master and child data must share the same data engine (child = $class)");
            $child = false;
        }

        return $child;
    }

    //}}}
}
?>
