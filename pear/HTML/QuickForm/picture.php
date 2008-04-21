<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

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

/**
 * HTML class for a generic picture based field
 *
 * @author Nicola Fontana <ntd@entidi.it>
 * @access public
 */
class HTML_QuickForm_picture extends HTML_QuickForm_input
{
    //{{{ properties

    /**
     * Uploaded data, from $_FILES
     * @var array
     */
    var $_value = null;

    /**
     * Running operation
     * @var QF_PICTURE_...
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
     * Picture file prefix
     * @var string
     */
    var $_file_prefix = '';

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

    /**
     * Autoresize feature (enabled or disabled)
     * @var bool
     */
    var $_autoresize = false;

    /**
     * Autoresize bounding box, if any
     * @var array
     * @internal
     */
    var $_autoresize_box = null;

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
    function HTML_QuickForm_picture($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->setType('file');
        $this->setPersistantFreeze(true);
    } //end constructor

    //}}}
    //{{{ getValue()

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

    //}}}
    //{{{ setValue()

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
            $this->_file = $value;
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

    //}}}
    //{{{ getFilePrefix()

    /**
     * Get the prefix prepended on every picture file
     *
     * @return string  The file prefix
     * @access public
     */
    function getFilePrefix()
    {
        return $this->_file_prefix;
    } //end func getFilePrefix

    //}}}
    //{{{ setFilePrefix()

    /**
     * Set the prefix to prepend on every picture file
     *
     * @param  string $prefix  The new file prefix
     * @access public
     */
    function setFilePrefix($prefix)
    {
        $this->_file_prefix = empty($prefix) ? '' : $prefix;
    } //end func setFilePrefix

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

    //}}}
    //{{{ setFile()

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

    //}}}
    //{{{ getAutoresize()

    /**
     * Get the autoresize feature status
     *
     * @return bool   true if the autoresize feature is enable or false otherwise
     * @access public
     */
    function getAutoresize()
    {
        return $this->_autoresize;
    } //end func getAutoresize

    //}}}
    //{{{ setAutoresize()

    /**
     * Enable or disable the autoresize feature
     *
     * The autoresize feature allows to resize the uploaded image on the fly,
     * keeping as bounding box the size in the 'maxpicturesize' rule
     * (that MUST be specified to work).
     *
     * @param  bool   $autoresize true to enable the autoresize feature
     * @access public
     */
    function setAutoresize($autoresize)
    {
        $this->_autoresize = $autoresize;
    } //end func setAutoresize

    //}}}
    //{{{ getState()

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

    //}}}
    //{{{ setState()

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

    //}}}
    //{{{ getPictureInfo()

    /**
     * Get the info of the uploaded image, as returned by getimagesize()
     *
     * @return array|null  The info array or null on errors
     * @access public
     */
    function getPictureInfo()
    {
        // Check only once and if a file was uploaded
        if (is_null($this->_info) &&
            @array_key_exists('tmp_name', $this->_value)) {
            $this->_info = getimagesize($this->_value['tmp_name']);
        }

        // Check for valid result
        if (!is_array($this->_info)) {
            // This avoid next checks
            $this->_info = false;
            return null;
        }

        return $this->_info;
    } //end func getPictureInfo

    //}}}
    //{{{ imageCreateFromFile()

    /**
     * Wrap the imagecreatefrom...() methods
     *
     * @param  string   $file The file name
     * @param  int      $type An IMAGETYPE_... constant
     * @return resource       A image resource or null on errors
     * @access public
     */
    function imageCreateFromFile($file, $type)
    {
        switch ($type) {

        case IMAGETYPE_JPEG:
            if (function_exists('imagecreatefromjpeg')) {
                return imagecreatefromjpeg($file);
            }
            return null;

        case IMAGETYPE_PNG:
            if (function_exists('imagecreatefrompng')) {
                return imagecreatefrompng($file);
            }
            return null;

        case IMAGETYPE_GIF:
            if (function_exists('imagecreatefromgif')) {
                return imagecreatefromgif($file);
            }
            return null;

        case IMAGETYPE_WBMP:
            if (function_exists('imagecreatefromwbmp')) {
                return imagecreatefromwbmp($file);
            }
            return null;

        case IMAGETYPE_XBM:
            if (function_exists('imagecreatefromxbm')) {
                return imagecreatefromxbm($file);
            }
            return null;
        }

        // Unsupported format
        return null;
    } //end func imageCreateFromFile

    //}}}
    //{{{ imageToFile()

    /**
     * Wrap the image...() serialization methods
     *
     * @param  resource &$image The image to serialize
     * @param  int       $type  An IMAGETYPE_... constant
     * @param  string    $file  The file name
     * @return boolean          true on success, false otherwise
     * @access public
     */
    function imageToFile(&$image, $type, $file)
    {
        switch ($type) {

        case IMAGETYPE_JPEG:
            if (function_exists('imagejpeg')) {
                return imagejpeg($image, $file);
            }
            return false;

        case IMAGETYPE_PNG:
            if (function_exists('imagepng')) {
                return imagepng($image, $file);
            }
            return false;

        case IMAGETYPE_GIF:
            if (function_exists('imagegif')) {
                return imagegif($image, $file);
            }
            return false;

        case IMAGETYPE_XBM:
            if (function_exists('imagexbm')) {
                return imagexbm($image, $file);
            }
            return false;

        case IMAGETYPE_WBMP:
            if (function_exists('imagewbmp')) {
                return imagewbmp($image, $file);
            }
            return false;
        }

        // Unsupported format
        return false;
    } //end func imageToFile

    //}}}
    //{{{ doUploads()

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
                } elseif ($element->getState() == QF_PICTURE_TO_UNLOAD) {
                    $element->_unload();
                }

                // This is a really dirty hack to force the value to be the
                // picture file name
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
                    $this->_state = QF_PICTURE_UPLOADED;
                } elseif (!is_null($value = $this->_findUploadedValue())) {
                    $this->_state = QF_PICTURE_TO_UPLOAD;
                } else {
                    $value = $this->_findValue($caller->_submitValues);
                    empty($value) && $value = $this->_findValue($caller->_defaultValues);
                    if ($this->_state != QF_PICTURE_TO_UNLOAD && !empty($value)) {
                        $this->_state = QF_PICTURE_UPLOADED;
                    }
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

    //}}}
    //{{{ toHtml()

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
            $alt = $this->getName();
            $html .= "<img src=\"$src\" alt=\"$alt\" />";
        }
        return $html . $this->_getPersistantData();
    } //end func getFrozenHtml

    //}}}
    //{{{ getTmpFile()

    /**
     * Build a temporary file name accordling to the uploaded image type
     *
     * @param  string      $prefix A string to prepend to the file name
     * @return string|null         The file name or null on errors
     * @access protected
     */
    function getTmpFile($prefix)
    {
        if (is_null($info = $this->getPictureInfo()) ||
            !is_string($ext = image_type_to_extension($info[2]))) {
            return null;
        }

        // Try to create a unique file in the base path
        $attempts = 5;
        do {
            -- $attempts;
            if ($attempts < 0) {
                return null;
            }
            $file = uniqid($prefix) . $ext;
        } while (!($handle = fopen($this->_base_path . $file, 'xb')));

        fclose($handle);
        return $file;
    } // end func getTmpFile

    //}}}
    //{{{ _reset()

    /**
     * Reset the picture internal state to its (empty) default
     *
     * @return boolean   true on success, false otherwise
     * @access protected
     */
    function _reset()
    {
        $this->_state = QF_PICTURE_EMPTY;
        $this->_file = null;
        $this->_value = '';
        $this->_info = null;
        return true;
    } // end func _reset

    //}}}
    //{{{ _upload()

    /**
     * Upload the picture
     *
     * @return bool      Whether the picture was uploaded successfully
     * @access protected
     */
    function _upload()
    {
        // Reset the autoresize bounding box
        $box = $this->_autoresize_box;
        $this->_autoresize_box = null;

        if ($this->_state != QF_PICTURE_TO_UPLOAD) {
            return false;
        }

        if (empty($this->_file)) {
            // Automatic file name
            $file = $this->getTmpFile($this->_file_prefix);
            if (!$file) {
                $this->_reset();
                return false;
            }
        } else {
            // Explicit file name
            $file = $this->_file;
        }

        // File upload
        $tmp_name = $this->_value['tmp_name'];
        $picture = $this->_base_path . $file;
        if (is_array($box)) {
            // Autoresize the image
            if (is_uploaded_file($tmp_name) && $this->_resizeImage($tmp_name, $picture, $box)) {
                // Success: store the new image data
                $this->_value['tmp_name'] = $picture;
                $this->_info[0] = $box[0];
                $this->_info[1] = $box[1];
            } else {
                $this->_reset();
                return false;
            }
        } elseif (!move_uploaded_file($tmp_name, $picture)) {
            $this->_reset();
            return false;
        }

        $this->_state = QF_PICTURE_UPLOADED;
        $this->_file = $file;
        return true;
    } // end func _upload

    //}}}
    //{{{ _unload()

    /**
     * Unload the picture
     *
     * This is the reverse operation of _upload().
     *
     * @return bool      Whether the picture was unloaded successfully
     * @access protected
     */
    function _unload()
    {
        if (!empty($this->_file)) {
            unlink($this->_base_path . $this->_file);
        }

        return $this->_reset();
    } // end func _unload

    //}}}
    //{{{ _resizeImage()

    /**
     * Resize an image
     *
     * The $_info property must be populated with the $from image data,
     * as returned by getimagesize().
     *
     * The new image size is returned in the in/out $box variable.
     *
     * @param  string    $from The image file
     * @param  string    $to   The destination file
     * @param  array    &$box  The bounding box, as array(max_width,max_height)
     * @return bool            true on success, false on errors
     * @access protected
     */
    function _resizeImage($from, $to, &$box)
    {
        list($org_width, $org_height, $type) = $this->_info;
        list($max_width, $max_height) = $box;

        // Check for valid dimensions
        if ($org_width <= 0 || $org_height <= 0 ||
            $max_width <= 0 || $max_height <= 0) {
            return false;
        }

        // Calculate the final picture size (retaining the aspect ratio)
        $ratio = $org_width / $org_height;
        if ($ratio > $max_width / $max_height) {
            $width  = $max_width;
            $height = $width / $ratio;
        } else {
            $height = $max_height;
            $width  = $height * $ratio;
        }

        // Try to acquire the source image
        $src = $this->imageCreateFromFile($from, $type);
        if (!$src) {
            return false;
        }

        // Create the destination image
        $dst = imagecreatetruecolor($width, $height);
        if (!$dst) {
            imagedestroy($src);
            return false;
        }

        // The real work: use imageToFile() to retain the same image type
        // of the original one (who knows...)
        $done = imagecopyresampled($dst, $src, 0, 0, 0, 0, $max_width, $max_height, $org_width, $org_height);
        imagedestroy($src);

        if ($done) {
            if ($this->imageToFile($dst, $type, $to)) {
                // Success! The new image size is stored in $box
                $box = array($width, $height);
            } else {
                // Failure in serialization
                @unlink($to);
                $done = false;
            }
        }

        imagedestroy($dst);
        return $done;
    } // end func _resizeImage

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

    //}}}
    //{{{ _ruleIsUploadedPicture()

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

    //}}}
    //{{{ _ruleCheckMaxFileSize()

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

        $file = $value['tmp_name'];
        return @file_exists($file) && @filesize($file) <= $size;
    } // end func _ruleCheckMaxFileSize

    //}}}
    //{{{ _ruleCheckMimeType()

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

    //}}}
    //{{{ _ruleMinPictureSize()

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
        if (is_null($info = $element->getPictureInfo())) {
            // No info availables: invalid image
            return false;
        }

        list($min_width, $min_height) = $box;
        return $info[0] >= $min_width && $info[1] >= $min_height;
    } //end func _ruleMinPictureSize

    //}}}
    //{{{ _ruleMaxPictureSize()

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
        if (is_null($info = $element->getPictureInfo())) {
            // No info availables: invalid image
            return false;
        }

        list($max_width, $max_height) = $box;
        $is_smaller = $info[0] <= $max_width && $info[1] <= $max_height;

        // Autoresize only pictures exceeding the bounding box
        if (!$is_smaller && $element->getAutoresize()) {
            // Autoresize feature enabled: set the autoresize bounding box
            // and always return true
            $element->_autoresize_box = $box;
            return true;
        }

        return $is_smaller;
    } //end func _ruleMaxPictureSize

    //}}}
} //end class HTML_QuickForm_picture
?>
