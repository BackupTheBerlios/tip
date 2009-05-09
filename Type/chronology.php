<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Chronology definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
 */

/**
 * A tree based on a date field
 *
 * @package TIP
 */
class TIP_Chronology extends TIP_Type
{
    //{{{ Properties

    /**
     * A reference to the master content module
     * @var TIP_Content
     */
    protected $master = null;

    /**
     * The hierarchy field
     *
     * The field id to parse for the date and to use as index for the hierarchy.
     *
     * @var string
     */
    protected $date_field = '_creation';

    /**
     * Title field
     *
     * The field to show in the leaf nodes. This can be an array of field ids,
     * in which case the string will be a comma separated list of the values.
     *
     * @var string|array
     */
    protected $title_field = 'title';

    /**
     * Tooltip field
     *
     * The field to show as tooltip for the leaf nodes. This can be an array
     * of field ids, as for 'title_field'
     *
     * @var string|array
     */
    protected $tooltip_field = null;

    /**
     * The field that forces a specified order
     * @var string
     */
    protected $count_field = '_count';

    /**
     * The action for this chronology
     * @var string
     */
    protected $action = null;

    /**
     * Maximum number of levels to keep online
     * @var int
     */
    protected $levels = null;

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['master'])) {
            return false;
        }

        if (is_string($options['master'])) {
            $options['master'] =& TIP_Type::getInstance($options['master']);
        } elseif (is_array($options['master'])) {
            $options['master'] =& TIP_Type::singleton($options['master']);
        }
        if (!$options['master'] instanceof TIP_Content) {
            return false;
        }

        isset($options['action']) || $options['action'] = 'view,-id-';
        return true;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Chronology instance.
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
     * Render this chronology in XHTML format
     * @return string The rendered html
     */
    public function toHtml()
    {
        if (!$this->_render()) {
            return '';
        }

        require_once 'HTML/Menu.php';
        $model =& new HTML_Menu($this->_tree);
        $model->forceCurrentUrl(TIP::getRequestUri());

        $renderer =& TIP_Renderer::getMenu($this->levels);
        $model->render($renderer, 'sitemap');
        $html = $renderer->toHtml();

        if ($renderer->isCurrentContainer()) {
            // If the current row is a container, don't index this page
            TIP_Application::setRobots(false, null);
        }

        return $html;
    }

    //}}}
    //{{{ Actions

    /**
     * Perform a browse action
     *
     * In $conditions, you must specify an associative array of
     * 'field_id' => 'value' to impose for this browse action. Only equal
     * conditions are allowed.
     *
     * @param  array &$conditions The browse conditions
     * @return bool               true on success or false on errors
     */
    protected function actionBrowse($id)
    {
        if (!TIP_AHAH) {
            // Browse actions implemented only as AHAH response
            return false;
        }

        sscanf($id, '%04s%02s', $year, $month);
        if (!$year || !$this->_render()) {
            return false;
        }

        if (array_key_exists($year, $this->_tree)) {
            $tree =& $this->_tree[$year]['sub'];
        } else {
            return true;
        }

        if (array_key_exists($id, $tree)) {
            $tree =& $tree[$id]['sub'];
        } elseif (isset($month)) {
            return true;
        }
        
        require_once 'HTML/Menu.php';
        $model =& new HTML_Menu($tree);
        $renderer =& TIP_Renderer::getMenu(0);
        $model->render($renderer, 'sitemap');

        $content =& TIP_Application::getGlobal('content');
        $content .= $renderer->toHtml();
        return true;
    }

    protected function runAction($action)
    {
        switch ($action) {

        case 'browse':
            if (is_null($id = TIP::getGet('id', 'string'))) {
                TIP::warning('GET not found (id)');
                TIP::notifyError('noparams');
                return false;
            }

            return $this->actionBrowse($id);
        }

        return null;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The cached tree in HTML_Menu format
     * @var array
     * @internal
     */
    private $_tree = array();

    //}}}
    //{{{ Internal methods

    private function _renderField($field, &$row)
    {
        if (is_string($field)) {
            return $row[$field];
        } elseif (is_array($field)) {
            return implode(', ', array_intersect_key($row, array_flip($field)));
        }

        // No way to render this field
        $field_type = gettype($field);
        TIP::warning("not a valid field type ($field_type)");
        return '';
    }

    private function _render()
    {
        if (!empty($this->_tree)) {
            return true;
        }

        $filter = $this->master->getProperty('data')->order($this->date_field);
        if (is_null($view =& $this->master->startDataView($filter))) {
            return false;
        }

        $rows =& $view->getProperty('rows');
        if (empty($rows)) {
            $this->_tree = array();
            $this->master->endView();
            return true;
        }

        $tree =& $this->_tree;
        foreach ($rows as $id => &$row) {
            $action = str_replace('-id-', $id, $this->action);
            $row['url'] = TIP::buildActionUriFromTag($action, (string) $this->master);
            $row['title'] = $this->_renderField($this->title_field, $row);

            // Suppose the date is in SQL format
            $date = $row[$this->date_field];
            list($y, $m, $day) = sscanf($date, "%d-%d-%d");

            // Compute the year
            $year = sprintf('%04d', $y);
            if (!array_key_exists($year, $tree)) {
                $tree[$year] = array(
                    'title' => $year,
                    'url'   => TIP::modifyActionUri(null, null, null, array($this->id => $year)),
                    'sub'   => array(),
                    'ITEMS' => 0
                );
                isset($this->count_field) && $tree[$year]['COUNT'] = 0;
            }
            ++ $tree[$year]['ITEMS'];
            isset($this->count_field) && $tree[$year]['COUNT'] += $row[$this->count_field];

            // Compute the month
            $months =& $tree[$year]['sub'];
            $month = strftime('%B', mktime(0, 0, 0, $m, $day, $year));
            $month_id = $year . sprintf('%02d', $m);
            if (!array_key_exists($month_id, $months)) {
                $months[$month_id] = array(
                    'title' => $month,
                    'url'   => TIP::modifyActionUri(null, null, null, array($this->id => $month_id)),
                    'sub'   => array(),
                    'ITEMS' => 0
                );
                isset($this->count_field) && $months[$month_id]['COUNT'] = 0;
            }
            ++ $months[$month_id]['ITEMS'];
            isset($this->count_field) && $months[$month_id]['COUNT'] += $row[$this->count_field];

            $months[$month_id]['sub'][$id] =& $row;
            isset($this->count_field) && $row['COUNT'] = $row[$this->count_field];
        }

        return true;
    }

    //}}}
}
?>
