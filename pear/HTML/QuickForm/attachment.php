<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

require_once 'HTML/QuickForm/input.php';

define('QF_ATTACHMENT_EMPTY', 0);
define('QF_ATTACHMENT_UPLOADED', 1);
define('QF_ATTACHMENT_TO_UPLOAD', 2);
define('QF_ATTACHMENT_TO_UNLOAD', 3);

// Register attachment-related rules
HTML_QuickForm::registerRule('requiredupload', 'callback', '_ruleRequiredUpload', 'HTML_QuickForm_attachment');
HTML_QuickForm::registerRule('uploaded', 'callback', '_ruleUploaded', 'HTML_QuickForm_attachment');
HTML_QuickForm::registerRule('maxfilesize', 'callback', '_ruleMaxFileSize', 'HTML_QuickForm_attachment');
HTML_QuickForm::registerRule('mimetype', 'callback', '_ruleMimeType', 'HTML_QuickForm_attachment');

/**
 * HTML class for a generic attachment
 *
 * @author Nicola Fontana <ntd@entidi.it>
 * @access public
 */
class HTML_QuickForm_attachment extends HTML_QuickForm_input
{
    //{{{ properties

    /**
     * Uploaded data, from $_FILES
     * @var array
     */
    var $_value = null;

    /**
     * Running operation
     * @var QF_ATTACHMENT_...
     */
    var $_state = QF_ATTACHMENT_EMPTY;

    /**
     * Base attachment path
     * @var string
     */
    var $_base_path = '';

    /**
     * Base attachment url
     * @var string
     */
    var $_base_url = '';

    /**
     * The string to prepend on every uploaded file
     * @var string
     */
    var $_prefix = '';

    /**
     * The text to append to every uploaded file
     * @var string
     */
    var $_suffix = null;

    /**
     * The file name of the attachment
     * @var string
     */
    var $_file = null;

    /**
     * Additional widget to manage attachment unloading
     * @var HTML_QuickForm_element
     */
    var $_unload_element = null;

    //}}}
    //{{{ constructor

    /**
     * Class constructor
     *
     * @param  string $elementName   Input field name attribute
     * @param  mixed  $elementLabel  Label(s) for a field
     * @param  mixed  $attributes    Either a typical HTML attribute string or an associative array
     * @access public
     */
    function HTML_QuickForm_attachment($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->setType('file');
        $this->setPersistantFreeze(true);
    } //end constructor

    //}}}
    //{{{ getValue()

    /**
     * Get the file name of the attachment
     *
     * @return string  The attachment file name
     * @access public
     */
    function getValue()
    {
        return $this->_file;
    } //end func getValue

    //}}}
    //{{{ setValue()

    /**
     * Set the attachment
     *
     * $value can be a string specifing the attachment file, for yet uploaded
     * attachments, or an array from $_FILES for uploading attachments.
     *
     * @param  string|array $value  The attachment data
     * @access public
     */
    function setValue($value)
    {
        if ($this->_state == QF_ATTACHMENT_TO_UNLOAD) {
            $this->_file = $value;
            return;
        } elseif (empty($value)) {
            $this->_file = null;
            $this->_state = QF_ATTACHMENT_EMPTY;
        } elseif (is_string($value) && !empty($value)) {
            $this->_file = $value;
        } elseif (is_array($value)) {
            // Check the validity of $value
            if ((!array_key_exists('error', $value) || $value['error'] == 0) &&
                !empty($value['tmp_name']) && $value['tmp_name'] != 'none' &&
                is_uploaded_file($value['tmp_name'])) {
                $this->_state = QF_ATTACHMENT_TO_UPLOAD;
                $this->_value = $value;
            }
        }
    } //end func setValue

    //}}}
    //{{{ getPrefix()

    /**
     * Get the prefix to prepended on every uploaded file
     *
     * @return string  The prefix
     * @access public
     */
    function getPrefix()
    {
        return $this->_prefix;
    } //end func getPrefix

    //}}}
    //{{{ setPrefix()

    /**
     * Set the prefix to prepend on every uploaded file
     *
     * @param  string $prefix  The new prefix
     * @access public
     */
    function setPrefix($prefix)
    {
        $this->_prefix = $prefix;
    } //end func setPrefix

    //}}}
    //{{{ getSuffix()

    /**
     * Get the string to append to the uploaded filename
     *
     * @return string  The suffix or null on errors
     * @access public
     */
    function getSuffix()
    {
        if (is_null($this->_suffix) && is_array($this->_value) &&
            array_key_exists('name', $this->_value)) {
            // Try to set the suffix to the uploaded file extension
            $ext = strrchr($this->_value['name'], '.');
            if ($ext) {
                $this->_suffix = strtolower($ext);
            }
        }

        return $this->_suffix;
    } //end func getSuffix

    //}}}
    //{{{ setSuffix()

    /**
     * Set the suffix to append on every uploaded filename
     *
     * @param  string $suffix  The new suffix
     * @access public
     */
    function setSuffix($suffix)
    {
        $this->_suffix = $suffix;
    } //end func setSuffix

    //}}}
    //{{{ getBasePath()

    /**
     * Get the base upload path
     *
     * @return string  The base upload path
     * @access public
     */
    function getBasePath()
    {
        return $this->_base_path;
    } //end func getBasePath

    //}}}
    //{{{ setBasePath()

    /**
     * Set the base path where uploaded attachments are stored
     *
     * @param  string $path  The base path
     * @access public
     */
    function setBasePath($path)
    {
        if (empty($path)) {
            $path = '';
        } elseif (substr($path, -1) != DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }
        $this->_base_path = $path;
    } //end func setBasePath

    //}}}
    //{{{ getBaseUrl()

    /**
     * Get the base upload url
     *
     * @return string|null  The base upload url
     * @access public
     */
    function getBaseUrl()
    {
        return $this->_base_url;
    } //end func getBaseUrl

    //}}}
    //{{{ setBaseUrl()

    /**
     * Set the base url where attachments are uploaded
     *
     * @param  mixed  $url  The base url
     * @access public
     */
    function setBaseUrl($url)
    {
        if (empty($url)) {
            $url = '';
        } elseif (substr($url, -1) != '/') {
            $url .= '/';
        }
        $this->_base_url = $url;
    } //end func setBaseUrl

    //}}}
    //{{{ setFile()

    /**
     * Set the file name of the attachment
     *
     * Here you can set explicily the file name of the uploaded attachment.
     * Leave it null to have an automatic file name.
     *
     * @param  string|null  The attachment file name
     * @access public
     */
    function setFile($file)
    {
        $this->_file = $file;
    } //end func setFile

    //}}}
    //{{{ getUnloadElement()

    /**
     * Get the unload element
     *
     * Returns a reference to the unload element set by setUnloadElement().
     *
     * @return HTML_QuickForm_element  A quick form element
     * @access public
     */
    function& getUnloadElement()
    {
        return $this->_unload_element;
    } //end func getUnloadElement

    //}}}
    //{{{ setUnloadElement()

    /**
     * Set the unload element
     *
     * The unload element is rendered after this element if the attachment is
     * in uploaded state.
     *
     * @param  HTML_QuickForm_element &$element  A quick form element
     * @access public
     */
    function setUnloadElement(&$element)
    {
        $this->_unload_element =& $element;
        // Unload element value must not be persistant
        $this->_unload_element->setPersistantFreeze(false);
    } //end func setUnloadElement

    //}}}
    //{{{ getState()

    /**
     * Get the current state
     *
     * Returns the current state of this attachment widget.
     *
     * @return int    A QF_ATTACHMENT_... constant
     * @access public
     */
    function& getState()
    {
        return $this->_state;
    } //end func getState

    //}}}
    //{{{ setState()

    /**
     * Set a new state
     *
     * Forces the state of this attachment widget to $state.
     *
     * @param  int    A QF_ATTACHMENT_... constant
     * @access public
     */
    function setState($state)
    {
        $this->_state = $state;
    } //end func setState

    //}}}
    //{{{ doUploads()

    /**
     * Perform the needed uploads/unloads
     *
     * MUST be called after the validation, so invalid attachments are not
     * uploaded, but before the processing, so the uploads/unloads are
     * effectively performed.
     *
     * @param  HTML_QuickForm &$form  The form to check for uploads
     * @access public
     * @static
     */
    function doUploads(&$form)
    {
        foreach (array_keys($form->_elements) as $key) {
            $element =& $form->_elements[$key];
            if (is_a($element, 'HTML_QuickForm_attachment')) {
                $name = $element->getName();
                if ($element->getState() == QF_ATTACHMENT_TO_UPLOAD) {
                    // An element is considered valid if does not have
                    // an error message: quite naive but working...
                    $error_message = $form->getElementError($name);
                    if (empty($error_message)) {
                        $element->_upload();
                        $element->setComment(null);
                    } else {
                        $element->_reset();
                        $form->updateAttributes(array('enctype' => 'multipart/form-data'));
                        $form->setMaxFileSize();
                    }
                } elseif ($element->getState() == QF_ATTACHMENT_TO_UNLOAD) {
                    $element->_unload();
                }

                // This is a really dirty hack to force the value to be the
                // attachment file name
                $form->_submitFiles[$name] = $element->getValue();
            }
        }
    } //end func doUploads

    //}}}
    //{{{ onQuickFormEvent()

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param  string $event   Name of event
     * @param  mixed  $arg     Event arguments
     * @param  object $caller  Calling object
     * @access public
     */
    function onQuickFormEvent($event, $arg, &$caller)
    {
        switch ($event) {

            case 'createElement':
            case 'addElement':
                $class = get_class($this);
                $name = $arg[0];
                $this->$class($arg[0], $arg[1], $arg[2], $arg[3], $arg[4]);
                if (array_key_exists($name, $caller->_submitFiles)) {
                    $caller->_submitFiles[$name]['_qf_element'] =& $this;
                }
                break;

            case 'updateValue':
                if (!is_null($value = $this->_findValue($caller->_constantValues))) {
                    $this->_state = QF_ATTACHMENT_UPLOADED;
                } elseif (!is_null($value = $this->_findUploadedValue())) {
                    $this->_state = QF_ATTACHMENT_TO_UPLOAD;
                } else {
                    $value = $this->_findValue($caller->_submitValues);
                    empty($value) && $value = $this->_findValue($caller->_defaultValues);
                    if ($this->_state != QF_ATTACHMENT_TO_UNLOAD && !empty($value)) {
                        $this->_state = QF_ATTACHMENT_UPLOADED;
                    }
                }
                $this->setValue($value);
                if ($this->_state == QF_ATTACHMENT_EMPTY || $this->_state == QF_ATTACHMENT_TO_UNLOAD) {
                    $caller->updateAttributes(array('enctype' => 'multipart/form-data'));
                    $caller->setMaxFileSize();
                }
                break;
        }

        return true;
    } // end func onQuickFormEvent

    //}}}
    //{{{ toHtml()

    /**
     * Returns the attachment element in HTML
     *
     * @return string  The html text
     * @access public
     */
    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } elseif ($this->_state == QF_ATTACHMENT_UPLOADED) {
            // Return the frozen html ...
            $html = $this->getFrozenHtml();
            // ... and append the html of the unload element (if any)
            if (isset($this->_unload_element)) {
                $html .= '&nbsp;' . $this->_unload_element->toHtml();
            }
            return $html;
        }

        return HTML_QuickForm_input::toHtml();
    } //end func toHtml

    //}}}
    //{{{ getFrozenHtml()

    /**
     * Returns the inline object
     *
     * @return string  The frozen html text
     * @access public
     */
    function getFrozenHtml()
    {
        $html = '';
        if (isset($this->_file)) {
            $src = $this->_base_url . $this->_file;
            $txt = $this->getComment();
            isset($txt) || $txt = $this->_file;
            $html .= "<a href=\"$src\">$txt</a>";
        }
        return $html . $this->_getPersistantData();
    } //end func getFrozenHtml

    //}}}
    //{{{ getTmpFile()

    /**
     * Build a temporary file name honoring prefix and suffix properties
     *
     * @return string|null  The file name or null on errors
     * @access protected
     */
    function getTmpFile()
    {
        if (is_null($prefix = $this->getPrefix()) ||
            is_null($suffix = $this->getSuffix())) {
            return null;
        }

        // Try to create a unique file in the base path
        $attempts = 5;
        do {
            -- $attempts;
            if ($attempts < 0) {
                return null;
            }
            $file = uniqid($prefix) . $suffix;
        } while (!($handle = fopen($this->_base_path . $file, 'xb')));

        fclose($handle);
        return $file;
    } // end func getTmpFile

    //}}}
    //{{{ _reset()

    /**
     * Reset the attachment internal state to its (empty) default
     *
     * @return boolean   true on success, false otherwise
     * @access protected
     */
    function _reset()
    {
        $this->_state = QF_ATTACHMENT_EMPTY;
        $this->_file = null;
        $this->_value = '';
        return true;
    } // end func _reset

    //}}}
    //{{{ _upload()

    /**
     * Upload the attachment
     *
     * @return bool    Whether the attachment was uploaded successfully
     * @access private
     */
    function _upload()
    {
        $file = empty($this->_file) ? $this->getTmpFile() : $this->_file;

        // File upload
        if (!$file || !$this->_realUpload($this->_value['tmp_name'], $file)) {
            $this->_reset();
            return false;
        }

        $this->_state = QF_ATTACHMENT_UPLOADED;
        $this->_file = $file;
        return true;
    } // end func _upload

    //}}}
    //{{{ _realUpload()

    /**
     * The real upload work
     *
     * @param  string    $tmp  The source temporary file
     * @param  string    $file The destination file name (only the name)
     * @return bool            true on success, false otherwise
     * @access protected
     */
    function _realUpload($tmp, $file)
    {
        return move_uploaded_file($tmp, $this->_base_path . $file);
    } // end func _realUpload

    //}}}
    //{{{ _unload()

    /**
     * Unload the attachment
     *
     * This is the reverse operation of _upload().
     *
     * @return bool    Whether the attachment was unloaded successfully
     * @access private
     */
    function _unload()
    {
        if (!empty($this->_file) && !$this->_realUnload($this->_file)) {
            return false;
        }

        return $this->_reset();
    } // end func _unload

    //}}}
    //{{{ _realUnload()

    /**
     * The real unloading method
     *
     * @param  string    $file The uploaded file name (only the name)
     * @return bool            true on success, false otherwise
     * @access protected
     */
    function _realUnload($file)
    {
        @unlink($this->_base_path . $file);
        return true;
    } // end func _realUnload

    //}}}
    //{{{ _findUploadedValue

    /**
     * Try to find the element value from $_FILES
     *
     * Directly stolen from HTML_QuickForm_file.
     *
     * @return array|null  An array as in $_FILES or null if not found
     * @access private
     */
    function _findUploadedValue()
    {
        if (empty($_FILES)) {
            return null;
        }

        $name = $this->getName();
        if (array_key_exists($name, $_FILES)) {
            return $_FILES[$name];
        } elseif (false !== ($pos = strpos($name, '['))) {
            $base  = str_replace(
                        array('\\', '\''), array('\\\\', '\\\''),
                        substr($name, 0, $pos)
                    );
            $idx   = "['" . str_replace(
                        array('\\', '\'', ']', '['), array('\\\\', '\\\'', '', "']['"),
                        substr($name, $pos + 1, -1)
                     ) . "']";
            $props = array('name', 'type', 'size', 'tmp_name', 'error');
            $code  = "if (!isset(\$_FILES['{$base}']['name']{$idx})) {\n" .
                     "    return null;\n" .
                     "} else {\n" .
                     "    \$value = array();\n";
            foreach ($props as $prop) {
                $code .= "    \$value['{$prop}'] = \$_FILES['{$base}']['{$prop}']{$idx};\n";
            }
            return eval($code . "    return \$value;\n}\n");
        }

        return null;
    } //end func _findUploadedValue

    //}}}
    //{{{ _ruleRequiredUpload()

    /**
     * Check if this attachment is uploaded
     *
     * @param  array   $value Value as returned by HTML_QuickForm_attachment::getValue()
     * @return bool           true if file has been uploaded, false otherwise
     * @access private
     */
    function _ruleRequiredUpload($value)
    {
        if (!is_array($value)) {
            $file = $value;
        } elseif (array_key_exists('name', $value)) {
            $file = $value['name'];
        }
        return isset($file);
    } //end func _ruleRequiredUpload

    //}}}
    //{{{ _ruleUploaded()

    /**
     * Check if the upload (if performed) was succesful
     *
     * @param  array   $value Value as returned by HTML_QuickForm_attachment::getValue()
     * @return bool           true on success, false on upload errors
     * @access private
     */
    function _ruleUploaded($value)
    {
        if (!is_array($value)) {
            return true;
        }

        $name = array_key_exists('name', $value) ? $value['name'] : null;
        $tmp_name = array_key_exists('tmp_name', $value) ? $value['tmp_name'] : null;
        return empty($name) && empty($tmp_name) ||
            !empty($name) && !empty($tmp_name) && is_uploaded_file($tmp_name);
    } //end func _ruleUploaded

    //}}}
    //{{{ _ruleMaxFileSize()

    /**
     * Check that the attachment does not exceed the max file size
     *
     * @param  array   $value Value as returned by HTML_QuickForm_attachment::getValue()
     * @param  int     $size  Max file size
     * @return bool           true if file is smaller than $size, false otherwise
     * @access private
     */
    function _ruleMaxFileSize($value, $size)
    {
        if (!is_array($value) || !array_key_exists('tmp_name', $value)) {
            // No recent upload done
            return true;
        }

        // Return false ONLY if tmp_name exists and exceeds $size
        return @filesize($value['tmp_name']) <= $size;
    } // end func _ruleMaxFileSize

    //}}}
    //{{{ _ruleMimeType()

    /**
     * Check if the given element contains an uploaded file of the right mime type
     *
     * @param  array        $value Value as returned by HTML_QuickForm_attachment::getValue()
     * @param  string|array $type  Mime type[s]
     * @return bool                true if mimetype is correct, false otherwise
     * @access private
     */
    function _ruleMimeType($value, $type)
    {
        if (!is_array($value) || !array_key_exists('tmp_name', $value)) {
            // No recent upload done
            return true;
        }

        if (is_array($type)) {
            return in_array($value['type'], $type);
        }
        return $value['type'] == $type;
    } // end func _ruleMimeType

    //}}}
} //end class HTML_QuickForm_attachment
?>
