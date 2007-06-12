<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Sponsor definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Sponsor module
 *
 * Provides a basic interface to the localization of short texts (less than
 * 256 bytes).
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Sponsor extends TIP_Content
{
    /**
     * Row of the current showed sponsor
     * @var array
     */
    private $_row = null;

    /**
     * Old content of $_row
     * @var array
     */
    private $_old_row = null;

    /**
     * Constructor
     *
     * Initializes a TIP_Sponsor instance.
     *
     * @param string $id The instance identifier
     */
    protected function __construct($id)
    {
        parent::__construct($id);
    }

    protected function postConstructor()
    {
        parent::postConstructor();

        $filter = $this->data->order('_count') . ' LIMIT 1';
        if (!is_null($view = $this->startView($filter))) {
            $this->_row = $this->_old_row = $view->current();
        }
    }

    /**
     * Destructor
     *
     * Updates the count
     */
    public function __destruct()
    {
        if (is_array($this->_row)) {
            // Increment _count
            ++ $this->_row['_count'];
            $this->data->updateRow($this->_row, $this->_old_row);
            $this->endView();
        }
    }
}
?>
