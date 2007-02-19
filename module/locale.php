<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Locale definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Locale module
 *
 * Provides a basic interface to the localization of short texts (less than
 * 256 bytes).
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Locale extends TIP_Block
{
    /**#@+ @access public */

    /**
     * Get a localized text
     *
     * Given an $id and a $module, retrieves the localized text (in the current
     * locale) binded to "$module.$id". This means the data object of
     * TIP_Locale must have a series of rows with module.id values as the
     * primary key.
     *
     * Usually the locale query is cached, that is, to avoid multiple queries,
     * a single request from a module gets all the localized text of this
     * module. If you are sure the other localized strings are not used (such
     * as for the TIP_Notify module) you can disable the cache by passig false
     * to the $cached argument.
     *
     * @param string $id     The text identifier
     * @param string $module The name of the caller module
     * @param bool   $cached Whether to perform or not a cached read
     * @return string The requested localized text or $id on errors
     */
    function get($id, $module, $cached = true)
    {
        $row_id = $module . '.' . $id;

        if ($cached) {
            $filter = $this->data->filter('id', $module . '.%', 'LIKE');
            $view =& $this->startView($filter);
            if (is_null($view)) {
                $this->logWarning("Module not localized ($module)");
                return $id;
            }

            $rows =& $this->view->rows;
            if (array_key_exists($row_id, $rows)) {
                $row =& $rows[$row_id];
            } else {
                $row = null;
            }

            $this->endView();
        } else {
            $row =& $this->data->getRow($row_id);
        }

        if (is_null($row)) {
            $this->logWarning("Localized text not found ($row_id)");
            return $id;
        }

        $locale = TIP::getOption('application', 'locale');
        if (!array_key_exists($locale, $row)) {
            $this->logWarning("Locale not defined ($locale)");
            return $id;
        }

        return $row[$locale];
    }

    /**#@-*/
}

return 'TIP_Locale';

?>
