<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Rcbt definition file
 * @package TIP
 * @subpackage SourceEngine
 */

/**
 * A context used by the Rcbt engine
 *
 * @package TIP
 * @subpackage SourceEngine
 */
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

    function __construct(&$module)
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

/**
 * The parser used by the Rcbt engine
 *
 * @package TIP
 * @subpackage SourceEngine
 */
class TIP_Rcbt_Parser
{
    /**#@+ @access private */

    var $_main_tag = null;
    var $_context_stack = array();
    var $_tp_stack = array();


    function _getLine()
    {
        return substr_count(substr($this->_buffer, 0, $this->pos), "\n") + 1;
    }

    /**#@-*/


    /**#@+ @access protected */

    function warning($text)
    {
        TIP::warning($text . ' on line ' . $this->_getLine());
    }

    function error($text)
    {
        $this->error = $text . ' on line ' . $this->_getLine();
    }

    /**#@-*/


    /**#@+ @access public */

    var $buffer = null;
    var $context = null;
    var $error = 'undefined error';
    var $nested_text = false;
    var $pos = 0;
    var $tp = 0;


    function __construct(&$buffer)
    {
        $this->buffer =& $buffer;
    }

    function parse()
    {
        $this->_main_tag =& new TIP_Rcbt_Tag;
        if (!$this->_main_tag->buildTag($this, true)) {
            $this->_main_tag = false;
            return false;
        }

        return true;
    }

    function run(&$module)
    {
        if (!$this->_main_tag) {
            return $this->error;
        }

        $this->context =& new TIP_Rcbt_Context($module);
        if (!$this->_main_tag->recurseTag($this) || !$this->reset()) {
            $this->_main_tag = false;
            return $this->error;
        }

        return true;
    }

    function reset()
    {
        if (count($this->_context_stack) > 0) {
            $this->pos = $this->context->start_pos;
            $this->error('unclosed context');
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
        $buffer =& $this->buffer;
        echo substr($buffer, $this->pos, $tag->start-$this->pos);
        $this->pos = $tag->start;
        if (count($this->_tp_stack) > 0) {
            ++ $this->pos;
        }
    }

    function endParse(&$tag)
    {
        $buffer =& $this->buffer;

        if ($tag->end === false) {
            echo substr($buffer, $this->pos);
            $this->pos = false;
            return true;
        }

        $text = $this->nested_text . substr($buffer, $this->pos, $tag->end-$this->pos);
        $this->nested_text = false;
        $this->pos = $tag->end+1;
        return $tag->explodeTag($this, $text) && $tag->runTag($this);
    }

    /**#@-*/
}
 
/**
 * A tag used by the Rcbt engine
 *
 * This class represents a portion of source enclosed between the '{' start
 * tag and the '}' end tag. The whole file content is a special case of tag
 * without start/end tags.
 *
 * @package TIP
 * @subpackage SourceEngine
 */
class TIP_Rcbt_Tag
{
    /// @privatesection

    var $start = false;
    var $end = false;
    var $subtag = array();
    var $subtags = 0;
    var $module_name = null;
    var $tag_name = null;
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
        $buffer =& $parser->buffer;

        $this->start = $parser->pos;
        if (! $unclosed_tag)
            ++ $parser->pos;

        for (;;) {
            $open_brace = strpos ($buffer, '{', $parser->pos);
            $close_brace = strpos ($buffer, '}', $parser->pos);
            if ($close_brace === false) {
                if ($open_brace !== false) {
                    $parser->pos = $open_brace;
                } elseif ($unclosed_tag) {
                    return true;
                }

                $parser->error('unclosed tag');
                return false;
            }

            if ($open_brace === false || $open_brace > $close_brace) {
                break;
            }

            $parser->pos = $open_brace;
            $subtag =& new TIP_Rcbt_Tag;
            if (!$subtag->buildTag($parser))
                return false;

            $this->subtag[$this->subtags] =& $subtag;
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
            $close_brace = strrpos($text, ')');
            if ($close_brace === false) {
                $parser->error('unclosed parameter');
                return false;
            }

            $this->params = substr($text, $params_pos, $close_brace-$params_pos);
            if (! $this->params)
                $this->params = '';

            $text = substr ($text, 0, $params_pos-1);
        }

        $token = explode('.', trim($text), 2);
        if (count($token) == 1) {
            $this->module_name = null;
            if ($this->params === false) {
                $this->tag_name = 'html';
                $this->params = $token[0];
            } elseif (empty ($token[0])) {
                $this->tag_name = 'tryhtml';
            } else {
                $this->tag_name = strtolower($token[0]);
            }
        } else {
            $this->module_name = $token[0];
            $this->tag_name = strtolower($token[1]);
        }

        return true;
    }

    function runTag(&$parser)
    {
        if ($this->module_name) {
            $module =& TIP_Type::getInstance($this->module_name, false);
        } else {
            $module =& $parser->context->module;
        }

        if (!$this->tag_name) {
            if (!$parser->pop()) {
                $parser->warning('too much {} tags');
            }
            return true;
        }

        switch ($this->tag_name) {
        
        case 'cache':
            // Reserved words for the RcbtNG engine
            return true;

        case 'if':
            if ($context =& $this->createContext($parser, $module)) {
                $condition = @create_function('', "return $this->params;");
                if ($condition) {
                    $context->skipIf(! $condition());
                } else {
                    $parser->warning("invalid condition ($this->params)");
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

        case 'else':
            $parser->context->skipIf(! $parser->context->skip);
            return true;

        case 'select':
            if ($context =& $this->createContext($parser, $module)) {
                $view =& $module->startDataView($this->params);
                if ($view && $view->isValid()) {
                    $context->on_start->set(array(&$view, 'rewind'));
                    $context->on_destroy->set(array(&$module, 'endView'));
                } else {
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

        case 'selectrow':
            if ($context =& $this->createContext($parser, $module)) {
                $filter = $module->getProperty('data')->rowFilter($this->params);
                $view =& $module->startDataView($filter);
                if ($view && $view->isValid()) {
                    $context->on_start->set(array(&$view, 'rewind'));
                    $context->on_destroy->set(array(&$module, 'endView'));
                } else {
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

        case 'forselect':
            if ($context =& $this->createContext($parser, $module)) {
                $view =& $module->startDataView($this->params);
                if ($view && $view->isValid()) {
                    $context->on_start->set(array(&$view, 'rewind'));
                    $context->on_loop->set(array(&$view, 'next'));
                    $context->on_destroy->set(array(&$module, 'endView'));
                } else {
                    $context->skipIf(true);
                }
                $parser->push($context);
            }
            return true;

        case 'foreach':
            if ($context =& $this->createContext($parser, $module)) {
                if (empty($this->params)) {
                    $view =& $module->getCurrentView();
                    if ($view && $view->isValid()) {
                        $context->on_start->set(array(&$view, 'rewind'));
                        $context->on_loop->set(array(&$view, 'next'));
                    } else {
                        $context->skipIf(true);
                    }
                } elseif ($this->params > 0) {
                    $context->on_start->set(create_function('&$module', '$module->keys[\'CNT\'] = 1; return true;'), array(&$module));
                    $context->on_loop->set(create_function('&$module,$n', 'return $n > $module->keys[\'CNT\'] ++;'), array(&$module, (int)$this->params));
                    $context->on_stop->set(create_function('&$module', 'unset($module->keys[\'CNT\']); return true;'), array(&$module));
                } else {
                    $view =& $module->startView($this->params);
                    if ($view && $view->isValid()) {
                        $context->on_start->set(array(&$view, 'rewind'));
                        $context->on_loop->set(array(&$view, 'next'));
                        $context->on_destroy->set(array(&$module, 'endView'));
                    } else {
                        $context->skipIf(true);
                    }
                }
                $parser->push($context);
            }
            return true;
        }

        if (!$parser->context->skip) {
            echo $module->getTag($this->tag_name, $this->params);
        }

        return true;
    }
}

/**
 * Recursive Curly Brace Tags source engine
 *
 * Simple implementation of TIP_SourceEngine.
 *
 * @package    TIP
 * @subpackage SourceEngine
 * @tutorial   TIP/SourceEngine/TIP_Rcbt.cls
 */
class TIP_Rcbt extends TIP_Source_Engine
{
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_Rcbt instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ TIP_Source_Engine implementation
 
    function runBuffer(&$source, &$caller)
    {
        if (is_null($source->_instance)) {
            $source->_instance =& new TIP_Rcbt_Parser($source->_buffer);
            $source->_instance->parse();
        }

        return $source->_instance->run($caller);
    }

    function compileBuffer(&$source)
    {
        // Compilation not implemented
        return null;
    }

    //}}}
}
?>
