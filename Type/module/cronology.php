<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

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
    /**
     * A reference to the master content module.
     * @var TIP_Content
     */
    private $_content = null;

    /**
     * The field id to parse for the date
     * @var string
     */
    private $_date_field = null;

    /**
     * The field to show as the leaf nodes. This can be an array of field ids,
     * in which case the string will be a comma separated list of the values.
     * @var string|array
     */
    private $_title_field = null;

    /**
     * The field to show as tooltip for the leaf nodes. This can be an array
     * of field ids, as for $_title_field.
     * @var string|array
     */
    private $_tooltip_field = null;

    /**
     * The cached buffer containing the rendered tree.
     * @var string
     */
    private $_html = null;


    /**
     * Constructor
     *
     * Initializes a TIP_Cronology instance.
     *
     * @param string $id The instance identifier
     */
    function __construct($id)
    {
        parent::__construct($id);

        $this->_content =& TIP_Type::getInstance($this->getOption('master_module'));
        $this->_date_field = $this->getOption('date_field');
        $this->_title_field = $this->getOption('title_field');
        $this->_tooltip_field = $this->getOption('tooltip_field');
    }

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

        $filter = $this->_content->data->order($this->_date_field, true);
        if (is_null($view =& $this->_content->startView($filter))) {
            return false;
        }

        $rows =& $view->getRows();
        if (empty($rows)) {
            $this->_html = '';
            $this->_content->endView();
            return true;
        }

        $base_action = TIP::getScriptURI();
        $action = $this->getOption('action');
        if ($action) {
            // Action specified: prepend the root URL
            $action = TIP::buildUrl($action);
        } else {
            // No action specified: construct the default action (browse)
            $id = $this->getId();
            $action = $base_action . '?module=' . substr($id, 0, strrpos($id, '_')) . '&amp;action=browse&amp;group=';
        }

        $tree = array();
        foreach ($rows as $id => &$row) {
            $row['CLASS'] = 'item';
            $row['url'] = $action . $id;
            $row['title'] = $this->_renderField($this->_title_field, $row);

            // Suppose the date is in ISO8601 format
            $date = $row[$this->_date_field];
            list($y, $m, $day) = sscanf($date, "%d-%d-%d");
            $year = (string) $y;
            $month = strftime('%B', mktime(0, 0, 0, $m, $day, $year));

            if (!array_key_exists($year, $tree)) {
                $tree[$year] = array(
                    'title' => $year,
                    'url'   => 'unused',
                    'CLASS' => 'folder',
                    'sub'   => array()
                );
            }
            $months =& $tree[$year]['sub'];
            $month_id = $year . '-' . $month;
            if (!array_key_exists($month_id, $months)) {
                $months[$month_id] = array(
                    'title' => $month,
                    'url'   => 'unused',
                    'CLASS' => 'folder',
                    'sub'   => array()
                );
            }
            $months[$month_id]['sub'][$id] =& $row;
        }

        require_once 'HTML/Menu.php';
        $model =& new HTML_Menu($tree);
        $model->forceCurrentUrl(htmlspecialchars(TIP::getRequestURI()));

        $renderer =& TIP_Renderer::getMenu($this->getId());
        $model->render($renderer, 'sitemap');
        $this->_html = $renderer->toHtml();
        return true;
    }

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
}
?>
