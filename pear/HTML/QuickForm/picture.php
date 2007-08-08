<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

require_once 'HTML/QuickForm/input.php';

define('QF_PICTURE_EMPTY', 0);
define('QF_PICTURE_UPLOADED', 1);
define('QF_PICTURE_TO_UPLOAD', 2);
define('QF_PICTURE_TO_UNLOAD', 3);

// Register picture-related rules
HTML_QuickForm::registerRule('uploadedpicture', 'callback', '_ruleUploadedPicture', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('maxfilesize', 'callback', '_ruleCheckMaxFileSize', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('mimetype', 'callback', '_ruleCheckMimeType', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('minpicturesize', 'callback', '_ruleMinPictureSize', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('maxpicturesize', 'callback', '_ruleMaxPictureSize', 'HTML_QuickForm_picture');

if (!function_exists('image_type_to_extension')) {
    /**
     * Get file extension for image type
     *
     * Returns the extension for the given IMAGETYPE_... constant.
     *
     * @param  int     $imagetype   One of the IMAGETYPE_... constant
     * @param  boolean $include_dot Must the result prepended with a dot?
     * @return string               The guessed extension
     */
    function image_type_to_extension($imagetype, $include_dot = true)
    {
        switch ($imagetype) {
        case IMAGETYPE_PNG:
            $ext = 'png';
            break;
        case IMAGETYPE_JPEG:
            $ext = 'jpg';
            break;
        case IMAGETYPE_GIF:
            $ext = 'gif';
            break;
        case IMAGETYPE_BMP:
        case IMAGETYPE_WBMP:
            $ext = 'bmp';
            break;
        case IMAGETYPE_TIFF_II:
        case IMAGETYPE_TIFF_MM:
            $ext = 'tif';
            break;
        case IMAGETYPE_SWF:
            $ext = 'swf';
            break;
        case IMAGETYPE_PSD:
            $ext = 'psd';
            break;
        case IMAGETYPE_JPC:
            $ext = 'jpc';
            break;
        case IMAGETYPE_JP2:
            $ext = 'jp2';
            break;
        case IMAGETYPE_JPX:
            $ext = 'jpx';
            break;
        case IMAGETYPE_JB2:
            $ext = 'jb2';
            break;
        case IMAGETYPE_SWC:
            $ext = 'swc';
            break;
        case IMAGETYPE_IFF:
            $ext = 'iff';
            break;
        case IMAGETYPE_XBM:
            $ext = 'xbm';
            break;
        default:
            // No dot prepending for unknown types
            return '';
        }

        return $include_dot ? '.' . $ext : $ext;
    }
}

/**
 * HTML class for a generic picture based field
 * 
 * @author  Nicola Fontana <ntd@users.sourceforge.net>
 * @access  public
 */
class HTML_QuickForm_picture extends HTML_QuickForm_input
{
    // {{{ properties
 

    /**
     * Uploaded data, from $_FILES
     * @var array 
     */
    var $_value = null;
 
    /**
     * Running operation
     * @var array 
     */
    var $_state = QF_PICTURE_EMPTY;
 
    /**
     * Base picture path
     * @var string
     */
    var $_base_path = '';

    /**
     * Base picture url
     * @var string
     */
    var $_base_url = '';

    /**
     * The file name of the picture
     * @var string
     */
    var $_file = null;

    /**
     * The info array of the uploading picture, as returned by getimagesize()
     * @var array
     */
    var $_info = null;

    /**
     * Additional widget to manage picture unloading
     * @var HTML_QuickForm_element
     */
    var $_unload_element = null;

    // }}}
    // {{{ constructor


    /**
     * Class constructor
     * 
     * @param  string $elementName   Input field name attribute
     * @param  mixed  $elementLabel  Label(s) for a field
     * @param  mixed  $attributes    Either a typical HTML attribute string or an associative array
     * @access public
     */
    function HTML_QuickForm_picture($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->setType('file');
        $this->_persistantFreeze = true;
    } //end constructor
    
    // }}}
    // {{{ getValue()
 
    
    /**
     * Get the file name of the picture
     *
     * @return string  The picture file name
     * @access public
     */
    function getValue()
    {
        return $this->_file;
    } //end func getValue
    
    // }}}
    // {{{ setValue()
 
    
    /**
     * Set the picture
     *
     * $value can be a string specifing the picture file, for yet uploaded
     * pictures, or an array from $_FILES for uploading pictures.
     *
     * @param  string|array $value  The picture data
     * @access public
     */
    function setValue($value)
    {
        if ($this->_state == QF_PICTURE_TO_UNLOAD) {
            return;
        } elseif (empty($value)) {
            $this->_file = null;
            $this->_state = QF_PICTURE_EMPTY;
        } elseif (is_string($value) && !empty($value)) {
            $this->_file = $value;
        } elseif (is_array($value)) {
            // Check the validity of $value
            if ((!isset($value['error']) || $value['error'] == 0) &&
                !empty($value['tmp_name']) && $value['tmp_name'] != 'none' &&
                is_uploaded_file($value['tmp_name'])) {
                $this->_state = QF_PICTURE_TO_UPLOAD;
                $this->_value = $value;
            }
        }
    } //end func setValue
    
    // }}}
    // {{{ getBasePath()
 
    
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
    
    // }}}
    // {{{ setBasePath()
 
    
    /**
     * Set the base path where uploaded pictures are stored
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
    
    // }}}
    // {{{ getBaseUrl()
 
    
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
    
    // }}}
    // {{{ setBaseUrl()
 
    
    /**
     * Set the base url where pictures are uploaded
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
    
    // }}}
    // {{{ setFile()
 
    
    /**
     * Set the file name of the picture
     *
     * Here you can set explicily the file name of the uploaded picture.
     * Leave it null to have an automatic file name.
     * 
     * @param  string|null  The picture file name
     * @access public
     */
    function setFile($file)
    {
        $this->_file = $file;
    } //end func setFile
    
    // }}}
    // {{{ getUnloadElement()
 
    
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
    
    // }}}
    // {{{ setUnloadElement()
 
    
    /**
     * Set the unload element
     *
     * The unload element is rendered after this element if the picture is
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
    
    // }}}
    // {{{ getState()
 
    
    /**
     * Get the current state
     *
     * Returns the current state of this picture widget.
     *
     * @return int    A STATE_... constant
     * @access public
     */
    function& getState()
    {
        return $this->_state;
    } //end func getState
    
    // }}}
    // {{{ setState()
 
    
    /**
     * Set a new state
     *
     * Forces the state of this picture widget to $state.
     *
     * @param  int    A STATE_... constant
     * @access public
     */
    function setState($state)
    {
        $this->_state = $state;
    } //end func setState
    
    // }}}
    // {{{ doUploads()


    /**
     * Perform the needed uploads/unloads
     *
     * MUST be called after the validation, so invalid pictures are not
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
            if ($element instanceof HTML_QuickForm_picture) {
                $name = $element->getName();
                if ($element->getState() == QF_PICTURE_TO_UPLOAD) {
                    // An element is considered valid if does not have an error message:
                    // not ever right but it is a good starting point ...
                    $error_message = $form->getElementError($name);
                    if (empty($error_message)) {
                        $element->_upload();
                    } else {
                        $element->_reset();
                        $form->updateAttributes(array('enctype' => 'multipart/form-data'));
                        $form->setMaxFileSize();
                    }
                } elseif ($element->getState() == QF_PICTURE_TO_UNLOAD) {
                    $element->_unload();
                }

                // This is a really dirty hack to force the value to be the
                // picture file name
                $form->_submitFiles[$name] = $element->getValue();
            }
        }
    } //end func doUploads

    // }}}
    // {{{ onQuickFormEvent()
 
    
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
                    $this->_state = QF_PICTURE_UPLOADED;
                } elseif (!is_null($value = $this->_findUploadedValue())) {
                    $this->_state = QF_PICTURE_TO_UPLOAD;
                } elseif ($this->_state != QF_PICTURE_TO_UNLOAD) {
                    $value = $this->_findValue($caller->_submitValues);
                    empty($value) && $value = $this->_findValue($caller->_defaultValues);
                    empty($value) || $this->_state = QF_PICTURE_UPLOADED;
                }
                $this->setValue($value);
                if ($this->_state == QF_PICTURE_EMPTY || $this->_state == QF_PICTURE_TO_UNLOAD) {
                    $caller->updateAttributes(array('enctype' => 'multipart/form-data'));
                    $caller->setMaxFileSize();
                }
                break;
        }

        return true;
    } // end func onQuickFormEvent
 
    // }}}
    // {{{ toHtml()
 
    
    /**
     * Returns the picture element in HTML
     *
     * @return string  The html text
     * @access public
     */
    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } elseif ($this->_state == QF_PICTURE_UPLOADED) {
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
    
    // }}}
    // {{{ getFrozenHtml()


    /**
     * Returns the inline object
     *
     * @return string  The frozen html text
     * @access public
     */
    function getFrozenHtml()
    {
        return $this->_getTabs() . '<img src="' . $this->_base_url . $this->_file . '" alt="' . $this->getName() . '" />' . $this->_getPersistantData();
    } //end func getFrozenHtml

    // }}}
    // {{{ _findUploadedValue


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
        if (isset($_FILES[$name])) {
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
 
    // }}}
    // {{{ _updatePictureInfo()

    
    /**
     * Update _info with the uploading picture array
     * 
     * @return bool    Whether the picture info was populated successfully
     * @access private
     */
    function _updatePictureInfo()
    {
        if (isset($this->_info)) {
            return true;
        }
        if (!@array_key_exists('tmp_name', $this->_value)) {
            return false;
        }

        $this->_info = getimagesize($this->_value['tmp_name']);
        return is_array($this->_info);
    } //end func _updatePictureInfo
    
    // }}}
    // {{{ _reset()


    /**
     * Reset the picture
     * 
     * Called after an unrecoverable error.
     *
     * @access private
     */
    function _reset()
    {
        $this->_state = QF_PICTURE_EMPTY;
        $this->_file = null;
        $this->_value = '';
    } // end func _reset
    
    // }}}
    // {{{ _upload()


    /**
     * Upload the picture
     * 
     * @return bool    Whether the picture was uploaded successfully
     * @access private
     */
    function _upload()
    {
        if (!$this->_updatePictureInfo()) {
            $this->_reset();
            return false;
        }

        if ($this->_file) {
            // Explicit file name
            $file = $this->_file;
        } else {
            // Automatic file name
            if (!is_string($ext = image_type_to_extension($this->_info[2]))) {
                $this->_reset();
                return false;
            }

            // Try 5 times to create a unique file in the base path
            $attempts = 5;
            do {
                -- $attempts;
                if ($attempts < 0) {
                    $this->_reset();
                    return false;
                }
                $file = uniqid('tip') . $ext;
            } while (!($handle = fopen($this->_base_path . $file, 'xb')));

            fclose($handle);
        }

        // File upload
        if (!move_uploaded_file($this->_value['tmp_name'], $this->_base_path . $file)) {
            $this->_reset();
            return false;
        }

        $this->_state = QF_PICTURE_UPLOADED;
        $this->_file = $file;
        return true;
    } // end func _upload
    
    // }}}
    // {{{ _unload()


    /**
     * Unload the picture
     *
     * This is the reverse operation of _upload().
     * 
     * @return bool    Whether the picture was unloaded successfully
     * @access private
     */
    function _unload()
    {
        if (!empty($this->_file)) {
            unlink($this->_base_path . $this->_file);
        }

        $this->_reset();
        return true;
    } // end func _unload
    
    // }}}
    // {{{ _ruleIsUploadedPicture()


    /**
     * Check if the given value is an uploaded picture
     *
     * @param  array   $value Value as returned by HTML_QuickForm_picture::getValue()
     * @return bool           true if file has been uploaded, false otherwise
     * @access private
     */
    function _ruleUploadedPicture($value)
    {
        if (!array_key_exists('_qf_element', $value)) {
            // No recent upload done
            return true;
        }

        $element =& $value['_qf_element'];
        return $element->isToUpload() || $element->isUploaded();
    } //end func _ruleIsUploadedPicture
    
    // }}}
    // {{{ _ruleCheckMaxFileSize()

    /**
     * Check that the file does not exceed the max file size
     *
     * @param  array   $value Value as returned by HTML_QuickForm_picture::getValue()
     * @param  int     $size  Max file size
     * @return bool           true if file is smaller than $size, false otherwise
     * @access private
     */
    function _ruleCheckMaxFileSize($value, $size)
    {
        if (!@array_key_exists('_qf_element', $value)) {
            // No recent upload done
            return true;
        }

        if (!@array_key_exists('tmp_name', $value)) {
            // Invalid uploaded image
            return false;
        }

        return ($size >= @filesize($value['tmp_name']));
    } // end func _ruleCheckMaxFileSize

    // }}}
    // {{{ _ruleCheckMimeType()

    /**
     * Check if the given element contains an uploaded file of the right mime type
     *
     * @param  array        $value Value as returned by HTML_QuickForm_picture::getValue()
     * @param  string|array $type  Mime type[s]
     * @return bool                true if mimetype is correct, false otherwise
     * @access private
     */
    function _ruleCheckMimeType($value, $type)
    {
        if (!@array_key_exists('_qf_element', $value)) {
            // No recent upload done
            return true;
        }

        if (is_array($type)) {
            return in_array($value['type'], $type);
        }
        return $value['type'] == $type;
    } // end func _ruleCheckMimeType

    // }}}
    // {{{ _ruleMinPictureSize()


    /**
     * Check if the specified box can be contained inside the picture
     *
     * @param  array   $value Value as returned by HTML_QuickForm_picture::getValue()
     * @param  array   $box   Inside box specified with array(width,height)
     * @return bool           true if the box is contained by the picture, false otherwise
     * @access private
     */
    function _ruleMinPictureSize($value, $box)
    {
        if (!@array_key_exists('_qf_element', $value)) {
            // No recent upload done
            return true;
        }

        $element =& $value['_qf_element'];
        if (!$element->_updatePictureInfo()) {
            // No info availables: invalid image
            return false;
        }

        list($min_width, $min_height) = $box;
        return $element->_info[0] >= $min_width && $element->_info[1] >= $min_height;
    } //end func _ruleMinPictureSize

    // }}}
    // {{{ _ruleMaxPictureSize()


    /**
     * Check if the picture is contained by the specified bounding box
     *
     * @param  array   $value  Value as returned by HTML_QuickForm_picture::getValue()
     * @param  array   $box    Bounding box specified with array(width,height)
     * @return bool            true if picture is contained by the box, false otherwise
     * @access private
     */
    function _ruleMaxPictureSize($value, $box)
    {
        if (!@array_key_exists('_qf_element', $value)) {
            // No recent upload done
            return true;
        }

        $element =& $value['_qf_element'];
        if (!$element->_updatePictureInfo()) {
            // No info availables: invalid image
            return false;
        }

        list($max_width, $max_height) = $box;
        return $element->_info[0] <= $max_width && $element->_info[1] <= $max_height;
    } //end func _ruleMaxPictureSize

    // }}}
} //end class HTML_QuickForm_picture
?>
