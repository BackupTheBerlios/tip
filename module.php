<?php

/**
 * @private
 *
 * A data view internally used by the tipModule type.
 **/
class tipModuleView extends tip
{
  /// @privatesection

  var $CURSOR;


  /// @publicsection

  var $ROWS;
  var $SUMMARY_FIELDS;


  function tipModuleView (&$Rows, &$SummaryFields)
  {
    $this->ROWS =& $Rows;
    $this->SUMMARY_FIELDS =& $SummaryFields;
    $this->CURSOR = NULL;
  }

  function& GetRow ($Id)
  {
    if (@array_key_exists ($Id, $this->ROWS))
      {
	$Row =& $this->ROWS[$Id];
	// Not really correct, but any chance to do it in a better way?
	$this->CURSOR = $Id;
      }
    else
      {
	$Row = NULL;
      }

    return $Row;
  }

  function& GetCurrentRow ()
  {
    return $this->GetRow ($this->CURSOR);
  }

  function ResetRow ()
  {
    if ($this->SUMMARY_FIELDS['COUNT'] == 0)
      return FALSE;

    reset ($this->ROWS);
    $this->CURSOR = key ($this->ROWS);
    return TRUE;
  }

  function EndRow ()
  {
    if ($this->SUMMARY_FIELDS['COUNT'] == 0)
      return FALSE;

    end ($this->ROWS);
    $this->CURSOR = key ($this->ROWS);
    return TRUE;
  }

  function UnsetRow ()
  {
    $this->CURSOR = NULL;
    return TRUE;
  }

  function PrevRow ()
  {
    if (is_null ($this->CURSOR))
      return $this->EndRow ();

    if (prev ($this->ROWS) === FALSE)
      {
	$this->CURSOR = NULL;
	return FALSE;
      }

    $this->CURSOR = key ($this->ROWS);
    return TRUE;
  }

  function NextRow ()
  {
    if (is_null ($this->CURSOR))
      return $this->ResetRow ();

    if (next ($this->ROWS) === FALSE)
      {
	$this->CURSOR = NULL;
	return FALSE;
      }

    $this->CURSOR = key ($this->ROWS);
    return TRUE;
  }
}


/**
 * Interaction between the tipSource and the tipData.
 *
 * A module can be thought as a black box that runs commands found in some
 * source programs (identified by a source path and parsed by a source engine)
 * getting the required informations from a data source (identified by a data
 * path and parsed by a data engine). Also, this black box must react to some
 * external requests (usually because of a user click): this is usually done
 * throught a call to the CallAction() method.
 *
 * Every TIP based site must have a starting point (in C terms, it must have a
 * \a main function), that is a module that runs a specified source program.
 * This module is an instance of the tipApplication type, the only one
 * automatically created by TIP.
 **/
class tipModule extends tipType
{
  /// @privatesection

  var $VIEW_CACHE;
  var $VIEW_STACK;

  function& GetLastView ()
  {
    $Last = @count ($this->VIEW_STACK);
    if ($Last > 0)
      {
	$Result =& $this->VIEW_STACK[$Last - 1];
      }
    else
      {
	$this->SetError ('no current query');
	$Result = NULL;
      }

    return $Result;
  }


  /// @protectedsection

  /**
   * Constructor.
   *
   * Initializes a tipModule instance.
   **/
  function tipModule ()
  {
    $this->tipType ();

    $this->SOURCE_ENGINE =& tipType::GetInstance ($this->GetOption ('source_engine'));
    $this->DATA_ENGINE =& tipType::GetInstance ($this->GetOption ('data_engine'));
    $this->FIELDS['SOURCE_PATH'] = $this->GetOption ('source_path');
    $this->FIELDS['DATA_PATH'] = $this->GetOption ('data_path');

    $this->VIEW_STACK = array ();
    $this->VIEW_CACHE = array ();
  }


  /**
   * Performs additional operations on the whole result of a query.
   * @param[in,out] $Rows   \c array The query result
   * @param[in,out] $Fields \c array Summary fields
   *
   * Called after every query and before CalculatedFields(). You can override
   * this method to add some summary fields or performs operation on the whole
   * result. Summary fields are informations logically connected to the query,
   * such as the row count or a total sum of a particular column.
   * As for CalculatedFields(), returning \c FALSE will invalidate the query.
   *
   * @note Remember to always chain up the parent method.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   *
   * The following summary fields are added by this module:
   * \li <b>COUNT</b>\n
   *     The number of rows matching the query.
   **/
  function SummaryFields (&$Rows, &$Fields)
  {
    $Fields['COUNT'] = count ($Rows);
    return TRUE;
  }

  /**
   * Adds calculated fields to the rows.
   * @param[in,out] $Row \c array A row
   *
   * Called for every row retrieved by the data engine. You can override this
   * method to add some calculated field to every rows. Returning \c FALSE will
   * a single row will invalidate the whole query, so usually you must ever
   * return \c TRUE.
   *
   * @note Remember to always chain up the parent method.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   *
   * The following calculated fields are added by this module:
   * \li <b>ROW</b>\n
   *     The row number, starting from 1.
   * \li <b> ODDEVEN</b>\n
   *     A field that will be set to 'odd' for every odd rows and to 'even' for
   *     the even rows.
   **/
  function CalculatedFields (&$Row)
  {
    return TRUE;
  }

  /**
   * Gets the current rows.
   *
   * Gets a reference to the rows matching the current query.
   *
   * @return The reference to an array of rows, or a reference to a variable
   *         containing \c NULL on errors.
   **/
  function& GetCurrentRows ()
  {
    $Row = NULL;
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return $Row;

    return $View->ROWS;
  }

  /**
   * Gets the current row.
   *
   * Gets a reference to the current row.
   *
   * @return The reference to the current row, or a reference to a variable
   *         containing \c NULL on errors.
   **/
  function& GetCurrentRow ()
  {
    $Row = NULL;
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return $Row;

    $Row =& $View->GetCurrentRow ();
    if (is_null ($Row))
      $this->SetError ('no current row');

    return $Row;
  }

  /**
   * Gets a specified row.
   * @param[in] Id \c mixed The row id
   *
   * Gets a referece to a specific row.
   *
   * @return The reference to the current row, or a reference to a variable
   *         containing \c NULL on errors.
   **/
  function& GetRow ($Id)
  {
    $Row = NULL;
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return $Row;

    $Row =& $View->GetRow ($Id);
    if (is_null ($Row))
      $this->SetError ("`$Id' row id not found");

    return $Row;
  }

  /**
   * Gets a field content from the current row.
   * @param[in] Field \c string The field id
   *
   * Gets the \p Field field content from the current row.
   *
   * @return The requested field content, or \c NULL on errors.
   **/
  function GetField ($Field)
  {
    $Row =& $this->GetCurrentRow ();
    if (is_null ($Row) || ! @array_key_exists ($Field, $Row))
      return NULL;

    return $Row[$Field];
  }

  /**
   * Gets a field content from the summary fields.
   * @param[in] Field \c string The field id
   *
   * Gets the \p Field summary field from the current query.
   *
   * @return The requested field content, or \c NULL on errors.
   **/
  function GetSummaryField ($Field)
  {
    $View =& $this->GetLastView ();
    if (! $View || ! @array_key_exists ($Field, $View->SUMMARY_FIELDS))
      return NULL;

    return $View->SUMMARY_FIELDS[$Field];
  }

  /**
   * Executes a command.
   * @param[in] Command \c string The command name
   * @param[in] Params  \c string Parameters to pass to the command
   *
   * Executes the \p Command command, using \p params as arguments. If
   * \p Command does not exist, this function return \c FALSE.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   *
   * Here's the list of commands provided by this module:
   *
   **/
  function RunCommand ($Command, &$Params)
  {
    global $APPLICATION;

    switch ($Command)
      {
      /**
       * \li <b>field(</b>\a fieldid<b>)</b>\n
       *     Outputs the content of the specified in field. The field is
       *     searched using the FindField() function. Notice the resulting text
       *     is converted using htmlentities(text,ENT_QUOTES,'UTF-8').
       **/
      case 'field':
	$Value = $this->FindField ($Params);
	if (is_bool ($Value))
	  echo $Value ? 'TRUE' : 'FALSE';
	elseif (! is_null ($Value))
	  echo htmlentities ($Value, ENT_QUOTES, 'UTF-8');
	return TRUE;

      /**
       * \li <b>get(</b>\a getid<b>)</b>\n
       *     Outputs the content of the specified get.
       **/
      case 'get':
	$Value = tip::GetGet ($Params, 'string');
	if (! is_null ($Value))
	  echo $Value;
	return TRUE;

      /**
       * \li <b>post(</b>\a postid<b>)</b>\n
       *     Outputs the content of the specified post.
       **/
      case 'post':
	$Value = tip::GetPost ($Params, 'string');
	if (! is_null ($Value))
	  echo $Value;
	return TRUE;

      /**
       * \li <b>url(</b>\a file<b>)</b>\n
       *     Prepends the source path of the current module to \p file and
       *     outputs the result. This command (or any of its variants) MUST be
       *     used for every file reference if you want a theme-aware site,
       *     because enabling themes will make the prepending path a dynamic
       *     variable.
       **/
      case 'url':
	echo "{$this->FIELDS['SOURCE_PATH']}/$Params";
	return TRUE;

      /**
       * \li <b>sourceurl(</b>\a file<b>)</b>\n
       *     Variants of the <b>url</b> command. Prepends to \p file the
       *     root source path.
       **/
      case 'sourceurl':
	echo "{$APPLICATION->FIELDS['SOURCE_ROOT']}/$Params";
	return TRUE;

      /**
       * \li <b>sourceurl(</b>\a file<b>)</b>\n
       *     Variants of the <b>url</b> command. Prepends to \p file the
       *     root source path and '/icons'.
       **/
      case 'iconurl':
	echo "{$APPLICATION->FIELDS['SOURCE_ROOT']}/icons/$Params";
	return TRUE;

      /**
       * \li <b>run(</b>\a file<b>)</b>\n
       *     Runs the \p file source found in the module directory using the
       *     current source engine.
       **/
      case 'run':
	return $this->Run ($Params);

      /**
       * \li <b>rundata(</b>\a file<b>)</b>\n
       *     Runs the \p file source found in the root data directory using
       *     the current source engine.
       **/
      case 'datarun':
	return $this->DataRun ($Params);

      /**
       * \li <b>moduleexists(</b>\a module<b>)</b>\n
       *     Outputs \c TRUE if the \p module module exists, \c FALSE
       *     otherwise. This command only checks if the module is configured,
       *     does not load the module itsself. Useful to provide conditional
       *     links in some module manager (such as the tipUser type).
       **/
      case 'moduleexists':
	global $CFG;
	echo array_key_exists ($Params, $CFG) ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * \li <b>itemexists(</b>\a item, \a list<b>)</b>\n
       *     Outputs \c TRUE if the \p item item is present in the comma
       *     separated \p list list. Useful to check if a value is contained
       *     (that is, if it is on) in a "set" field.
       **/
      case 'itemexists':
	$Pos = strpos ($Params, ',');
	if ($Pos === FALSE)
	  return FALSE;

	$Item = substr ($Params, 0, $Pos);
	$Set  = substr ($Params, $Pos+1);
	echo tip::ItemExists ($Item, $Set) ? 'TRUE' : 'FALSE';
	return TRUE;

      /**
       * \li <b>date(</b>\a date<b>)</b>\n
       *     Formats the \p date date (specified in iso8601) in the format
       *     "date_" . $CFG['application']['locale']. For instance, if you
       *     set 'it' in $CFG['application']['locale'], the format used will be
       *     "date_it". Check the tip::FormatDate() function for details.
       **/
      case 'date':
	echo tip::FormatDate ($Params, 'iso8601', 'date_' . $APPLICATION->FIELDS['LOCALE']);
	return TRUE;

      /**
       * \li <b>datetime(</b>\a datetime<b>)</b>\n
       *     Formats the \p datetime date (specified in iso8601) in the format
       *     "datetime_" . $CFG['application']['locale']. For instance, if you
       *     set 'it' in $CFG['application']['locale'], the format used will be
       *     "datetime_it". Check the tip::FormatDate() function for details.
       **/
      case 'datetime':
	echo tip::FormatDate ($Params, 'iso8601', 'datetime_' . $APPLICATION->FIELDS['LOCALE']);
	return TRUE;

      /**
       * \li <b>nlreplace(</b>\a replacer, \a text<b>)</b>\n
       *     Replaces all the occurrences of a newline in \p text with the
       *     \p replacer string.
       **/
      case 'nlreplace':
	$Pos = strpos ($Params, ',');
	if ($Pos === FALSE)
	  return FALSE;

	$From   = "\n";
	$To     = substr ($Params, 0, $Pos);
	$Buffer = str_replace ("\r", '', substr ($Params, $Pos+1));

	echo str_replace ($From, $To, $Buffer);
	return TRUE;

      }

    $this->SetError ("command `$Command' not found");
    return FALSE;
  }

  /**
   * Executes an action.
   * @param[in] Action \c string The action name.
   *
   * Executes the \p Action action. An action is something that must be run
   * before any output. Some examples are <tt>Add a row</tt>, <tt>Select a
   * theme</tt> and so on.
   *
   * Usually the actions are called adding variables to the URL, but this is not
   * a strict rule.
   * An example of call to an action is the following URL:
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
   *
   * Here's the list of action provided by this module:
   *
   **/
  function RunAction ($Action)
  {
    tip::LogError ("action `$Action' not found");
    return FALSE;
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
   * Executes a global data source.
   * @param[in] File \c string The source to execute
   *
   * Executes the \p File file found in application data path, using the
   * current engine.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function DataRun ($File)
  {
    global $APPLICATION;
    $Path = $APPLICATION->FIELDS['DATA_PATH'] . "/$File";
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
   * @param[in] Field \c string The field id
   *
   * Gets the content of the \p Field field. A field is the unit of information
   * used by the TIP system. Searching the content of a field performs a search
   * operation which follows these steps:
   *
   * \li <b>Current row fields</b>\n
   *     Checks if the there is a current row and if this row has a field named
   *     \p Field. If yes, returns the field content.
   * \li <b>Summary fields of the current query</b>\n
   *     Checks if the there is a current query and if it has a summary field
   *     matching the requested one. If yes, returns the field content.
   * \li <b>Module fields</b>\n
   *     Every module has a protected variable ($FIELDS) that can be filled
   *     with arbitrary key => values pairs. If a \p Field key exists in
   *     $FIELDS, its value it is returned.
   * \li <b>Global fields</b>\n
   *     Checks if \p Field is the id of a global fields. Global fields are
   *     nothing more than module fields of the "application" instance. If
   *     found, the content of the global field is returned.
   * \li <b>Field not found</b>\n
   *     If no search was successful, the internal error is set accordling and
   *     \c FALSE is returned.
   *
   * @return The content of the requested field, or \c NULL on errors.
   **/
  function FindField ($Field)
  {
    $Value = $this->GetField ($Field);
    if (! is_null ($Value))
      return $Value;
    $this->ResetError ();

    $Value = $this->GetSummaryField ($Field);
    if (! is_null ($Value))
      return $Value;
    $this->ResetError ();

    if (@array_key_exists ($Field, $this->FIELDS))
      return $this->FIELDS[$Field];

    global $APPLICATION;
    if ($this != $APPLICATION)
      {
	$Value = $APPLICATION->FindField ($Field);
	if (! is_null ($Value))
	  return $Value;
      }

    $this->SetError ("field `$Field' not found");
    return NULL;
  }


  /// @publicsection

  /**
   * Global fields of the module.
   *
   * Every module can have a bounch of specific fields. This array contains
   * the fields related to a specific module. These fields are used while
   * looking for a field with the FindField() method.
   *
   * Also, it is public because provides a way to interact between modules.
   **/
  var $FIELDS;

  /**
   * Starts a query.
   * @param[in] Query \c string The query to start.
   *
   * Starts the \p Query query. Starting a query means you can traverse the
   * results of the query using the ResetRow() and NextRow() commands. Also,
   * you can get the number of rows throught RowsCount().
   *
   * @attention After starting a query, there no current row, so trying to
   *            retrieve some data will fail. You must use ResetRow() or
   *            NextRow() to set the cursor before.
   *
   * When the query is succesful executed, this function returns \c TRUE.
   * When finished to use the results, close the query with EndQuery().
   *
   * @attention You must close the query only if StartQuery() returns
   *            succesful.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function StartQuery ($Query)
  {
    if (array_key_exists ($Query, $this->VIEW_CACHE))
      {
	$View =& $this->VIEW_CACHE[$Query];
	$View->UnsetRow ();
      }
    else
      {
	$Rows =& $this->DATA_ENGINE->GetRows ($Query, $this);
	if (is_null ($Rows))
	  return FALSE;

	$SummaryFields = array ();
	if (! $this->SummaryFields ($Rows, $SummaryFields))
	  return FALSE;

	$View =& new tipModuleView ($Rows, $SummaryFields);
	$nRow = 0;
	foreach (array_keys ($Rows) as $Id)
	  {
	    $Row =& $Rows[$Id];
	    ++ $nRow;
	    $Row['ROW'] = $nRow;
	    /* Cursor starts from 0, but the natural ordering starts from 1,
	     * so 'odd' and 'even' are swapped. */
	    $Row['ODDEVEN'] = ($nRow & 1) > 0 ? 'even' : 'odd';

	    if (! $this->CalculatedFields ($Row))
	      return FALSE;
	  }

	$this->VIEW_CACHE[$Query] =& $View;
      }

    $this->VIEW_STACK[count ($this->VIEW_STACK)] =& $View;
    return TRUE;
  }

  /**
   * Counts the rows of the current query.
   *
   * Returns the rows count of the results of the current query.
   *
   * @returns The number of rows, or \c FALSE if there is no query active.
   **/
  function RowsCount ()
  {
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return FALSE;

    return $View->SUMMARY_FIELDS['COUNT'];
  }

  /**
   * Resets the cursor.
   *
   * Resets (set to the first row) the internal cursor. This function hangs if
   * there is no active query, but also if the results does not have any row.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function ResetRow ()
  {
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return FALSE;

    return $View->ResetRow ();
  }

  /**
   * Moves the cursor to the end.
   *
   * Moves the internal cursor to the last row. This function hangs if there is
   * no active query, but also if the results does not have any row.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EndRow ()
  {
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return FALSE;

    return $View->EndRow ();
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
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return FALSE;

    return $View->UnsetRow ();
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
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return FALSE;

    return $View->PrevRow ();
  }

  /**
   * Sets the cursor to the next row.
   *
   * Increments the cursor so it referes to the next row. If the cursor was
   * never set, this function moves it to the first row (same as ResetRow()).
   * If there are no more rows, returns \c FALSE.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function NextRow ()
  {
    $View =& $this->GetLastView ();
    if (is_null ($View))
      return FALSE;

    return $View->NextRow ();
  }

  /**
   * Ends a query.
   *
   * Ends the current query. Ending a query means the query active before the
   * last StartQuery() command is make current.
   *
   * Usually, you always have to close all queries. Anyway, in some situations,
   * is useful to have the base query ever active (so called default query) where
   * all commands of a module refers if no queries were performed.
   *
   * @attention You can't have more EndQuery() than StartQuery().
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function EndQuery ()
  {
    $Last = count ($this->VIEW_STACK);
    if ($Last < 1)
      {
	$this->LogWarning ('`EndQuery()\' requested without a previous `Query()\' call');
	return FALSE;
      }

    unset ($this->VIEW_STACK[$Last - 1]);
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
    $Command = strtolower ($Command);
    return $this->RunCommand ($Command, $Params);
  }

  /**
   * Executes an action.
   * @param[in] Action \c string The action name
   *
   * Executes the \p Action action. This function is merely a wrapper to
   * RunAction(). Anyway, \p Action is converted to lowercase, so you can
   * specify it in the way you prefer.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function CallAction ($Action = NULL)
  {
    $Action = strtolower ($Action);
    return $this->RunAction ($Action);
  }
}

// The application is the "main" module and MUST be present.
$APPLICATION =& tipType::GetInstance ('application');

?>
