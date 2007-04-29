<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 * @subpackage Module
 */

/**
 * The advertisement block
 *
 * Enables advertisement management.
 *
 * @package TIP
 * @subpackage Module
 */
class TIP_Advertisement extends TIP_Block
{
    /**#@+ @access protected */

    function runAdminAction($action)
    {
        switch ($action) {

        case 'browse':
            // TODO
            return false;

        case 'delete':
            // TODO
            return false;

        case 'legalize':
            // TODO
            return false;

        case 'illegalize':
            // TODO
            return false;
        }

        return parent::runAdminAction($action);
    }

    function runTrustedAction($action)
    {
        switch ($action) {

        case 'browse':
            $user_id = TIP::getUserId();
            if (is_null ($user_id) || $user_id === false) {
                TIP::notifyError ('reserved');
                return false;
            }

            $this->appendToContent('browse-user.src');
            return true;

        case 'add':
            return !is_null($this->form(TIP_FORM_ACTION_ADD));

        case 'edit':
            // TODO
            return false;

        case 'update':
            // TODO
            return false;

        case 'delete':
            // TODO
            return false;

        case 'check':
            // TODO
            return false;
        }

        return parent::runTrustedAction($action);
    }

    function runUntrustedAction($action)
    {
        switch ($action) {

        case 'browse':
            if (is_null($id = TIP::getGet('group', 'integer'))) {
                TIP::notifyError('noparams');
                return false;
            }

            $this->appendToContent('browse-group.src');
            return true;
        }

        return parent::runAction ($action);
    }

    /**#@-*/
}

return 'TIP_Advertisement';

?>
