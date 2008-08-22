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

    /**
     * The summary view path to use for browsing
     * @var string
     */
    protected $summary_view = null;

    //}}}
    //{{{ Constructor/destructor

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options)) {
            return false;
        }

        if (is_array($options['summary_view'])) {
            if (!array_key_exists('path', $options['summary_view']))
                return false;
            TIP::arrayDefault($options['summary_view'], 'type', array('data'));
        } elseif (is_string($options['summary_view'])) {
            $options['summary_view'] = array(
                'type' => array('data'),
                'path' => $options['summary_view']
            );
        } elseif (isset($options['summary_view'])) {
            // Unhandled summary_view format
            return false;
        }

        return true;
    }

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
    }

    //}}}
    //{{{ Tags

    /**#@+
     * @param  string      $params Parameters of the tag
     * @return string|null         The string result or null on errors
     */

    /**
     * Switch the data to summary view
     *
     * Changes the internal TIP_Data object to the summary view.
     * This means all subsequential queries are applied to this
     * view instead of the default data object.
     *
     * tagStartSummary() calls cannot be nested and must be followed
     * by tagEndSummary() to restore the default data.
     *
     * The $params arg is not used.
     */
    protected function tagStartSummary($params)
    {
        if (isset($this->_old_data)) {
            TIP::error('nested startSummary tags not allowed');
            return null;
        }

        $this->_old_data =& $this->data;
        unset($this->data);
        $this->data =& TIP_Type::singleton($this->summary_view);
        return '';
    }

    /**
     * Restore the default data
     *
     * Changes the internal TIP_Data object to the default data,
     * that is restore the situation before to the tagStartSummary()
     * call.
     *
     * Every tagStartSummary() call must be followed by tagEndSummary().
     *
     * The $params arg is not used.
     */
    protected function tagEndSummary($params)
    {
        if (!isset($this->_old_data)) {
            TIP::error('no previous startSummary tag called');
            return null;
        }

        unset($this->data);
        $this->data =& $this->_old_data;
        return '';
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
    protected function actionAdd($id, $options = array())
    {
        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['add'])) {
            $options = array_merge($this->form_options['add'], $options);
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onAdd'));
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '-lastid-'));

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_ADD;

        $form =& TIP_Type::singleton($options);

        $valid = $form->validate();
        if ($valid) {
            $child_name = $this->id . '-' . TIP::getPost($this->class_field, 'string');
            if ($this->_child =& TIP_Type::getInstance($child_name, false)) {
                if (!$this->_validEngine()) {
                    return false;
                }

                // Child module valid: chain-up the child form
                $valid = $form->validateAlso($this->_child);
            }
        }

        if ($valid)
            $form->process();

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
        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['edit'])) {
            $options = array_merge($this->form_options['edit'], $options);
        }

        TIP::arrayDefault($options, 'on_process', array(&$this, '_onEdit'));
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '-lastid-'));

        // Populate "defaults" with master field values
        if (is_null($row = $this->fromRow($id))) {
            TIP::notifyError('notfound');
            return false;
        }

        if (@is_array($options['defaults'])) {
            $options['defaults'] = array_merge($row, $options['defaults']);
        } else {
            $options['defaults'] =& $row;
        }

        // Populate "defaults" with child field values
        $child_name = $this->id . '-' . $row[$this->class_field];
        if ($this->_child =& TIP_Type::getInstance($child_name, false)) {
            if (!$this->_validEngine()) {
                return false;
            }

            if (is_null($child_row = $this->_child->fromRow($id))) {
                TIP::notifyError('notfound');
                return false;
            }

            $options['defaults'] = array_merge($child_row, $options['defaults']);
        }

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_EDIT;
        $options['readonly'] = array($this->class_field);

        $form =& TIP_Type::singleton($options);
        $valid = $form->validate();

        // On edit, the child form is chained-up also if $valid==false
        if ($this->_child && !$form->validateAlso($this->_child)) {
            $valid = false;
        }

        if ($valid)
            $form->process();

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
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '-lastid-'));

        // Populate "defaults" with master field values
        if (is_null($row = $this->fromRow($id))) {
            TIP::notifyError('notfound');
            return false;
        }
        if (@is_array($options['defaults'])) {
            $options['defaults'] = array_merge($row, $options['defaults']);
        } else {
            $options['defaults'] =& $row;
        }

        // Populate "defaults" with child field values
        $child_name = $this->id . '-' . $row[$this->class_field];
        if ($this->_child =& TIP_Type::getInstance($child_name, false)) {
            if (!$this->_validEngine()) {
                return false;
            }

            if (is_null($child_row = $this->_child->fromRow($id))) {
                TIP::notifyError('notfound');
                return false;
            }

            $options['defaults'] = array_merge($child_row, $options['defaults']);
        }

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_DELETE;
        $options['readonly'] = array($this->class_field);

        $form =& TIP_Type::singleton($options);
        $valid = $form->validate();

        // On delete, the child form is chained-up also if $valid==false
        if ($this->_child && !$form->validateAlso($this->_child)) {
            $valid = false;
        }

        if ($valid)
            $form->process();

        return $form->render($valid);
    }

    protected function actionBrowse(&$conditions)
    {
        // Chain-up the parent method on no summary view defined
        if (is_null($this->summary_view))
            return parent::actionBrowse($conditions);

        if (is_null($this->tagStartSummary(''))) {
            return false;
        }

        // Call the template
        $this->_browse_conditions =& $conditions;
        $this->appendToPage($this->browse_template);

        return !is_null($this->tagEndSummary(''));
    }

    //}}}
    //{{{ Callbacks

    /**
     * Add both master and child rows
     *
     * @param  array &$row The joined row to add
     * @return bool        always false, to avoid chaining-up
     *                     the default action
     */
    public function _onAdd(&$row)
    {
        if (is_null($this->_child)) {
            // No child module: chain-up the parent method
            return parent::_onAdd($row);
        }

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be catched here to avoid the rollback
            TIP::notifyError('fatal');
            return false;
        }

        // Copy the row: putRow() is destructive
        $child_row = $row;

        $done = parent::_onAdd($row) &&
            $this->data->putRow($row) &&
            !is_null($child_row[$this->master_field] = $this->data->getLastId()) &&
            $this->_child->getProperty('data')->putRow($child_row);
        $done = $engine->endTransaction($done) && $done;

        if ($done) {
            TIP::notifyInfo('done');
        } else {
            TIP::notifyError('fatal');
        }

        return false;
    }

    /**
     * Update master and child rows
     *
     * @param  array &$row The joined row to update
     * @return bool        always false, to avoid chaining-up
     *                     the default action
     */
    public function _onEdit(&$row)
    {
        if (is_null($this->_child)) {
            // No child module: chain-up the parent method
            return parent::_onEdit($row);
        }

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be catched here to avoid the rollback
            TIP::notifyError('fatal');
            return false;
        }

        // The class_field MUST NOT be changed (only deleting allowed)
        unset($row[$this->class_field]);

        // Copy the row: updateRow() is destructive
        $child_row = $row;

        $done = parent::_onEdit($row) &&
            $this->data->updateRow($row) &&
            $this->_child->getProperty('data')->updateRow($child_row);
        $done = $engine->endTransaction($done) && $done;

        if ($done) {
            TIP::notifyInfo('done');
        } else {
            TIP::notifyError('fatal');
        }

        return false;
    }

    /**
     * Delete master and child rows
     *
     * @param  array &$row The joined row to delete
     * @return bool        always false, to avoid chaining-up
     *                     the default action
     */
    public function _onDelete(&$row)
    {
        if (is_null($this->_child)) {
            // No child module: chain-up the parent method
            return parent::_onEdit($row);
        }

        $primary_key = $this->data->getProperty('primary_key');
        if (!array_key_exists($primary_key, $row) ||
            is_null($id = $row[$primary_key])) {
            TIP::error("no primary key defined (field $primary_key)");
            return null;
        }

        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be catched here to avoid the rollback
            TIP::notifyError('fatal');
            return false;
        }

        $done = parent::_onDelete($row) &&
            $this->data->deleteRow($id) &&
            $this->_child->getProperty('data')->deleteRow($id);
        $done = $engine->endTransaction($done) && $done;

        if ($done) {
            TIP::notifyInfo('done');
        } else {
            TIP::notifyError('fatal');
        }

        return false;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The child module
     * @var TIP_Content
     * @internal
     */
    private $_child = null;

    /**
     * The "official" TIP_Data object stored by tagStartSummary()
     * @var TIP_Data
     * @internal
     */
    private $_old_data = null;

    //}}}
    //{{{ Internal methods

    /**
     * Check if the child module has the same data engine
     *
     * If the child module does not share the same data engine,
     * the transaction is not applicable and an error is raised.
     *
     * @return bool true on success or false on errors
     * @internal
     */
    private function _validEngine()
    {
        $child_data =& $this->_child->getProperty('data');
        if (!$child_data instanceof TIP_Data) {
            TIP::error('the child module has no data');
            return false;
        }

        $this_engine =& $this->data->getProperty('engine');
        if ($child_data->getProperty('engine') != $this_engine) {
            TIP::error('master and child data must share the same data engine');
            return false;
        }

        return true;
    }

    //}}}
}
?>
