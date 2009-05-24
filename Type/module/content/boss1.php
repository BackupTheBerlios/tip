<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Boss1 definition file
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
 * @since     0.3.4
 */

/**
 * A content module managing Yahoo BOSS searches
 *
 * A TIP_Content implementation that sets a default data engine
 * capable to directly parse Yahoo BOSS XML responses (version 1).
 *
 * To be able to use this module, you must sign up to yahoo and
 * get a BOSS application ID:
 *
 * http://developer.yahoo.com/
 *
 * This is a required option. When done, you can easily build a
 * search module by providing the following values in the
 * configuration options:
 *
 * <code>
 * $cfg = array(
 *     ...
 *     'search'   => array(
 *         'type' => array('module', 'content', 'boss1'),
 *         'data' => '...'
 *     )
 *     ...
 * );
 * </code>
 *
 * putting your newly BOSS application ID in 'data'. Alternatively
 * you can directly specify an URI as data, leaving the {terms} tag
 * where you want the terms to be substituted. This means
 * the following options is roughly equivalent (if the Yahoo URI
 * does not change) to the previous ones:
 *
 * <code>
 * $cfg = array(
 *     ...
 *     'search'   => array(
 *         'type' => array('module', 'content', 'boss1'),
 *         'data' => 'http://boss.yahooapis.com/ysearch/web/v1/{terms}?appid=...&format=xml&abstract=long'
 *     )
 *     ...
 * );
 * </code>
 *
 * After configuring it, you can use your newly "search" module in
 * the same way a common TIP_Content module is used.
 *
 * First of all, you need to implement a search form that calls the
 * "browse" action of this search module. In the following, a really
 * basic example using the TIP_RcbtNg template engine:
 *
 * <code>
 * <form id="idSearch" action="{actionUri(browse,,module=search)}" method="post">
 *   <fieldset>
 *     <legend>Search on this domain</legend>
 *     <label for="terms">Terms:</label>
 *     <input type="text" name="terms" id="terms" value="{(post[terms])}" />
 *     <input value="Search" type="submit" />
 *   </fieldset>
 * </form>
 * </code>
 *
 * To be able to access the responses, TIP_Boss1 considers any
 * <result> in the XML as any SQL engine treats a row in a table.
 * The field of this virtual row are mapped to a usable field id
 * using the "fields_xpath" property of the TIP_XML engine. Here
 * it is a list of these virtual fields with their own XPath
 * used to map them:
 *
 * - **id** (automatically generated integer)
 * - **title** ({{Title}})
 * - **summary** ({{Summary}})
 * - **url** ({{Url}})
 * - **displayurl** ({{DisplayUrl}})
 * - **clickurl** ({{ClickUrl}})
 *
 * Adding new field mappings is trivial: actually, I mapped
 * the fields I'm using.
 *
 * The above fields can be used in you templates just as every other
 * field provided by the more usual TIP_Mysql engine, just remember
 * to use tags in the browse template of the search module.
 * For instance, to show the search result as a simple list of the
 * above module, you can save the following snippet in
 * style/search/browse.rcbt:
 *
 * <code>
 * <h1>Search results</h1>
 * <ul class="search">{forSelect()}
 *   <li> 
 *     <strong>{raw(title)}</strong>
 *     <div>{raw(summary)}</div>
 *     <a href="{raw(clickurl)}">{raw(url)}</a>
 *   </li>{}
 * </ul>
 * </code>
 *
 * Because TIP_Boss1 accesses the responses using the TIP_XML
 * data engine there are some known restriction: for instance,
 * you can't do a complex SQL query using the ORDER BY or GROUP BY
 * clauses. For common usage, I think it was not worth the effort for a
 * full-fledged SQL parser. Check the TIP_XML documentation to exactly
 * know which queries can be executed.
 *
 * @package TIP
 */
class TIP_Boss1 extends TIP_Content
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

        // If the 'data' option does not start with "http://", it is
        // supposed to be a BOSS developer API and treated as such
        if (strncmp($options['data']['path'], 'http://', 7) != 0) {
            $options['data']['path'] =
                'http://boss.yahooapis.com/ysearch/web/v1/{terms}' .
                '?format=xml&abstract=long&appid=' . $options['data']['path'];
        }

        // The TIP_XML engine used to access TIP_Boss1 modules
        // is non-reentrant and shared among multiple instances
        // (this is obtained by using the same id, 'boss1').
        TIP::arrayDefault($options['data'], 'data_engine', array(
            'id'             => 'boss1',
            'type'           => array('data_engine', 'xml'),
            'base_xpath'     => 'resultset_web',
            'row_xpath'      => 'result',
            'fields_xpath'   => array(
                'title'      => 'title',
                'summary'    => 'abstract',
                'displayurl' => 'dispurl',
                'url'        => 'url',
                'clickurl'   => 'clickurl'
        )));

        // Allows queries for everyone
        TIP::arrayDefault($options, 'browsable_fields', array(
            TIP_PRIVILEGE_NONE  => array('__ALL__')
        ));

        return parent::checkOptions($options);
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Boss1 instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ Actions

    /**
     * Perform a browse action
     *
     * Overrides the "browse" action to substitute {terms} in the query URI.
     *
     * @param  array &$conditions The browse conditions
     * @return bool               true on success or false on errors
     */
    protected function actionBrowse(&$conditions)
    {
        // Prepare the terms: append " site:thisdomain" to limit the query
        // to the current site
        $terms = TIP::getGetOrPost('terms', 'string');
        $terms .= ' site:' . $_SERVER['SERVER_NAME'];

        $query =& $this->data->getProperty('path');
        $query = str_replace('{terms}', urlencode($terms), $query);

        return parent::actionBrowse($conditions);
    }

    //}}}
}
?>
