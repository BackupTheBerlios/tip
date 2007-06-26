<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * @package TIP
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

    /**
     * Add a comments form
     *
     * Allows to show an inline form in the middle of a page.
     *
     * If the form is validated, the result is rendered in the page. Also, the
     * cancel button in the invalidated form is removed (it is not useful for
     * inline forms).
     *
     * @param  int  $params The id of the master row
     * @return bool         true on success or false on errors
     */
    protected function tagAdd($params)
    {
        if (empty($params)) {
            TIP::notifyError('noparams');
            return false;
        }

        $options['defaults'][$this->parent_field] = (int) $params;
        $options['buttons'] = TIP_FORM_BUTTON_SUBMIT;
        $options['invalid_render'] = TIP_FORM_RENDER_HERE;
        $options['valid_render'] = TIP_FORM_RENDER_IN_PAGE;
        return $this->actionAdd($options);
    }

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Overrides the default add action, assuring the 'parent_field' has a
     * valid value.
     *
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionAdd($options = array())
    {
        // Check for the default value of 'parent_field' (the parent id)
        if (!isset($options['defaults'], $options['defaults'][$this->parent_field])) {
            // Try to get the parent id from GET or POST
            if (is_null($parent_id = $this->fromGetOrPost($this->parent_field))) {
                return false;
            }
            $options['defaults'][$this->parent_field] = $parent_id;
        }

        return parent::actionAdd($options);
    }

    //}}}
    //{{{ Callbacks

    /**
     * Update statistic on the master module
     *
     * Updates the counter of the linked row in the master module accordling
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
        $id = $comment_row[$this->parent_field];
        if (empty($id)) {
            TIP::notifyError('notfound');
            TIP::warning("master row not specified ($id)");
            return false;
        }

        if (is_null($row =& $this->master->fromRow($id, false))) {
            return false;
        }

        $old_row = $row;
        $row[$this->master_count] += $offset;

        if (!$this->master->getProperty('data')->updateRow($row, $old_row)) {
            TIP::notifyError('update');
            TIP::warning("no way to update comments counter on master row ($id)");
            return false;
        }

        return true;
    }

    /**
     * Provide additional statistic update on the master module
     * @param  array &$row The data row to add
     * @return bool        true on success, false on errors
     */
    public function _onAdd(&$row)
    {
        return $this->_updateMaster($row, +1) && parent::_onAdd($row);
    }

    /**
     * Provide additional statistic update on the master module
     * @param  array &$row The data row to add
     * @return bool        true on success, false on errors
     */
    public function _onDelete(&$row)
    {
        return $this->_updateMaster($row, -1) && parent::_onDelete($row);
    }

    /**
     * Remove all comments linked to a master row
     * @param  array &$row The row deleted from the master module
     * @return bool        true on success, false on errors
     */
    public function _onMasterDelete(&$row)
    {
        $primary_key = $this->master->getProperty('data')->getProperty('primary_key');
        if (!isset($row[$primary_key])) {
            return false;
        }

        $filter = $this->getProperty('data')->filter($this->parent_field, $row[$primary_key]);
        return $this->getProperty('data')->deleteRows($filter);
    }

    //}}}
}
?>
