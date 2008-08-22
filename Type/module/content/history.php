<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_History definition file
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
 * @since     0.3.1
 */

/**
 * History module
 *
 * @package TIP
 */
class TIP_History extends TIP_Content
{
    //{{{ Properties

    /**
     * A reference to the master module
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The field where the id of the **first** row version is stored
     * @var string
     */
    protected $origin_field = '_origin';

    /**
     * The field where the id of the next row version is stored
     * @var string
     */
    protected $next_field = '_next';

    //}}}
    //{{{ Constructor/destructor

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['master'])) {
            return false;
        }

        if (is_string($options['master'])) {
            $options['master'] =& TIP_Type::getInstance($options['master']);
        } elseif (is_array($options['master'])) {
            $options['master'] =& TIP_Type::singleton($options['master']);
        }

        if (!$options['master'] instanceof TIP_Content) {
            return false;
        }

        // Check if the master module has the same data engine:
        // this is required to be able to use transactions
        $master_data =& $options['master']->getProperty('data');
        $this_engine =& $options['data']->getProperty('engine');
        return $master_data->getProperty('engine') == $this_engine;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_History instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Actions

    /**
     * Perform an update action
     *
     * Generates and executes a TIP_Form instance to update a new row,
     * that is duplicating a row and adding history management so the
     * new row will be a considered as a new version of the duplicated one.
     *
     * @param  mixed $id      The identifier of the row to update
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionUpdate($id, $options = array())
    {
        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['update'])) {
            $options = array_merge($this->form_options['update'], $options);
        }

        // Enable the history by setting _row_id
        $this->_row_id = $id;

        TIP::arrayDefault($options, 'action_id', 'update');
        $done = $this->master->actionAdd($id, $options);

        // Disable the history
        unset($this->_row_id);

        return $done;
    }

    /**
     * Perform a delete action
     *
     * Overrides the default delete action by calling the master
     * delete action.
     *
     * @param  mixed $id      The identifier of the row to delete
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionDelete($id, $options = array())
    {
        if (@is_array($this->form_options['delete'])) {
            $options = array_merge($this->form_options['delete'], $options);
        }

        return $master->actionDelete($id, $options);
    }

    protected function runTrustedAction($action)
    {
        switch ($action) {

        case 'update':
            return
                !is_null($id = $this->fromGetOrPost()) &&
                $this->actionUpdate($id);
        }

        return parent::runTrustedAction($action);
    }

    //}}}
    //{{{ Callbacks

    /**
     * Update the history on a master row update
     *
     * Adds the new row in the history of $_row_id. If $_row_id is not
     * set this callback does nothing, allowing a normal edit operation.
     *
     * @param  array &$row The row to be updated from the master module
     * @return bool        true on success, false on errors
     */
    public function _onMasterEdit(&$row)
    {
        if (is_null($this->_row_id)) {
            // History management disabled
            return true;
        }

        // Get the previous version row
        $query = $this->data->rowFilter($this->_row_id);
        if (!$view =& $this->startDataView($query)) {
            return false;
        }
        $previous_row = $view->current();
        $this->endView();
        if (empty($previous_row)) {
            TIP::error("no previous row to update ($this->_row_id)");
            return false;
        }

        $master_data =& $this->master->getProperty('data');
        $id = $row[$master_data->getProperty('primary_key')];
        $primary_key = $this->data->getProperty('primary_key');

        // Build the current version row
        $new_row[$primary_key] = $id;
        $new_row[$this->origin_field] = $previous_row[$this->origin_field];
        $new_row[$this->next_field] = $previous_row[$this->next_field];
        $this->setMagicFields($new_row);

        // Update the next_field of previous_row
        $new_previous_row = $previous_row;
        $new_previous_row[$this->next_field] = $id;

        $engine =& $this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be catched here to avoid the rollback
            TIP::notifyError('fatal');
            return false;
        }

        // Perform the operations
        $done = $this->data->putRow($new_row) &&
            $this->data->updateRow($new_previous_row, $previous_row);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**
     * Update the history on a master row deletion
     *
     * Updates the linked list by skipping the deleted history row
     * before deleting the row itsself.
     *
     * @param  array &$row The row to be deleted from the master module
     * @return bool        true on success, false on errors
     */
    public function _onMasterDelete(&$row)
    {
        $master_data =& $this->master->getProperty('data');
        $id = $row[$master_data->getProperty('primary_key')];

        // Get the current version row
        $query = $this->data->rowFilter($id);
        if (!$view =& $this->startDataView($query)) {
            return false;
        }
        $current_row = $view->current();
        $this->endView();
        if (empty($current_row)) {
            // No history found: return operation done (just in case...)
            return true;
        }

        // Get the previous version row
        $query = $this->data->filter($this->next_field, $id);
        if (!$view =& $this->startDataView($query)) {
            return false;
        }
        $previous_row = $view->current();
        $this->endView();

        if (empty($previous_row)) {
            // Previous row not found: simply delete current_row
            return $this->data->deleteRow($id);
        }

        $engine =& $this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be catched here to avoid the rollback
            TIP::notifyError('fatal');
            return false;
        }

        // Update the next_field of previous_row
        $new_previous_row = $previous_row;
        $new_previous_row[$this->next_field] = $current_row[$this->next_field];

        // Perform the operations
        $done = $this->data->deleteRow($id) &&
            $this->data->updateRow($new_previous_row, $previous_row);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The row id
     *
     * If not null, it specifies the row to be updated and the
     * _onMasterAdd() callback will generate the properly history.
     *
     * @var mixed
     * @internal
     */
    private $_row_id = null;

    //}}}
}
?>
