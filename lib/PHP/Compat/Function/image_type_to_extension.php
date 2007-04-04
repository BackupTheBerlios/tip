<?php

/**
 * Replace image_type_to_extension()
 *
 * @category    PHP
 * @package     PHP_Compat
 * @author      Nicola Fontana <ntd@users.sourceforge.net>
 * @version     1.0
 * @since       PHP 4.2.0
 */
if (!function_exists('image_type_to_extension')) {
    function image_type_to_extension($imagetype, $include_dot = false)
    {
        switch ($imagetype) {
        case 1:     $ext = 'gif';   break;
        case 2:     $ext = 'jpg';   break;
        case 3:     $ext = 'png';   break;
        case 4:     $ext = 'swf';   break;
        case 5:     $ext = 'psd';   break;
        case 6:
        case 15:    $ext = 'bmp';   break;
        case 7:
        case 8:     $ext = 'tiff';  break;
        case 9:     $ext = 'jpc';   break;
        case 10:    $ext = 'jp2';   break;
        case 11:    $ext = 'jpx';   break;
        case 12:    $ext = 'jb2';   break;
        case 13:    $ext = 'swc';   break;
        case 14:    $ext = 'iff';   break;
        case 16:    $ext = 'xbm';   break;
        default:    return '';
        }

        return $include_dot ? '.' . $ext : $ext;
    }
}

?>
