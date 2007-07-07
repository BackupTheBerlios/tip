<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Cronology definition file
 *
 * @package TIP
 */

/**
 * A tree based on a date field
 *
 * @package TIP
 */
class TIP_Cronology extends TIP_Module
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

    //}}}
    //{{{ Construction/destruction

    static protected function checkOptions(&$options)
    {
        if (!parent::checkOptions($options) || !isset($options['master'])) {
            return false;
        }

        is_object($options['master']) || $options['master'] =& TIP_Type::getInstance($options['master']);
        return $options['master'] instanceof TIP_Content;
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Cronology instance.
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
     * Render this cronology in a DHTML format
     * @return string The rendered html
     */
    public function &toHtml()
    {
        $this->_render();
        return $this->_html;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The cached buffer containing the rendered tree
     * @var string
     * @internal
     */
    private $_html = null;

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
        if (isset($this->_html)) {
            return true;
        }

        $filter = $this->master->getProperty('data')->order($this->date_field);
        if (is_null($view =& $this->master->startDataView($filter))) {
            return false;
        }

        $rows =& $view->getProperty('rows');
        if (empty($rows)) {
            $this->_html = '';
            $this->master->endView();
            return true;
        }

        $base_action = TIP::getScriptURI();
        $action = $this->getOption('action');
        if ($action) {
            // Action specified: prepend the root URL
            $action = TIP::buildUrl($action);
        } else {
            // No action specified: construct the default cronology action (view)
            $action = $base_action . '?module=' . $this->master . '&amp;action=view&amp;id=';
        }

        $tree = array();
        foreach ($rows as $id => &$row) {
            $row['CLASS'] = 'item';
            $row['url'] = $action . $id;
            $row['title'] = $this->_renderField($this->title_field, $row);

            // Suppose the date is in ISO8601 format
            $date = $row[$this->date_field];
            list($y, $m, $day) = sscanf($date, "%d-%d-%d");

            // Compute the year
            $year = (string) $y;
            if (!array_key_exists($year, $tree)) {
                $tree[$year] = array(
                    'title' => $year,
                    'url'   => 'unused',
                    'CLASS' => 'folder',
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
            $month_id = $year . '-' . $month;
            if (!array_key_exists($month_id, $months)) {
                $months[$month_id] = array(
                    'title' => $month,
                    'url'   => 'unused',
                    'CLASS' => 'folder',
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

        require_once 'HTML/Menu.php';
        $model =& new HTML_Menu($tree);
        $model->forceCurrentUrl(htmlspecialchars(TIP::getRequestURI()));

        $renderer =& TIP_Renderer::getMenu($this->id);
        $model->render($renderer, 'sitemap');
        $this->_html = $renderer->toHtml();
        return true;
    }

    //}}}
}
?>
