<?php
// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
/**
 * Url rule end renderer for Xhtml
 *
 * PHP versions 4 and 5
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    CVS: $Id: Url.php,v 1.18 2007/05/26 17:15:41 mic Exp $
 * @link       http://pear.php.net/package/Text_Wiki
 */

/**
 * This class renders URL links in XHTML.
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Text_Wiki
 */
class Text_Wiki_Render_Xhtml_Url extends Text_Wiki_Render {


    var $conf = array(
        'target' => '_blank',
        'images' => true,
        'img_ext' => array('jpg', 'jpeg', 'gif', 'png'),
        'regexes' => array(),
        'movies' => true,
        'movie_regex' => '|http://www.youtube.com/v/|',
        'css_inline' => null,
        'css_footnote' => null,
        'css_descr' => null,
        'css_img' => null
    );

    /**
    *
    * Renders a token into text matching the requested format.
    *
    * @access public
    *
    * @param array $options The "options" portion of the token (second
    * element).
    *
    * @return string The text rendered from the token options.
    *
    */

    function token($options)
    {
        // create local variables from the options array (text,
        // href, type)
        extract($options);

        // find the rightmost dot and determine the filename
        // extension.
        $pos = strrpos($href, '.');
        $ext = strtolower(substr($href, $pos + 1));
        $href = $this->textEncode($href);
        $movie_regex = $this->getConf('movie_regex', '');
        $regexes = $this->getConf('regexes');

        // Compare against custom regexes
        foreach ($regexes as $pattern => $callback) {
            $matches = array();
            if (preg_match($pattern, $href, $matches)) {
                $output = call_user_func_array($callback, $matches);
                if (is_string($output))
                    return $output;
            }
        }

        // does the filename extension indicate an image file?
        if ($this->getConf('images') &&
            in_array($ext, $this->getConf('img_ext', array()))) {

            // create alt text for the image
            if (! isset($text) || $text == '') {
                $text = basename($href);
                $text = $this->textEncode($text);
            }

            // generate an image tag
            $css = $this->formatConf(' class="%s"', 'css_img');
            $start = "<img$css src=\"$href\" alt=\"$text\" title=\"$text\" /><!-- ";
            $end = " -->";

        } elseif ($this->getConf('movies') &&
                  preg_match($movie_regex, $href)) {

            $start = <<<EOT
<div class="movie">
  <div>
  <object width="425" height="355">
    <param name="movie" value="$href"></param>
    <embed src="
EOT;
            $text = $href;
            $end = <<<EOT
" type="application/x-shockwave-flash" width="425" height="355"></embed>
  </object>
  </div>
</div>
EOT;

        } else {

            // should we build a target clause?
            if ($href{0} == '#' ||
              strtolower(substr($href, 0, 7)) == 'mailto:') {
              // targets not allowed for on-page anchors
              // and mailto: links.
                $target = '';
            } else {
        // allow targets on non-anchor non-mailto links
                $target = $this->getConf('target');
            }

            // generate a regular link (not an image)
            $text = $this->textEncode($text);
            $css = $this->formatConf(' class="%s"', "css_$type");
            $start = "<a$css href=\"$href\"";

            if ($target && $target != '_self') {
                // use a "popup" window.  this is XHTML compliant, suggested by
                // Aaron Kalin.  uses the $target as the new window name.
                $target = $this->textEncode($target);
                $start .= " onclick=\"window.open(this.href, '$target');";
                $start .= " return false;\"";
            }
            
            if (isset($name)) {
                $start .= " id=\"$name\"";
            }

            // finish up output
            $start .= ">";
            $end = "</a>";

            // make numbered references look like footnotes when no
            // CSS class specified, make them superscript by default
            if ($type == 'footnote' && ! $css) {
                $start = '<sup>' . $start;
                $end = $end . '</sup>';
            }
        }

        if ($options['type'] == 'start') {
            $output = $start;
        } else if ($options['type'] == 'end') {
            $output = $end;
        } else {
            $output = $start . $text . $end;
        }
        return $output;
    }
}
?>
