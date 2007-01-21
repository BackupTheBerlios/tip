<?php

class tipRcbtContext
{
  /// @publicsection

  var $MODULE;
  var $SKIP;
  var $ON_CREATE;
  var $ON_DESTROY;
  var $ON_START;
  var $ON_STOP;
  var $ON_LOOP;
  var $START_TP;
  var $START_POS;


  function tipRcbtContext (&$Module)
  {
    $this->MODULE =& $Module;
    $this->SKIP = FALSE;
    $this->ON_CREATE =& new tipCallback;
    $this->ON_DESTROY =& new tipCallback;
    $this->ON_START =& new tipCallback;
    $this->ON_STOP =& new tipCallback;
    $this->ON_LOOP =& new tipCallback (FALSE);
    $this->START_TP = 0;
    $this->START_POS = 0;
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
    $this->START_TP = $Parser->TP;
    $this->START_POS = $Parser->POS;
    if (! $this->ON_CREATE->Go () || ! $this->ON_START->Go ())
      $this->SkipIf (TRUE);
  }

  function Stop (&$Parser)
  {
    if ($this->ON_LOOP->Go ())
      {
	$Parser->Push (new tipRcbtContext ($this->MODULE));
	$Parser->TP = $this->START_TP;
	$Parser->POS = $this->START_POS;
	return;
      }

    if ($this->ON_CREATE->RESULT)
      {
	if ($this->ON_START->RESULT)
	  $this->ON_STOP->Go ();
	$this->ON_DESTROY->Go ();
      }

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
    $NewContext->Start ($this);
    $Last = count ($this->CONTEXT_STACK);
    $this->CONTEXT_STACK[$Last] =& $this->CONTEXT;
    $this->CONTEXT =& $NewContext;
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
  /// @privatesection

  var $START;
  var $END;
  var $SUBTAG;
  var $SUBTAGS;
  var $MODULE_NAME;
  var $COMMAND;
  var $PARAMS;

  function& CreateContext (&$Parser, &$Module)
  {
    if ($Parser->CONTEXT->SKIP)
      {
	$DummyContext =& new tipRcbtContext ($Module);
	$DummyContext->SkipIf (TRUE);
	$Parser->Push ($DummyContext);
	$Context = FALSE;
      }
    else
      {
	$Context =& new tipRcbtContext ($Module);
      }
    return $Context;
  }


  /// @publicsection

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
      case 1:
	$this->MODULE_NAME = FALSE;
	if ($this->PARAMS === FALSE)
	  {
	    $this->COMMAND = 'html';
	    $this->PARAMS = $Token[0];
	  }
	elseif (empty ($Token[0]))
	  {
	    $this->COMMAND = 'tryhtml';
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
	  $Text = substr ($Text, 0, 17) . '...';
	$Parser->LogError ("malformed tag ($Text)");
	return FALSE;
      }

    return TRUE;
  }

  function RunTag (&$Parser)
  {
    if ($this->MODULE_NAME)
      $Module =& tipType::GetInstance ($this->MODULE_NAME);
    else
      $Module =& $Parser->CONTEXT->MODULE;

    if (! $this->COMMAND)
      {
	$Error = $Module->GetError ();
	if ($Error)
	  $Parser->LogWarning ($Error);

	if (! $Parser->Pop ())
	  $Parser->LogWarning ('too much {} tags');

	return TRUE;
      }

    switch ($this->COMMAND)
      {
      /**
       * \rcbtbuiltin <b>If(</b><i>conditions</i><b>)</b>\n
       * Executes the text enclosed by this tag and the <tt>{}</tt> tag
       * only if \c conditions is true. During the execution of this text, the
       * default module will be the current module.
       **/
      case 'if':
	if ($Context =& $this->CreateContext ($Parser, $Module))
	  {
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
	  }
	return TRUE;

      /**
       * \rcbtbuiltin <b>Else()</b>\n
       * Switches the skip condition of an enclosed text.
       **/
      case 'else':
	$Parser->CONTEXT->SkipIf (! $Parser->CONTEXT->SKIP);
	return TRUE;

      /**
       * \rcbtbuiltin <b>Query(</b><i>query</i><b>)</b>\n
       * Performs the specified \p query on the module and runs the text
       * enclosed by this tag and the <tt>{}</tt> tag with this query active.
       * This can be helpful, for instance, to show the summary fields of a
       * query. During the execution of this text, the default module will
       * be the current module. This means if you do not specify the query,
       * this tag simply change the default module.
       **/
      case 'query':
	if ($Context =& $this->CreateContext ($Parser, $Module))
	  {
	    if (! empty ($this->PARAMS))
	      {
		$Context->ON_CREATE->Set (array (&$Module, 'StartView'), array ($this->PARAMS));
		$Context->ON_START->Set (array (&$Module, 'ResetRow'));
		$Context->ON_STOP->Set (array (&$Module, 'EndView'));
	      }
	    $Parser->Push ($Context);
	  }
	return TRUE;

      /**
       * \rcbtbuiltin <b>QueryById(</b><i>id</i><b>)</b>\n
       * A shortcut to the \c query command performing a query on the primary
       * key content.
       **/
      case 'querybyid':
	if ($Context =& $this->CreateContext ($Parser, $Module))
	  {
	    $Query = $Module->DATA_ENGINE->QueryById ($this->PARAMS, $Module);
	    $Context->ON_CREATE->Set (array (&$Module, 'StartView'), array ($Query));
	    $Context->ON_START->Set (array (&$Module, 'ResetRow'));
	    $Context->ON_STOP->Set (array (&$Module, 'EndView'));
	    $Parser->Push ($Context);
	  }
	return TRUE;

      /**
       * \rcbtbuiltin <b>ForQuery(</b><i>query</i><b>)</b>\n
       * Performs the specified \c query on the module and, for every row, runs
       * the text enclosed by this tag and the <tt>{}</tt> tag. During the
       * execution of this text, the current module will be the default one.
       **/
      case 'forquery':
	if ($Context =& $this->CreateContext ($Parser, $Module))
	  {
	    $Context->ON_CREATE->Set (array (&$Module, 'StartView'), array ($this->PARAMS));
	    $Context->ON_START->Set (array (&$Module, 'ResetRow'));
	    $Context->ON_STOP->Set (array (&$Module, 'EndView'));
	    $Context->ON_LOOP->Set (array (&$Module, 'NextRow'));
	    $Parser->Push ($Context);
	  }
	return TRUE;

      /**
       * \rcbtbuiltin <b>ForEach(</b><i>list</i><b>)</b>\n
       * If you do not specify \p list, it is the same as \c forquery but
       * traverses the current query instead of a specific one.
       * If \p list is a number, the enclosed text is executed \p list times;
       * in the enclosed text, the special field \b CNT keeps track of the
       * current time counter.
       * In the other cases, a special query is performed by calling
       * tipModule::StartSpecialView() and using \p list as argument.
       * A common value is \b FIELD, that generates a special query that
       * browses the field structure, or \b MODULE, that traverses all the
       * installed modules of the site.
       **/
      case 'foreach':
	if ($Context =& $this->CreateContext ($Parser, $Module))
	  {
	    if (empty ($this->PARAMS))
	      {
		$Context->ON_START->Set (array (&$Module, 'ResetRow'));
		$Context->ON_LOOP->Set (array (&$Module, 'NextRow'));
	      }
	    elseif ($this->PARAMS > 0)
	      {
		$Context->ON_START->Set (create_function ('&$Module', '$Module->FIELDS[\'CNT\'] = 1; return TRUE;'), array (&$Module));
		$Context->ON_LOOP->Set (create_function ('&$Module,$n', 'return $n > $Module->FIELDS[\'CNT\'] ++;'), array (&$Module, (int)$this->PARAMS));
		$Context->ON_STOP->Set (create_function ('&$Module', 'unset ($Module->FIELDS[\'CNT\']); return TRUE;'), array (&$Module));
	      }
	    else
	      {
		$Context->ON_CREATE->Set (array (&$Module, 'StartSpecialView'), array ($this->PARAMS));
		$Context->ON_START->Set (array (&$Module, 'ResetRow'));
		$Context->ON_LOOP->Set (array (&$Module, 'NextRow'));
		$Context->ON_STOP->Set (array (&$Module, 'EndView'));
	      }
	    $Parser->Push ($Context);
	  }
	return TRUE;

      /**
       * \rcbtbuiltin <b>RecurseIf(</b><i>fieldid,value</i><b>)</b>\n
       * Inside a loop construct (such as \c ForQuery or \c ForEach), you can
       * recursively run the next rows that have the \p fieldid field equal to
       * \p value. Recursively means the text enclosed by the \c For... tag
       * is run for every matching row and the result is put in place of the
       * \c RecurseIf tag. This command is useful to generate hierarchies, such
       * as tree or catalogs. Notice you must have a query that generates a
       * properly sorted sequence of rows.
       **/
      case 'recurseif':
	if ($Parser->CONTEXT->SKIP)
	  return TRUE;

	$Pos = strpos ($this->PARAMS, ',');
	if ($Pos === FALSE)
	  {
	    $this->LogWarning ("malformed recurseif tag ($this->PARAMS)");
	    return TRUE;
	  }

	$Field = substr ($this->PARAMS, 0, $Pos);
	$Value = substr ($this->PARAMS, $Pos+1);
	if (! $Module->NextRow () || $Module->GetField ($Field) != $Value)
	  {
	    $Module->PrevRow ();
	    return TRUE;
	  }

	// Find the innermost loop
	$n = count ($Parser->CONTEXT_STACK);
	do
	  $LoopContext =& $Parser->CONTEXT_STACK[--$n];
	while ($LoopContext->ON_LOOP->IS_DEFAULT);

	$Context =& new tipRcbtContext ($Module);
	$Context->START_POS = $LoopContext->START_POS;
	$Context->START_TP = $LoopContext->START_TP;
	$Context->ON_LOOP->Set (create_function ('&$Module,$Field,$Value', 'return $Module->NextRow () && $Module->GetField ($Field) == $Value;'), array (&$Module, $Field, $Value));
	$Context->ON_STOP->Set (array (&$Module, 'PrevRow'));
	$Context->ON_DESTROY->Set (create_function ('&$Parser,$TP,$Pos', '$Parser->TP = $TP; $Parser->POS = $Pos; return TRUE;'), array (&$Parser, $Parser->TP, $Parser->POS));

	$Parser->TP = $Context->START_TP;
	$Parser->POS = $Context->START_POS;
	$Parser->Push ($Context);
	return TRUE;
      }

    if (! $Parser->CONTEXT->SKIP && ! $Module->CallCommand ($this->COMMAND, $this->PARAMS))
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
 * \attention The tags are identified by the curly braces, so the rcbt file
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
 * <tt>{Html(age)}</tt> a tag without \c module
 *
 * <tt>{Logout()}</tt> a tag without \c module and \c params
 *
 * <tt>{age}</tt> a special case: this will expand to <tt>{Html(age)}</tt>
 *
 * <tt>{(age)}</tt> another special case: this will expand to <tt>{TryHtml(age)}</tt>
 *
 * - <b>\c module</b> (case insensitive)\n
 *   Identifies the module to use while executing this tag. If not specified,
 *   the caller module will be used.
 * - <b>\c command</b> (case insensitive)\n
 *   Defines the comman to call. The available commands are module dependents:
 *   consult the module documentation to see which commands are available,
 *   particulary the tipModule::CallCommand() function. As mentioned above,
 *   if not specified the 'Html' command will be executed.
 * - <b>\c params</b> (case sensitive)\n
 *   The arguments to pass to the command. Obviously, what \c params means
 *   depend by the command called.\n
 *   The content of \c params will be recursively executed before the call:
 *   this means a \c params can contains itsself any sort of valid tags.
 *
 * The special empty tag <tt>{}</tt> is used to close the context opened by
 * some commands, such as ForQuery() of If() tags.
 *
 * See the \subpage RcbtBuiltins page to know which are the available commands.
 * If the tag is not built-in, a call to tipModule::RunCommand(), using the
 * proper module and passing the parsed command and params as arguments, is
 * performed. See the \subpage Commands page for details.
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
