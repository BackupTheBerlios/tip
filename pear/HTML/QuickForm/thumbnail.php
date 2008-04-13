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
     * Base thumbnail path: must be different from getBasePath()
     * @var string
     */
    var $_thumbnail_path = '';

    /**
     * Base thumbnail url: must be different from getBaseUrl()
     * @var string
     */
    var $_thumbnail_url = '';

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
    } //end constructor

    //}}}
    //{{{ setBasePath()

    /**
     * Overriden method
     *
     * On empty thumbnail path, it builds a default one by appending
     * 'thumbnail' to this base path.
     *
     * @param  string $path  The base path
     * @access public
     */
    function setBasePath($path)
    {
        HTML_QuickForm_picture::setBasePath($path);

        if (empty($this->_thumbnail_path)) {
            $this->setThumbnailPath($this->getBasePath() . 'thumbnail');
        }
    } //end func setBasePath

    //}}}
    //{{{ setBaseUrl()

    /**
     * Overriden method
     *
     * On empty thumbnail url, it builds a default one by appending
     * 'thumbnail' to this base url.
     *
     * @param  string $url  The base url
     * @access public
     */
    function setBaseUrl($url)
    {
        HTML_QuickForm_picture::setBaseUrl($url);

        if (empty($this->_thumbnail_url)) {
            $this->setThumbnailUrl($this->getBaseUrl() . 'thumbnail');
        }
    } //end func setBaseUrl

    //}}}
    //{{{ getThumbnailPath()

    /**
     * Get the base thumbnail path
     *
     * @return string  The thumbnail path
     * @access public
     */
    function getThumbnailPath()
    {
        return $this->_thumbnail_path;
    } //end func getThumbnailPath

    //}}}
    //{{{ setThumbnailPath()

    /**
     * Set the path where thumbnails will be generated
     *
     * Must be different from the base picture path,
     * that is what returned by getBasePath().
     *
     * @param  string $path  The thumbnail path
     * @access public
     */
    function setThumbnailPath($path)
    {
        if (empty($path)) {
            $path = '';
        } elseif (substr($path, -1) != DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }
        $this->_thumbnail_path = $path;
    } //end func setThumbnailPath

    //}}}
    //{{{ getThumbnailUrl()

    /**
     * Get the base thumbnail url
     *
     * @return string|null  The thumbnail upload url
     * @access public
     */
    function getThumbnailUrl()
    {
        return $this->_thumbnail_url;
    } //end func getThumbnailUrl

    //}}}
    //{{{ setThumbnailUrl()

    /**
     * Set the url where thumbnails will be generated
     *
     * Must be different from the base picture url,
     * that is what returned by getBaseUrl().
     *
     * @param  mixed  $url  The thumbnail url
     * @access public
     */
    function setThumbnailUrl($url)
    {
        if (empty($url)) {
            $url = '';
        } elseif (substr($url, -1) != '/') {
            $url .= '/';
        }
        $this->_thumbnail_url = $url;
    } //end func setThumbnailUrl

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
        $file = $this->getValue();
        if (is_string($file) && !empty($string)) {
            $picture = $this->getBaseUrl() . $file;
            $thumbnail = $this->getThumbnailUrl() . $file;
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

        $file = $this->getValue();
        $picture = $this->getBasePath() . $file;
        $thumbnail = $this->getThumbnailPath() . $file;
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
        $file = $this->getValue();

        if (!HTML_QuickForm_picture::_unload()) {
            return false;
        }

        unlink($this->getThumbnailPath() . $file);
        return true;
    } // end func _unload

    //}}}
} //end class HTML_QuickForm_thumbnail
?>
