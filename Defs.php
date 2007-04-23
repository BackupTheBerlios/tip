<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP constants
 *
 * This file must be included before the config file to define constants
 * that can be used in the config file itsself.
 *
 * @package TIP
 */

/**
 * The TIP prefix 
 *
 * This is the prefix used by the TIP objects. It is used in various place,
 * such as to get the type name from the class by stripping this prefix
 * (avoiding tedious prefix repetitions) and to manage the view names.
 */
define('TIP_PREFIX', 'TIP_');

/**
 * The name of the main module
 *
 * The name of the global variable holding the reference to the main module.
 * It defaults to '_tip_application' and can be accessed throught
 * <code>$GLOBALS[TIP_MAIN_MODULE]</code>.
 */
define('TIP_MAIN_MODULE', '_tip_application');

/**#@+ Privileges */
define('TIP_PRIVILEGE_INVALID',   0);
define('TIP_PRIVILEGE_NONE',      1);
define('TIP_PRIVILEGE_UNTRUSTED', 2);
define('TIP_PRIVILEGE_TRUSTED',   3);
define('TIP_PRIVILEGE_ADMIN',     4);
define('TIP_PRIVILEGE_MANAGER',   5);
/**#@-*/


/**#@+ Form related constants */

/**#@+ Form action */
define('TIP_FORM_ACTION_ADD',    'add');
define('TIP_FORM_ACTION_EDIT',   'edit');
define('TIP_FORM_ACTION_VIEW',   'view');
define('TIP_FORM_ACTION_DELETE', 'delete');
/**#@-*/

/**#@+ Form button */
define('TIP_FORM_BUTTON_SUBMIT', 1);
define('TIP_FORM_BUTTON_RESET',  2);
define('TIP_FORM_BUTTON_DELETE', 4);
define('TIP_FORM_BUTTON_CANCEL', 8);
define('TIP_FORM_BUTTON_CLOSE',  16);
/**#@-*/

/**#@+ Form rendering */
define('TIP_FORM_RENDER_HERE',        1);
define('TIP_FORM_RENDER_IN_CONTENT',  2);
define('TIP_FORM_RENDER_NOTHING',     3);
/**#@-*/

/**#@-*/

?>
