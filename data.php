<?php

/**
 * @protected
 * A generic data context.
 *
 * This class provides an abstraction layer to the data view.
 **/
class tipDataContext extends tip
{
  /// @publicsection

  /**
   * The data source identified.
   *
   * This field is filled by the tipData type during this class instantiation.
   **/
  var $DATAID;

  /**
   * The primary key field id.
   *
   * Defaults to 'id' but can be changed anytime using the
   * tipData::SetPrimaryKey() method. The primary key univoquely identifies a
   * single row of data.
   **/
  var $PRIMARYKEY;

  /**
   * The primary key field type.
   *
   * Defaults to 'int' but can be changed anytime using the
   * tipData::SetPrimaryKey() method. The type can be any valid string accepted
   * by the settype() PHP function.
   **/
  var $PRIMARYKEY_TYPE;

  /**
   * Field details.
   *
   * Contains the description of all the fields of this data source. It can be
   * filled by the engine when it wants. This allows a sort of performance gain
   * if filled, for example, during a select query.
   *
   * This array MUST strictly have the following format:
   *
   * @verbatim
FIELDS[fieldid] = array
  (
    'id'          => fieldid,
    'type'        => a valid settype() type string,
    'subtype'     => 'date', 'time', 'datetime', 'enum', 'set', 'unsigned' or NULL,
    'length'      => an integer specifing the max length, or 0 if not used,
    'can_be_null' => TRUE or FALSE,

    // The optional part to be filled only when requested
    'choices'	  => the number of choices for 'set' or 'enum' subtypes,
    'choice1'     => the first choice,
    'choice2'	  => the second choice,
    ...
  );
@endverbatim
   **/
  var $FIELDS;

  /**
   * Constructor.
   * @param[in] DataId \c string A identifier of the data source
   *
   * Initializes a tipDataContext object.
   **/
  function tipDataContext ($DataId)
  {
    $this->DATAID = $DataId;
    $this->PRIMARYKEY = 'id';
    $this->PRIMARYKEY_TYPE = 'int';
    $this->FIELDS = NULL;
  }
}


/**
 * Base class for data engines.
 *
 * Provides a common interface to access any data requested by TIP modules.
 *
 * In TIP, all the data are based on a PrimaryKey access, that is there must
 * be, for every source data, a field that identify a row (record). The primary
 * key field is named by default 'id', but can be easely changed to any other
 * valid field id.
 **/
class tipData extends tipType
{
  /// @privatesection

  var $CONTEXT_CACHE;

  function& GetContext (&$Module)
  {
    $DataId = $Module->FIELDS['DATA_PATH'];
    if (array_key_exists ($DataId, $this->CONTEXT_CACHE))
      return $this->CONTEXT_CACHE[$DataId];

    $Context =& new tipDataContext ($DataId);
    $this->CONTEXT_CACHE[$DataId] =& $Context;
    return $Context;
  }


  /// @protectedsection

  /**
   * Constructor.
   *
   * Initializes a tipData instance.
   **/
  function tipData ()
  {
    $this->tipType ();
    $this->CONTEXT_CACHE = array ();
  }

  /**
   * Creates a query from a row id.
   * @param[in]     Id      \c mixed          The row id
   * @param[in,out] Context \c tipDataContext A data context
   *
   * Returns the query (in the proper engine format) to access the row with
   * the specified \p Id.
   *
   * \note This method MUST be overriden by all the types that inherits tipData.
   *
   * @return The requested query, or \c NULL on errors.
   **/
  function RealQueryById ($Id, &$Context)
  {
    $this->LogFatal ('method tipData::RealQueryById() not implemented');
  }

  /**
   * Prepares a variable for a query.
   * @param[in,out] Value   \c mixed          The value to querify
   * @param[in,out] Context \c tipDataContext A data context
   *
   * Prepares the value to be inserted in a query. The tipMysql engine, for
   * example, escapes and quotes all the string values.
   *
   * \note This method CAN be overriden by all the types inherited from
   *       tipData and requiring user data treatment. This includes SQL
   *       based engines that must avoid SQL injection.
   **/
  function RealQuerify (&$Value, &$Context)
  {
  }


  /**
   * Fills the data source structure.
   * @param[in,out] Context  \c tipDataContext A data context
   * @param[in,out] Detailed \c boolean        Fill also the optional part
   *
   * Gets the data source structure and store the result in $Context->FIELDS.
   * Obviously, the implementation must fills the FIELDS array a specific way:
   * read the tipDataContext documentation for further details.
   *
   * \note This method MUST be overriden by all the types that inherits tipData.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function RealFillFields (&$Context, $Detailed = FALSE)
  {
    $this->LogFatal ('method tipData::RealFillFields() not implemented');
  }


  /**
   * Executes a select query.
   * @param[in]     Query   \c string         The query to perform
   * @param[in,out] Context \c tipDataContext A data context
   *
   * Executes the select query specified in \p Query. The result returned by
   * this function must be homogeneus. This means for all the engines the
   * resulting array must be:
   *
   * Result = array (value of PrimaryKey1 => array (FieldId1 => value of FieldId1, ...),
   *                 value of PrimaryKey2 => array (FieldId1 => value of FieldId1, ...),
   *                 ...);
   *
   * The result must be an empty array if there's no matching rows.
   *
   * \note This method MUST be overriden by all the types that inherits tipData.
   *
   * @return A reference to an array of rows matching the specified query,
   *         or a reference to a variable containing \c FALSE on errors.
   **/
  function& Select ($Query, &$Context)
  {
    $this->LogFatal ('method tipData::Select() not implemented');
  }

  /**
   * Inserts a new row.
   * @param[in]     Row     \c array          A row
   * @param[in,out] Context \c tipDataContext A data context
   *
   * Inserts a new \p Row row. The primary key of the new row is returned. If
   * the primary key is specified in \p Row and a row with this primary key yet
   * exists, this function must fail.
   *
   * Notice \p Row can be an empty array, in which case a new empty row must
   * be added without errors. In this case, the \p Row row must be filled with
   * its default values.
   *
   * \note This method MUST be overriden by all the types that inherits tipData.
   *
   * @return The content of the newly inserted primary key (usually an
   *         integer, but can be any valid type) or \c NULL on errors.
   **/
  function Insert (&$Row, &$Context)
  {
    $this->LogFatal ('method tipData::Insert() not implemented');
  }

  /**
   * Executes an update query.
   * @param[in]     Query   \c mixed          The query to perform
   * @param[in]     Row     \c array          A row
   * @param[in,out] Context \c tipDataContext A data context
   *
   * Updates the rows that match \p Query using the new \c Row contents.
   *
   * \note This method MUST be overriden by all the types that inherits tipData.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function Update ($Query, &$Row, &$Context)
  {
    $this->LogFatal ('method tipData::Update() not implemented');
  }

  /**
   * Executes a delete query.
   * @param[in]     Query   \c string         The query to perform
   * @param[in,out] Context \c tipDataContext A data context
   *
   * Executes the delete query specified in \p Query.
   *
   * \note This method MUST be overriden by all the types that inherits tipData.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function Delete ($Query, &$Context)
  {
    $this->LogFatal ('method tipData::Delete() not implemented');
  }

  /**
   * Casts a row content to the proper type
   * @param[in,out] Row     \c Array           The row to cast
   * @param[in]     Context \c tipDataContext  A data context
   *
   * Given a row and a context, forces every field in the row that matches the fields
   * in the context to be of the type specified in the context. The type forcing is
   * done using settype().
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function ForceFieldType (&$Row, &$Context)
  {
    if (! $this->RealFillFields ($Context))
      return FALSE;

    foreach ($Context->FIELDS as $Id => $Meta)
      if (array_key_exists ($Id, $Row))
	{
	  if ($Row[$Id] == '' && $Meta['can_be_null'])
	    $Row[$Id] = NULL;
	  else
	    settype ($Row[$Id], $Meta['type']);
	}
  }


  /// @publicsection

  /**
   * Changes the primary key.
   * @param[in] Id     \c mixed     The new primary key
   * @param[in] Type   \c string    The new primary key type
   * @param[in] Module \c tipModule The caller module
   *
   * Changes the default primary key to the \p Id field. If not specified, the
   * 'id' field is used as primary key and it is of 'int' type.
   **/
  function SetPrimaryKey ($Id, $Type, &$Module)
  {
    $Context =& $this->GetContext ($Module);
    $Context->PRIMARYKEY = $Id;
    $Context->PRIMARYKEY_TYPE = $Type;
  }

  /**
   * Prepares a variable for a query.
   * @param[in,out] Value  \c mixed     The value to querify
   * @param[in]     Module \c tipModule The caller module
   *
   * All the values you put inside a query MUST pass throught this method for
   * security reasons. For the tipMysql type, for example, all the string
   * variables are escaped and quoted.
   **/
  function Querify (&$Value, &$Module)
  {
    $Context =& $this->GetContext ($Module);
    $this->RealQuerify ($Value, $Context);
  }

  /**
   * Creates a query from a row id.
   * @param[in] Id     \c mixed     The row id
   * @param[in] Module \c tipModule The caller module
   *
   * Returns the query (in the proper engine format) to access the specified
   * \p Id row id.
   *
   * @return The requested query, or \c NULL on errors.
   **/
  function QueryById ($Id, &$Module)
  {
    $Context =& $this->GetContext ($Module);
    if (! settype ($Id, $Context->PRIMARYKEY_TYPE))
      return NULL;

    return $this->RealQueryById ($Id, $Context);
  }

  /**
   * Gets the fields structure
   * @param[in] Module \c tipModule The caller module
   *
   * Gets the field structure of the context of the specified module: the
   * structure details are descripted in tipDataContext::FIELDS. Notice also
   * the optional part of the field structure will be filled.
   *
   * @return A reference to the field structure or a reference to a variable
   *         containing \c NULL on errors.
   **/
  function& GetFields (&$Module)
  {
    $Context =& $this->GetContext ($Module);
    $this->RealFillFields ($Context, TRUE);
    return $Context->FIELDS;
  }

  /**
   * Executes a read query.
   * @param[in] Query  \c string    The query to perform
   * @param[in] Module \c tipModule The caller module
   *
   * Executes the read query specified in \p Query. The syntax of \c Query is
   * data engine dependent: no assumptions are made by the tipData class. This
   * also means the \p Query parameter must be prepared for the engine: use
   * Querify() on every value get from the user.
   *
   * Of course, the result can be an empty array if there's no matching rows
   * that satisfy \p Query or \c FALSE on errors.
   *
   * @return A reference to an array of rows matching the specified query
   *         or a reference to a variable containing \c FALSE on errors.
   **/
  function& GetRows ($Query, &$Module)
  {
    $Context =& $this->GetContext ($Module);
    return $this->Select ($Query, $Context);
  }

  /**
   * Inserts a new row.
   * @param[in,out] Row    \c array     A row
   * @param[in]     Module \c tipModule The caller module
   *
   * Inserts a new row in the data source. If the primary key is not
   * found in \p Row, will be defined after the insert operation so after this
   * call <tt>$Row['id']</tt> (or whatever have you choosed as primary key)
   * contains the value of the recently added row.
   * Instead, if the primary key is defined in \p Row, this function fails if
   * a row with the same primary key exists.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function PutRow (&$Row, &$Module)
  {
    $Context =& $this->GetContext ($Module);

    // Remove the primary key from row, if present
    if (array_key_exists ($Context->PRIMARYKEY, $Row))
      unset ($Row[$Context->PRIMARYKEY]);

    $Id = $this->Insert ($Row, $Context);
    if (is_null ($Id))
      return FALSE;

    // Add the recently added primary key to row
    settype ($Id, $Context->PRIMARYKEY_TYPE);
    $Row[$Context->PRIMARYKEY] = $Id;
    return TRUE;
  }

  /**
   * Updates one row.
   * @param[in] OldRow \c array     The row to update
   * @param[in] NewRow \c array     The new row contents
   * @param[in] Module \c tipModule The caller module
   *
   * Updates the row matching the primary key of \p OldRow with the \p NewRow
   * content. Only the fields that are presents in \p NewRow and that differs
   * from \p OldRow are updated. Obviously, if \p OldRow and \p NewRow are
   * equals no update operations are performed.
   *
   * This function is quite different from the other because require an array
   * instead of a query string. This is done to allow a check between the old
   * and new row content, trying to avoid the update operation.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function UpdateRow (&$OldRow, &$NewRow, &$Module)
  {
    $DeltaRow = FALSE;
    $Context =& $this->GetContext ($Module);

    // No primary key found: error
    if (! array_key_exists ($Context->PRIMARYKEY, $OldRow))
      {
	$this->LogWarning ('No primary key in the row to update');
	return FALSE;
      }

    if (! $this->RealFillFields ($Context))
      return FALSE;

    foreach (array_keys ($Context->FIELDS) as $Id)
      if (array_key_exists ($Id, $OldRow) && array_key_exists ($Id, $NewRow)
	  && $OldRow[$Id] != $NewRow[$Id])
	$DeltaRow[$Id] = $NewRow[$Id];

    if (! $DeltaRow)
      return TRUE;

    $Query = $this->RealQueryById ($OldRow[$Context->PRIMARYKEY], $Context);
    if (is_null ($Query))
      return FALSE;

    return $this->Update ($Query, $DeltaRow, $Context);
  }

  /**
   * Executes an update query.
   * @param[in] Query    \c string  The query to perform
   * @param[in] DeltaRow \c array   The fields to change
   * @param[in] Module   \c tipModule The caller module
   *
   * Executes the update query specified in \p Query accordling to the
   * \c DeltaRow array, which must be a collection of "fieldid => value" to
   * change. The syntax of \c Query is data engine dependent: no assumptions
   * are made by the tipData class. This also means the \p Query parameter
   * must be prepared for the engine: use Querify() on every value get from
   * the user.
   *
   * This function must be used to update more than one row: this means
   * \p DeltaRow must not have the primary key defined (a primary key is
   * unique). To update only one row, use UpdateRow() instead.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function UpdateRows ($Query, &$DeltaRow, &$Module)
  {
    $Context =& $this->GetContext ($Module);

    // Found a primary key: error
    if (array_key_exists ($Context->PRIMARYKEY, $DeltaRow))
      return FALSE;

    return $this->Update ($Query, $DeltaRow, $Context);
  }

  /**
   * Executes a delete query.
   * @param[in] Query  \c string    The query to perform
   * @param[in] Module \c tipModule The caller module
   *
   * Executes the delete query specified in \p Query. The syntax of \c Query
   * is data engine dependent: no assumptions are made by the tipData class.
   * This also means the \p Query parameter must be prepared for the engine:
   * use Querify() on every value get from the user.
   *
   * Usually, empty queries are rejected by the engine, to avoid dropping the
   * whole content of a table.
   *
   * @return \c TRUE on success, \c FALSE otherwise.
   **/
  function DeleteRows ($Query, &$Module)
  {
    $Context =& $this->GetContext ($Module);
    return $this->Delete ($Query, $Context);
  }
}

?>
