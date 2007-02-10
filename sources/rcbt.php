<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * TIP_Rcbt definition file
 * @package TIP
 **/

class TIP_Rcbt_Context
{
    /**#@+ @access public */

    var $module = null;
    var $skip = false;
    var $start_tp = 0;
    var $start_pos = 0;
    var $on_create = null;
    var $on_destroy = null;
    var $on_start = null;
    var $on_stop = null;
    var $on_loop = null;

    function TIP_Rcbt_Context(&$module)
    {
        $this->module =& $module;
        $this->on_create =& new TIP_Callback;
        $this->on_destroy =& new TIP_Callback;
        $this->on_start =& new TIP_Callback;
        $this->on_stop =& new TIP_Callback;
        $this->on_loop =& new TIP_Callback(false);
    }

    function skipIf($skip)
    {
        if ($this->skip != $skip) {
            if ($skip) {
                ob_start();
            } else {
                ob_end_clean();
            }
            $this->skip = $skip;
        }
    }

    function start(&$parser)
    {
        $this->start_tp = $parser->tp;
        $this->start_pos = $parser->pos;
        if (! $this->on_create->go() || ! $this->on_start->go()) {
            $this->skipIf(true);
        }
    }

    function stop(&$parser)
    {
        if ($this->on_loop->go()) {
            $parser->push(new TIP_Rcbt_Context($this->module));
            $parser->tp = $this->start_tp;
            $parser->pos = $this->start_pos;
            return;
        }

        if ($this->on_create->result) {
            if ($this->on_start->result) {
                $this->on_stop->go();
            }
            $this->on_destroy->go();
        }

        $this->skipIf(false);
    }

    /**#@-*/
}


class TIP_Rcbt_Parser
{
    /**#@+ @access private */

    var $_context_message = 'undefined sources';
    var $_context_stack = array();
    var $_tp_stack = array();


    function _buildMessage($message)
    {
        $line = substr_count(substr($this->buffer, 0, $this->pos), "\n") + 1;
        return "$this->_context_message: $message on line $line";
    }

    /**#@-*/


    /**#@+ @access public */

    var $buffer = null;
    var $context = null;
    var $nested_text = false;
    var $pos = 0;
    var $tp = 0;

    function TIP_Rcbt_Parser(&$buffer, &$module, $context_message)
    {
        $this->buffer =& $buffer;
        $this->context =& new TIP_Rcbt_Context($module);

        if ($context_message) {
            $this->_context_message = $context_message;
        }
    }

    function reset()
    {
        if (count($this->_context_stack) > 0) {
            $this->pos = $this->context->start_pos;
            $this->logError('unclosed context');
            return false;
        }

        $this->pos = 0;
        $this->tp = 0;
        return true;
    }

    function nest()
    {
        array_push($this->_tp_stack, $this->tp);
        $this->tp = 0;

        if (count($this->_tp_stack) > 1) {
            ob_start();
        }
    }

    function unnest()
    {
        if (count($this->_tp_stack) > 1) {
            $this->nested_text = ob_get_clean();
        }

        $this->tp = array_pop($this->_tp_stack);
    }


    function push(&$context)
    {
        $context->start($this);
        $this->_context_stack[count($this->_context_stack)] =& $this->context;
        $this->context =& $context;
    }

    function pop()
    {
        if (count($this->_context_stack) < 1) {
            return false;
        }

        // Beware: the stop call can push a new context in the stack
        $this->context->stop($this);
        $last = count($this->_context_stack)-1;
        $this->context =& $this->_context_stack[$last];
        unset($this->_context_stack[$last]);
        return true;
    }

    function beginParse(&$tag)
    {
        echo substr($this->buffer, $this->pos, $tag->start-$this->pos);
        $this->pos = $tag->start;
        if (count($this->_tp_stack) > 0) {
            ++ $this->pos;
        }
    }

    function endParse(&$tag)
    {
        if ($tag->end === false) {
            echo substr($this->buffer, $this->pos);
            $this->pos = false;
            return true;
        }

        $text = $this->nested_text . substr($this->buffer, $this->pos, $tag->end-$this->pos);
        $this->nested_text = false;
        $this->pos = $tag->end+1;
        return $tag->explodeTag($this, $text) && $tag->runTag($this);
    }

    function logWarning($message)
    {
        TIP::logWarning($this->_buildMessage($message));
    }

    function logError($message)
    {
        TIP::logError($this->_buildMessage($message));
    }

    /**#@-*/
}

class TIP_Rcbt_Tag
{
    /// @privatesection

    var $start = false;
    var $end = false;
    var $subtag = array();
    var $subtags = 0;
    var $module_name = null;
    var $command = null;
    var $params = null;

    function& createContext(&$parser, &$module)
    {
        if ($parser->context->skip) {
            $dummy_context =& new TIP_Rcbt_Context($module);
            $dummy_context->skipIf(true);
            $parser->push($dummy_context);
            $result = false;
        } else {
            $result =& new TIP_Rcbt_Context($module);
        }
        return $result;
    }


    /// @publicsection

    function buildTag(&$parser, $unclosed_tag = false)
    {
        $this->start = $parser->pos;
        if (! $unclosed_tag)
            ++ $parser->pos;

        for (;;) {
            $open_brace = strpos ($parser->buffer, '{', $parser->pos);
            $close_brace = strpos ($parser->buffer, '}', $parser->pos);
            if ($close_brace === false) {
                if ($open_brace !== false) {
                    $parser->pos = $open_brace;
                } elseif ($unclosed_tag) {
                    return true;
                }

                $parser->logError('unclosed tag');
                return false;
            }

            if ($open_brace === false || $open_brace > $close_brace) {
                break;
            }

            $parser->pos = $open_brace;
            $Subtag =& new TIP_Rcbt_Tag;
            if (! $Subtag->buildTag($parser))
                return false;

            $this->subtag[$this->subtags] =& $Subtag;
            ++ $this->subtags;
        }

        $this->end = $close_brace;
        $parser->pos = $close_brace+1;
        return true;
    }

    function recurseTag(&$parser)
    {
        $parser->beginParse($this);

        if ($this->subtags > 0) {
            $parser->nest();
            while ($parser->tp < $this->subtags) {
                if (! $this->subtag[$parser->tp]->recurseTag($parser)) {
                    return false;
                }
                ++ $parser->tp;
            }
            $parser->unnest();
        }

        return $parser->endParse($this);
    }

    function explodeTag(&$parser, &$text)
    {
        if ($this->end == $this->start+1) {
            return true;
        }

        $open_brace = strpos ($text, '(');
        if ($open_brace === false) {
            $this->params = false;
        } else {
            $params_pos = $open_brace+1;
            $close_brace = strrpos ($text, ')');
            if ($close_brace === false) {
                $parser->logError ('unclosed parameter');
                return false;
            }

            $this->params = substr ($text, $params_pos, $close_brace-$params_pos);
            if (! $this->params)
                $this->params = '';

            $text = substr ($text, 0, $params_pos-1);
        }

        $token = explode ('.', trim ($text));
        switch (count ($token))
        {
        case 1:
            $this->module_name = null;
            if ($this->params === false) {
                $this->command = 'html';
                $this->params = $token[0];
            } elseif (empty ($token[0])) {
                $this->command = 'tryhtml';
            } else {
                $this->command = strtolower($token[0]);
            }
            break;
        case 2:
            $this->module_name = $token[0];
            $this->command = strtolower ($token[1]);
            break;
        default:
            if (strlen ($text) > 20)
                $text = substr ($text, 0, 17) . '...';
            $parser->logError ("malformed tag ($text)");
            return false;
        }

        return true;
    }

    function runTag(&$parser)
    {
        if ($this->module_name) {
            $module =& TIP_Module::getInstance($this->module_name);
        } else {
            $module =& $parser->context->module;
        }

        if (! $this->command) {
            $error = $module->resetError();
            if ($error) {
                $parser->logWarning($error);
            }

            if (! $parser->pop()) {
                $parser->logWarning('too much {} tags');
            }

            return true;
        }

        switch ($this->command) {
            /**
             * \rcbtbuiltin <b>If(</b><i>conditions</i><b>)</b>\n
             * Executes the text enclosed by this tag and the <tt>{}</tt> tag
             * only if \c conditions is true. During the execution of this text, the
             * default module will be the current module.
             **/
        case 'if':
            if ($context =& $this->createContext($parser, $module)) {
                $condition = @create_function('', "return $this->params;");
                if ($condition) {
                    $context->skipIf(! $condition());
                } else {
                    $parser->logWarning("invalid condition ($this->params)");
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

            /**
             * \rcbtbuiltin <b>Else()</b>\n
             * Switches the skip condition of an enclosed text.
             **/
        case 'else':
            $parser->context->skipIf(! $parser->context->skip);
            return true;

            /**
             * \rcbtbuiltin <b>select([filter])</b>\n
             * Performs the specified select on the module and runs the text
             * enclosed by this tag and the <tt>{}</tt> tag with this select active.
             * This can be helpful, for instance, to show the summary fields of a
             * select. During the execution of this text, the default module will
             * be the current module. This means if you do not specify the select,
             * this tag simply change the default module.
             **/
        case 'select':
            if ($context =& $this->createContext($parser, $module)) {
                $view =& $module->startView($this->params);
                if ($view) {
                    $context->on_start->set(array(&$view, 'rowReset'));
                    $context->on_destroy->set(array(&$module, 'endView'));
                } else {
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

            /**
             * \rcbtbuiltin <b>selectRow(</b><i>id</i><b>)</b>\n
             * A shortcut to the \c select command performing a select on the primary
             * key content.
             **/
        case 'selectrow':
            if ($context =& $this->createContext($parser, $module)) {
                $filter = $module->data->rowFilter($this->params);
                $view =& $module->startView($filter);
                if ($view) {
                    $context->on_start->set(array(&$view, 'rowReset'));
                    $context->on_destroy->set(array(&$module, 'endView'));
                } else {
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

            /**
             * \rcbtbuiltin <b>forSelect([filter])</b>\n
             * Performs the specified filter on the module and, for every row, runs
             * the text enclosed by this tag and the <tt>{}</tt> tag. During the
             * execution of this text, the current module will be the default one.
             **/
        case 'forselect':
            if ($context =& $this->createContext($parser, $module)) {
                $view =& $module->startView($this->params);
                if ($view) {
                    $context->on_start->set(array(&$view, 'rowReset'));
                    $context->on_loop->set(array(&$view, 'rowNext'));
                    $context->on_destroy->set(array(&$module, 'endView'));
                } else {
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

            /**
             * \rcbtbuiltin <b>forEach([list])</b>\n
             * If you do not specify \p list, it is the same as forselect but
             * traverses the current view instead of building a new one.
             * If \p list is a number, the enclosed text is executed \p list times;
             * in the enclosed text, the special field \b CNT keeps track of the
             * current time counter.
             * In the other cases, a special view is created by calling
             * TIP_Module::startSpecialView() and using \p list as argument.
             * A common value is FIELDS, that generates a special view that
             * browses the field structure, or MODULES, that traverses all the
             * installed modules of the site.
             **/
        case 'foreach':
            if ($context =& $this->createContext($parser, $module)) {
                if (empty ($this->params)) {
                    $view =& $module->view;
                    if ($view) {
                        $context->on_start->set(array(&$view, 'rowReset'));
                        $context->on_loop->set(array(&$view, 'rowNext'));
                    } else {
                        $this->logWarning('no current views');
                        $context->skipIf(true);
                    }
                } elseif ($this->params > 0) {
                    $context->on_start->set(create_function('&$module', '$module->keys[\'CNT\'] = 1; return true;'), array(&$module));
                    $context->on_loop->set(create_function('&$module,$n', 'return $n > $module->keys[\'CNT\'] ++;'), array(&$module, (int)$this->params));
                    $context->on_stop->set(create_function('&$module', 'unset($module->keys[\'CNT\']); return true;'), array(&$module));
                } else {
                    $view =& $module->startSpecialView($this->params);
                    if (! is_object($view)) {
                        $context->on_start->set(array(&$view, 'rowReset'));
                        $context->on_loop->set(array(&$view, 'rowNext'));
                        $context->on_destroy->set(array(&$module, 'endView'));
                    } else {
                        $context->skipIf(true);
                    }
                }
                $parser->push($context);
            }
            return true;
        }

        if (! $parser->context->skip) {
            $module->callCommand($this->command, $this->params);
            $error = $module->resetError();
            if ($error) {
                $parser->logWarning($error);
            }
        }

        return true;
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
 * <code>{[module.]command([params])}</code>
 *
 * The square brakets identify an optional part. All the following tags are
 * valid tags:
 *
 * <code>{Blog.run(block.html)}</code> a complete tag
 *
 * <code>{User.logout()}</code> a tag without \c params
 *
 * <code>{Html(age)}</code> a tag without \c module
 *
 * <code>{logout()}</code> a tag without \c module and \c params
 *
 * <code>{age}</code> a special case: this will expand to <code>{html(age)}</code>
 *
 * <code>{(age)}</code> another special case: this will expand to <code>{tryHtml(age)}</code>
 *
 * - <b>\c module</b> (case insensitive)\n
 *   Identifies the module to use while executing this tag. If not specified,
 *   the caller module will be used.
 * - <b>\c command</b> (case insensitive)\n
 *   Defines the comman to call. The available commands are module dependents:
 *   consult the module documentation to see which commands are available,
 *   particulary the TIP_Module::callCommand() function. As mentioned above,
 *   if not specified the 'Html' command will be executed.
 * - <b>\c params</b> (case sensitive)\n
 *   The arguments to pass to the command. Obviously, what \c params means
 *   depend by the command called.\n
 *   The content of \c params will be recursively executed before the call:
 *   this means a \c params can contains itsself any sort of valid tags.
 *
 * The special empty tag <tt>{}</tt> is used to close the context opened by
 * some commands, such as forSelect() of If() tags.
 *
 * See the \subpage RcbtBuiltins page to know which are the available commands.
 * If the tag is not built-in, a call to TIP_Module::command...(), using the
 * proper module and params as arguments, is performed.
 *
 * @package TIP
 * @final
 **/
class TIP_Rcbt extends TIP_Source_Engine
{
    function run(&$buffer, &$module, $context_message)
    {
        $parser =& new TIP_Rcbt_Parser($buffer, $module, $context_message);
        $source =& new TIP_Rcbt_Tag;
        return $source->buildTag($parser, true) && $parser->reset() && $source->recurseTag($parser);
    }
}

return new TIP_Rcbt;

?>
