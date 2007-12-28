<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_RcbtNG definition file
 * @package TIP
 * @subpackage SourceEngine
 */


/**
 * Recursive Curly Brace Tags source engine (next generation) implementation
 *
 * The TIP_RcbtNG implementation, to be registered
 * for every TIP_Source instance.
 *
 * @package    TIP
 * @subpackage SourceEngine
 * @tutorial   TIP/SourceEngine/TIP_Rcbt.cls
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

    public function compile(&$caller)
    {
        if (isset($this->error) || !$this->_init()) {
            return false;
        }

        echo "<?php\n\n" . $this->_code . "\n?>";
        return true;
    }

    //}}}
    //{{{ Predefined tags

    /**
     * Special closing tag
     * @return     bool         true on success or false on errors
     * @subpackage SourceEngine
     */
    protected function tag()
    {
        $this->_code .= "}\n";
        if (!$this->_popModule()) {
            $this->error = 'unmatched closing brace';
            return false;
        }
        return true;
    }

    /**#@+
     * @param      TIP_Module   $module The module to use
     * @param      array       &$node   Parameters node
     * @return     bool                 true on success or false on errors
     * @subpackage SourceEngine
     */

    protected function tagCache($module, &$node)
    {
        $this->cache = true;
        return true;
    }

    protected function tagIf(&$module, &$node)
    {
        $params = $this->_expand($node);
        if (is_null($params)) {
            $this->_code .= "if (eval('return '.ob_get_clean().';')) {\n";
        } else {
            $this->_code .= "if ($params) {\n";
        }
        return $this->_pushModule($module);
    }

    protected function tagElse($module, &$node)
    {
        $this->_code .= "} else {\n";
        return true;
    }

    protected function tagSelect($module, &$node)
    {
        $view = '$view' . count($this->_stack) . '_' . $this->_level;
        $params = $this->_expand($node);
        $params = is_null($params) ? 'ob_get_clean()' : $this->_quote($params);
        $this->_code .= "$view =& {$module}->startDataView($params);\n";
        $this->_code .= "if ($view && {$view}->rewind()) {\n";
        return $this->_pushModule($module);
    }

    protected function tagSelectRow($module, &$node)
    {
        $view = '$view' . count($this->_stack) . '_' . $this->_level;
        $params = $this->_expand($node);
        $params = is_null($params) ? 'ob_get_clean()' : $this->_quote($params);
        $this->_code .= "$view =& {$module}->startDataView({$module}->getProperty('data')->rowFilter($params));\n";
        $this->_code .= "if ($view && {$view}->rewind()) {\n";
        return $this->_pushModule($module);
    }

    protected function tagForSelect($module, &$node)
    {
        $view = '$view' . count($this->_stack) . '_' . $this->_level;
        $params = $this->_expand($node);
        $params = is_null($params) ? 'ob_get_clean()' : $this->_quote($params);
        $this->_code .= "$view =& {$module}->startDataView($params);\n";
        $this->_code .= "for ($view && {$view}->rewind(); {$view}->valid(); {$view}->next()) {\n";
        return $this->_pushModule($module);
    }

    protected function tagForEach($module, &$node)
    {
        $params = $this->_expand($node);

        if ($params == '') {
            $view = '$view' . count($this->_stack) . '_' . $this->_level;
            $this->_code .= "$view =& {$module}->getCurrentView();\n";
            $this->_code .= "for ($view && {$view}->rewind(); {$view}->valid(); {$view}->next()) {\n";
        } elseif ($params > 0) {
            $cnt = '$cnt' . count($this->_stack) . '_' . $this->_level;
            $this->_code .= "for ($cnt = 1; $cnt < $params; ++ $cnt) {\n";
            $this->_code .= "{$module}->keys['CNT'] = $cnt;\n";
        } else {
            $params = is_null($params) ? 'ob_get_clean()' : $this->_quote($params);
            $view = '$view' . count($this->_stack) . '_' . $this->_level;
            $this->_code .= "$view =& {$module}->startView($params);\n";
            $this->_code .= "for ($view && {$view}->rewind(); {$view}->valid(); {$view}->next()) {\n";
        }

        return $this->_pushModule($module);
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
    private $_modules = array();
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
            // Unnesting command
            $tag = array('name' => '');
            return true;
        } else {
            isset($pre) || $pre = array('');
            $post = array('html');
        }

        empty($current) && $current = array('');
        $tag = array('module' => $pre, 'name' => $post, 'params' => $current);
        return true;
    }

    private function _renderer()
    {
        foreach ($this->_tree as &$tag) {
            if (is_string($tag)) {
                $this->_code .= 'echo ' . $this->_quote($tag) . ";\n";
            } elseif (!$this->_renderTag($tag)) {
                return false;
            }
        }

        $head = '';
        foreach (array_keys($this->_modules) as $id) {
            $head .= "\$$id =& TIP_Type::getInstance('$id', false);\n";
        }

        $this->_code = $head . "\n" . $this->_code;
        return true;
    }

    private function _renderTag(&$tag)
    {
        if (empty($tag['name'])) {
            return $this->tag();
        }

        // Get the module
        $module = $this->_expand($tag['module']);
        if (is_null($module)) {
            $module = '$module' . $this->_level;
            $this->_code .= "$module =& TIP_Type::getInstance(ob_get_clean(), false);\n";
        } else {
            $module = strtolower($module);
            if (empty($module)) {
                $module = $this->_caller;
            } else {
                $this->_modules[$module] = true;
                $module = '$' . $module;
            }
        }

        $name = $this->_expand($tag['name']);
        if (is_null($name)) {
            $name = '$name' . $this->_level;
            $this->_code .= "$name = ob_get_clean();\n";
        } elseif (method_exists($this, 'tag' . $name)) {
            // Call a predefined tag (a tag defined in this source engine)
            return $this->{'tag' . $name}($module, $tag['params']);
        } else {
            $name = $this->_quote($name);
        }

        $params = $this->_expand($tag['params'], true);
        is_null($params) && $params = 'ob_get_clean()';

        $this->_code .= "{$module}->callTag($name, $params);\n";
        return true;
    }

    /**
     * Expand a node
     * @param  array      &$node  The node to expand
     * @param  boolean     $quote Wheter to quote or not the text
     * @return string|null        The expanded text or null for buffered result
     */
    private function _expand(&$node, $quote = false)
    {
        // Simplest case: only one string
        if (count($node) == 1 && is_string($node[0])) {
            return $quote ? $this->_quote($node[0]) : $node[0];
        }

        $this->_code .= "ob_start();\n";

        foreach ($node as &$tag) {
            if (is_string($tag)) {
                $this->_code .= 'echo ' . $this->_quote($tag) . ";\n";
            } else {
                ++ $this->_level;
                $this->_renderTag($tag);
                -- $this->_level;
            }
        }

        return null;
    }

    private function _quote($params)
    {
        return "'" . addcslashes($params, '\'\\') . "'";
    }

    private function _pushModule($module)
    {
        array_push($this->_stack, $this->_caller);
        $this->_caller = $module;
        return true;
    }

    private function _popModule()
    {
        $module = array_pop($this->_stack);
        if (is_null($module)) {
            return false;
        }
        $this->_caller = $module;
        return true;
    }

    //}}}
}


/**
 * Recursive Curly Brace Tags source engine (next generation)
 *
 * Simple implementation of TIP_SourceEngine.
 *
 * @package    TIP
 * @subpackage SourceEngine
 * @tutorial   TIP/SourceEngine/TIP_Rcbt.cls
 */
class TIP_RcbtNG extends TIP_Source_Engine
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
    //{{{ TIP_Source_Engine implementation
 
    public function compileBuffer(&$instance, &$buffer, &$caller)
    {
        isset($instance) || $instance =& new TIP_RcbtNG_Instance($buffer);

        if (!$instance->compile($caller)) {
            return $instance->error;
        }

        return true;
    }

    public function runBuffer(&$instance, &$buffer, &$caller)
    {
        isset($instance) || $instance =& new TIP_RcbtNG_Instance($buffer);

        if (!$instance->run($caller)) {
            return $instance->error;
        }

        return !$instance->cache;
    }

    //}}}
}
?>
