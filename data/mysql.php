<?php
   
/**
 * The MySql data engine.
 *
 * Interface to the MySql database.
 *
 * The \p Query parameters must be specified with a stripped syntax, that is
 * without the SQL command and the table reference.
 *
 * For example, if you want to show the user called 'nicola', you must specify
 * the query <tt>WHERE `user`='nicola'</tt> in the appropriate module. This
 * applies also to every method that has a \p Query parameter on a Mysql based
 * module. Using the tipRcbt engine, for instance, you could create a source
 * file like the following:
 * @verbatim
<h1>List of the first ten users whose name begins with 'c'</h1>

{user.forquery(WHERE `user` LIKE 'c%' LIMIT 10)}
  <p>{user} ({publicname})</p>
{}

@endverbatim
 * In the above example, the query is called from the \b user module. Also, the
 * \b forquery method is by definition a read method (\a select, in SQL). This
 * means the command will expand to the real SQL query:
 *
 * <tt>SELECT * FROM `tip_user` WHERE `user` LIKE 'c\%' LIMIT 10)</tt>
 **/
class tipMysql extends tipData
{
  /// @protectedsection

  function RealQueryById ($Id, &$Context)
  {
    if (empty ($Id))
      return NULL;

    $this->RealQuerify ($Id, $Context);
    return "WHERE `$Context->PRIMARYKEY`=$Id";
  }

  function RealQuerify (&$Value, &$Context)
  {
    if (is_string ($Value))
      $Value = "'" . mysql_real_escape_string ($Value, $this->CONNECTION) . "'";
  }

  function RealFillFields (&$Context, $Detailed = FALSE)
  {
    if (is_null ($Context->FIELDS))
      {
	$Result = mysql_list_fields ($this->GetOption ('database'),
				     $Context->DATAID,
				     $this->CONNECTION);
	if (! $Result)
	  {
	    $this->LogError (mysql_error ($this->CONNECTION));
	    return FALSE;
	  }
	$this->TryGetFields ($Result, $Context);
      }

    if ($Detailed)
      $this->TryGetFieldsDetails ($Context);

    return ! is_null ($Context->FIELDS);
  }

  function& Select ($Query, &$Context)
  {
    $Rows = FALSE;
    $Result = $this->RunQuery ("SELECT * FROM `$Context->DATAID` $Query");
    if ($Result === FALSE)
      return $Rows;

    $this->TryGetFields ($Result, $Context);
    $Rows = array ();

    while ($Row = mysql_fetch_assoc ($Result))
      {
	$this->ForceFieldType ($Row, $Context);
	$Rows[$Row[$Context->PRIMARYKEY]] =& $Row;
	unset ($Row);
      }

    // To free or not to free
    mysql_free_result ($Result);
    return $Rows;
  }

  function Insert (&$Row, &$Context)
  {
    $Fieldset =& $this->MakeFieldset ($Row, $Context);
    $Fieldset = empty ($Fieldset) ? '' : " SET $Fieldset";

    if ($this->RunQuery ("INSERT INTO `$Context->DATAID`$Fieldset") === FALSE)
      return FALSE;

    return mysql_insert_id ($this->CONNECTION);
  }

  function Update ($Query, &$Row, &$Context)
  {
    $Fieldset =& $this->MakeFieldset ($Row, $Context);
    if (empty ($Fieldset))
      return TRUE;

    return $this->RunQuery ("UPDATE `$Context->DATAID` SET $Fieldset $Query");
  }

  function Delete ($Query, &$Context)
  {
    // Delete query without the query part are by default not accepted
    if (empty ($Query))
      return FALSE;

    return $this->RunQuery ("DELETE FROM `$Context->DATAID` $Query");
  }


  /// @privatesection

  var $CONNECTION;

  function tipMysql ()
  {
    $this->tipData ();
    $this->CONNECTION = mysql_connect ($this->GetOption ('server'),
				       $this->GetOption ('user'),
				       $this->GetOption ('password'));
    if (! $this->CONNECTION)
      {
	$this->LogError (mysql_error ($this->CONNECTION));
	$this->ERROR = TRUE;
	return;
      }

    if (! mysql_select_db ($this->GetOption ('database'), $this->CONNECTION))
      {
	$this->LogError (mysql_error ($this->CONNECTION));
	$this->ERROR = TRUE;
	return;
      }

    // The charset does not set $this->ERROR interface error flag
    $this->RunQuery ('SET CHARACTER SET utf8');
  }

  function& RunQuery ($Query)
  {
    $Result = mysql_query ($Query, $this->CONNECTION);
    if ($Result === FALSE)
      $this->LogError (mysql_error ($this->CONNECTION) . " ($Query)");
    return $Result;
  }

  function& MakeFieldset (&$Row, &$Context)
  {
    $Fieldset = '';
    foreach ($Row as $Field => $Value)
      {
	if (! empty ($Fieldset))
	  $Fieldset .= ',';

	if (is_null ($Value))
	  $Value = 'NULL';
	else
	  $this->RealQuerify ($Value, $Context);

	$Fieldset .= "`$Field`=$Value";
      }

    return $Fieldset;
  }

  function TryGetFields (&$Result, &$Context)
  {
    if (! is_null ($Context->FIELDS))
      return;

    $nFields = mysql_num_fields ($Result);
    for ($n = 0; $n < $nFields; ++ $n)
      {
	$Name = mysql_field_name ($Result, $n);
	$Type = mysql_field_type ($Result, $n);
	$Flags = mysql_field_flags ($Result, $n);

	$Context->FIELDS[$Name] = array ('id' => $Name);
	$Field =& $Context->FIELDS[$Name];

	switch (strtoupper ($Type))
	  {
	  case 'BOOL':
	  case 'BOOLEAN':
	    $Field['type'] = 'bool';
	    $Field['subtype'] = NULL;
	    $Field['length'] = 0;
	    break;
	  case 'BIT':
	  case 'TINYINT':
	  case 'SMALLINT':
	  case 'MEDIUMINT':
	  case 'INT':
	  case 'INTEGER':
	  case 'BIGINT':
	    $Field['type'] = 'int';
	    $Field['subtype'] = strpos ($Flags, 'unsigned') !== FALSE ? 'unsigned' : NULL;
	    $Field['length'] = 0;
	    break;
	  case 'FLOAT':
	  case 'DOUBLE':
	  case 'DOUBLE PRECISION':
	  case 'REAL':
	  case 'DECIMAL':
	  case 'DEC':
	  case 'NUMERIC':
	  case 'FIXED':
	    $Field['type'] = 'float';
	    $Field['subtype'] = NULL;
	    $Field['length'] = 0;
	    break;
	  case 'STRING':
	  case 'CHAR':
	  case 'VARCHAR':
	  case 'BINARY':
	  case 'VARBINARY':
	  case 'TINYBLOB':
	  case 'TINYTEXT':
	  case 'BLOB':
	  case 'TEXT':
	  case 'MEDIUMBLOB':
	  case 'MEDIUMTEXT':
	  case 'LONGBLOB':
	  case 'LONGTEXT':
	    $Field['type'] = 'string';
	    if (strpos ($Flags, 'enum') !== FALSE)
	      $Field['subtype'] = 'enum';
	    elseif (strpos ($Flags, 'set') !== FALSE)
	      $Field['subtype'] = 'set';
	    else
	      $Field['subtype'] = NULL;
	    $Field['length'] = mysql_field_len ($Result, $n) / 3;
	    break;
	  case 'ENUM':
	    $Field['type'] = 'string';
	    $Field['subtype'] = 'enum';
	    $Field['length'] = 0;
	    break;
	  case 'SET':
	    $Field['type'] = 'string';
	    $Field['subtype'] = 'set';
	    $Field['length'] = 0;
	    break;
	  case 'DATE':
	    $Field['type'] = 'string';
	    $Field['subtype'] = 'date';
	    $Field['length'] = 10;
	    break;
	  case 'TIME':
	    $Field['type'] = 'string';
	    $Field['subtype'] = 'time';
	    $Field['length'] = 8;
	    break;
	  case 'DATETIME':
	    $Field['type'] = 'string';
	    $Field['subtype'] = 'datetime';
	    $Field['length'] = 19;
	    break;
	  case 'TIMESTAMP':
	    $Field['type'] = 'int';
	    $Field['subtype'] = 'datetime';
	    $Field['length'] = 0;
	    break;
	  case 'YEAR':
	    $Field['type'] = 'string';
	    $Field['subtype'] = NULL; // Not implemented
	    $Field['length'] = 4;
	    break;
	  default:
	    $this->LogError ("field type not supported ($Type)");
	  }

	$Field['can_be_null'] = (bool) (strpos ($Flags, 'not_null') === FALSE);
      }
  }

  function TryGetFieldsDetails (&$Context)
  {
    $Fields = array ();
    foreach ($Context->FIELDS as $Id => $Field)
      if ($Field['subtype'] == 'set' || $Field['subtype'] == 'enum')
	{
	  if (@array_key_exists ('choices', $Field))
	    return;
	  else
	    $Fields[] = $Id;
	}

    $InClause = implode ("','", $Fields);
    if (empty ($InClause))
      return;

    $Database = $this->GetOption ('database');
    $Result =& $this->RunQuery ("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS" .
				"  WHERE TABLE_SCHEMA='$Database' AND TABLE_NAME='$Context->DATAID'" .
				"  AND COLUMN_NAME IN ('$InClause')");
    if (! $Result)
      return;

    while ($Row = mysql_fetch_assoc ($Result))
      {
	$Id = $Row['COLUMN_NAME'];
	$Values = $Row['COLUMN_TYPE'];
	$OpenBrace = strpos ($Values, '(');
	$CloseBrace = strrpos ($Values, ')');
	if ($OpenBrace === FALSE || $CloseBrace < $OpenBrace)
	  continue;

	$Values = substr ($Values, $OpenBrace+1, $CloseBrace-$OpenBrace-1);
	$n = 0;
	for ($Token = strtok ($Values, "',"); $Token !== FALSE; $Token = strtok ("',"))
	  {
	    ++ $n;
	    $Context->FIELDS[$Id]["choice$n"] = $Token;
	  }
	$Context->FIELDS[$Id]['choices'] = $n;
      }
  }
}

?>
