<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Youtube2 definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006,2007,2008,2009 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.3.3
 */

/**
 * A content module managing youtube version 2 feeds
 *
 * A TIP_Content implementation that sets a default data engine
 * capable to directly parse youtube feeds (version 2).
 *
 * To be able to use this module, it suffices to configure a
 * youtube2 module in your configuration options and feed it
 * with a YouTube 2 video RSS file:
 *
<informalexample>
$cfg = array(
    ...
    'movie'    => array(
        'type' => array('module', 'content', 'youtube2'),
        'data' => 'http://gdata.youtube.com/feeds/...?v=2'
    )
    ...
);
</informalexample>
 *
 * The TIP_Youtube2 access to the feed is read-only, so there is
 * no need for user authentication. To determine the URI to put
 * in the 'data' option, please refer to the YouTube documentation:
 *
 * http://code.google.com/apis/youtube/2.0/reference.html#Video_Feeds
 *
 * Also, keep in mind this module expects a version 2 feed, so
 * remember to always append "?v=2" at the end of the URI if you
 * are using the standard feeds provided by YouTube.
 *
 * After configuring it, you can use your newly "movie" module in
 * the same way a common TIP_Content module is used.
 *
 * To be able to access the feed, TIP_Youtube2 considers any
 * <entry> in the feed as any SQL engine treats a row in a table.
 * The field of this virtual row are mapped to a usable field id
 * using the "fields_xpath" property of the TIP_XML engine. Here
 * it is a (probably outdated) list of these virtual fields
 * (together with the XPath used to map their value):
 *
 * * **id** ({{media:group/yt:videoid}})
 * * **title** ({{media:group/media:title[@type="plain"]}})
 * * **description** ({{media:group/media:description[@type="plain"]}})
 * * **swfurl** ({{media:group/media:content[@yt:format="5"]/@url}})
 * * **thumbnail120x90** ({{media:group/media:thumbnail[@width="120" and @height="90"]/@url}})
 * * **thumbnail480x360** ({{media:group/media:thumbnail[@width="480" and @height="360"]/@url}})
 * * **date** ({{media:group/yt:uploaded}})
 * * **uploader** ({{media:group/media:credit[@role="uploader"]}})
 * * **author** ({{author/name}})
 * * **duration** ({{media:group/yt:duration/@seconds}})
 * * **hits** ({{yt:statistics/@viewCount}})
 * * **favorites** ({{yt:statistics/@favoriteCount}})
 * * **rating** ({{gd:rating/@average}})
 * * **raters** ({{gd:rating/@numRaters}})
 *
 * Adding new field mapping is trivial: up to now it was added what
 * I actually need.
 *
 * The above fields can be used in you templates just as every other
 * field provided by the more usual TIP_Mysql engine. For instance,
 * to get a list of the first 5 items, you can do something like this:
 *
<informalexample>
<ul class="movie">{movie.forSelect(LIMIT 5)}
  <li><a href="{actionUri(view,{id})}">
    <img src="{thumbnail120x90}" width="120" height="90" />
    {title}
  </li>{}
</ul>
</informalexample>
 *
 * Because TIP_Youtube2 accesses the underlying feed using the TIP_XML
 * data engine there are some known restriction: for instance,
 * you can't do a complex SQL query using the ORDER BY or GROUP BY
 * clauses. For common usage, I think it was not worth the effort for a
 * full-fledged SQL parser. Check the TIP_XML documentation to exactly
 * know which queries can be executed.
 *
 * @package TIP
 */
class TIP_Youtube2 extends TIP_Content
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
            'id'                    => 'youtube2',
            'type'                  => array('data_engine', 'xml'),
            'fields_xpath'          => array(
                'id'                => 'media:group/yt:videoid',
                'title'             => 'media:group/media:title[@type="plain"]',
                'description'       => 'media:group/media:description[@type="plain"]',
                'swfurl'            => 'media:group/media:content[@yt:format="5"]/@url',
                'thumbnail120x90'   => 'media:group/media:thumbnail[@width="120" and @height="90"]/@url',
                'thumbnail480x360'  => 'media:group/media:thumbnail[@width="480" and @height="360"]/@url',
                'date'              => 'media:group/yt:uploaded',
                'uploader'          => 'media:group/media:credit[@role="uploader"]',
                'author'            => 'author/name',
                'duration'          => 'media:group/yt:duration/@seconds',
                'hits'              => 'yt:statistics/@viewCount',
                'favorites'         => 'yt:statistics/@favoriteCount',
                'rating'            => 'gd:rating/@average',
                'raters'            => 'gd:rating/@numRaters'
        )));

        return parent::checkOptions($options);
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Youtube2 instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
}
?>
