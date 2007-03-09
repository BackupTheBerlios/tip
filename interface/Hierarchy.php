<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Hierarchy definition file
 *
 * @package TIP
 */

/** HTML_TreeMenu PEAR package */
require_once 'HTML/Menu.php';

/**
 * A TIP_Block with a hierarchy as data model
 *
 * @package TIP
 */
class TIP_Hierarchy extends TIP_Block
{
    /**#@+ @access private */

    var $_master_id = null;
    var $_model = null;

    var $_action = 'browse';
    var $_icon = null;
    var $_closed_icon = null;
    var $_opened_icon = null;


    function _onView(&$view)
    {
        if (isset($this->_model)) {
            return true;
        }

        $view->summaries['TOTAL_COUNT'] = 0;
        $total_count =& $view->summaries['TOTAL_COUNT'];
        $base_url = TIP::buildUrl(
            'index.php?' .
            'module=' . $this->_master_id .
            '&amp;action=' . $this->_action .
            '&amp;id='
        );

        $tree  = array();
        $nodes = array();
        foreach ($view->rows as $id => $node) {
            $parent_id     = @$node['parent'];
            $count         = @$node['_count'];
            $total_count  += $count;

            $node['url']   = $base_url . $id;
            $node['COUNT'] = $count;
            $node['CLASS'] = 'item';

            if ($parent_id) {
                $parent =& $nodes[$parent_id];
                $parent['sub'][$id] =  $node;
                $parent['COUNT']    += $count;
                $parent['CLASS']    =  'folder';
                $nodes[$id]         =& $parent['sub'][$id];
            } else {
                $tree[$id]  = $node;
                $nodes[$id] =& $tree[$id];
            }

        }

        $this->_model =& new HTML_Menu($tree);
        return true;
    }

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Constructor
     *
     * Initializes an implementation of a TIP_Hierarchy interface.
     *
     * @param string $block_id The id of the master block
     */
    function TIP_Hierarchy($block_id)
    {
        // There is a singleton for every master block
        $this->_id = strtolower($block_id) . '_hierarchy';
        $this->_master_id = $block_id;
        $this->TIP_Block();
    }

    function getOption($option)
    {
        return @$GLOBALS['cfg'][$this->_master_id]['hierarchy'][$option];
    }

    function& startView($filter)
    {
        $view =& TIP_View::getInstance($filter, $this->data);
        $view->on_view->set(array(&$this, '_onView'));
        return $this->push($view);
    }

    /**#@+
     * @param string @params The parameter string
     * @return bool true on success or false on errors
     * @subpackage SourceEngine
     */

    /**
     * Echo the hierarchy of the master block
     *
     * Outputs the DHTML hierarchy of the specified block.
     */
    function commandShow($params)
    {
        return $this->show();
    }

    /**#@-*/

    /**#@-*/


    /**#@+ @access public */

    /**
     * Render a DHTML hierarchy
     *
     * Renders this hierarchy in a DHTML form.
     */
    function show()
    {
        static $renderer = false;

        if (is_null($this->_model)) {
            // Generate the model by starting a view on the whole data
            if (!$this->startView('')) {
                return false;
            }
            $this->endView();
        }

        // The renderer is unique for all the TIP_Hierarchy instances
        if (!$renderer) {
            require_once 'HTML/Menu/TipRenderer.php';
            $renderer =& new HTML_Menu_TipRenderer();
        }

        $renderer->setId($this->getId());
        $this->_model->render($renderer, 'sitemap');
        echo $renderer->toHtml();
        return true;
    }

    /**#@-*/
}

?>
