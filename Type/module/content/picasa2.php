<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Picasa2 photo access
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2010 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.3.4
 */

/**
 * A content module to access picasa version 2 photo throught its album feed
 *
 * A TIP_Content implementation that sets a default data engine
 * capable to directly parse picasa album feed (version 2) to get
 * metadata of photos.
 *
 * To be able to use this module, you should configure a picasa2
 * module in your configuration options and feed it with the URI
 * of the feed of a picasa album:
 *
 * <code>
 * $cfg = array(
 *     ...
 *     'picture'  => array(
 *         'type' => array('module', 'content', 'picasa2'),
 *         'data' => 'http://picasaweb.google.com/data/feed/base/user/.../albumid/...'
 *     )
 *     ...
 * );
 * </code>
 *
 * The TIP_Picasa2 access to the feed is read-only but you need
 * to fulfill the PicasaWeb Terms of Service in order to be allowed
 * to access the feed:
 *
 * http://code.google.com/apis/picasaweb/terms.html
 *
 * After configuring it, you can use your newly "picture" module in
 * the same way a common TIP_Content module is used.
 *
 * To be able to access the feed, TIP_Picasa2 considers any
 * <entry> in the feed as any SQL engine treats a row in a table.
 * The field of this virtual row are mapped to a usable field id
 * using the "fields_xpath" property of the TIP_XML engine. Here
 * it is a (probably outdated) list of these virtual fields
 * (together with the XPath used to map their value):
 *
 * - **id** ({{link[contains(@rel,"#canonical")]/@href}})
 * - **title** ({{media:group/media:title[@type="plain"]}})
 * - **description** ({{media:group/media:description[@type="plain"]}})
 * - **imageurl** ({{media:group/media:content[@medium="image"]/@url}})
 * - **thumbnail** ({{media:group/media:thumbnail[3]/@url}})
 * - **thumbnail_width** ({{media:group/media:thumbnail[3]/@width}})
 * - **thumbnail_height** ({{media:group/media:thumbnail[3]/@height}})
 * - **date** ({{published}})
 * - **author** ({{../author/name}})
 * - **uploader** ({{media:group/media:credit}})
 *
 * Adding new field mapping is trivial: up to now it was added what
 * I actually need.
 *
 * The above fields can be used in you templates just as every other
 * field provided by the more usual TIP_Mysql engine. For instance,
 * to get a list of the first 5 items, you can do something like this:
 *
 * <code>
 * <ul class="picture">{picture.forSelect(LIMIT 5)}
 *   <li><a href="{imageurl}">
 *     <img src="{thumbnail}" width="{thumbnail_width}" height="{thumbnail_height}" alt="{description}" />
 *   </li>{}
 * </ul>
 * </code>
 *
 * Because TIP_Picasa2 accesses the underlying feed using the TIP_XML
 * data engine there are some known restriction: for instance,
 * you can't do a complex SQL query using the ORDER BY or GROUP BY
 * clauses. Check the TIP_XML documentation to exactly know which
 * queries can be executed.
 *
 * @package TIP
 */
class TIP_Picasa2 extends TIP_Content
{
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (@is_string($options['data'])) {
            $options['data'] = array('path' => $options['data']);
        }

        // The data path is a required option
        if (!@is_array($options['data']) || !isset($options['data']['path'])) {
            return false;
        }

        TIP::arrayDefault($options, 'id_type', 'string');
        TIP::arrayDefault($options['data'], 'data_engine', array(
            'id'                    => 'picasa2',
            'type'                  => array('data_engine', 'xml'),
            'fields_xpath'          => array(
                'id'                => 'link[contains(@rel,"#canonical")]/@href',
                'title'             => 'media:group/media:title[@type="plain"]',
                'description'       => 'media:group/media:description[@type="plain"]',
                'imageurl'          => 'media:group/media:content[@medium="image"]/@url',
                'thumbnail'         => 'media:group/media:thumbnail[3]/@url',
                'thumbnail_width'   => 'media:group/media:thumbnail[3]/@width',
                'thumbnail_height'  => 'media:group/media:thumbnail[3]/@height',
                'date'              => 'published',
                'author'            => '../author/name',
                'uploader'          => 'media:group/media:credit'
        )));

        return parent::checkOptions($options);
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Picasa2 instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Methods

    /**
     * Get the html code for a PicasaWeb picture
     *
     * Renders the provided PicasaWeb URI (in the canonical form,
     * usually something like http://picasaweb.google.com/lh/photo/...)
     * to the proper <a ...><img ... /></a> element.
     *
     * This method starts (and ends) a view to find the row, so any
     * further request is cached.
     *
     * @param  string       $id  The PicasaWeb URI of the photo
     * @return string|false      The string to render or false on errors
     */
    public function toHtml($id)
    {
        if (is_null($view =& $this->startDataView($this->getData()->rowFilter($id)))) {
            TIP::notifyError('select');
            return false;
        }

        $rows =& $view->getProperty('rows');
        if (!@array_key_exists($id, $rows))
            return false;

        return self::_renderRow($rows[$id], array('class' => 'picasa2'));
    }

    /**
     * Get the html code for the whole PicasaWeb album
     *
     * Renders all the photo included by this album.
     *
     * @return string|false  The string to render or false on errors
     */
    public function toHtmlAlbum()
    {
        static $cnt = 1;

        if (is_null($view =& $this->startDataView())) {
            TIP::notifyError('select');
            return false;
        }

        $rows =& $view->getProperty('rows');
        $output = '';
        $max_width = 0;
        $max_height = 0;
        $options = array(
            'max-width' => &$max_width,
            'max-height' => &$max_height
        );

        foreach ($rows as $row) {
            $output .= '  <li>' . self::_renderRow($row, $options) . '</li>';
        }

        if (empty($output))
            return false;

        $id = 'Album' . $cnt;
        ++$cnt;

        $output = <<<EOT
<div class="caption" style="float: right">
<ul id="$id">
$output
</ul>
<p class="caption-title"><a id="$id-prev" href="#">« PRECEDENTE</a> <a id="$id-next" href="#">SEGUENTE »</a></p>
</div>
<script type="text/javascript">
    jQuery(function() {
        jQuery('#$id').ulslide({
            width: $max_width,
            height: $max_height,
            bprev: '#$id-prev',
            bnext: '#$id-next',
            axis: 'x'
        });
    });
</script>
EOT;

        return $output;
    }

    static private function _renderRow($row, $options = null)
    {
        $id = TIP::toHtml($row['id']);
        $description = TIP::toHtml($row['description']);
        $src = TIP::toHtml($row['thumbnail']);
        $width = (int) $row['thumbnail_width'];
        $height = (int) $row['thumbnail_height'];

        $attrs = 'href="' . $id . '"';
        empty($description) || $attrs .= ' title="' . $description . '"';
        isset($options['class']) && $attrs .= ' class="' . $options['class'] . '"';

        if (isset($options['max-width']) && $width > $options['max-width'])
            $options['max-width'] = $width;
        if (isset($options['max-height']) && $height > $options['max-height'])
            $options['max-height'] = $height;

        return "<a $attrs><img src=\"$src\" width=\"$width\" height=\"$height\" alt=\"$description\" /></a>\n";
    }

    //}}}
}
?>
