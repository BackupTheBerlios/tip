<?php

/**
 * \page ModuleFields Module specific fields
 *
 * Here is the list of fields specific to the TIP modules.
 *
 * Every module can have a bounch of specific fields. The tipModule::FIELDS
 * property is the array containining these specific key => value pairs.
 * These fields are used by the tipModule::FindField() method while looking
 * for a field value.
 *
 * Also, remember a module inherits the field from its parents, in a hierarchy
 * order.
 **/

/**
 * \page UnprivilegedActions Unprivileged actions
 *
 * Here is the list of unprivileged actions.
 **/

/**
 * Interaction between tipSource and tipData.
 *
 * A module can be thought as a black box that parses a generic source file,
 * identified by a \c SOURCE_PATH, using the engine \c SOURCE_ENGINE. The data
 * requested by the source file is get from \c DATA_PATH using the
 * \c DATA_ENGINE interface object.
 *
 * Also, this black box must react to some external requests (usually because
 * of a user click): this is done using the CallAction() method.
 *
 * The data get by a module is managed by views: a module can have more views
 * on the same data source. See the tipView class to get an idea on how the
 * view works.
 *
 * The views can also be thought as different queries applied on \c DATA_PATH.
 * A view can be started by calling StartView() or StartSpecialView() and must
 * be closed by a EndView() call. Also, the views are internally stacked, so
 * ending a view reactivates the previous one.
 *
 * The result of a view can be browsed using ResetRow(), EndRow(), UnsetRow(),
 * PrevRow() and NextRow().
 *
 * A module provides more data the the informations found in \c DATA_PATH. The
 * FindField() method is the method used to retrieve field contents: see the
 * documentation to know how this method works.
 **/
class tipModule extends tipType
{
  /// \publicsection

  /**
   * Module fields
   *
   * An array containing the module fields.
   * See \subpage ModuleFields for the list of these fields.
   **/
  var $FIELDS;


  /// @privatesection

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


  /// @protectedsection

  /**
   * Constructor
   *
   * Initializes a tipModule instance.
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
     * \modulefield <b>SOURCE_PATH</b>\n
     * Expands to the source path of this module as specified in the
     * configuration file (logic/config.php).
     **/
    $this->FIELDS['SOURCE_PATH'] = $this->GetOption ('source_path');
    /**
     * \modulefield <b>DATA_PATH</b>\n
     * Expands to the data path of this module as specified in the configuration
     * file (logic/config.php).
     **/
    $this->FIELDS['DATA_PATH'] = $this->GetOption ('data_path');
  }

  function PostConstructor ()
  {
    if (is_null ($this->PRIVILEGE))
      $this->PRIVILEGE = tipApplication::GetPrivilege ($this);

    $Privilege =& $this->PRIVILEGE;

    /**
     * \modulefield <b>IS_MANAGER</b>\n
     * Expands to \c TRUE if the current user has the 'manager' privilege
     * on this module, \c FALSE otherwise.
     **/
    $this->FIELDS['IS_MANAGER'] = strcmp ($Privilege, 'manager') == 0;
    /**
     * \modulefield <b>IS_ADMIN</b>\n
     * Expands to \c TRUE if the current user has the 'manager' or
     * 'admin' privilege on this module, \c FALSE otherwise.
     **/
    $this->FIELDS['IS_ADMIN'] = strcmp ($Privilege, 'admin') == 0 || $this->FIELDS['IS_MANAGER'];
    /**
     * \modulefield <b>IS_TRUSTED</b>\n
     * Expands to \c TRUE if the current user has the 'manager', 'admin' or
     * 'trusted' privilege on this module, \c FALSE otherwise.
     **/
    $this->FIELDS['IS_TRUSTED'] = strcmp ($Privilege, 'trusted') == 0 || $this->FIELDS['IS_ADMIN'];
    /**
     * \modulefield <b>IS_UNTRUSTED</b>\n
     * Expands to \c TRUE if the current user has the 'manager', 'admin',
     * 'trusted' or 'untrusted' privilege on this module, \c FALSE otherwise.
     **/
    $this->FIELDS['IS_UNTRUSTED'] = strcmp ($Privilege, 'untrusted') == 0 || $this->FIELDS['IS_TRUSTED'];
  }

  /**
   * Push a view
   * @param[in] View \c tipView  The view to push
   *
   * Pushes a view object in the stack of this module. You can restore the
   * previous view calling Pop().
   *
   * @return \c TRUE on success of \c FALSE on errors
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
   * @return \c TRUE on success of \c FALSE on errors
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
   * @return The reference to an array of rows, or a reference to a variable
   *         containing \c NULL on errors.
   **/
  function& GetCurrentRows ()
  {
    $Rows = NULL;
    if (is_null ($this->VIEW))
      return $Rows;

    return $this->VIEW->ROWS;
  }

  /**
   * Gets the current row.
   *
   * Gets a reference to the row pointed by the internal cursor.
   *
   * @return The reference to the current row, or a reference to a variable
   *         containing \c NULL on errors.
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
   * Gets a specified row.
   * @param[in] Id \c mixed The row id
   *
   * Gets a reference to a specific row. This function does not move the
   * internal cursor.
   *
   * @return The reference to the current row, or a reference to a variable
   *         containing \c NULL on errors.
   **/
  function& GetRow ($Id)
  {
    $Row = NULL;
    if (@array_key_exists ($Id, $this->VIEW->ROWS))
      $Row =& $this->VIEW->ROWS[$Id];
    return $Row;
  }

  /**
   * Gets a field content from the current row.
   * @param[in] Field \c string The field id
   *
   * Gets the \p Field field content from the current row. If the field exists
   * but its content is \c NULL, the value is converted in an empty string to
   * avoid confusion between error and NULL value.
   *
   * @return The requested field content, or \c NULL on errors.
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
   * Gets a field content from the summary fields.
   * @param[in] Field \c string The field id
   *
   * Gets the \p Field summary field from the current view.
   *
   * @return The requested field content, or \c NULL on errors.
   **/
  function GetSummaryField ($Field)
  {
    if (! @array_key_exists ($Field, $this->VIEW->SUMMARY_FIELDS))
      return NULL;

    return $this->VIEW->SUMMARY_FIELDS[$Field];
  }

  /**
   * Executes a command
   * \param[in] Command \c string The command name
   * \param[in] Params  \c string Parameters to pass to the command
   *
   * Executes the \p Command command, using \p Params as arguments. A command
   * is a request from the source engine to echoes something. It can be tought
   * as the dinamic primitive of the TIP preprocessor: every dinamic tag parsed
   * by the source engine runs a command.
   *
   * The commands - as everything else - are inherited from the module parents,
   * so every tipModule commands are available to the tipModule children.
   *
   * See \subpage Commands for a list of available commands.
   *
   * @return \c TRUE on success, \c FALSE on errors or \c NULL if \p Command
   *         is not found.
   **/
  function RunCommand ($Command, &$Params)
  {
    global $APPLICATION;

    switch ($Command)
      {
      /**
       * \command <b>Html(</b><i>itemid, itemid, ...</i><b>)</b>\n
       * Outputs the content of the first defined item, escaping the value for
       * html view throught htmlentities().
       * An item can be a field, a get, a post or a localized text: the
       * type of the item is obtained parsing the \p itemid tokens.
       * Specify <tt>field[...]</tt> for fields, <tt>get[...]</tt> for
       * gets, <tt>post[...]</tt> for posts and <tt>locale[...]</tt> for
       * localized text. If no type is specified (that is, \p itemid is
       * directly an identifier), the system will expand \p id in
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
       * \command <b>TryHtml(</b><i>itemid, itemid, ...</i><b>)</b>\n
       * Equal to \a Html, but do not log any message if the item is not found.
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
       * \command <b>Is(</b>\a userid<b>)</b>\n
       * Expands to \c TRUE if the current logged-in user equals to
       * \p userid or \c FALSE otherwise.
       **/
      case 'is':
	$UID = (int) $Params;
	echo $UID === $APPLICATION->GetUserId () ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * \command <b>Url(</b>\a file<b>)</b>\n
       * Prepends the source path of the current module to \p file and
       * outputs the result. This command (or any of its variants) MUST be
       * used for every file reference if you want a theme-aware site,
       * because enabling themes will make the prepending path a dynamic
       * variable.
       **/
      case 'url':
	echo $this->FIELDS['SOURCE_PATH'] . DIRECTORY_SEPARATOR . $Params;
	return TRUE;

      /**
       * \command <b>SourceUrl(</b>\a file<b>)</b>\n
       * Variants of the <b>Url</b> command. Prepends to \p file the root
       * source path.
       **/
      case 'sourceurl':
	echo $APPLICATION->FIELDS['SOURCE_ROOT'] . DIRECTORY_SEPARATOR . $Params;
	return TRUE;

      /**
       * \command <b>IconUrl(</b>\a file<b>)</b>\n
       * Variants of the <b>Url</b> command. Prepends to \p file the root
       * source path and '/icons'.
       **/
      case 'iconurl':
	echo $APPLICATION->FIELDS['SOURCE_ROOT'] . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . $Params;
	return TRUE;

      /**
       * \command <b>Run(</b>\a file<b>)</b>\n
       * Runs the \p file source found in the module directory using the
       * current source engine.
       **/
      case 'run':
	return $this->Run ($Params);

      /**
       * \command <b>RunShared(</b>\a file<b>)</b>\n
       * Runs the \p file source found in the root data directory using the
       * current source engine.
       **/
      case 'runshared':
	return $this->RunShared ($Params);

      /**
       * \command <b>ModuleExists(</b>\a module<b>)</b>\n
       * Outputs \c TRUE if the \p module module exists, \c FALSE
       * otherwise. This command only checks if the module is configured,
       * does not load the module itsself. Useful to provide conditional
       * links in some module manager (such as the tipUser type).
       **/
      case 'moduleexists':
	global $CFG;
	echo array_key_exists ($Params, $CFG) ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * \command <b>InList(</b>\a item, \a list<b>)</b>\n
       * Outputs \c TRUE if the \p item item is present in the comma
       * separated \p list list. Useful to check if a value is contained
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
       * \command <b>Date(</b>\a date<b>)</b>\n
       * Formats the \p date date (specified in iso8601) in the format
       * <tt>"date_" . $CFG['application']['locale']</tt>. For instance, if you
       * set 'it' in <tt>$CFG['application']['locale']</tt>, the format used
       * will be "date_it". Check the tip::FormatDate() function for details.
       **/
      case 'date':
	echo tip::FormatDate ($Params, 'iso8601', 'date_' . $APPLICATION->FIELDS['LOCALE']);
	return TRUE;

      /**
       * \command <b>DateTime(</b>\a datetime<b>)</b>\n
       * Formats the \p datetime date (specified in iso8601) in the format
       * <tt>"datetime_" . $CFG['application']['locale']</tt>.
       **/
      case 'datetime':
	echo tip::FormatDate ($Params, 'iso8601', 'datetime_' . $APPLICATION->FIELDS['LOCALE']);
	return TRUE;

      /**
       * \command <b>NlReplace(</b>\a replacer, \a text<b>)</b>\n
       * Replaces all the occurrences of a newline in \p text with the
       * \p replacer string.
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

  /**
   * Executes a management action.
   * @param[in] Action \c string The action name.
   *
   * Executes an action that requires the 'manager' privilege.
   * See \subpage ManagerActions for a list of available actions.
   **/
  function RunManagerAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes an administrator action.
   * @param[in] Action \c string The action name.
   *
   * Executes an action that requires at least the 'admin' privilege.
   * See \subpage AdminActions for a list of available actions.
   **/
  function RunAdminAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes a trusted action.
   * @param[in] Action \c string The action name.
   *
   * Executes an action that requires at least the 'trusted' privilege.
   * See \subpage TrustedActions for a list of available actions.
   **/
  function RunTrustedAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes an untrusted action.
   * @param[in] Action \c string The action name.
   *
   * Executes an action that requires at least the 'untrusted' privilege.
   * See \subpage UntrustedActions for a list of available actions.
   **/
  function RunUntrustedAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes an unprivileged action.
   * @param[in] Action \c string The action name.
   *
   * Executes an action that does not require any privileges.
   * See \subpage UnprivilegedActions for a list of available actions.
   **/
  function RunAction ($Action)
  {
    return NULL;
  }

  /**
   * Executes a module source.
   * @param[in] File \c string The source to execute
   *
   * Executes the \p File file found in the source path, using the current
   * engine.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function Run ($File)
  {
    $Path = $this->FIELDS['SOURCE_PATH'] . "/$File";
    return $this->SOURCE_ENGINE->Run ($Path, $this);
  }

  /**
   * Executes a shared data source
   * @param[in] File \c string The source to execute
   *
   * Executes the \p File program found in shared source path
   * ($CFG['application']['source_root']/shared) using the current source
   * engine.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function RunShared ($File)
  {
    global $APPLICATION;
    $Path = $APPLICATION->FIELDS['SOURCE_ROOT'] . "/shared/$File";
    return $this->SOURCE_ENGINE->Run ($Path, $this);
  }

  /**
   * Executes a file, appending the result to content.
   * @param[in] File \c string The source file
   *
   * Executes the \p File source found in the module path using the current
   * source engine and appends the result to the end of the application
   * content.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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
   * Executes a file, inserting the result in content.
   * @param[in] File \c string The source file
   *
   * Executes the \p File source found in the module path using the current
   * source engine and inserts the result at the beginning of the application
   * content.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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

  /**
   * The source program engine.
   *
   * Contains a reference to the source engine to use when parsing a file.
   * See the tipSource class for details on what is a source engine.
   **/
  var $SOURCE_ENGINE;

  /**
   * The data engine.
   *
   * Contains a reference to the data engine to use to read and write rows.
   * See the tipData class for details on what is a data engine.
   **/
  var $DATA_ENGINE;

  /**
   * Returns the content of a field.
   * \param[in] Field \c string The field id
   *
   * Gets the content of the \p Field field. A field is the unit of information
   * used by the TIP system. Searching the content of a field performs a search
   * operation which follows these steps in the following order:
   *
   * - <b>Current row fields</b>\n
   *   Checks if the there is a current row and if this row has a field named
   *   \p Field. If yes, returns the field content.
   * - <b>Summary fields of the current view</b>\n
   *   Checks if the there is a current view and if it has a summary field
   *   matching the requested one. If yes, returns the field content.
   * - <b>Module fields</b>\n
   *   Every module has a public variable (\p FIELDS) that can be filled
   *   with arbitrary key => values pairs. If a \p Field key exists in
   *   \p FIELDS, its value it is returned.
   * - <b>Global fields</b>\n
   *   Checks if \p Field is the id of a global fields. Global fields are
   *   nothing more than module fields of the tipApplication instance. If
   *   found, the content of the global field is returned.
   *
   * \return The content of the requested field or \c NULL if not found.
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
   * @return \c TRUE on success of \c FALSE on errors
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
   * @param[out] Destination \c array  The destination row
   *
   * Stores the posts content in the specified row, accordling to the data
   * source of the module. This method complements ValidatePosts() to manage
   * the user modules: usually you must validate the posts and after store
   * them in some place for further data operations (update or insert).
   *
   * @return \c TRUE on success of \c FALSE on errors
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


  /// \publicsection

  /**
   * Starts a view
   * @param[in] Query \c string  The query to execute
   *
   * Starts a view using the \p Query query. Starting a view means you can
   * traverse the results of the query using the ResetRow() and NextRow()
   * commands. Also, you can get the number of rows throught RowsCount().
   *
   * @attention After starting a view, there is no current row, so trying to
   *            retrieve some data will fail. You must use ResetRow() or
   *            NextRow() to set the cursor position.
   *
   * When the view is succesful started, this function returns \c TRUE.
   * When finished to use the results, close the view with EndView().
   *
   * @attention You must close the view only if StartView() is succesful.
   *
   * @return \c TRUE on success, \c FALSE otherwise
   **/
  function StartView ($Query)
  {
    return $this->Push (new tipView ($this, $Query));
  }

  /**
   * Starts a special view
   * @param[in] Name \c string  The name of the special view
   *
   * Starts a view trying to instantiate the class named tip{\p Name}View.
   * All the StartView() advices also applies to StartSpecialView().
   *
   * @return \c TRUE on success, \c FALSE otherwise
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
   * @returns The number of rows, or \c NULL on errors.
   **/
  function RowsCount ()
  {
    $Result = $this->GetSummaryField ('COUNT');
    if (is_null ($Result))
      return FALSE;
    return $this->GetSummaryField ('COUNT');
  }

  /**
   * Resets the cursor.
   *
   * Resets (set to the first row) the internal cursor. This function hangs if
   * there is no active view, but also if the results does not have any row.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function ResetRow ()
  {
    if (is_null ($this->VIEW) || ! is_array ($this->VIEW->ROWS))
      return FALSE;

    return reset ($this->VIEW->ROWS) !== FALSE;
  }

  /**
   * Moves the cursor to the end.
   *
   * Moves the internal cursor to the last row. This function hangs if there is
   * no active view, but also if the results does not have any row.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EndRow ()
  {
    if (is_null ($this->VIEW))
      return FALSE;

    return end ($this->VIEW->ROWS) !== FALSE;
  }

  /**
   * Unsets the cursor.
   *
   * Sets the internal cursor to an unidentified row.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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
   * Sets the cursor to the previous row.
   *
   * Decrements the cursor so it referes to the previous row. If the cursor is
   * not set, this function moves it to the last row (same as EndRow()).
   * If the cursor is on the first row, returns \c FALSE.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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
   * Sets the cursor to the next row.
   * @param[in] Rewind \c boolean  Reset the cursor when no more rows
   *
   * Increments the cursor so it referes to the next row. If the cursor was
   * never set and \p rewind is \c TRUE, this function moves it to the first
   * row (same as ResetRow()). If there are no more rows, returns \c FALSE.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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
   * all commands of a module refers if no views were started.
   *
   * @attention You can't have more EndView() than StartView().
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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
   * Executes a command.
   * @param[in] Command \c string The command name
   * @param[in] Params  \c string Parameters to pass to the command
   *
   * Executes the \p Command command, using \p params as arguments. This
   * function is merely a wrapper to RunCommand(). Anyway, \p Command is
   * converted to lowercase, so you can specify it in the way you prefer.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function CallCommand ($Command, &$Params)
  {
    return $this->RunCommand ($Command, $Params);
  }

  /**
   * Executes an action.
   * @param[in] Action \c string The action name
   *
   * Executes the \p Action action. This function tries to run \p Action
   * by calling the following protected methods in this order:
   *
   * - RunManagerAction()
   * - RunAdminAction()
   * - RunTrustedAction()
   * - RunUntrustedAction()
   * - RunAction()
   *
   * The first method called depends on the current privilege, get throught a
   * tipApplication::GetPrivilege() call. The first method that returns \c TRUE
   * (meaning the requested action is executed) stops the chain.
   *
   * Usually the actions are called adding variables to the URL. An example of
   * an action call is the following URL:
   * @verbatim
http://www.example.org/?module=news&action=view&id=23
@endverbatim
   *
   * This URL will call the "view" action on the "news" module, setting "id" to
   * 23 (it is request to view a news and its comments). You must check the
   * documentation of every module to see which actions are available and what
   * variables they require.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
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
}

// The application is the "main" module and MUST be present.
$APPLICATION =& tipType::GetInstance ('application');

?>
