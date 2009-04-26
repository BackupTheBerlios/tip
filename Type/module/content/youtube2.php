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
 * capable to directly manage youtube feeds (version 2).
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
