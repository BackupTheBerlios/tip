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

        TIP::arrayDefault($options, 'on_process', false);
        TIP::arrayDefault($options, 'follower', TIP::buildActionUri($this->id, 'view', '-lastid-'));

        $options['type']   = array('module', 'form');
        $options['master'] =& $this;
        $options['action'] = TIP_FORM_ACTION_ADD;

        $form =& TIP_Type::singleton($options);

        $form->populate();
        $processed = $form->process(1);
        if ($processed) {
            $child_name = $this->id . '-' . TIP::getPost($this->class_field, 'string');
            if ($child =& TIP_Type::getInstance($child_name, false)) {
                // Child module found: chain-up the child form
                $form->append($child);
                $processed = $form->process(null);
            }
        }

        return $form->render($processed);
    }

    //}}}
    //{{{ Internal properties

    /**
     * The validated class row
     * @var array
     * @internal
     */
    private $_class_row = null;

    //}}}
    //{{{ Callbacks

    /**
     * Chain-up the child form
     * @param  array &$row The validated class row to add
     * @return bool        true on success, false on errors
     */
    public function _onClassAdd(&$row)
    {
        $field = $this->class_field;
        if (!array_key_exists($field, $row) || empty($row[$field])) {
            TIP::error("undefined class (field '$field')");
            return false;
        }

        $child_name = $this->id . '-' . $row[$field];
        if ($child =& TIP_Type::getInstance($child_name, false)) {
            // Child module found: chain-up the child form
            $options = array(
                'on_process', array(&$this, '_onAdd')
            );
            return $child->actionAdd($options);
        }

        // Child module not found: fallback to the default behaviour
        return parent::_onAdd($row);
    }

    /**
     * Save both class and child rows
     * @param  array &$row The child row to add
     * @return bool        true on success, false on errors
     */
    public function _onAdd(&$row)
    {
        /* TODO */
        return true;
        return parent::_onAdd($row);
    }

    /**
     * Provide additional statistic update on the master module
     * @param  array &$row The data row to add
     * @return bool        true on success, false on errors
     */
    public function _onDelete(&$row)
    {
        /* TODO */
        return true;
        return parent::_onDelete($row);
    }

    //}}}
}
?>
