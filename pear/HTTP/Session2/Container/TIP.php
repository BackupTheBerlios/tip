<?php

require_once 'HTTP/Session2/Container.php';

/**
 * TIP container for session data
 *
 * Saves the session data (only for logged in users) in the TIP_User module.
 *
 * @author  Nicola Fontana <ntd@users.sourceforge.net>
 * @package HTTP_Session2
 */
class HTTP_Session2_Container_TIP extends HTTP_Session2_Container
{
    /**
     * User module
     *
     * @var TIP_User
     */
    private $_user = null;


    /**
     * Constuctor
     */
    public function __construct()
    {
        $this->_user =& TIP_Application::getSharedModule('user');
    }

    /**
     * Check if the current user is logged-in
     *
     * @param  string $save_path    Not used
     * @param  string $session_name Not used
     * @return bool                 true on success, false on errors
     */
    public function open($save_path, $session_name)
    {
        return isset($this->_user->keys['CID']);
    }

    /**
     * Free resources
     *
     * @return bool true on success, false on errors
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param  mixed $id Session id (not used)
     * @return bool      true on success, false on errors
     */
    public function read($id)
    {
        return $this->_user->getLoggedField('_session');
    }

    /**
     * Write session data
     *
     * @param  mixed $id   Session id (not used)
     * @param  mixed $data Session data
     * @return bool        true on success, false on errors
     */
    public function write($id, $data)
    {
        return $this->_user->setLoggedField('_session', $data);
    }

    /**
     * Destroy session data
     *
     * @param  mixed $id Session id (not used)
     * @return bool      true on success, false on errors
     */
    public function destroy($id)
    {
        return $this->_user->setLoggedField('_session', '');
    }

    /**
     * Garbage collection
     *
     * In the TIP system, for logged users, the data is retained forever.
     *
     * @param  int  $maxlifetime Not used
     * @return bool              true on success, false on errors
     */
    public function gc($maxlifetime)
    {
        return true;
    }
}
?>
