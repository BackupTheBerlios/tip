<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

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
    //{{{ Properties

    /**
     * The file to run to notify errors
     * @var string
     */
    protected $error_source = 'error.src';

    /**
     * The file to run to notify warnings
     * @var string
     */
    protected $warning_source = 'warning.src';

    /**
     * The file to run to notify informations
     * @var string
     */
    protected $info_source = 'info.src';

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Notify instance.
     *
     * $options inherits the TIP_Module properties, and add the following:
     * - $options['error_source']:   the file to run to show errors
     * - $options['warning_source']: the file to run to show warnings
     * - $options['info_source']:    the file to run to show informations
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
     * Generic message notification
     *
     * Outputs a generic notification message running the specified source
     * program with the current source engine. The output will be inserted
     * at the beginning of the page content.
     *
     * If $id is null, the 'fallback' value will be used instead.
     *
     * @param  string $source  The source to run
     * @param  mixed  $prefix  The string to be prepended to $id,
     *                         without leading 'notify.' and trailing '.'
     * @param  mixed  $id      The TIP_Locale id of the message,
     *                         without 'notify.' and $prefix prefix
     * @param  array  $context Optional associative array of tags to be
     *                         expanded in the message string
     * @return bool            true on success or false on errors
     */
    public function notify($source, $prefix, $id, $context)
    {
        static $running = false;

        // The running flag avoid recursive calls to notify()
        if ($running) {
            TIP::warning('recursive call to notify()');
            return false;
        }

        $running = true;
        if (is_null($locale =& TIP_Type::getInstance('locale'))) {
            TIP::warning('TIP_Notify without TIP_Locale is not implemented');
            return false;
        }

        isset($id) || $id = 'fallback';
        $title_id = $this->id . '.' . $prefix;
        $message_id = $title_id . '.' . $id;
        $data =& $locale->getProperty('data');

        $key = $data->getProperty('primary_key');
        $filter = $data->filter($key, $title_id) . $data->addFilter('OR', $key, $message_id);
        if (!is_null($view =& $locale->startDataView($filter))) {
            $rows =& $view->getProperty('rows');
            $locale->endView();
            $locale_id = $locale->getProperty('locale');
            $title = @$rows[$title_id][$locale_id];
            $message = @$rows[$message_id][$locale_id];
        }

        // Fallback values
        isset($title) || ($title = 'UNDEFINED TITLE') && TIP::warning("localized id not found ($title_id)");
        isset($message) || ($message = 'Undefined message') && TIP::warning("localized id not found ($message_id)");

        // Run the source
        $this->keys['TITLE'] = $title;
        $this->keys['MESSAGE'] = $message;
        $this->insertInPage($source);
        $running = false;
        return true;
    }

    /**
     * Error notification
     *
     * This is a convenience function that wraps notify(), passing the
     * proper arguments for error notifications.
     *
     * If $id is not specified, it will default to 'fallback'.
     *
     * @param  string $id      The TIP_Locale id of the message
     *                         without the 'notify.error.' prefix
     * @param  array  $context Optional associative array of tags to be
     *                         expanded in the message string
     * @return bool            true on success or false on errors
     */
    public function notifyError($id = null, $context = null)
    {
        return $this->notify($this->error_source, 'error', $id, $context);
    }

    /**
     * User warning notification
     *
     * This is a convenience function that wraps notify(), passing the
     * proper arguments for warning notifications.
     *
     * If $id is not specified, it will default to 'fallback'.
     *
     * @param  string $id      The TIP_Locale id of the message
     *                         without the 'notify.warning.' prefix
     * @param  array  $context Optional associative array of tags to be
     *                         expanded in the message string
     * @return bool            true on success or false on errors
     */
    public function notifyWarning($id = null, $context = null)
    {
        return $this->notify($this->warning_source, 'warning', $id, $context);
    }

    /**
     * User info notification
     *
     * This is a convenience function that wraps notify(), passing the
     * proper arguments for info notifications.
     *
     * If $id is not specified, it will default to 'fallback'.
     *
     * @param  string $id      The TIP_Locale id of the message
     *                         without the 'notify.info.' prefix
     * @param  array  $context Optional associative array of tags to be
     *                         expanded in the message string
     * @return bool            true on success or false on errors
     */
    public function notifyInfo($id = null, $context = null)
    {
        return $this->notify($this->info_source, 'info', $id, $context);
    }

    //}}}
}
?>
