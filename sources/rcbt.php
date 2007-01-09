<?php

class tipRcbtContext
{
  /// @publicsection

  var $MODULE;
  var $SKIP;
  var $START_CALLBACK;
  var $STOP_CALLBACK;
  var $LOOP_CALLBACK;
  var $START_TP;
  var $START_POS;
  var $PRIVATE;



  function tipRcbtContext (&$Module)
  {
    $this->MODULE =& $Module;
    $this->SKIP = FALSE;
    $this->START_CALLBACK = NULL;
    $this->STOP_CALLBACK = NULL;
    $this->LOOP_CALLBACK = NULL;
    $this->START_TP = 0;
    $this->START_POS = 0;
    $this->PRIVATE = NULL;
  }

  function SkipIf ($Skip)
  {
    if ($this->SKIP == $Skip)
      return;
    if ($Skip)
      ob_start ();
    else
      ob_end_clean ();
    $this->SKIP = $Skip;
  }

  function Start (&$Parser)
  {
    if (! is_null ($this->START_CALLBACK) && ! call_user_func ($this->START_CALLBACK, $this))
      {
	if (! is_null ($this->STOP_CALLBACK))
	  call_user_func_array ($this->STOP_CALLBACK, array (&$this, &$Parser));
	return FALSE;
      }

    $this->START_TP = $Parser->TP;
    $this->START_POS = $Parser->POS;
    return TRUE;
  }

  function Stop (&$Parser)
  {
    if (! is_null ($this->LOOP_CALLBACK))
      if (call_user_func_array ($this->LOOP_CALLBACK, array (&$this, &$Parser)))
	{
	  $Parser->Push (new tipRcbtContext ($this->MODULE));
	  $Parser->TP = $this->START_TP;
	  $Parser->POS = $this->START_POS;
	  return;
	}

    if (! is_null ($this->STOP_CALLBACK))
      call_user_func_array ($this->STOP_CALLBACK, array (&$this, &$Parser));

    $this->SkipIf (FALSE);
  }
}


class tipRcbtParser
{
  /// @privatesection

  var $PRE_MESSAGE;
  var $CONTEXT_STACK;
  var $TP_STACK;


  function BuildMessage ($Message)
  {
    $Line = substr_count (substr ($this->BUFFER, 0, $this->POS), "\n") + 1;
    return "$this->PRE_MESSAGE: $Message on line $Line";
  }


  /// @publicsection

  var $BUFFER;
  var $NESTED_TEXT;
  var $CONTEXT;
  var $POS;
  var $TP;


  function tipRcbtParser (&$Buffer, &$Module, $PreMessage)
  {
    $this->PRE_MESSAGE = $PreMessage;
    $this->CONTEXT_STACK = array ();
    $this->TP_STACK = array ();
    $this->BUFFER =& $Buffer;
    $this->NESTED_TEXT = FALSE;
    $this->POS = 0;
    $this->TP = 0;
    $this->CONTEXT =& new tipRcbtContext ($Module);
  }

  function Reset ()
  {
    if (count ($this->CONTEXT_STACK) > 0)
      {
	$this->POS = $this->CONTEXT->START_POS;
	$this->LogError ('unclosed context');
	return FALSE;
      }

    $this->POS = 0;
    $this->TP = 0;
    return TRUE;
  }

  function Nest ()
  {
    array_push ($this->TP_STACK, $this->TP);
    $this->TP = 0;

    if (count ($this->TP_STACK) > 1)
      ob_start ();
  }

  function Unnest ()
  {
    if (count ($this->TP_STACK) > 1)
      $this->NESTED_TEXT = ob_get_clean ();

    $this->TP = array_pop ($this->TP_STACK);
  }

  function Push (&$NewContext)
  {
    if (! $NewContext->Start ($this))
      return FALSE;

    $Last = count ($this->CONTEXT_STACK);
    $this->CONTEXT_STACK[$Last] =& $this->CONTEXT;
    $this->CONTEXT =& $NewContext;
    return TRUE;
  }

  function Pop ()
  {
    if (count ($this->CONTEXT_STACK) < 1)
      return FALSE;

    $this->CONTEXT->Stop ($this);
    $Last = count ($this->CONTEXT_STACK) - 1;
    $this->CONTEXT =& $this->CONTEXT_STACK[$Last];
    unset ($this->CONTEXT_STACK[$Last]);
    return TRUE;
  }

  function BeginParse (&$Tag)
  {
    echo substr ($this->BUFFER, $this->POS, $Tag->START - $this->POS);
    $this->POS = $Tag->START;
    if (count ($this->TP_STACK) > 0)
      ++ $this->POS;
    return TRUE;
  }

  function EndParse (&$Tag)
  {
    if ($Tag->END === FALSE)
      {
	echo substr ($this->BUFFER, $this->POS);
	$this->POS = FALSE;
	return TRUE;
      }

    $Text = $this->NESTED_TEXT . substr ($this->BUFFER, $this->POS, $Tag->END - $this->POS);
    $this->NESTED_TEXT = FALSE;
    $this->POS = $Tag->END+1;
    return $Tag->ExplodeTag ($this, $Text) && $Tag->RunTag ($this);
  }

  function LogWarning ($Message)
  {
    tip::LogWarning ($this->BuildMessage ($Message));
  }

  function LogError ($Message)
  {
    tip::LogError ($this->BuildMessage ($Message));
  }
}

class tipRcbtTag
{
  var $START;
  var $END;
  var $SUBTAG;
  var $SUBTAGS;
  var $MODULE_NAME;
  var $COMMAND;
  var $PARAMS;

  function tipRcbtTag ()
  {
    $this->START = FALSE;
    $this->END = FALSE;
    $this->SUBTAG = array ();
    $this->SUBTAGS = 0;
    $this->MODULE_NAME = FALSE;
    $this->COMMAND = FALSE;
    $this->PARAMS = FALSE;
  }

  function BuildTag (&$Parser, $UnclosedTag = FALSE)
  {
    $this->START = $Parser->POS;
    if (! $UnclosedTag)
      ++ $Parser->POS;

    for (;;)
      {
	$OpenBrace = strpos ($Parser->BUFFER, '{', $Parser->POS);
	$CloseBrace = strpos ($Parser->BUFFER, '}', $Parser->POS);
	if ($CloseBrace === FALSE)
	  {
	    if ($OpenBrace !== FALSE)
	      $Parser->POS = $OpenBrace;
	    elseif ($UnclosedTag)
	      return TRUE;

	    $Parser->LogError ('unclosed tag');
	    return FALSE;
	  }

	if ($OpenBrace === FALSE || $OpenBrace > $CloseBrace)
	  break;

	$Parser->POS = $OpenBrace;
	$Subtag =& new tipRcbtTag;
	if (! $Subtag->BuildTag ($Parser))
	    return FALSE;

	$this->SUBTAG[$this->SUBTAGS] =& $Subtag;
	++ $this->SUBTAGS;
      }

    $this->END = $CloseBrace;
    $Parser->POS = $CloseBrace+1;
    return TRUE;
  }

  function RecurseTag (&$Parser)
  {
    if (! $Parser->BeginParse ($this))
      return FALSE;

    if ($this->SUBTAGS > 0)
      {
	$Parser->Nest ();
	while ($Parser->TP < $this->SUBTAGS)
	  {
	    if (! $this->SUBTAG[$Parser->TP]->RecurseTag ($Parser))
	      return FALSE;
	    ++ $Parser->TP;
	  }
	$Parser->Unnest ();
      }

    return $Parser->EndParse ($this);
  }

  function ExplodeTag (&$Parser, &$Text)
  {
    if ($this->END == $this->START+1)
      return TRUE;

    $OpenBrace = strpos ($Text, '(');
    if ($OpenBrace === FALSE)
      {
	$this->PARAMS = FALSE;
      }
    else
      {
	$ParamsPos = $OpenBrace+1;
	$CloseBrace = strrpos ($Text, ')');
	if ($CloseBrace === FALSE)
	  {
	    $Parser->LogError ('unclosed parameter');
	    return FALSE;
	  }

	$this->PARAMS = substr ($Text, $ParamsPos, $CloseBrace-$ParamsPos);
	if (! $this->PARAMS)
	  $this->PARAMS = '';

	$Text = substr ($Text, 0, $ParamsPos-1);
      }

    $Token = explode ('.', trim ($Text));
    switch (count ($Token))
      {
      case 0:
	$this->MODULE_NAME = FALSE;
	$this->COMMAND = FALSE;
	break;
      case 1:
	$this->MODULE_NAME = FALSE;
	if ($this->PARAMS === FALSE)
	  {
	    $this->COMMAND = 'field';
	    $this->PARAMS = $Token[0];
	  }
	else
	  {
	    $this->COMMAND = strtolower ($Token[0]);
	  }
	break;
      case 2:
	$this->MODULE_NAME = $Token[0];
	$this->COMMAND = strtolower ($Token[1]);
	break;
      default:
	if (strlen ($Text) > 20)
	  $Text = substr ($Text, 0, 15) . '...';
	$Parser->LogError ("malformed tag ($Text)");
	return FALSE;
      }

    return TRUE;
  }

  function RunTag (&$Parser)
  {
    if (! $this->COMMAND)
      {
	if (! $Parser->Pop ())
	  $Parser->LogWarning ('too much {} tags');

	return TRUE;
      }

    if ($this->MODULE_NAME)
      $Module =& tipType::GetInstance ($this->MODULE_NAME);
    else
      $Module =& $Parser->CONTEXT->MODULE;

    switch ($this->COMMAND)
      {
      case 'if':
	$Context =& new tipRcbtContext ($Module);
	$Condition = @create_function ('', "return $this->PARAMS;");
	if (empty ($Condition))
	  {
	    $Parser->LogWarning ("invalid condition ($this->PARAMS)");
	    $Context->SkipIf (TRUE);
	  }
	else
	  {
	    $Context->SkipIf (! $Condition ());
	  }
	$Parser->Push ($Context);
	return TRUE;

      case 'else':
	if (empty ($this->PARAMS))
	  {
	    $Parser->CONTEXT->SkipIf (! $Parser->CONTEXT->SKIP);
	  }
	elseif ($Parser->CONTEXT->SKIP)
	  {
	    $Condition = @create_function ('', "return $this->PARAMS;");
	    if (empty ($Condition))
	      $Parser->LogWarning ("invalid condition ($this->PARAMS)");
	    else
	      $Parser->CONTEXT->SkipIf (! $Condition ());
	  }
	else
	  {
	    $Parser->CONTEXT->SkipIf (TRUE);
	  }
	return TRUE;

      case 'query':
	$Context =& new tipRcbtContext ($Module);
	if ($Module->StartQuery ($this->PARAMS))
	  {
	    $Context->STOP_CALLBACK = array (&$Module, 'EndQuery');
	    $Context->SkipIf (! $Module->ResetRow ());
	  }
	else
	  {
	    $Context->SkipIf (TRUE);
	  }
	$Parser->Push ($Context);
	return TRUE;

      case 'querybyid':
	$Context =& new tipRcbtContext ($Module);
	$Query = $Module->DATA_ENGINE->QueryById ($this->PARAMS, $Module);
	if ($Module->StartQuery ($Query))
	  {
	    $Context->STOP_CALLBACK = array (&$Module, 'EndQuery');
	    $Context->SkipIf (! $Module->ResetRow ());
	  }
	else
	  {
	    $Context->SkipIf (TRUE);
	  }
	$Parser->Push ($Context);
	return TRUE;

      case 'forquery':
	$Context =& new tipRcbtContext ($Module);
	if ($Module->StartQuery ($this->PARAMS))
	  {
	    $Context->STOP_CALLBACK = array (&$Module, 'EndQuery');
	    if (! $Module->ResetRow ())
	      $Context->SkipIf (TRUE);
	    else
	      $Context->LOOP_CALLBACK = array (&$Module, 'NextRow');
	  }
	else
	  {
	    $Context->SkipIf (TRUE);
	  }
	$Parser->Push ($Context);
	return TRUE;

      case 'foreach':
	$Context =& new tipRcbtContext ($Module);
	if ($Module->ResetRow ())
	  $Context->LOOP_CALLBACK = array (&$Module, 'NextRow');
	else
	  $Context->SkipIf (TRUE);
	$Parser->Push ($Context);
	return TRUE;

      case 'recurseif':
	if ($Parser->CONTEXT->SKIP)
	  return TRUE;

	$Pos = strpos ($this->PARAMS, ',');
	if ($Pos === FALSE)
	  {
	    $this->LogWarning ('malformed recurseif tag');
	    return TRUE;
	  }

	// Find the innermost loop
	$n = count ($Parser->CONTEXT_STACK);
	do
	  $Context = $Parser->CONTEXT_STACK[--$n];
	while (is_null ($Context->LOOP_CALLBACK));

	$Field = substr ($this->PARAMS, 0, $Pos);
	$Value = substr ($this->PARAMS, $Pos+1);

	$Context->PRIVATE['field'] = $Field;
	$Context->PRIVATE['value'] = $Value;
	$Context->PRIVATE['tp'] = $Parser->TP;
	$Context->PRIVATE['pos'] = $Parser->POS;

	$ModuleRef = '$Context->MODULE';
	$FieldRef = '$Context->PRIVATE[\'field\']';
	$ValueRef = '$Context->PRIVATE[\'value\']';
	$Condition = create_function ('&$Context', "return ${ModuleRef}->NextRow () && ${ModuleRef}->GetField ($FieldRef) == $ValueRef;");
	$Context->START_CALLBACK =& $Condition;
	$Context->LOOP_CALLBACK =& $Condition;

	$Context->STOP_CALLBACK = create_function ('&$Context,&$Parser', '$Parser->TP = $Context->PRIVATE["tp"]; $Parser->POS = $Context->PRIVATE["pos"]; return $Context->MODULE->PrevRow ();');

	$Parser->TP = $Context->START_TP;
	$Parser->POS = $Context->START_POS;
	$Parser->Push ($Context);
	return TRUE;
      }

    if ($Parser->CONTEXT->SKIP)
      return TRUE;

    if (! $Module->CallCommand ($this->COMMAND, $this->PARAMS))
      {
	$Parser->LogWarning ($Module->GetError ());
	$Module->ResetError ();
      }

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
 * \li <b><tt>{[module.]else([conditions])}</tt></b>\n
 *     Changes the skip condition of an enclosed buffer to \c conditions. If
 *     \c conditions is not specified, reverts the previous skip condition.
 * \li <b><tt>{}</tt></b>\n
 *     Special tag that specifies the end of an enclosed buffer.
 **/
class tipRcbt extends tipSource
{
  /// @protectedsection

  function RealRun (&$Buffer, &$Module, $PreMessage)
  {
    $Parser =& new tipRcbtParser ($Buffer, $Module, $PreMessage);
    $Source =& new tipRcbtTag;

    return $Source->BuildTag ($Parser, TRUE)
        && $Parser->Reset ()
        && $Source->RecurseTag ($Parser);
  }
}

?>
