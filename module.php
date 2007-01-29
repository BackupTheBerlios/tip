<?php

/**
 * Module definition file
 * @package tip
 **/

/**
 * The list of available module fields
 *
 * Every module can have a bounch of specific fields. The tipModule::FIELDS
 * property is the array containining these specific key => value pairs.
 * These fields are used by the tipModule::FindField() method while looking
 * for a field value.
 *
 * Also, remember a module inherits the field from its parents, in a hierarchy
 * order.
 *
 * @package tip
 * @subpackage ModuleFields
 **/

/**
 * The list of available actions
 * @package tip
 * @subpackage Actions
 **/

/**
 * Interaction between tipSource and tipData
 *
 * A module can be thought as a black box that parses a generic source file,
 * identified by a SOURCE_PATH, using the engine SOURCE_ENGINE. The data
 * requested by the source file is get from DATA_PATH using the
 * DATA_ENGINE interface object.
 *
 * Also, this black box must react to some external requests (usually because
 * of a user click): this is done using the CallAction() method.
 *
 * The data get by a module is managed by views: a module can have more views
 * on the same data source. See the tipView class to get an idea on how the
 * view works.
 *
 * The views can also be thought as different queries applied on DATA_PATH.
 * A view can be started by calling StartView() or StartSpecialView() and must
 * be closed by a EndView() call. Also, the views are internally stacked, so
 * ending a view reactivates the previous one.
 *
 * The result of a view can be browsed using ResetRow(), EndRow(), UnsetRow(),
 * PrevRow() and NextRow().
 *
 * A module provides more data the the informations found in DATA_PATH. The
 * FindField() method is the method used to retrieve field contents: see the
 * documentation to know how this method works.
 *
 * @abstract
 **/
class tipModule extends tipType
{
  /**#@+
   * @access private
   **/

  var $PRIVILEGE;
  var $LOCALES;
  var $VIEW_CACHE;
  var $VIEW_STACK;
  var $VIEW;


  function GetLocale ($Id)
  {
    if (! $this->LOCALES)
      {
	$Locale = tip::GetOption ('application', 'locale');
	$LocaleRoot = tip::GetOption ('application', 'locale_root');
	$ModuleName = $this->GetName ();
	include_once "$LocaleRoot/$Locale/$ModuleName.php";
	$this->LOCALES = $Messages;
      }

    if (! array_key_exists ($Id, $this->LOCALES))
      return NULL;

    return $this->LOCALES[$Id];
  }

  function GetItem ($Item)
  {
    $OpenBrace = strpos ($Item, '[');
    if ($OpenBrace === FALSE)
      {
	$Type = 'field';
	$Id = $Item;
      }
    else
      {
	$CloseBrace = strrpos ($Item, ']');
	if ($CloseBrace === FALSE || $CloseBrace < $OpenBrace)
	  {
	    $this->SetError ("unclosed item id ($Item)");
	    return FALSE;
	  }
	$Type = strtolower (trim (substr ($Item, 0, $OpenBrace)));
	$Id = substr ($Item, $OpenBrace+1, $CloseBrace-$OpenBrace-1);
      }

    switch ($Type)
      {
      case 'field':
	$Value = $this->FindField ($Id);
	if (is_null ($Value) && is_subclass_of ($this->VIEW, 'tipView'))
	  {
	    $Cnt = count ($this->VIEW_STACK)-1;
	    do
	      {
		if ($Cnt <= 0)
		  return NULL;
		$View =& $this->VIEW_STACK[-- $Cnt];
	      }
	    while (is_subclass_of ($View, 'tipView'));

	    $OldView =& $this->VIEW;
	    $this->VIEW =& $View;
	    $Value = $this->GetField ($Id);
	    $this->VIEW =& $OldView;
	  }
	return $Value;
      case 'get':
	return tip::GetGet ($Id, 'string');
      case 'post':
	return tip::GetPost ($Id, 'string');
      case 'locale':
	return $this->GetLocale ($Id);
      }

    $this->SetError ("undefined field type ($Type)");
    return FALSE;
  }

  function& GetFirstItem ($Items)
  {
    $this->ResetError ();
    $Item = strtok ($Items, ', ');
    $Value = NULL;
    while (is_null ($Value) && $Item !== FALSE)
      {
	$Value = $this->GetItem ($Item);
	$Item = strtok (', ');
      }
    return $Value;
  }

  /**#@-*/


  /**#@+
   * @access protected
   **/

  /**
   * Constructor
   *
   * Initializes a tipModule instance.
   *
   * @access protected
   **/
  function tipModule ()
  {
    $this->tipType ();

    $this->PRIVILEGE = NULL;
    $this->LOCALES = FALSE;
    $this->VIEW_CACHE = array ();
    $this->VIEW_STACK = array ();
    $this->VIEW = NULL;

    $this->SOURCE_ENGINE =& tipType::GetInstance ($this->GetOption ('source_engine'));
    $this->DATA_ENGINE =& tipType::GetInstance ($this->GetOption ('data_engine'));

    /**
     * SOURCE_PATH
     *
     * Expands to the source path of this module as specified in the
     * configuration file (logic/config.php).
     *
     * @package tip
     * @subpackage ModuleFields
     **/
    $this->FIELDS['SOURCE_PATH'] = $this->GetOption ('source_path');
    /**
     * DATA_PATH
     *
     * Expands to the data path of this module as specified in the configuration
     * file (logic/config.php).
     *
     * @package tip
     * @subpackage ModuleFields
     **/
    $this->FIELDS['DATA_PATH'] = $this->GetOption ('data_path');
  }

  function PostConstructor ()
  {
    if (is_null ($this->PRIVILEGE))
      $this->PRIVILEGE = tipApplication::GetPrivilege ($this);

    $Privilege =& $this->PRIVILEGE;

    /**
     * IS_MANAGER
     * Expands to TRUE if the current user has the 'manager' privilege
     * on this module, FALSE otherwise.
     **/
    $this->FIELDS['IS_MANAGER'] = strcmp ($Privilege, 'manager') == 0;
    /**
     * IS_ADMIN
     * Expands to TRUE if the current user has the 'manager' or
     * 'admin' privilege on this module, FALSE otherwise.
     **/
    $this->FIELDS['IS_ADMIN'] = strcmp ($Privilege, 'admin') == 0 || $this->FIELDS['IS_MANAGER'];
    /**
     * IS_TRUSTED
     * Expands to TRUE if the current user has the 'manager', 'admin' or
     * 'trusted' privilege on this module, FALSE otherwise.
     **/
    $this->FIELDS['IS_TRUSTED'] = strcmp ($Privilege, 'trusted') == 0 || $this->FIELDS['IS_ADMIN'];
    /**
     * IS_UNTRUSTED
     * Expands to TRUE if the current user has the 'manager', 'admin',
     * 'trusted' or 'untrusted' privilege on this module, FALSE otherwise.
     **/
    $this->FIELDS['IS_UNTRUSTED'] = strcmp ($Privilege, 'untrusted') == 0 || $this->FIELDS['IS_TRUSTED'];
  }

  /**
   * Push a view
   *
   * Pushes a view object in the stack of this module. You can restore the
   * previous view calling Pop().
   *
   * @param tipView &$View The view to push
   * @return bool TRUE on success of FALSE on errors
   **/
  function Push (&$View)
  {
    if (array_key_exists ($View->QUERY, $this->VIEW_CACHE))
      {
	$View =& $this->VIEW_CACHE[$View->QUERY];
      }
    else
      {
	if (! $View->Populate ())
	  return FALSE;
	$this->VIEW_CACHE[$View->QUERY] =& $View;
      }

    $this->VIEW_STACK[count ($this->VIEW_STACK)] =& $View;
    $this->VIEW =& $View;
    $this->UnsetRow ();
    return TRUE;
  }

  /**
   * Pop a view
   *
   * Pops a view object from the stack of this module. This operation restores
   * the previously active view.
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function Pop ()
  {
    unset ($this->VIEW);
    $this->VIEW = NULL;

    $Last = count ($this->VIEW_STACK);
    if ($Last < 1)
      return FALSE;

    unset ($this->VIEW_STACK[$Last - 1]);
    if ($Last > 1)
      $this->VIEW =& $this->VIEW_STACK[$Last-2];

    return TRUE;
  }

  /**
   * Gets the current rows
   *
   * Gets a reference to the rows of the current view.
   *
   * @return array|NULL The array of rows or NULL on errors
   **/
  function& GetCurrentRows ()
  {
    $Rows = NULL;
    if (is_null ($this->VIEW))
      return $Rows;

    return $this->VIEW->ROWS;
  }

  /**
   * Gets the current row
   *
   * Gets a reference to the row pointed by the internal cursor.
   *
   * @return array|NULL The current row or NULL on errors
   **/
  function& GetCurrentRow ()
  {
    $Row = NULL;
    if (@current ($this->VIEW->ROWS) !== FALSE)
      {
	$Key = key ($this->VIEW->ROWS);
	$Row =& $this->VIEW->ROWS[$Key];
      }
    return $Row;
  }

  /**
   * Gets a specified row
   *
   * Gets a reference to a specific row. This function does not move the
   * internal cursor.
   *
   * @param mixed $Id The row id
   * @return array|NULL The current row or containing NULL on errors
   **/
  function& GetRow ($Id)
  {
    $Row = NULL;
    if (@array_key_exists ($Id, $this->VIEW->ROWS))
      $Row =& $this->VIEW->ROWS[$Id];
    return $Row;
  }

  /**
   * Gets a field content from the current row
   *
   * Gets a field content from the current row. If the field exists but its
   * content is NULL, the value is converted in an empty string to avoid
   * confusion between error and NULL value.
   *
   * @param string $Field The field id
   * @return array|NULL The requested field content or NULL on errors
   **/
  function GetField ($Field)
  {
    $Row =& $this->GetCurrentRow ();
    if (! @array_key_exists ($Field, $Row))
      return NULL;

    if (is_null ($Row[$Field]))
      return '';

    return $Row[$Field];
  }

  /**
   * Gets a summary field
   *
   * Gets the content of a summary field from the current view.
   *
   * @param string $Field The field id
   * @return mixed|NULL The requested field content or NULL on errors
   **/
  function GetSummaryField ($Field)
  {
    if (! @array_key_exists ($Field, $this->VIEW->SUMMARY_FIELDS))
      return NULL;

    return $this->VIEW->SUMMARY_FIELDS[$Field];
  }

  /**
   * Executes a command
   *
   * A command is a request from the source engine to echoes something. It can
   * be tought as the dinamic primitive of the TIP preprocessor: every dinamic
   * tag parsed by the source engine runs a command.
   *
   * The commands - as everything else - are inherited from the module parents,
   * so every tipModule commands are available to the tipModule children.
   *
   * @param string $Command The command name
   * @param string &$Params Parameters to pass to the command
   * @return bool|NULL TRUE on command executed, FALSE on command error or
   *                   NULL on command not found
   * @tutorial tip.pkg#Commands
   **/
  function RunCommand ($Command, &$Params)
  {
    global $APPLICATION;

    switch ($Command)
      {
      /**
       * Html(itemid, itemid, ...)
       * Outputs the content of the first defined item, escaping the value for
       * html view throught htmlentities().
       * An item can be a field, a get, a post or a localized text: the
       * type of the item is obtained parsing the itemid tokens.
       * Specify <tt>field[...]</tt> for fields, <tt>get[...]</tt> for
       * gets, <tt>post[...]</tt> for posts and <tt>locale[...]</tt> for
       * localized text. If no type is specified (that is, itemid is
       * directly an identifier), the system will expand id in
       * <tt>field[...]</tt>. This means <tt>Value(name)</tt> is equal to
       * <tt>Value(field[name])</tt>.
       **/
      case 'html':
	$Value = $this->GetFirstItem ($Params);
	if ($this->ERROR !== FALSE)
	  return FALSE;
	if (is_null ($Value))
	  {
	    $this->SetError ("no item found ($Params)");
	    return FALSE;
	  }

	if (is_bool ($Value))
	  $Value = $Value ? 'TRUE' : 'FALSE';
	echo htmlentities ($Value, ENT_QUOTES, 'UTF-8');
	return TRUE;

      /**
       * TryHtml(itemid, itemid, ...)
       * Equal to Html, but do not log any message if the item is not found.
       **/
      case 'tryhtml':
	$Value = $this->GetFirstItem ($Params);
	if ($this->ERROR !== FALSE)
	  return FALSE;
	if (is_null ($Value))
	  return TRUE;

	if (is_bool ($Value))
	  $Value = $Value ? 'TRUE' : 'FALSE';
	echo htmlentities ($Value, ENT_QUOTES, 'UTF-8');
	return TRUE;

      /**
       * Is(userid)
       * Expands to TRUE if the current logged-in user equals to
       * userid or FALSE otherwise.
       **/
      case 'is':
	$UID = (int) $Params;
	echo $UID === $APPLICATION->GetUserId () ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * Url(file)
       * Prepends the source path of the current module to file and
       * outputs the result. This command (or any of its variants) MUST be
       * used for every file reference if you want a theme-aware site,
       * because enabling themes will make the prepending path a dynamic
       * variable.
       **/
      case 'url':
	echo $this->FIELDS['SOURCE_PATH'] . DIRECTORY_SEPARATOR . $Params;
	return TRUE;

      /**
       * SourceUrl(file)
       * Variants of the <b>Url</b> command. Prepends to file the root
       * source path.
       **/
      case 'sourceurl':
	echo $this->sourceURL ($Params);
	return TRUE;

      /**
       * IconUrl(file)
       * Variants of the <b>Url</b> command. Prepends to file the root
       * source path and '/shared/icons'.
       **/
      case 'iconurl':
	echo $this->sourceURL ('shared', 'icons', $Params);
	return TRUE;

      /**
       * Run(file)
       * Runs the file source found in the module directory using the
       * current source engine.
       **/
      case 'run':
	return $this->Run ($Params);

      /**
       * RunShared(file)
       * Runs the file source found in the root data directory using the
       * current source engine.
       **/
      case 'runshared':
	return $this->RunShared ($Params);

      /**
       * ModuleExists(module)
       * Outputs TRUE if the module module exists, FALSE
       * otherwise. This command only checks if the module is configured,
       * does not load the module itsself. Useful to provide conditional
       * links in some module manager (such as the tipUser type).
       **/
      case 'moduleexists':
	global $CFG;
	echo array_key_exists ($Params, $CFG) ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * InList(item, list)
       * Outputs TRUE if the item item is present in the comma
       * separated list list. Useful to check if a value is contained
       * (that is, if it is on) in a "set" field.
       **/
      case 'inlist':
	$Pos = strpos ($Params, ',');
	if ($Pos === FALSE)
	  return FALSE;

	$Item = substr ($Params, 0, $Pos);
	$Set  = substr ($Params, $Pos+1);
	echo tip::InList ($Item, $Set) ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * Date(date)
       * Formats the date date (specified in iso8601) in the format
       * <tt>"date_" . $CFG['application']['locale']</tt>. For instance, if you
       * set 'it' in <tt>$CFG['application']['locale']</tt>, the format used
       * will be "date_it". Check the tip::FormatDate() function for details.
       **/
      case 'date':
	echo tip::FormatDate ($Params, 'iso8601', 'date_' . $APPLICATION->FIELDS['LOCALE']);
	return TRUE;

      /**
       * DateTime(datetime)
       * Formats the datetime date (specified in iso8601) in the format
       * <tt>"datetime_" . $CFG['application']['locale']</tt>.
       **/
      case 'datetime':
	echo tip::FormatDate ($Params, 'iso8601', 'datetime_' . $APPLICATION->FIELDS['LOCALE']);
	return TRUE;

      /**
       * NlReplace(replacer, text)
       * Replaces all the occurrences of a newline in text with the
       * replacer string.
       **/
      case 'nlreplace':
	$Pos = strpos ($Params, ',');
	if ($Pos === FALSE)
	  {
	    $this->SetError ('no text to replace');
	    return FALSE;
	  }

	$From   = "\n";
	$To     = substr ($Params, 0, $Pos);
	$Buffer = str_replace ("\r", '', substr ($Params, $Pos+1));

	echo str_replace ($From, $To, $Buffer);
	return TRUE;
      }

    $this->SetError ("command not found ($Command)");
    return NULL;
  }


  /**#@+
   * @param string $Action The action name
   * @return bool|NULL TRUE on command executed, FALSE on command error or
   *                   NULL on command not found
   **/

  /**
   * Executes a management action
   *
   * Executes an action that requires the 'manager' privilege.
   **/
  function RunManagerAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes an administrator action
   *
   * Executes an action that requires at least the 'admin' privilege.
   **/
  function RunAdminAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes a trusted action
   *
   * Executes an action that requires at least the 'trusted' privilege.
   **/
  function RunTrustedAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes an untrusted action
   *
   * Executes an action that requires at least the 'untrusted' privilege.
   **/
  function RunUntrustedAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes an unprivileged action
   *
   * Executes an action that does not require any privileges.
   **/
  function RunAction ($Action)
  {
    return NULL;
  }

  /**#@-*/


  /**#@+
   * @param string $File The source file
   * @return bool TRUE on success or FALSE on errors
   **/

  /**
   * Executes a source
   *
   * Executes a source file found in the source path, using the current
   * engine.
   **/
  function Run ($File)
  {
    return $this->SOURCE_ENGINE->Run ($this->FIELDS['SOURCE_PATH'] . '/' . $File, $this);
  }

  function sourceURL ()
  {
    $list = func_get_args ();
    global $APPLICATION;
    return $APPLICATION->source_root . '/' . implode ('/', $list);
  }

  /**
   * Executes a shared source
   *
   * Executes the File program found in shared source path using the current
   * source engine.
   **/
  function RunShared ($File)
  {
    return $this->SOURCE_ENGINE->Run ($this->sourceURL ('shared', $File), $this);
  }

  /**
   * Executes a file, appending the result to content
   *
   * Executes the File source found in the module path using the current
   * source engine and appends the result to the end of the application
   * content.
   **/
  function AppendToContent ($File)
  {
    global $APPLICATION;

    $Buffer = FALSE;
    $Path = $this->FIELDS['SOURCE_PATH'] . "/$File";
    $Result = $this->SOURCE_ENGINE->RunTo ($Path, $this, $Buffer);
    if ($Result && ! empty ($Buffer))
      $APPLICATION->CONTENT .= $Buffer;

    return $Result;
  }

  /**
   * Executes a file, inserting the result in content
   *
   * Executes the File source found in the module path using the current
   * source engine and inserts the result at the beginning of the application
   * content.
   **/
  function InsertInContent ($File)
  {
    global $APPLICATION;

    $Buffer = FALSE;
    $Path = $this->FIELDS['SOURCE_PATH'] . "/$File";
    $Result = $this->SOURCE_ENGINE->RunTo ($Path, $this, $Buffer);
    if ($Result && ! empty ($Buffer))
      $APPLICATION->CONTENT = $Buffer . $APPLICATION->CONTENT;

    return $Result;
  }

  /**#@-*/


  /**
   * The source program engine
   *
   * Contains a reference to the source engine to use when parsing a file.
   * See the tipSource class for details on what is a source engine.
   *
   * @var string
   **/
  var $SOURCE_ENGINE;

  /**
   * The data engine
   *
   * Contains a reference to the data engine to use to read and write rows.
   * See the tipData class for details on what is a data engine.
   *
   * @var string
   **/
  var $DATA_ENGINE;

  /**
   * Returns the content of a field
   *
   * Gets the content of the Field field. A field is the unit of information
   * used by the TIP system. Searching the content of a field performs a search
   * operation which follows these steps in the following order:
   *
   * - <b>Current row fields</b>
   *
   *   Checks if the there is a current row and if this row has a field named
   *   Field. If yes, returns the field content.
   *
   * - <b>Summary fields of the current view</b>
   *
   *   Checks if the there is a current view and if it has a summary field
   *   matching the requested one. If yes, returns the field content.
   *
   * - <b>Module fields</b>
   *
   *   Every module has a public variable (FIELDS) that can be filled
   *   with arbitrary key => values pairs. If a Field key exists in
   *   FIELDS, its value it is returned.
   *
   * - <b>Global fields</b>
   *
   *   Checks if Field is the id of a global fields. Global fields are
   *   nothing more than module fields of the tipApplication instance. If
   *   found, the content of the global field is returned.
   *
   * @param string $Field The field id
   * @return mixed|NULL The content of the requested field or NULL if not found
   **/
  function FindField ($Field)
  {
    $Value = $this->GetField ($Field);
    if (! is_null ($Value))
      return $Value;

    $Value = $this->GetSummaryField ($Field);
    if (! is_null ($Value))
      return $Value;

    if (@array_key_exists ($Field, $this->FIELDS))
      return $this->FIELDS[$Field];

    global $APPLICATION;
    if ($this != $APPLICATION)
      {
	$Value = $APPLICATION->FindField ($Field);
	if (! is_null ($Value))
	  return $Value;
      }

    return NULL;
  }

  /**
   * Validates the posts
   *
   * Checks if the posts contain valid data, accordling to the data source of
   * the module.
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function ValidatePosts ()
  {
    if (! $this->StartSpecialView ('Fields'))
      {
	$this->LogWarning ('No data to validate');
	return TRUE;
      }

    global $APPLICATION;
    $Result = TRUE;
    while ($this->NextRow ())
      {
	$Row =& $this->GetCurrentRow ();
	if (! array_key_exists ('importance', $Row))
	  continue;

	$Id =& $Row['id'];
	$Label = $this->GetLocale ("{$Id}_label");
	$Value = tip::GetPost ($Id, $Row['type']);
	if ($Row['importance'] == 1 && empty ($Value))
	  {
	    $APPLICATION->Error ('E_VL_REQUIRED', " ($Label)");
	    $Result = FALSE;
	    break;
	  }
	if ($Row['mode'] == 'secret')
	  {
	    $ReLabel = $this->GetLocale ("re{$Id}_label");
	    $ReValue = tip::GetPost ("re$Id", $Row['type']);
	    if ($Row['importance'] == 1 && empty ($ReValue))
	      {
		$APPLICATION->Error ('E_VL_REQUIRED', " ($ReLabel)");
		$Result = FALSE;
		break;
	      }
	    elseif ($Value != $ReValue)
	      {
		$APPLICATION->Error ('E_VL_DIFFER', " ($ReLabel)");
		$Result = FALSE;
		break;
	      }
	  }
	$Length = @$Row['length'];
	if ($Length > 0 && @strlen ($Value) > $Length)
	  {
	    $APPLICATION->Error ('E_VL_LENGTH', " ($Label)");
	    $Result = FALSE;
	    break;
	  }
	$Validator = @$Row['validator'];
	if (is_object ($Validator) && ! $Validator->Go (array (&$Row, &$Value)))
	  {
	    $Result = FALSE;
	    break;
	  }
      }

    $this->EndView ();
    return $Result;
  }

  /**
   * Stores the posts
   *
   * Stores the posts content in the specified row, accordling to the data
   * source of the module. This method complements ValidatePosts() to manage
   * the user modules: usually you must validate the posts and after store
   * them in some place for further data operations (update or insert).
   *
   * @param array &$Destination Where to store the posts
   * @return bool TRUE on success or FALSE on errors
   **/
  function StorePosts (&$Destination)
  {
    if (! is_array ($Destination))
      {
	$this->LogWarning ('Invalid destination to store data');
	return TRUE;
      }
    if (! $this->StartSpecialView ('Fields'))
      {
	$this->LogWarning ('No data to store');
	return TRUE;
      }

    global $APPLICATION;
    $Result = TRUE;
    while ($this->NextRow ())
      {
	$Row =& $this->GetCurrentRow ();
	if (array_key_exists ('importance', $Row))
	  {
	    $Id =& $Row['id'];
	    $Value = tip::GetPost ($Id, 'string');
	    if (strlen ($Value) == 0 && $Row['can_be_null'])
	      $Destination[$Id] = NULL;
	    elseif (settype ($Value, $Row['type']))
	      $Destination[$Id] = $Value;
	    else
	      $this->LogWarning ("Unable to cast '$Value' to '$Row[type]'");
	  }
      }

    $this->EndView ();
    return $Result;
  }

  /**#@-*/


  /**#@+
   * @access public
   **/

  /**
   * Module fields
   *
   * Every module can have a bounch of specific fields. This property is the
   * array containining these specific key => value pairs. These fields are
   * used by FindField() method while looking for a field value.
   *
   * Also, remember a module inherits the field from its parents, in a hierarchy
   * order.
   *
   * @var array
   **/
  var $FIELDS;


  /**
   * Starts a view
   *
   * Starts a view using the Query query. Starting a view means you can
   * traverse the results of the query using the ResetRow() and NextRow()
   * commands. Also, you can get the number of rows throught RowsCount().
   *
   * Notice after starting a view, there is no current row, so trying to
   * retrieve some data will fail. You must use ResetRow() or NextRow() to set
   * the cursor position.
   *
   * When the view is succesful started, this function returns TRUE.
   * When finished to use the results, close the view with EndView().
   * Remember to close the view only if StartView() is succesful.
   *
   * @param string $Query The query to execute
   * @return bool TRUE on success or FALSE on errors
   **/
  function StartView ($Query)
  {
    return $this->Push (new tipView ($this, $Query));
  }

  /**
   * Starts a special view
   *
   * Starts a view trying to instantiate the class named tip{Name}View.
   * All the StartView() advices also applies to StartSpecialView().
   *
   * @param string $Name The name of the special view
   * @return bool TRUE on success or FALSE on errors
   **/
  function StartSpecialView ($Name)
  {
    $ClassName = "tip{$Name}View";
    if (! class_exists ($ClassName))
      {
	$this->SetError ("Class does not exist ($ClassName)");
	return FALSE;
      }
    return $this->Push (new $ClassName ($this));
  }

  /**
   * Counts the rows of the current view
   *
   * Returns the rows count of the result of the current view.
   *
   * @return int|NULL The number of rows or NULL on errors
   **/
  function RowsCount ()
  {
    $Result = $this->GetSummaryField ('COUNT');
    if (is_null ($Result))
      return FALSE;
    return $this->GetSummaryField ('COUNT');
  }

  /**
   * Resets the cursor
   *
   * Resets (set to the first row) the internal cursor. This function hangs if
   * there is no active view, but also if the results does not have any row.
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function ResetRow ()
  {
    if (is_null ($this->VIEW) || ! is_array ($this->VIEW->ROWS))
      return FALSE;

    return reset ($this->VIEW->ROWS) !== FALSE;
  }

  /**
   * Moves the cursor to the end
   *
   * Moves the internal cursor to the last row. This function hangs if there is
   * no active view, but also if the results does not have any row.
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function EndRow ()
  {
    if (is_null ($this->VIEW))
      return FALSE;

    return end ($this->VIEW->ROWS) !== FALSE;
  }

  /**
   * Unsets the cursor
   *
   * Sets the internal cursor to an unidentified row.
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function UnsetRow ()
  {
    if (is_null ($this->VIEW))
      return FALSE;

    if (! is_array ($this->VIEW->ROWS))
      return TRUE;

    end ($this->VIEW->ROWS);
    return next ($this->VIEW->ROWS) === FALSE;
  }

  /**
   * Sets the cursor to the previous row
   *
   * Decrements the cursor so it referes to the previous row. If the cursor is
   * not set, this function moves it to the last row (same as EndRow()).
   * If the cursor is on the first row, returns FALSE.
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function PrevRow ()
  {
    if (is_null ($this->VIEW))
      return FALSE;

    if (current ($this->VIEW->ROWS) === FALSE)
      return end ($this->VIEW->ROWS) !== FALSE;

    return prev ($this->VIEW->ROWS) !== FALSE;
  }

  /**
   * Sets the cursor to the next row
   *
   * Increments the cursor so it referes to the next row. If the cursor was
   * never set and rewind is TRUE, this function moves it to the first
   * row (same as ResetRow()). If there are no more rows, returns FALSE.
   *
   * @param bool $Rewind If reset the cursor when no more rows
   * @return bool TRUE on success or FALSE on errors
   **/
  function NextRow ($Rewind = TRUE)
  {
    if (is_null ($this->VIEW))
      return FALSE;

    if (current ($this->VIEW->ROWS) === FALSE)
      return $Rewind ? reset ($this->VIEW->ROWS) !== FALSE : FALSE;

    return next ($this->VIEW->ROWS) !== FALSE;
  }

  /**
   * Ends a view
   *
   * Ends the current view. Ending a view means the previously active view is
   * made current.
   *
   * Usually, you always have to close all views. Anyway, in some situations,
   * is useful to have the base view ever active (so called default view) where
   * all commands of a module refers if no views were started. In any case, you
   * can't have more EndView() than StartView().
   *
   * @return bool TRUE on success or FALSE on errors
   **/
  function EndView ()
  {
    if (! $this->Pop ())
      {
	$this->LogWarning ('\'EndView()\' requested without a previous \'StartView\' or \'StartSpecialView\' call');
	return FALSE;
      }

    return TRUE;
  }

  /**
   * Executes a command
   *
   * Executes the Command command, using params as arguments. This
   * function is merely a wrapper to RunCommand(). Anyway, Command is
   * converted to lowercase, so you can specify it in the way you prefer.
   *
   * @param string $Command The command name
   * @param string &$Params Parameters to pass to the command
   * @return bool TRUE on success or FALSE on errors
   **/
  function CallCommand ($Command, &$Params)
  {
    return $this->RunCommand ($Command, $Params);
  }

  /**
   * Executes an action
   *
   * Executes the Action action. This function tries to run Action
   * by calling the following protected methods in this order:
   *
   * - RunManagerAction()
   * - RunAdminAction()
   * - RunTrustedAction()
   * - RunUntrustedAction()
   * - RunAction()
   *
   * The first method called depends on the current privilege, get throught a
   * tipApplication::GetPrivilege() call. The first method that returns TRUE
   * (meaning the requested action is executed) stops the chain.
   *
   * Usually the actions are called adding variables to the URL. An example of
   * an action call is the following URL:
   * <samp>http://www.example.org/?module=news&action=view&id=23</samp>
   *
   * This URL will call the "view" action on the "news" module, setting "id" to
   * 23 (it is request to view a news and its comments). You must check the
   * documentation of every module to see which actions are available and what
   * variables they require.
   *
   * @param string Action The action name
   * @return bool TRUE on success or FALSE on errors
   **/
  function CallAction ($Action = NULL)
  {
    $Action = strtolower ($Action);

    switch ($this->PRIVILEGE)
      {
      case 'manager':
	$Result = $this->RunManagerAction ($Action);
	if (! is_null ($Result))
	  break;
      case 'admin':
	$Result = $this->RunAdminAction ($Action);
	if (! is_null ($Result))
	  break;
      case 'trusted':
	$Result = $this->RunTrustedAction ($Action);
	if (! is_null ($Result))
	  break;
      case 'untrusted':
	$Result = $this->RunUntrustedAction ($Action);
	if (! is_null ($Result))
	  break;
      case 'none':
	$Result = $this->RunAction ($Action);
      }

    return $Result;
  }

  /**#@-*/
}

/**
 * Application entry point
 *
 * The application is the "main" module and MUST be present.
 *
 * @global tipModule $APPLICATION
 **/
$APPLICATION =& tipType::GetInstance ('application');

?>
