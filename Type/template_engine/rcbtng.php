<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_RcbtNG definition file
 *
 * LICENSE: This source file is subject to the New BSD license that is 
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the New BSD License and are unable to
 * obtain it through the web, please send a note to license@php.net so we
 * can mail you a copy immediately.
 *
 * @author    Nicola Fontana <ntd@entidi.it>
 * @copyright Copyright &copy; 2006-2008 Nicola Fontana
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package   TIP
 * @since     0.0.1
 */

/**
 * Recursive Curly Brace Tags (Next Generation) template engine
 *
 * The TIP_RcbtNG implementation, to be used instead of the deprecated
 * TIP_Rcbt engine.
 *
 * @package    TIP
 * @subpackage TemplateEngine
 */
class TIP_RcbtNG_Instance
{
    //{{{ Public properties

    public $error = null;

    public $cache = false;

    //}}}
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_RcbtNG_Instance.
     *
     * @param string &$text The text buffer
     */
    public function __construct(&$text)
    {
        $this->_text =& $text;
        $this->_len = strlen($text);
    }

    //}}}
    //{{{ Public methods
 
    public function run(&$caller)
    {
        if (isset($this->error) || !$this->_init()) {
            return false;
        }

        eval($this->_code);
        return true;
    }

    public function compile()
    {
        if (isset($this->error) || !$this->_init()) {
            return null;
        }

        return "<?php\n\n" . $this->_code . "\n?>";
    }

    //}}}
    //{{{ Predefined tags

    /**#@+
     * @param      string   $module The module to use
     * @param      string   $params Tag parameters
     * @return     bool             true on success or false on errors
     * @subpackage TemplateEngine
     */

    protected function tag($module, $params)
    {
        $this->_code .= "}\n";
        if (!$this->_pop()) {
            $this->error = 'unmatched closing brace';
            return false;
        }
        return true;
    }

    protected function tagCache($module, $params)
    {
        $this->cache = true;
        return true;
    }

    protected function tagIf($module, $params)
    {
        $this->_code .= "if (eval('return ' . $params . ';')) {\n";
        return $this->_push($module);
    }

    protected function tagElse($module, $params)
    {
        $this->_code .= "} else {\n";
        return true;
    }

    protected function tagElseIf($module, $params)
    {
        $this->_code .= "} elseif (eval('return ' . $params . ';')) {\n";
        return true;
    }

    protected function tagSelect($module, $params)
    {
        $view = '$view' . count($this->_stack) . '_' . $this->_level;
        $this->_code .= "$view =& {$module}->startDataView($params);\n";
        $this->_code .= "if ($view && {$view}->rewind()) {\n";
        return $this->_push($module, $view);
    }

    protected function tagSelectRow($module, $params)
    {
        $view = '$view' . count($this->_stack) . '_' . $this->_level;
        $this->_code .= "$view =& {$module}->startDataView({$module}->getProperty('data')->rowFilter($params));\n";
        $this->_code .= "if ($view && {$view}->rewind()) {\n";
        return $this->_push($module, $view);
    }

    protected function tagForSelect($module, $params)
    {
        $view = '$view' . count($this->_stack) . '_' . $this->_level;
        $this->_code .= "$view =& {$module}->startDataView($params);\n";
        $this->_code .= "if ($view) for ({$view}->rewind(); {$view}->valid(); {$view}->next()) {\n";
        return $this->_push($module, $view);
    }

    protected function tagForEach($module, $params)
    {
        if ($params == "''") {
            $view = '$view' . count($this->_stack) . '_' . $this->_level;
            $this->_code .= "$view =& {$module}->getCurrentView();\n";
            $this->_code .= "if ($view) for ({$view}->rewind(); {$view}->valid(); {$view}->next()) {\n";
            $view = null;
        } elseif (!is_null($params) && eval("return $params;") > 0) {
            $cnt = '$cnt' . count($this->_stack) . '_' . $this->_level;
            $this->_code .= "for ($cnt = 1; $cnt <= $params; ++ $cnt) {\n";
            $this->_code .= "{$module}->keys['CNT'] = $cnt;\n";
            $view = null;
        } else {
            $view = '$view' . count($this->_stack) . '_' . $this->_level;
            $this->_code .= "$view =& {$module}->startView($params);\n";
            $this->_code .= "if ($view) for ({$view}->rewind(); {$view}->valid(); {$view}->next()) {\n";
        }

        return $this->_push($module, $view);
    }

    /**#@-*/

    //}}}
    //{{{ Private properties

    private $_text = null;
    private $_len = null;

    private $_tree = array();
    private $_pos = 0;
    private $_open = null;
    private $_close = null;

    private $_code = null;
    private $_caller = '$caller';
    private $_stack = array();
    private $_level = 0;

    //}}}
    //{{{ Private methods

    private function _init()
    {
        if (isset($this->_code)) {
            return true;
        }

        if ($this->_tokenizer() && $this->_parser() && $this->_renderer()) {
            // Free the most large memory blocks
            $this->_text = null;
            unset($this->_text, $this->_tree);
            return true;
        }

        return false;
    }

    private function _tokenizer()
    {
        $this->_tree =& $this->_tokenize();
        if (is_numeric($this->_open)) {
            $this->error = 'Unclosed tag';
            return false;
        } elseif (is_numeric($this->_close)) {
            $this->error = 'Unopened tag';
            return false;
        }
        return true;
    }

    private function &_tokenize()
    {
        $tree = array();

        while ($this->_pos < $this->_len) {
            is_null($this->_open) && $this->_open = strpos($this->_text, '{', $this->_pos);
            is_null($this->_close) && $this->_close = strpos($this->_text, '}', $this->_pos);

            if ($this->_open === $this->_close) {
                // No more tags
                $tree[] = substr($this->_text, $this->_pos);
                break;
            } elseif ($this->_open !== false && $this->_open < $this->_close) {
                // Opening tag
                $this->_open > $this->_pos && $tree[] = substr($this->_text, $this->_pos, $this->_open-$this->_pos);
                $this->_pos = $this->_open+1;
                $this->_open = null;
                $tree[] =& $this->_tokenize();
            } else {
                // Closing tag
                $this->_close > $this->_pos && $tree[] = substr($this->_text, $this->_pos, $this->_close-$this->_pos);
                $this->_pos = $this->_close+1;
                $this->_close = null;
                break;
            }
        }

        return $tree;
    }

    private function _parser()
    {
        foreach ($this->_tree as &$tag) {
            if (is_array($tag) && !$this->_parse($tag)) {
                return false;
            }
        }

        return true;
    }

    private function _parse(&$tag)
    {
        $current = array();

        foreach ($tag as &$item) {
            if (is_array($item)) {
                $current[] =& $item;
                $this->_parse($item);
            } else {
                $start = 0;
                $dot = isset($pre) ? false : strpos ($item, '.');
                $open = isset($post) ? false : strpos ($item, '(');

                if ($dot !== false && ($open === false || $dot < $open)) {
                    $dot && $current[] = substr($item, 0, $dot);
                    $start = $dot+1;
                    $pre =& $current;
                    unset($current);
                    $current = array();
                }
               
                if ($open !== false) {
                    isset($pre) || $pre = array('');
                    $open > $start && $current[] = substr($item, $start, $open-$start);
                    $start = $open+1;
                    $post =& $current;
                    unset($current);
                    $current = array();
                }

                if (substr($item, $start) != '')
                    $current[] = substr($item, $start);
            }
        }

        if (isset($post)) {
            // Find matching close brace
            $last = count($current)-1;
            if ($last < 0 || substr($current[$last], -1) != ')') {
                $this->error = 'unclosed argument';
                return false;
            }
            if ($current[$last] == ')') {
                unset($current[$last]);
            } else {
                $current[$last] = substr($current[$last], 0, -1);
            }
            empty($post) && $post = array('tryHtml');
        } elseif (empty($current)) {
            $post = array('');
        } else {
            $post = array('html');
        }

        isset($pre) || $pre = array('');
        empty($current) && $current = array('');
        $tag = array('module' => $pre, 'name' => $post, 'params' => $current);
        return true;
    }

    private function _renderer()
    {
        return $this->_render($this->_tree);
    }

    private function _render(&$node)
    {
        foreach ($node as &$tag) {
            if (is_string($tag)) {
                $text = $this->_quote($tag);
            } else {
                ++ $this->_level;
                $text = $this->_renderTag($tag);
                -- $this->_level;
                if (is_null($text)) {
                    return false;
                } elseif ($text == "''") {
                    continue;
                }
            }
            $this->_code .= "echo $text;\n";
        }

        return true;
    }

    /**
     * Render a tag
     * @param  array      &$tag    The tag to render
     * @param  boolean     $inline Try to force an inline rendering
     * @return string|null         The rendered text or null if not possible
     */
    private function _renderTag(&$tag, $inline = false)
    {
        // Get the module
        if (is_null($module = $this->_expandInline($tag['module']))) {
            if ($inline || !$this->_expandBuffer($tag['module'])) {
                return null;
            }
            $module = '$module' . $this->_level;
            $this->_code .= "$module =& TIP_Type::getInstance(ob_get_clean());\n";
        } elseif ($module == "''") {
            $module = $this->_caller;
        } else {
            $module = "TIP_Type::getInstance($module)";
        }

        // Get the params
        if (is_null($params = $this->_expandInline($tag['params']))) {
            if ($inline || !$this->_expandBuffer($tag['params'])) {
                return null;
            }
            $params = '$params' . $this->_level;
            $this->_code .= "$params = ob_get_clean();\n";
        }

        // Get the name
        if (count($tag['name']) == 1 && is_string($tag['name'][0])) {
            $name = $tag['name'][0];
            if (method_exists($this, 'tag' . $name)) {
                // Call a predefined tag (a tag defined in this template engine)
                return $inline || !$this->{'tag' . $name}($module, $params) ? null : "''";
            } else {
                $name = $this->_quote($name);
            }
        } else {
            if (is_null($name = $this->_expandInline($tag['name']))) {
                if ($inline || !$this->_expandBuffer($tag['name'])) {
                    return null;
                }
                $name = 'ob_get_clean()';
            }
        }

        return "{$module}->getTag($name, $params)";
    }

    /**
     * Expand a node using inline rendering
     * @param  array      &$node The node to expand
     * @return string|null       The expanded text or null
     *                           if inline rendering not possible
     */
    private function _expandInline(&$node)
    {
        $items = array();
        foreach ($node as &$tag) {
            $item = is_string($tag) ? $this->_quote($tag) : $this->_renderTag($tag, true);
            if (!$item) {
                // Inline rendering not possible
                return null;
            }
            $item != "''" && $items[] = $item;
        }

        return empty($items) ? "''" : implode(' . ', $items);
    }

    /**
     * Expand a node using ob_start() buffering
     * @param  array      &$node The node to expand
     * @return boolean           true on success or false on errors
     */
    private function _expandBuffer(&$node)
    {
        $this->_code .= "ob_start();\n";
        return $this->_render($node);
    }

    private function _quote($params)
    {
        return "'" . addcslashes($params, '\'\\') . "'";
    }

    private function _push($module, $view = null)
    {
        array_push($this->_stack, array(
            'module' => $this->_caller,
            'view' => $view)
        );
        $this->_caller = $module;
        return true;
    }

    private function _pop()
    {
        if (empty($this->_stack)) {
            return false;
        }

        $context = array_pop($this->_stack);
        $module = $context['module'];
        $view = $context['view'];
        if ($view) {
            $this->_code .= "$view && {$this->_caller}->endView();\n";
        }
        $this->_caller = $module;
        return true;
    }

    //}}}
}


/**
 * Recursive Curly Brace Tags template engine (next generation)
 *
 * Simple implementation of TIP_Template_Engine.
 *
 * @package    TIP
 * @subpackage TemplateEngine
 */
class TIP_RcbtNG extends TIP_Template_Engine
{
    //{{{ Constructor/destructor

    /**
     * Constructor
     *
     * Initializes a TIP_RcbtNG instance.
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
    }

    //}}}
    //{{{ TIP_Template_Engine implementation
 
    public function compileBuffer(&$template)
    {
        isset($template->_instance) || $template->_instance =& new TIP_RcbtNG_Instance($template->_buffer);
        return $template->_instance->compile();
    }

    public function runBuffer(&$template, &$caller)
    {
        isset($template->_instance) || $template->_instance =& new TIP_RcbtNG_Instance($template->_buffer);

        if (!$template->_instance->run($caller)) {
            return $template->_instance->error;
        }

        return !$template->_instance->cache;
    }

    //}}}
}
?>
