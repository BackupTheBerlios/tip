<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

require_once 'HTML/QuickForm.php';

/**
 * @package TIP
 */

/**
 * Form generator
 *
 * @final
 * @package TIP
 */
class TIP_Form extends TIP_Module
{
    /**#@+ @access private */

    var $_block = null;
    var $_form = null;
    var $_row = null;


    function& _addEnum(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        $items = array_flip($field['choices']);
        array_walk($items, array(&$this->_block, 'localize'), array($id . '_', '_label'));

        // On lot of available choices, use a select menu
        if (count($field['choices']) > 3) {
            return $this->_form->addElement('select', $id, $label, $items);
        }

        // On few available choices, use radio button
        $group = array();
        foreach ($items as $i_value => $i_label) {
            $item =& $this->_form->createElement('radio', $id, $label, $i_label, $i_value);
            $group[] =& $item;
        }
        return $this->_form->addElement('group', $id, $label, $group, ' &nbsp; ', false);
    }

    function& _addSet(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');
        $items = array_flip($field['choices']);
        array_walk($items, array(&$this->_block, 'localize'), $id . '_label');

        $group = array();
        foreach ($items as $i_value => $i_label) {
            $item =& $this->_form->createElement('advcheckbox', $id, $label, $i_label);
            $group[] =& $item;
        }

        return $this->_form->addElement('group', $id, $label, $group, ' &nbsp; ');
    }

    function& _addGeneric(&$field)
    {
        $id = $field['id'];
        $label = $this->_block->getLocale($id . '_label');

        if (strpos(strtolower($id), 'password') !== false) {
            $element =& $this->_form->addElement('password', $id, $label);

            $reid = 're' . $id;
            $relabel = $this->_block->getLocale($reid . '_label');
            $slave =& $this->_form->addElement('password', $reid, $relabel);
            if ($field['length'] > 0) {
                $slave->setMaxLength($field['length']);
            }

            $this->_addRule($reid, 'required');
            $message = $this->getLocale('repeat');
            $this->_form->addRule(array($reid, $id), $message, 'compare', null);
            return $element;
        }

        return $this->_form->addElement('text', $id, $label);
    }

    function _addRule($element, $type, $format = '')
    {
        $message = $this->getLocale($type);
        $this->_form->addRule($element, $message, $type, $format);
    }

    function _onProcess($row)
    {
        $primary_key = $this->_block->data->primary_key;
        $this->_block->data->forceFieldType($row);

        $id = @$row[$primary_key];
        if (is_null($id)) {
            // Put operation
            $this->_block->data->putRow($row);
        } else {
            // Update operation
            $this->_block->data->updateRow($id, $row);
        }
    }

    /**#@-*/


    /**#@+ @access protected */

    function TIP_Form()
    {
        $this->TIP_Module();
        $this->on_process =& $this->callback('_onProcess');
    }

    /**#@-*/


    /**#@+ @access public */

    var $on_process = null;


    function make(&$block, &$row)
    {
        $this->_block =& $block;
        $this->_form =& new HTML_QuickForm($block->data->path);
        $this->_row =& $row;

        $application =& $GLOBALS[TIP_MAIN_MODULE];
        $fields =& $block->data->getFields();
        $primary_key = $block->data->primary_key;

        $this->_form->removeAttribute('name'); // XHTML compliance

        $header = $block->getLocale(is_null($row) ? 'add_header' : 'edit_header');
        $this->_form->addElement('header', 'PageHeader', $header);
        $this->_form->addElement('hidden', 'module', $this->_block->getName());
        $this->_form->addElement('hidden', 'action', $application->keys['ACTION']);
        if (array_key_exists($primary_key, $row)) {
            $this->_form->addElement('hidden', $primary_key, $row[$primary_key]);
        }

        foreach (array_keys($fields) as $id) {
            if (substr($id, 0, 1) == '_') {
                continue;
            }

            $field =& $fields[$id];
            if ($field['automatic']) {
                continue;
            }

            $method = '_add' . @$field['subtype'];
            if (! method_exists($this, $method)) {
                $method = '_addGeneric';
            }

            $element =& $this->$method($field);
            if (is_null($element)) {
                continue;
            }

            $maxlength = $field['length'];
            if ($maxlength > 0) {
                if (method_exists($element, 'setMaxLength')) {
                    $element->setMaxLength($maxlength);
                }
                $this->_addRule($id, 'maxlength', $field['length']);
            }

            if (is_numeric($field['default'])) {
                $this->_addRule($id, 'numeric');
            }

            if ($field['info'] == 'required') {
                $this->_addRule($id, 'required');
            }
        }

        $this->_form->applyFilter('__ALL__', 'trim');
        return true;
    }

    function process()
    {
        if ($this->_form->validate()) {
            if (array_key_exists('processing', $_COOKIE)) {
                $this->_form->process(array(&$this->on_process, 'go'));
                //$this->_form->process($this->on_process->_callback);
                setcookie('processing', '', time()-3600);
                TIP::info('I_DONE');
            }

            return $this->freeze();
        } else {
            setcookie('processing', 'true', time()+3600);
            $this->_form->addElement('submit', null, 'Conferma');

            if (is_array($this->_row)) {
                // Set the default values from the given row
                $defaults =& $this->_row;
            } else {
                // Set the default values with the defaults from TIP_Data
                $fields =& $this->_block->data->getFields();
                $defaults = array_map(create_function('&$f', 'return $f["default"];'), $fields);
            }

            $this->_form->setDefaults($defaults);
        }

        return true;
    }

    function freeze()
    {
        $this->_form->freeze();
        return true;
    }

    function render()
    {
        $this->_form->display();
    }

    /**#@-*/
}

return 'TIP_Form';

?>
