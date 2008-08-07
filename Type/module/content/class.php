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
 * @since     0.0.1
 */

/**
 * Class module
 *
 * @package TIP
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
     * The field in the implementation content to be joined
     * to the class primary key
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
    }

    //}}}
    //{{{ Actions

    /**
     * Perform an add action
     *
     * Overrides the default add action, chaining the child module
     * form if the class form validates.
     *
     * @param  array $options Options to pass to the form() call
     * @return bool           true on success or false on errors
     */
    protected function actionAdd($options = array())
    {
        // Merge the argument options with the configuration options, if found
        // The argument options have higher priority...
        if (@is_array($this->form_options['add'])) {
            $options = array_merge($this->form_options['add'], (array) $options);
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
                // Child module found: chain-up the child form
                $valid = $form->validateAlso($this->_child);
            }
        }
        if ($valid)
            $form->process();

        return $form->render($valid);
    }

    //}}}
    //{{{ Internal properties

    /**
     * The child module
     * @var TIP_Content
     * @internal
     */
    private $_child = null;

    //}}}
    //{{{ Callbacks

    /**
     * Save both class and child rows
     *
     * TODO: this operation must be transaction protected!
     *
     * @param  array &$row The joined row to add
     * @return bool        true to chain-up the default action, false otherwise
     */
    public function _onAdd(&$row)
    {
        if (is_null($this->_child)) {
            // No child module: fallback to the default behaviour
            return true;
        }

        // Save the row, also because putRow() is destructive
        $child_data =& $this->_child->getProperty('data');
        $child_row = $row;

        $processed = $this->data->putRow($row);
        if ($processed) {
            $child_row[$this->master_field] = $this->data->getLastId();
            $processed = $processed && $child_data->putRow($child_row);
        }

        if ($processed) {
            TIP::notifyInfo('done');
        } else {
            TIP::notifyError('fatal');
        }

        return false;
    }

    /**
     * Provide additional statistic update on the master module
     * @param  array &$row The data row to add
     * @return bool        false, to avoid chaining the default method
     */
    public function _onDelete(&$row)
    {
        /* TODO */
        return false;
    }

    //}}}
}
?>
