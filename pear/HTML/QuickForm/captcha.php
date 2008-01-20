<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

require_once 'HTML/QuickForm/input.php';

HTML_QuickForm::registerRule('captcha', 'callback', '_ruleCaptcha', 'HTML_QuickForm_captcha');

/**
 * Common class for HTML_QuickForm_captcha elements
 */
class HTML_QuickForm_captcha extends HTML_QuickForm_input
{
    //{{{ Properties

    /**
     * The value to be matched
     * @var mixed
     */
    var $_value = null;

    /**
     * The current locale
     * @var string
     */
    var $_locale = 'en_US';

    /**
     * The captcha method
     * @var string
     */
    var $_method = 'Numbers_Words';

    //}}}
    //{{{ Constructor/destructor

    /**
     * Class constructor
     *
     * @param      string    Name
     * @param      mixed     Label for the captcha
     * @param      mixed     HTML Attributes for the <a> tag surrounding the
     *                       image. Can be a string or array.
     * @access     public
     */
    function HTML_QuickForm_captcha($elementName = null, $elementLabel = null, $attributes = null)
    {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
    }

    //}}}
    //{{{ Methods

    /**
     * Return the current captcha method
     * @return string
     * @access public
     */
    function getMethod()
    {
        return $this->_method;
    }

    /**
     * Set the captcha method
     *
     * @param  string $method The new method
     * @access public
     */
    function setMethod($method)
    {
        $this->_method = $method;
    }

    /**
     * Return the current locale to use
     *
     * @return string
     * @access public
     */
    function getLocale()
    {
        return $this->_locale;
    }

    /**
     * Set a new locale
     *
     * @param  string $locale The new locale to use
     * @access public
     */
    function setLocale($locale)
    {
        $this->_locale = $locale;
    }

    //}}}
    //{{{ Overriden methods

    /**
     * Return the current captcha number
     *
     * @return int
     * @access public
     */
    function getValue()
    {
        return $this->_value;
    }

    /**
     * Set the captcha number
     *
     * @param  int    $value The new captcha number
     * @access public
     */
    function setValue($value)
    {
        $this->_value = $value;
    }

    /**
     * Return the captcha number
     *
     * @return int
     * @access public
     */
    function exportValue(&$submitValues, $assoc = false)
    {
        return $assoc ? array($this->getName() => $this->_value) : $this->_value;
    }

    function toHtml()
    {
        // The captcha must desappear in frozen state
        if ($this->_flagFrozen) {
            return '';
        }

        switch ($this->_method) {

        case 'Numbers_Words':
            require_once 'Numbers/Words.php';
            isset($this->_value) || $this->_value = rand(1, 1000);
            $html = Numbers_Words::toWords($this->_value, $this->_locale);
            $html = parent::toHtml() . '&nbsp;' . ucfirst($html);
            break;

        default:
            $html = '';
        }

        HTTP_Session2::set('_HTML_QuickForm_captcha', $this->_value);
        return $html;
    }

    //}}}
    //{{{ Callbacks

    /**
     * Check if the picture is contained by the specified bounding box
     *
     * @param  array  $value  Value as returned by HTML_QuickForm_captcha::getValue()
     * @return bool           true if the captcha matches, false otherwise
     * @access public
     */
    function _ruleCaptcha($value)
    {
        $old = HTTP_Session2::get('_HTML_QuickForm_captcha');
        return $value == $old;
    }

    //}}}
    //{{{ Internal methods

    function _findValue(&$values)
    {
        return $this->_value;
    }

    //}}}
}
?>
