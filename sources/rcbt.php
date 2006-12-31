<?php

/**
 * @private
 *
 * A context bookmark used by the tipRcbt source engine.
 **/
class tipRcbtContext extends tip
{
  /// @publicsection

  var $BUFFER;
  var $MODULE;
  var $IN_ERROR;
  var $PARSERPOS;
  var $TAGPOS;
  var $TAGMODULE;
  var $TAGCOMMAND;
  var $TAGPARAMS;
  var $DISCARD;
  var $LASTFORPOS;


  function tipRcbtContext (&$Buffer, &$Module)
  {
    $this->BUFFER    =& $Buffer;
    $this->MODULE    =& $Module;
    $this->IN_ERROR  =  FALSE;
    $this->PARSERPOS =  0;
    $this->TAGPOS    =  FALSE;
    $this->TAGMODULE =  FALSE;
    $this->TAGCOMMAND=  FALSE;
    $this->TAGPARAMS =  FALSE;
    $this->DISCARD   =  FALSE;
    $this->LASTFORPOS=  FALSE;
  }

  function GetTag ()
  {
    $this->TAGPOS = strpos ($this->BUFFER, '{', $this->PARSERPOS);

    // No tags found
    if ($this->TAGPOS === FALSE)
      return FALSE;

    $this->PARSERPOS = $this->TAGPOS;
    $ClosedBraces = 0;

    // Find the matching '}' brace
    do
      {
	$this->PARSERPOS = strpos ($this->BUFFER, '}', $this->PARSERPOS+1);

	$this->IN_ERROR = $this->PARSERPOS === FALSE;
	if ($this->IN_ERROR)
	  {
	    $Tag = substr ($this->BUFFER, $this->TAGPOS, 40);
	    tip::LogError ("unclosed tag on pos $this->TAGPOS (...$Tag...)");
	    return FALSE;
	  }

	++ $ClosedBraces;
	$Tag = substr ($this->BUFFER, $this->TAGPOS+1, $this->PARSERPOS-$this->TAGPOS-1);
      }
    while (substr_count ($Tag, '{') >= $ClosedBraces);

    ++ $this->PARSERPOS;
    return $Tag;
  }

  function ParseTag ($Tag)
  {
    // Remember: ParseTag error are not fatals and don't set the IN_ERROR flag
    if (! preg_match ('/(([\da-z_]*)\.)?([\da-z_]*)\s*(\((.*)\))?/iS', $Tag, $Result))
      {
	tip::LogWarning ("malformed tag on pos $this->TAGPOS (...$Tag...)");
	return FALSE;
      }

    if (! empty ($Result[2]))
      {
	$this->TAGMODULE =& tipType::GetInstance ($Result[2]);

	if ($this->TAGMODULE === FALSE)
	  {
	    tip::LogWarning ("module `$Result[2]' not found on pos $this->TAGPOS (...$Tag...)");
	    return FALSE;
	  }
      }

    if (! @is_null ($Result[5]))
      {
	$this->TAGCOMMAND = $Result[3];
	$this->TAGPARAMS = $Result[5];
      }
    else if ($Result[1] == '.')
      $this->TAGCOMMAND = $Result[3];
    else
      $this->TAGPARAMS = $Result[3];

    return TRUE;
  }
}



/**
 * Recursive Curly Brace Tags source engine.
 *
 * The format of an rcbt file is quite simple: it is a pure HTML file that can
 * contains some tags.
 *
 * @attention The tags are identified by the curly braces, so the rcbt file
 *            CANNOT USE curly braces for other reasons than specifying a tag.
 *
 * A rcbt tag has the following syntax:
 *
 * <b><tt>{[module.]command([params])}</tt></b>
 *
 * The square brakets identify an optional part. All the following tags are
 * valid tags:
 *
 * <tt>{Blog.Run(block.html)}</tt> a complete tag
 *
 * <tt>{User.Logout()}</tt> a tag without \c params
 *
 * <tt>{Field(age)}</tt> a tag without \c module
 *
 * <tt>{Logout()}</tt> a tag without \c module and \c params
 *
 * <tt>{age}</tt> a special case: this will expand to <tt>{Field(age)}</tt>
 *
 *
 * \li <b>\c module</b> (case insensitive)\n
 *     Identifies the module to use while executing this tag. If not specified,
 *     the caller module will be used.
 * \li <b>\c command</b> (case insensitive)\n
 *     Defines the comman to call. The available commands are module dependents:
 *     consult the module documentation to see which commands are available,
 *     particulary the tipModule::CallCommand() function. As mentioned above,
 *     if not specified the 'Field' operation will be executed.
 * \li <b>\c params</b> \n
 *     The arguments to pass to the command. Obviously, what \c params means
 *     depend by the command called.\n
 *     The content of \c params will be recursively executed before the call:
 *     this means a \c params can contains itsself any sort of valid tags.
 *
 * Some tags are specially managed by the rcbt engine:
 *
 * \li <b><tt>{[module.]query([query])}</tt></b>\n
 *     Performs the specified \c query on \c module and runs the buffer
 *     enclosed by this tag and the <tt>{}</tt> tag with this query actve.
 *     This can be helpful, for instance, to show the summary fields of a
 *     query. During the execution of this buffer, the default module will be
 *     \c module.
 * \li <b><tt>{[module.]querybyid(id)}</tt></b>\n
 *     A shortcut to the \c query command performing a query on the primary
 *     key content.
 * \li <b><tt>{[module.]forquery([query])}</tt></b>\n
 *     Performs the specified \c query on \c module and, for every row, runs
 *     the buffer enclosed by this tag and the <tt>{}</tt> tag. During the
 *     execution of this buffer, the default module will be \c module.
 * \li <b><tt>{[module.]foreach()}</tt></b>\n
 *     Same as forquery, but traverses the current query instead of a specific
 *     one.
 * \li <b><tt>{recurseif(field,value)}</tt></b>\n
 *     Inside a \b forquery or a \b foreach command, you can recursively run
 *     the next rows that have the \c fieldid field equal to \c value.
 *     Recursively means the buffer enclosed by the forquery tag is run for
 *     every matching row and the result is put in place of the \c recurseif
 *     tag. This command is useful to generate hierarchies, such as tree or
 *     catalogs. Notice you must have a query that generates a properly sorted
 *     sequence of rows.
 * \li <b><tt>{[module.]if([conditions])}</tt></b>\n
 *     Executes the buffer enclosed by this tag and the <tt>{}</tt> tag
 *     only if \c conditions is true. During the execution of this buffer, the
 *     default module will be \c module.
 * \li <b><tt>{}</tt></b>\n
 *     Special tag that specifies the end of an enclosed buffer.
 *
 * @todo The errors shows its position relative to the context, not the
 *       absolute position in the source file. Furthermore, in some situations
 *       I don't have access to the source file name. Consider creating an
 *       error context class.
 * @todo Needed code cleanup: the implementation is a bit confusing. Peraphs
 *	 the tipRcbtContext private class must be splitted in tipRcbtTag and
 *       tipRcbtFile (this could solve the missing information in the error
 *	 context).
 **/
class tipRcbt extends tipSource
{
  /// @protectedsection

  function RealRun (&$Buffer, &$Module)
  {
    return $this->EchoParsed (new tipRcbtContext ($Buffer, $Module));
  }


  /// @privatesection

  function EchoParsed (&$Context)
  {
    while (TRUE)
      {
	$RunPos = $Context->PARSERPOS;

	$Tag = $Context->GetTag ();
	if ($Tag === FALSE)
	  if ($Context->IN_ERROR)
	    return FALSE;
	  else
	    break;

	if (! $Context->DISCARD)
	  echo substr ($Context->BUFFER, $RunPos, $Context->TAGPOS - $RunPos);

	if (empty ($Tag))
	  return FALSE;

	$Context->TAGMODULE =& $Context->MODULE;
	$Context->TAGCOMMAND = 'Field';
	$Context->TAGPARAMS = FALSE;

	// ParseTag errors are not fatals
	if (! $Context->ParseTag ($Tag))
	  continue;

	// Also RunCommand errors are not fatals
	$this->RunCommand ($Context);

	// Instead, subparsing errors (set in RunCommand) are fatals
	if ($Context->IN_ERROR)
	  return FALSE;
      }

    if (! $Context->DISCARD)
      echo substr ($Context->BUFFER, $Context->PARSERPOS);

    return TRUE;
  }

  function GetParsed (&$Context, &$Destination)
  {
    ob_start ();
    $Result = $this->EchoParsed ($Context);
    $Destination = ob_get_clean ();
    return $Result;
  }

  function SkipParsed (&$Context)
  {
    $OldDiscard = $Context->DISCARD;
    $Context->DISCARD = TRUE;
    $Result = $this->EchoParsed ($Context);
    $Context->DISCARD = $OldDiscard;
    return $Result;
  }

  function RunCommand (&$Context)
  {
    if ($Context->DISCARD)
      $Params =& $Context->TAGPARAMS;
    elseif (! $this->GetParsed (new tipRcbtContext ($Context->TAGPARAMS, $Context->MODULE), $Params))
      return FALSE;
    
    $ParserPos = $Context->PARSERPOS;
    $Module =& $Context->TAGMODULE;
    $Command =& $Context->TAGCOMMAND;
    $UnclosedBody = FALSE;

    switch (strtolower ($Command))
      {
      case 'query':
	if ($Context->DISCARD || ! $Module->StartQuery ($Params))
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	    break;
	  }

	if ($Module->ResetRow ())
	  {
	    $OldModule =& $Context->MODULE;
	    $Context->MODULE =& $Module;
	    $UnclosedBody = $this->EchoParsed ($Context);
	    $Context->MODULE =& $OldModule;
	  }
	else
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	  }

	$Module->EndQuery ();
	break;

      case 'querybyid':
	if ($Context->DISCARD)
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	    break;
	  }

	$Query = $Module->DATA_ENGINE->QueryById ($Params, $Module);
	if (! $Module->StartQuery ($Query))
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	    break;
	  }

	if ($Module->ResetRow ())
	  {
	    $OldModule =& $Context->MODULE;
	    $Context->MODULE =& $Module;
	    $UnclosedBody = $this->EchoParsed ($Context);
	    $Context->MODULE =& $OldModule;
	  }
	else
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	  }

	$Module->EndQuery ();
	break;

      case 'forquery':
	if ($Context->DISCARD || ! $Module->StartQuery ($Params))
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	    break;
	  }

	if ($Module->RowsCount () > 0)
	  {
	    $OldModule =& $Context->MODULE;
	    $Context->MODULE =& $Module;
	    $Context->LASTFORPOS = $ParserPos;
	    while ($Module->NextRow () && ! $UnclosedBody)
	      {
		$Context->PARSERPOS = $ParserPos;
		$UnclosedBody = $this->EchoParsed ($Context);
	      }

	    $Context->MODULE =& $OldModule;
	    $Context->LASTFORPOS = FALSE;
	  }
	else
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	  }

	$Module->EndQuery ();
	break;

      case 'foreach':
	if ($Context->DISCARD || ! $Module->RowsCount ())
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	    break;
	  }

	$OldModule =& $Context->MODULE;
	$Context->MODULE =& $Module;
	$Context->LASTFORPOS = $ParserPos;

	$Module->UnsetRow ();
	while ($Module->NextRow () && ! $UnclosedBody)
	  {
	    $Context->PARSERPOS = $ParserPos;
	    $UnclosedBody = $this->EchoParsed ($Context);
	  }

	$Context->MODULE =& $OldModule;
	$Context->LASTFORPOS = FALSE;
	break;

      case 'recurseif':
	if ($Context->DISCARD)
	  break;

	$Pos = strpos ($Params, ',');
	if ($Pos === FALSE)
	  {
	    $this->LogWarning ("malformed \{$Command($Context->TAGPARAMS)} command on pos $ParserPos");
	    return FALSE;
	  }

	$Field = substr ($Params, 0, $Pos);
	$Value = substr ($Params, $Pos+1);
	$OldParserPos = $ParserPos;

	while ($Module->NextRow () && $Module->GetField ($Field) == $Value)
	  {
	    $Context->PARSERPOS = $Context->LASTFORPOS;
	    $this->EchoParsed ($Context);
	  }

	$Context->PARSERPOS = $OldParserPos;
	$Module->PrevRow ();
	break;

      case 'if':
	if ($Context->DISCARD)
	  {
	    $UnclosedBody = $this->SkipParsed ($Context);
	    break;
	  }

	$Evaluate = create_function ('', "return $Params;");
	if ($Evaluate ())
	  $UnclosedBody = $this->EchoParsed ($Context);
	else
	  $UnclosedBody = $this->SkipParsed ($Context);
	break;

      default:
	if (! $Context->DISCARD)
	  $Module->CallCommand ($Command, $Params);
	break;
      }

    // Unclosed bodies are fatals (IN_ERROR flag set)
    if ($UnclosedBody)
      {
	$Context->IN_ERROR = TRUE;
	$this->LogError ("unclosed \{$Command($Context->TAGPARAMS)} body on pos $ParserPos");
	return FALSE;
      }

    // Module errors are not fatals
    $ModuleError = $Module->GetError ();
    if ($ModuleError)
      {
	$this->LogWarning ("\{$Command($Context->TAGPARAMS)} on pos $Context->TAGPOS reports: $ModuleError");
	return FALSE;
      }

    return TRUE;
  }
}

?>
