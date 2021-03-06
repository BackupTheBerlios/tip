<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Comments definition file
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

/**
 * Comment module
 *
 * @package TIP
 */
class TIP_Comments extends TIP_Content
{
    //{{{ Properties

    /**
     * A reference to the master module
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The field to be joined to the master primary key
     * @var string
     */
    protected $parent_field = '_parent';

    /**
     * The field of 'master' that holds the comment counter
     * @var string
     */
    protected $master_count = '_comments';

    //}}}
    //{{{ Constructor/destructor

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['master'])) {
            return false;
        }

        isset($options['statistics']) || $options['statistics'] = array(
            '_onAdd'    => '_comments',
            '_onEdit'   => null,
            '_onDelete' => '_deleted_comments'
        );

        if (is_string($options['master'])) {
            $options['master'] =& TIP_Type::getInstance($options['master']);
        } elseif (is_array($options['master'])) {
            $options['master'] =& TIP_Type::singleton($options['master']);
        }

        return $options['master'] instanceof TIP_Content;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Comments instance.
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
     * @param      string       $params Parameters of the tag
     * @return     string|null          The string result or null
     */

    /**
     * Add a comments form
     *
     * Allows to show an inline form in the middle of a page.
     *
     * If the form is validated, the result is rendered in the page. Also, the
     * cancel button in the invalidated form is removed (it is not useful for
     * inline forms). $params must contain the id of the master row.
     */
    protected function tagAdd($params)
    {
        if ($this->privilege < TIP_PRIVILEGE_UNTRUSTED) {
            // Privilege level too low: return empty rendering result
            return '';
        } elseif (empty($params)) {
            // No param id specified
            TIP::notifyError('noparams');
            return null;
        }

        // Check for default options defined in configuration
        if (isset($this->form_options['tagadd'])) {
            $options = $this->form_options['tagadd'];
        } else {
            $options = array();
        }

        $options['defaults'][$this->browse_field] = (int) $params;

        TIP::arrayDefault($options, 'buttons', TIP_FORM_BUTTON_SUBMIT);
        TIP::arrayDefault($options, 'invalid_render', TIP_FORM_RENDER_HERE);
        TIP::arrayDefault($options, 'valid_render', TIP_FORM_RENDER_IN_PAGE);

        ob_start();
        if ($this->actionAdd(null, $options)) {
            return ob_get_clean();
        }

        ob_end_clean();
        return null;
    }

    /**#@-*/

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Overrides the default add action, assuring the 'browse_field' has a
     * valid value.
     *
     * @param  mixed $id      The identifier of the row to duplicate
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionAdd($id, $options = array())
    {
        // Merge the argument before the parent actionAdd(), so also
        // the defaults here defined can be overriden in configuration
        if (isset($this->form_options['add'])) {
            $options = array_merge($this->form_options['add'], $options);
        }

        // Check for the default value of 'browse_field' (the parent id)
        if (!isset($options['defaults'], $options['defaults'][$this->browse_field])) {
            // Try to get the parent id from GET or POST
            if (is_null($parent_id = $this->fromGetOrPost($this->browse_field))) {
                return false;
            }
            $options['defaults'][$this->browse_field] = $parent_id;
        } else {
            $parent_id = $options['defaults'][$this->browse_field];
        }

        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->master, 'view', $parent_id));

        return parent::actionAdd($id, $options);
    }

    //}}}
    //{{{ Callbacks

    /**
     * Update statistic on the master module
     *
     * Updates the counter of the linked row in the master module according
     * to the specified $offset.
     *
     * This is an high level method that raises errors and notify them to the
     * user on any errors.
     *
     * @param  array &$comment_row The comment row
     * @param  int    $offset      The offset of the counter (+1 add, -1 delete)
     * @return bool                true on success or false on errors
     */
    private function _updateMaster(&$comment_row, $offset)
    {
        if (!isset($this->master_count)) {
            // No count field to update
            return true;
        }

        $id = @$comment_row[$this->parent_field];
        if (empty($id)) {
            // No master row specified: don't update anything
            return true;
        }

        if (is_null($row =& $this->master->fromRow($id, false))) {
            // Error: master row specified but not found
            return false;
        }

        if (!array_key_exists($this->master_count, $row)) {
            // No count field found in master: don't update anything
            return true;
        }

        $old_row = $row;
        $row[$this->master_count] += $offset;
        if (!$this->master->getProperty('data')->updateRow($row, $old_row)) {
            return false;
        }

        return true;
    }

    /**#@+
     * This callback is transaction protected to avoid data corruptions.
     *
     * @param  array      &$row     The subject row
     * @param  array|null  $old_row The old row or null on no old row
     * @return bool                 true on success or false on error
     */

    /**
     * Provide additional statistic update on the master module
     */
    public function _onAdd(&$row, $old_row)
    {
        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        // Process the row
        $done = parent::_onAdd($row, $old_row) &&
            $this->_updateMaster($row, +1);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**
     * Provide additional statistic update on the master module
     */
    public function _onDelete(&$row, $old_row)
    {
        $engine = &$this->data->getProperty('engine');
        if (!$engine->startTransaction()) {
            // This error must be caught here to avoid the rollback
            return false;
        }

        // Process the row
        $done = parent::_onDelete($row, $old_row) &&
            $this->_updateMaster($row, -1);
        $done = $engine->endTransaction($done) && $done;

        return $done;
    }

    /**#@-*/

    /**
     * Remove all comments linked to a master row
     *
     * @param  array      &$row     The subject row
     * @param  array|null  $old_row The old row or null on no old row
     * @return bool                 true on success or false on error
     */
    public function _onMasterDelete(&$row, $old_row)
    {
        $data =& $this->getProperty('data');
        $master_data =& $this->master->getProperty('data');
        $primary_key = $master_data->getProperty('primary_key');
        if (!isset($row[$primary_key])) {
            return false;
        }

        $filter = $data->filter($this->parent_field, $row[$primary_key]);
        return $data->deleteRows($filter);
    }

    //}}}
}
?>
