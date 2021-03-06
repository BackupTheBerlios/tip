<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TiP constants
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
 * @since     0.0.1
 */

/**
 * The TIP prefix 
 *
 * This is the prefix used by the TIP objects. It is used in various place,
 * such as to get the type name from the class by stripping this prefix
 * (avoiding tedious prefix repetitions).
 */
define('TIP_PREFIX',              'TIP_');

/**
 * The TiP branch version, to be used to roughly identify the TiP used
 */
define('TIP_VERSION_BRANCH',      '0.2');

/**
 * The name of the main module
 *
 * The name of the global variable holding the reference to the main module.
 * It be accessed throught <code>$GLOBALS[TIP_MAIN]</code>.
 */
define('TIP_MAIN',                '_tip_main_');

/**#@+ Privileges */
define('TIP_PRIVILEGE_INVALID',   0);
define('TIP_PRIVILEGE_NONE',      1);
define('TIP_PRIVILEGE_UNTRUSTED', 2);
define('TIP_PRIVILEGE_TRUSTED',   3);
define('TIP_PRIVILEGE_ADMIN',     4);
define('TIP_PRIVILEGE_MANAGER',   5);
/**#@-*/

/**#@+ Severity levels */
define('TIP_SEVERITY_INFO',       'info');
define('TIP_SEVERITY_WARNING',    'warning');
define('TIP_SEVERITY_ERROR',      'error');
/**#@-*/

/**#@+ Sorting orders, used by TIP_Data::order() */
define('TIP_ORDER_ASCENDING',     false);
define('TIP_ORDER_DESCENDING',    true);
/**#@-*/

/**#@+ Transaction actions, used by TIP_Data_Engine */
define('TIP_TRANSACTION_START',   1);
define('TIP_TRANSACTION_COMMIT',  2);
define('TIP_TRANSACTION_ROLLBACK',3);
/**#@-*/


/**#@+ Form related constants */

/**#@+ Form method */
define('TIP_FORM_METHOD_GET',     'get');
define('TIP_FORM_METHOD_POST',    'post');
/**#@-*/

/**#@+ Form action */
define('TIP_FORM_ACTION_ADD',     'add');
define('TIP_FORM_ACTION_EDIT',    'edit');
define('TIP_FORM_ACTION_VIEW',    'view');
define('TIP_FORM_ACTION_DELETE',  'delete');
define('TIP_FORM_ACTION_CUSTOM',  'custom');
/**#@-*/

/**#@+ Form button */
define('TIP_FORM_BUTTON_SUBMIT',  1);
define('TIP_FORM_BUTTON_RESET',   2);
define('TIP_FORM_BUTTON_DELETE',  4);
define('TIP_FORM_BUTTON_CANCEL',  8);
define('TIP_FORM_BUTTON_CLOSE',   16);
define('TIP_FORM_BUTTON_OK',      32);
/**#@-*/

/**#@+ Form rendering */
define('TIP_FORM_RENDER_HERE',    1);
define('TIP_FORM_RENDER_IN_PAGE', 2);
define('TIP_FORM_RENDER_NOTHING', 3);
define('TIP_FORM_RENDER_REDIRECT',4);
/**#@-*/

/**#@-*/

/**
 * If set to true, this flag will avoid postConstructor calls
 *
 * I think the following approach is apache specific.
 */

define('TIP_AHAH', strcasecmp(@$_SERVER["HTTP_X_REQUESTED_WITH"], 'XMLHttpRequest') == 0);

?>
