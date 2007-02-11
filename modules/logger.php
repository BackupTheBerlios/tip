<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Logger definition file
 * @package TIP
 * @subpackage Module
 */

/**
 * Logger module
 *
 * Provides a way to log messages to a data source.
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Logger extends TIP_Block
{
    /**#@+ @access public */

    /**
     * Append a log
     *
     * Appends a log message to the data source of the logger object.
     *
     * @param string @domain A custom domain description
     * @param string @message The text of the log
     * @param string @uri     The URI that caused the log
     * @param bool   @notify  Wheter or not to notify the log to the webmaster
     */
    function logMessage($domain, $message, $uri, $notify = false)
    {
        $user_id = TIP::getUserId();
        if ($user_id > 0) {
            $row['user'] = $user_id;
        }

        if ($notify) {
            $row['notify'] = $notify;
        }

        $row['when'] = TIP::formatDate('datetime_iso8601');
        $row['domain'] = $domain;
        $row['message'] = $message;
        $row['uri'] = $uri;

        $this->data->putRow($row);
    }

    /**#@-*/
}

return new TIP_Logger;

?>
