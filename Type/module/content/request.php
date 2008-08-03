<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Request definition file
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
 * @since     0.2.5
 */

/**
 * Request module
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Request extends TIP_Content
{
    //{{{ Properties

    /**
     * The email where the request must be forwarded
     * @var string
     */
    protected $notify_to = null;

    /**
     * The email address to use in the 'From:' field
     * or null to try to use the admin email
     * @var string
     */
    protected $notify_from = null;

    /**
     * The subject to use for email notification
     * @var string
     */
    protected $subject_text = 'Info request from website';

    /**
     * The message template to use as body of the email notification
     * @var string
     */
    protected $message_template = 'body.tip';

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Request instance.
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
     * Get a field value
     *
     * Gets a field from the current row of the current view.
     *
     * @param  string     $id The field id
     * @return mixed|null     The field value or null on errors
     */
    public function getField($id)
    {
        if (array_key_exists($id, $this->_current_row)) {
            return $this->_current_row[$id];
        }
        return parent::getField($id);
    }

    //}}}
    //{{{ Internal properties

    /**
     * The current row in use, to be checked by getField()
     * @var array
     * @internal
     */
    private $_current_row = null;

    //}}}
    //{{{ Callbacks

    /**
     * Override the default callback providing email notification when requested
     * @param  array &$row The subject row
     * @return bool        true on success, false on errors
     */
    public function _onAdd(&$row)
    {
        if (!parent::_onAdd($row)) {
            return false;
        }

        if (empty($this->notify_to)) {
            return true;
        }

        $eol = "\r\n";
        $env = 'TiP-' . TIP_VERSION_BRANCH;
        $sys = 'PHP-' . phpversion();

        $headers  = 'From: ' . $this->_getServerEmail() . $eol;
        $headers .= 'X-Mailer: TIP_Request module ' . "($env; $sys)" . $eol;
        $headers .= 'MIME-Version: 1.0' . $eol;
        $headers .= 'Content-Type: text/plain; charset=ISO-8859-1';

        // Assign current_row, so getField() checks for values in this row
        $this->_current_row =& $row;

        ob_start();
        if ($this->tryRun($this->message_template)) {
            $message = ob_get_clean();
        } elseif (array_key_exists($this->message_template, $row)) {
            ob_end_clean();
            $message = $row[$this->message_template];
        } else {
            ob_end_clean();
            $message = 'Undefined message';
        }

        $message = wordwrap(utf8_decode($message), 66);

        if (!mail($this->notify_to, $this->subject_text, $message, $headers)) {
            TIP::warning("Unable to send an email message to $this->notify_to");
            TIP::notifyError('nosend');
        }

        return true;
    }

    //}}}
    //{{{ Internal methods

    /**
     * Get the server email to use in the 'From:' field
     * @return   string The requested email address
     * @internal
     */
    private function _getServerEmail()
    {
        // Use the custom address, if provided
        $email = $this->notify_from;

        if (empty($email)) {
            // Try to use the administrator email
            $email = @$_SERVER['SERVER_ADMIN'];
        }

        if (empty($email)) {
            // Try to build a dummy email address using this domain
            $domain = @$_SERVER['HTTP_HOST'];
            isset($domain) || $domain = @$_SERVER['SERVER_NAME'];

            $token = explode('.', $domain);
            if (count($token) >= 2) {
                // Get rid of the prepended subdomains (if any)
                $domain = implode('.', array_slice($token, -2, 2));

                $email = 'donotreply@' . $domain;
            }
        }

        if (empty($email)) {
            // Use a dummy email address on a dummy domain
            $email = 'donotreply@dummydomain.com';
        }

        return $email;
    }

    //}}}
}
?>
