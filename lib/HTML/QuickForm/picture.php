<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

require_once 'HTML/QuickForm/input.php';

// Register picture-related rules
HTML_QuickForm::registerRule('uploadedpicture', 'callback', '_ruleUploadedPicture', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('minpicturesize', 'callback', '_ruleMinPictureSize', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('maxpicturesize', 'callback', '_ruleMaxPictureSize', 'HTML_QuickForm_picture');


/**
 * HTML class for a generic picture based field
 * 
 * @author       Nicola Fontana <ntd@users.sourceforge.net>
 * @version      1.0
 * @since        PHP4.3.0
 * @access       public
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
     * Base picture path
     * @var string
     */
    var $_base_path = null;

    /**
     * Base picture url
     * @var string
     */
    var $_base_url = null;

    /**
     * The name of the picture (without extension)
     * @var string
     */
    var $_picture_name = null;

    /**
     * The file extension of the picture (with a prepended dot)
     *
     * The extension is used also to check if the picture if uploaded: if
     * is a string (also empty, but a string) means that the picture is
     * uploaded, because the uploadPicture() method has defined the extension.
     *
     * @var string
     */
    var $_picture_extension = null;

    /**
     * The info array of the uploading picture, as returned by getimagesize()
     * @var array
     */
    var $_picture_info = null;

    /**
     * If true, prevent the initialization of the picture
     * @var array
     */
    var $_is_unloaded = false;

    /**
     * Additional HTML_QuickForm_element to manage picture unloading
     */
    var $_unload_element = null;

    // }}}
    // {{{ constructor


    /**
     * Class constructor
     * 
     * @param    string $elementName   Input field name attribute
     * @param    mixed  $elementLabel  Label(s) for a field
     * @param    mixed  $attributes    Either a typical HTML attribute string or an associative array
     * @access   public
     * @since    1.0
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
     * @return   string|null  The picture file name
     * @access   public
     * @since    1.0
     */
    function getValue()
    {
        if (!$this->isUploaded()) {
            return null;
        }

        return $this->_picture_name . $this->_picture_extension;
    } //end func getValue
    
    // }}}
    // {{{ setValue()
 
    
    /**
     * Set the picture
     *
     * $value can be a string specifing the picture file, for yet uploaded
     * pictures, or an array from $_FILES for uploading pictures.
     *
     * @param    string|array $value  The picture data
     * @access   public
     * @since    1.0
     */
    function setValue($value)
    {
        $this->_picture_extension = null;

        if (is_string($value)) {
            if (!empty($value)) {
                if (($dotpos = strrpos($value, '.')) !== false) {
                    $this->_picture_name = substr($value, 0, $dotpos);
                    $this->_picture_extension = substr($value, $dotpos);
                } else {
                    $this->_picture_name = $value;
                    $this->_picture_extension = '';
                }
            }
        } elseif (is_array($value)) {
            // Check the validity of $value
            if ((!isset($value['error']) || $value['error'] == 0) &&
                !empty($value['tmp_name']) && $value['tmp_name'] != 'none' &&
                is_uploaded_file($value['tmp_name'])) {
                $this->_value = $value;
            }
        }
    } //end func setValue
    
    // }}}
    // {{{ getBasePath()
 
    
    /**
     * Get the base upload path
     * 
     * @return   string  The base upload path
     * @access   public
     * @since    1.0
     */
    function getBasePath()
    {
        return empty($this->_base_path) ? '' : $this->_base_path;
    } //end func getBasePath
    
    // }}}
    // {{{ setBasePath()
 
    
    /**
     * Set the base path where uploaded pictures are stored
     * 
     * @param    string $path  The base path
     * @access   public
     * @since    1.0
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
     * @return   string|null  The base upload url
     * @access   public
     * @since    1.0
     */
    function getBaseUrl()
    {
        return empty($this->_base_url) ? '' : $this->_base_url;
    } //end func getBaseUrl
    
    // }}}
    // {{{ setBaseUrl()
 
    
    /**
     * Set the base url where pictures are uploaded
     * 
     * @param    mixed  $url  The base url
     * @access   public
     * @since    1.0
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
    // {{{ getPictureName()
 
    
    /**
     * Get the name of the picture
     *
     * The name can be explicily set by the application if you want a specific
     * name to be assigned to the picture or automatically by the
     * uploadPicture() method. In any case, the returned string is the
     * file name WITHOUT extension.
     * 
     * @return   string|null  The picture name
     * @access   public
     * @since    1.0
     */
    function getPictureName()
    {
        return $this->_picture_name;
    } //end func getPictureName
    
    // }}}
    // {{{ setPictureName()
 
    
    /**
     * Set the name of the picture
     * 
     * @param    mixed  $name   The picture name
     * @access   public
     * @since    1.0
     */
    function setPictureName($name)
    {
        $this->_picture_name = empty($name) ? null : $name;
    } //end func setPictureName
    
    // }}}
    // {{{ getPictureExtension()
 
    
    /**
     * Get the extension of the picture (with a prepended dot)
     *
     * @return   string|null  The picture extension
     * @access   public
     * @since    1.0
     */
    function getPictureExtension()
    {
        return is_string($this->_picture_extension) ? $this->_picture_extension : null;
    } //end func getPictureExtension
    
    // }}}
    // {{{ getPicturePath()
 
    
    /**
     * Get the complete path of the picture
     *
     * @return   string|null  The path of the picture
     * @access   public
     * @since    1.0
     */
    function getPicturePath()
    {
        if (is_null($value = $this->getValue())) {
            return null;
        }

        return $this->getBasePath() . $value;
    } //end func getPicturePath
    
    // }}}
    // {{{ getPictureUrl()
 
    
    /**
     * Get the complete url of the picture
     *
     * @return   string|null  The url of the picture
     * @access   public
     * @since    1.0
     */
    function getPictureUrl()
    {
        if (is_null($value = $this->getValue())) {
            return null;
        }

        return $this->_base_url . $value;
    } //end func getPictureUrl
    
    // }}}
    // {{{ setUnloadElement()
 
    
    /**
     * Set the unload element
     *
     * The unload element is rendered after this element if the picture is
     * in uploaded state.
     *
     * @param    HTML_QuickForm_element &$element  A quick form element
     * @access   public
     * @since    1.0
     */
    function setUnloadElement(&$element)
    {
        $this->_unload_element =& $element;
    } //end func setUnloadElement
    
    // }}}
    // {{{ uploadPicture()


    /**
     * Upload the picture
     * 
     * @return   bool    Whether the picture was uploaded successfully
     * @access   public
     * @since    1.0
     */
    function uploadPicture()
    {
        if ($this->isUploaded() || !$this->_updatePictureExtension()) {
            return false;
        }

        if (empty($this->_picture_name)) {
            // Try 5 times to create a unique file in $_base_path
            $attempts = 5;

            do {
                if (--$attempts < 0) {
                    $this->_picture_extension = false;
                    return false;
                }
                $this->_picture_name = uniqid('tip');
            } while (!($handle = fopen($this->getPicturePath(), 'xb')));

            fclose($handle);
        }

        // File upload
        if (!move_uploaded_file($this->_value['tmp_name'], $this->getPicturePath())) {
            $this->_picture_extension = false;
            return false;
        }

        return true;
    } // end func uploadPicture
    
    // }}}
    // {{{ unloadPicture()


    /**
     * Unload the picture
     *
     * This is the reverse operation of uploadPicture().
     * 
     * @access   public
     * @since    1.0
     */
    function unloadPicture()
    {
        if (!is_null($path = $this->getPicturePath())) {
            unlink($path);
        }

        $this->_is_unloaded = true;
    } // end func unloadPicture
    
    // }}}
    // {{{ toUpload()


    /**
     * Check if the picture needs to be uploaded
     *
     * @return   bool     true if the picture must be uploaded, false otherwise
     * @access   public
     * @since    1.0
     */
    function toUpload()
    {
        return is_array($this->_value);
    } // end func toUpload

    // }}}
    // {{{ isUploaded()


    /**
     * Check if the picture was uploaded
     *
     * @return   bool     true if the picture was uploaded, false otherwise
     * @access   public
     * @since    1.0
     */
    function isUploaded()
    {
        return is_string($this->_picture_extension);
    } // end func isUploaded

    // }}}
    // {{{ doUploads()


    /**
     * Perform the needed uploads
     *
     * MUST be called after the validation, so invalid pictures are not
     * uploaded, but before the rendering process, so the html output is
     * properly generated.
     *
     * @param    HTML_QuickForm &$form  The form to check for uploads
     * @access   public
     * @since    1.0
     * @static
     */
    function doUploads(&$form)
    {
        foreach (array_keys($form->_elements) as $key) {
            $element =& $form->_elements[$key];
            if (is_a($element, 'HTML_QuickForm_picture')) {
                $name = $element->getName();
                // An element is considered valid if does not have an error message:
                // not ever right but it is a good starting point ...
                $error_message = $form->getElementError($name);
                if (empty($error_message)) {
                    $element->uploadPicture();
                    // This is a really dirty hack to force the value to be the
                    // picture file name
                    $form->_submitFiles[$name] = $element->getValue();
                }
            }
        }
    } //end func doUploads

    // }}}
    // {{{ onQuickFormEvent()
 
    
    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param    string $event   Name of event
     * @param    mixed  $arg     Event arguments
     * @param    object $caller  Calling object
     * @access   public
     * @since    1.0
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
                if ($this->_is_unloaded) {
                } elseif (!is_null($value = $this->_findValue($caller->_constantValues)) ||
                          !is_null($value = $this->_findUploadedValue()) ||
                          !is_null($value = $this->_findValue($caller->_submitValues)) ||
                          !is_null($value = $this->_findValue($caller->_defaultValues))) {
                    $this->setValue($value);
                    // No need to set enctype and maxfilesize
                    //break;
                }

                $caller->updateAttributes(array('enctype' => 'multipart/form-data'));
                $caller->setMaxFileSize();
                break;
        }

        return true;
    } // end func onQuickFormEvent
 
    // }}}
    // {{{ toHtml()
 
    
    /**
     * Returns the picture element in HTML
     *
     * @return   string  The html text
     * @access   public
     * @since    1.0
     */
    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } elseif ($this->isUploaded()) {
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
     * @return   string  The frozen html text
     * @access   public
     * @since    1.0
     */
    function getFrozenHtml()
    {
        if (is_null($src = $this->getPictureUrl())) {
            // No uploaded picture to view
            return '';
        }

        return $this->_getTabs() . '<img src="' . $src . '" />' . $this->_getPersistantData();
    } //end func getFrozenHtml

    // }}}
    // {{{ _findUploadedValue


    /**
     * Try to find the element value from $_FILES
     *
     * Directly stolen from HTML_QuickForm_file.
     * 
     * @return   array|null  An array as in $_FILES or null if not found 
     * @access   private
     * @since    1.0
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
     * Update _picture_info with the uploading picture array
     * 
     * @return   bool    Whether the picture info was populated successfully
     * @access   private
     * @since    1.0
     */
    function _updatePictureInfo()
    {
        if (is_null($this->_picture_info)) {
            $this->_picture_info = $this->toUpload() ? getimagesize($this->_value['tmp_name']) : false;
        }

        return is_array($this->_picture_info);
    } //end func _updatePictureInfo
    
    // }}}
    // {{{ _updatePictureExtension()
 
    
    /**
     * Update _picture_extension using the _picture_info array
     *
     * @return   bool    Whether the picture extension was updated successfully
     * @access   public
     * @since    1.0
     */
    function _updatePictureExtension()
    {
        require_once 'PHP/Compat/Function/image_type_to_extension.php';

        if (!$this->_updatePictureInfo()) {
            $this->_picture_extension = false;
            return false;
        }

        $extension = image_type_to_extension($this->_picture_info[2], true);
        $this->_picture_extension = isset($extension) ? $extension : false;
        return is_string($this->_picture_extension);
    } //end func _updatePictureExtension
    
    // }}}
    // {{{ _ruleIsUploadedPicture()


    /**
     * Check if the given value is an uploaded picture
     *
     * @param    array   $value  Value as returned by HTML_QuickForm_picture::getValue()
     * @return   bool            true if file has been uploaded, false otherwise
     * @access   private
     * @since    1.0
     */
    function _ruleUploadedPicture($value)
    {
        if (!array_key_exists('_qf_element', $value)) {
            // No recent upload done
            return true;
        }

        $element =& $value['_qf_element'];
        return $element->toUpload() || $element->isUploaded();
    } //end func _ruleIsUploadedPicture
    
    // }}}
    // {{{ _ruleMinPictureSize()


    /**
     * Check if the specified bounding box is contained by the picture
     *
     * @param    array   $value  Value as returned by HTML_QuickForm_picture::getValue()
     * @param    array   $box    Bounding box specified with array(width,height)
     * @return   bool            true if the box is contained by the picture, false otherwise
     * @access   private
     * @since    1.0
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
        return $element->_picture_info[0] >= $min_width && $element->_picture_info[1] >= $min_height;
    } //end func _ruleMinPictureSize

    // }}}
    // {{{ _ruleMaxPictureSize()


    /**
     * Check if the picture is contained by the specified bounding box
     *
     * @param    array   $value  Value as returned by HTML_QuickForm_picture::getValue()
     * @param    array   $box    Bounding box specified with array(width,height)
     * @return   bool            true if picture is contained by the box, false otherwise
     * @access   private
     * @since    1.0
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
        return $element->_picture_info[0] <= $max_width && $element->_picture_info[1] <= $max_height;
    } //end func _ruleMaxPictureSize

    // }}}
} //end class HTML_QuickForm_picture
?>
