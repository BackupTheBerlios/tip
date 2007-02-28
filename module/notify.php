<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP definition file
 *
 * @package TIP
 * @subpackage Module
 */

/**
 * The user notification manager
 *
 * Provides an easy way to display error/warning/whatever messages to the
 * users.
 *
 * The message content is look-up by an id in the database configured for the
 * TIP_Notify module: the message so retrieved is called system message.
 * Anyway, you can add a custom text by setting the 'CONTEXT_MESSAGE' key
 * before a TIP_Notify::echo... call.
 *
 * The differents echo methods only affect the template source to use to show
 * the notify, do not perform any other operation.
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Notify extends TIP_Module
{
    /**#@+ @access private */

    var $_no_reentrant = false;

    /**#@-*/


    /**#@+ @access protected */

    /**
     * Generic message notification
     *
     * Outputs a generic notification message running the specified source
     * program with the current source engine. The output will be inserted
     * at the beginning of the page content.
     *
     * @param mixed  $id   The message id
     * @param string $source The source file to use as template
     * @return bool true on success or false on errors
     */
    function notify($source, $title_id, $message_id, $context)
    {
        if ($this->_no_reentrant) {
            TIP::warning('recursive call to notify()');
            return false;
        }

        $this->_no_reentrant = true;
        $locale =& TIP_Module::getInstance('locale', false);
        if (!is_object($locale)) {
            // TIP_Notify without TIP_Locale is not implemented 
            return false;
        }

        $message_id = $this->getId() . '.' . $message_id;
        $view =& $locale->startView($locale->data->rowFilter($message_id));
        if (!is_object($view)) {
            return false;
        }

        $view->summaries['TITLE'] = $locale->get($title_id, $this->getId(), null, false);
        if (!$view->rowReset()) {
            TIP::warning("message id not found ($message_id)");
            $view->summaries['MESSAGE'] = $message_id;
        }

        $locale->insertInContent($this->buildModulePath($source));
        $locale->endView();
        $this->_no_reentrant = false;
        return true;
    }

    /**#@-*/


    /**#@+
     * @access public
     * @param string $id      The locale id of the message
     * @param array  $context The message context
     * @return bool true on success or false on errors
     */

    /**
     * Error notification
     *
     * This is a convenience function that wraps notify(), passing the
     * proper arguments for error notifications.
     *
     * If $id is not specified, it will default to 'error.fallback'.
     * If there are no dots in $id, 'error.' will be prepended.
     */
    function notifyError($id = null, $context = null)
    {
        if (is_null($id)) {
            $id = 'error.fallback';
        } elseif (strpos($id, '.') === false) {
            $id = 'error.' . $id;
        }

        $source = $this->getOption('error_source');
        return $this->notify($source, 'error', $id, $context);
    }

    /**
     * User warning notification
     *
     * This is a convenience function that wraps notify(), passing the
     * proper arguments for warning notifications.
     *
     * If $id is not specified, it will default to 'warning.fallback'.
     * If there are no dots in $id, 'warning.' will be prepended.
     */
    function notifyWarning($id = null, $context = null)
    {
        if (is_null($id)) {
            $id = 'warning.fallback';
        } elseif (strpos($id, '.') === false) {
            $id = 'warning.' . $id;
        }

        $source = $this->getOption('warning_source');
        return $this->notify($source, 'warning', $id, $context);
    }

    /**
     * User info notification
     *
     * This is a convenience function that wraps notify(), passing the
     * proper arguments for info notifications.
     *
     * If $id is not specified, it will default to 'info.fallback'.
     * If there are no dots in $id, 'info.' will be prepended.
     */
    function notifyInfo($id = null, $context = null)
    {
        if (is_null($id)) {
            $id = 'info.fallback';
        } elseif (strpos($id, '.') === false) {
            $id = 'info.' . $id;
        }

        $source = $this->getOption('info_source');
        return $this->notify($source, 'info', $id, $context);
    }

    /**#@-*/
}

return 'TIP_Notify';

?>
