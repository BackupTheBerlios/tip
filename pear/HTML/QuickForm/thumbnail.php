<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

require_once 'HTML/QuickForm/picture.php';

/**
 * HTML class for a thumbnail picture field
 *
 * @author Nicola Fontana <ntd@entidi.it>
 * @access public
 */
class HTML_QuickForm_thumbnail extends HTML_QuickForm_picture
{
    //{{{ properties

    /**
     * Prefix for thumbnail files
     * @var string
     */
    var $_thumbnail_prefix = 'tmb';

    /**
     * Thumbnail size
     * @var array
     */
    var $_thumbnail_size = array(80, 80);

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
    function HTML_QuickForm_thumbnail($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_picture::HTML_QuickForm_picture($elementName, $elementLabel, $attributes);

        // Use prefix to avoid name collision and to easely switch
        // from the picture to its thumbnail file (by switching the prefix)
        HTML_QuickForm_picture::setFilePrefix('pic');
    } //end constructor

    //}}}
    //{{{ getThumbnailPrefix()

    /**
     * Get the prefix prepended on every thumbnail file
     *
     * @return string  The file prefix
     * @access public
     */
    function getThumbnailPrefix()
    {
        return $this->_thumbnail_prefix;
    } //end func getThumbnailPrefix

    //}}}
    //{{{ setThumbnailPrefix()

    /**
     * Set the prefix to prepend on every thumbnail file
     *
     * @param  string $prefix  The new file prefix
     * @access public
     */
    function setThumbnailPrefix($prefix)
    {
        $this->_thumbnail_prefix = is_null($prefix) ? 'tmb' : $prefix;
    } //end func setThumbnailPrefix

    //}}}
    //{{{ getThumbnailSize()

    /**
     * Get the thumbnail size
     *
     * @return array  The thumbnail size, that is array(width, height)
     * @access public
     */
    function getThumbnailSize()
    {
        return $this->_thumbnail_size;
    } //end func getThumbnailSize

    //}}}
    //{{{ setThumbnailSize()

    /**
     * Set the thumbnail size
     *
     * On invalid arguments, a default value is used: the standard
     * thumbnail size is 80x80 pixels.
     *
     * @param  int    $width  The new thumbnail width
     * @param  int    $height The new thumbnail height
     * @access public
     */
    function setThumbnailSize($width, $height)
    {
        $width > 0 || $width = 80;
        $height > 0 || $height = 80;
        $this->_thumbnail_size = array($width, $height);
    } //end func setThumbnailSize

    //}}}
    //{{{ getThumbnailFile()

    /**
     * Get the thumbnail file name
     *
     * @return string|null The thumbnail file name
     * @access public
     */
    function getThumbnailFile()
    {
        $file = $this->getValue();
        if (empty($file)) {
            return null;
        }

        $prefix = $this->getFilePrefix();
        $prefix_len = strlen($prefix);

        // Strip the file prefix, if found
        if ($prefix_len > 0 && strncmp($file, $prefix, $prefix_len) == 0) {
            $file = substr($file, $prefix_len);
        }

        // Prepend the thumbnail prefix
        return $this->_thumbnail_prefix . $file;
    } //end func getThumbnailFile

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
        $thumbnail = $this->getThumbnailFile();
        if (!empty($thumbnail)) {
            $picture = $this->getBaseUrl() . $this->getValue();
            $thumbnail = $this->getBaseUrl() . $thumbnail;
            $alt = $this->getName();
            $html .= "<a href=\"$picture\"><img src=\"$thumbnail\" alt=\"$alt\" /></a>";
        }
        return $html . $this->_getPersistantData();
    } //end func getFrozenHtml

    //}}}
    //{{{ _upload()

    function _upload()
    {
        // Get the info array and ensure the original image is uploaded
        if (is_null($info = $this->getPictureInfo()) ||
            !HTML_QuickForm_picture::_upload()) {
            return false;
        }

        $picture = $this->getBasePath() . $this->getValue();
        $thumbnail = $this->getBasePath() . $this->getThumbnailFile();
        list($pic_width, $pic_height) = $info;
        list($tmb_width, $tmb_height) = $this->_thumbnail_size;

        // Check for valid dimensions
        if ($pic_width <= 0 || $pic_height <= 0 ||
            $tmb_width <= 0 || $tmb_height <= 0) {
            $this->_unload();
            return false;
        }

        // Calculate the final thumbnail size (retaining its aspect ratio)
        $ratio = $pic_width / $pic_height;
        if ($ratio > $tmb_width / $tmb_height) {
            $width = $tmb_width;
            $height = $width / $ratio;
        } else {
            $height = $tmb_height;
            $width = $height * $ratio;
        }

        // Try to acquire the source image
        $src = $this->imageCreateFromFile($picture, $info[2]);
        if (!$src) {
            $this->_unload();
            return false;
        }

        // Create the destination image
        $dst = imagecreatetruecolor($width, $height);
        if (!$dst) {
            imagedestroy($src);
            $this->_unload();
            return false;
        }

        // The real work
        $done =
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tmb_width, $tmb_height, $pic_width, $pic_height) &&
            imagejpeg($dst, $thumbnail);

        // Finalization: I destroy the images for safety reasons
        imagedestroy($src);
        imagedestroy($dst);
        $done || $this->_unload();

        return $done;
    } // end func _upload

    //}}}
    //{{{ _unload()

    function _unload()
    {
        // Must be called before chaining the parent method
        // because _unload() resets the $_file property
        $thumbnail = $this->getThumbnailFile();

        if (!HTML_QuickForm_picture::_unload()) {
            return false;
        }

        if (!empty($thumbnail)) {
            unlink($this->getBasePath() . $thumbnail);
        }

        return true;
    } // end func _unload

    //}}}
} //end class HTML_QuickForm_thumbnail
?>
