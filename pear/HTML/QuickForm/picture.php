<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

require_once 'HTML/QuickForm/attachment.php';

// Register picture-related rules
HTML_QuickForm::registerRule('minpicturesize', 'callback', '_ruleMinPictureSize', 'HTML_QuickForm_picture');
HTML_QuickForm::registerRule('maxpicturesize', 'callback', '_ruleMaxPictureSize', 'HTML_QuickForm_picture');

/**
 * HTML class for a generic picture based field
 *
 * @author Nicola Fontana <ntd@entidi.it>
 * @access public
 */
class HTML_QuickForm_picture extends HTML_QuickForm_attachment
{
    //{{{ properties

    /**
     * The info array of the uploading picture, as returned by getimagesize()
     * @var array
     */
    var $_picture_info = null;

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
        HTML_QuickForm_attachment::HTML_QuickForm_attachment($elementName, $elementLabel, $attributes);
    } //end constructor

    //}}}
    //{{{ getSuffix()

    /**
     * Try to guess the image extension directly from the image
     *
     * @return string  The suffix or null on errors
     * @access public
     */
    function getSuffix()
    {
        // Try to guess the extension from the image info
        if (is_null($this->_suffix) &&
            is_array($info = $this->getPictureInfo()) &&
            is_string($ext = image_type_to_extension($info[2], true))) {
            $this->_suffix = $ext;
            return $ext;
        }

        // Otherwise fallbacks to the default method
        return HTML_QuickForm_attachment::getSuffix();
    } //end func getSuffix

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
        if (is_null($this->_picture_info) &&
            @array_key_exists('tmp_name', $this->_value)) {
            $this->_picture_info = getimagesize($this->_value['tmp_name']);
        }

        // Check for valid result
        if (!is_array($this->_picture_info)) {
            // This avoid next checks
            $this->_picture_info = false;
            return null;
        }

        return $this->_picture_info;
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
            $html .= "<img src=\"$src\" alt=\"$txt\" />";
        }
        return $html . $this->_getPersistantData();
    } //end func getFrozenHtml

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
        $this->_picture_info = null;
        return HTML_QuickForm_attachment::_reset();
    } // end func _reset

    //}}}
    //{{{ _realUpload()

    /**
     * Override the default method adding the autoresize feature
     *
     * @param  string    $tmp  The source temporary file
     * @param  string    $file The destination file name (only the name)
     * @return bool             true on success, false otherwise
     * @access protected
     */
    function _realUpload($tmp, $file)
    {
        // Reset the autoresize bounding box
        $box = $this->_autoresize_box;
        $this->_autoresize_box = null;

        if (!is_array($box)) {
            // No autoresize requested: chain-up the parent method
            return HTML_QuickForm_attachment::_realUpload($tmp, $file);
        }

        // Autoresize the image
        $picture = $this->getBasePath() . $file;
        if (!is_uploaded_file($tmp) ||
            !$this->_resizeImage($tmp, $picture, $box)) {
            return false;
        }

        // Autoresizing succesful: update the new image data
        $this->_value['tmp_name'] = $picture;
        $this->_picture_info[0] = $box[0];
        $this->_picture_info[1] = $box[1];
        return true;
    } // end func _realUpload

    //}}}
    //{{{ _resizeImage()

    /**
     * Resize an image: the new size is returned in the in/out $box argument
     *
     * @param  string    $from The image file
     * @param  string    $to   The destination file
     * @param  array    &$box  The bounding box, as array(max_width,max_height)
     * @return bool            true on success, false on errors
     * @access protected
     */
    function _resizeImage($from, $to, &$box)
    {
        if (!is_array($info = $this->getPictureInfo())) {
            return false;
        }

        list($src_width, $src_height, $type) = $info;
        list($dst_width, $dst_height) = $box;

        // Check for valid dimensions
        if ($src_width <= 0 || $src_height <= 0 ||
            $dst_width <= 0 || $dst_height <= 0) {
            return false;
        }

        // Calculate the final picture size (retaining the aspect ratio)
        $src_ratio = $src_width / $src_height;
        $dst_ratio = $dst_width / $dst_height;
        if ($src_ratio > $dst_ratio) {
            $dst_height = $dst_width / $src_ratio;
        } else {
            $dst_width = $dst_height * $src_ratio;
        }

        // Try to acquire the source image
        $src = $this->imageCreateFromFile($from, $type);
        if (!$src) {
            return false;
        }

        // Create the destination image
        $dst = imagecreatetruecolor($dst_width, $dst_height);
        if (!$dst) {
            imagedestroy($src);
            return false;
        }

        // The real work: use imageToFile() to retain the same image type
        // of the original one (who knows...)
        $done = imagecopyresampled($dst, $src, 0, 0, 0, 0,
                                   $dst_width, $dst_height,
                                   $src_width, $src_height);
        imagedestroy($src);

        if ($done) {
            if ($this->imageToFile($dst, $type, $to)) {
                // Success! The new image size is stored in $box
                $box = array($dst_width, $dst_height);
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
