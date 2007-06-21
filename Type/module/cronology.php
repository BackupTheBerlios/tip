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

    //}}}
    //{{{ Internal properties

    /**
     * The cached buffer containing the rendered tree
     * @var string
     * @internal
     */
    private $_html = null;

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
     * $options inherits the TIP_Module properties, and add the following:
     * - $options['master']:        a reference to the master content module (required)
     * - $options['date_field']:    the field id to parse for the date
     * - $options['title_field']:   the field to show in the leaf nodes
     * - $options['tooltip_field']: the field to show as tooltip
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
     * Render a DHTML cronology
     *
     * Renders this cronology in a DHTML format.
     *
     * @return true on success or false on errors
     */
    public function &getHtml()
    {
        $this->_render();
        return $this->_html;
    }

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
        if (!is_null($this->_html)) {
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
            // No action specified: construct the default action (browse)
            $action = $base_action . '?module=' . $this->master . '&amp;action=browse&amp;group=';
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
                    'COUNT' => 0
                );
            }
            ++ $tree[$year]['COUNT'];

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
                    'COUNT' => 0
                );
            }
            ++ $months[$month_id]['COUNT'];
            $months[$month_id]['sub'][$id] =& $row;
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
    //{{{ Commands

    /**
     * Echo the cronology
     *
     * Outputs the DHTML cronology of this instance.
     *
     * @param  string $params Not used
     * @return bool           true on success or false on errors
     */
    protected function commandShow($params)
    {
        if (!$this->_render()) {
            return false;
        }

        echo $this->_html;
        return true;
    }

    //}}}
}
?>
