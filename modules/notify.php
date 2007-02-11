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
class TIP_Notify extends TIP_Block
{
    /**#@+ @access public */

    /**
     * Generic message notification
     *
     * Outputs a generic notification message running the specified source
     * program with the current source engine. The output will be inserted
     * at the beginning of the page content.
     *
     * @param mixed  $id   The message id
     * @param string $file The source file to use as template
     * @return bool TRUE on success or FALSE on errors
     */
    function echoMessage($id, $file)
    {
        $view =& $this->startView($this->data->rowFilter($id));
        $done = is_object($view);
        if ($done) {
            $done = ! is_null($view->rowReset()) && $this->insertInContent($file);
            $this->endView();
        }

        $this->keys['CONTEXT_MESSAGE'] = null;
        return $done;
    }

    /**#@+
     * @param mixed $id The id of the system message to show
     * @return bool TRUE on success or FALSE on errors
     */

    /**
     * User error notification
     *
     * This is a convenience function that wraps echoMessage().
     * Outputs the specified error message to notify the user about something
     * wrong. The output is generated running the source specified in the
     * 'error_source' configuration option and using the current source engine.
     *
     * If $id is not specified, the error id defaults to the one configured
     * by the 'error_fallback' option.
     */
    function echoError($id = null)
    {
        if (is_null($id)) {
            $id = $this->getOption('error_fallback');
        }

        return $this->echoMessage($id, $this->getOption('error_source'));
    }

    /**
     * User warning notification
     *
     * This is a convenience function that wraps echoMessage().
     * Outputs the specified warning message to notify the user about something
     * important. The output is generated running the source specified in the
     * 'warning_source' configuration option and using the current source
     * engine.
     *
     * If $id is not specified, the warning id defaults to the one configured
     * by the 'warning_fallback' option.
     */
    function echoWarning($id = null)
    {
        if (is_null($id)) {
            $id = $this->getOption('warning_fallback');
        }

        return $this->echoMessage($id, $this->getOption('warning_source'));
    }

    /**
     * User info notification
     *
     * This is a convenience function that wraps echoMessage().
     * Outputs the specified info message to notify the user about something.
     * The output is generated running the source specified in the
     * 'info_source' configuration option and using the current source engine.
     *
     * If $id is not specified, the info id defaults to the one configured
     * by the 'info_fallback' option.
     */
    function echoInfo($id = null)
    {
        if (is_null($id)) {
            $id = $this->getOption('info_fallback');
        }

        return $this->echoMessage($id, $this->getOption('info_source'));
    }

    /**#@-*/

    /**#@-*/
}

return new TIP_Notify;

?>
