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
    //{{{ Constants

    const SKIP_TEXT = 1;
    const SKIP_TAGS = 2;
    const SKIP_ELSE = 4;

    //}}}
    //{{{ Public properties

    public $error = null;

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
 
    public function run(&$module)
    {
        if (isset($this->error) || !$this->_init()) {
            return false;
        }

        $this->_mode = 0;
        $last = 0;
        if (!$this->_renderer($this->_tree, $module, $last)) {
            return false;
        }

        /*if ($last < count($source->_implementation)) {
            return $this->_catchError($source->_implementation);
        }*/

        return true;
    }

    //}}}
    //{{{ Private properties

    private $_text = null;
    private $_len = null;

    private $_pos = 0;
    private $_open = null;
    private $_close = null;

    private $_tree = array();

    /**
     * Skip level, 0 = pen down, > 0 skip content
     * @var int
     */
    private $_mode = 0;


    //}}}
    //{{{ Private methods

    private function _init()
    {
        if (empty($this->_tree)) {
            return $this->_tokenizer() && $this->_parser($this->_tree);
        }
        return true;
    }

    private function _tokenizer()
    {
        $this->_tree =& $this->_tokenize();
        if (is_numeric($this->_open)) {
            $this->error = 'Opened tag';
            return false;
        } elseif (is_numeric($this->_close)) {
            $this->error = 'Closing a no existent tag';
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

    private function _parser(&$tree)
    {
        foreach ($tree as &$tag) {
            if (!is_array($tag)) {
                continue;
            }

            $done = count($tag) == 1 ? $this->_parse($tag) : $this->_parser($tag);
            if (!$done) {
                return false;
            }
        }

        return true;
    }

    private function _parse(&$leaf)
    {
        $token = $leaf[0];
        if (!is_string($token)) {
            $this->error = 'malformed token';
            return false;
        } else {
            unset($leaf[0]);
        }

        $leaf['params'] = null;
        $params =& $leaf['params'];

        $open = strpos ($token, '(');
        if ($open !== false) {
            $pos = $open+1;
            $close = strrpos($token, ')');
            if ($close === false) {
                $this->error = 'unclosed parameter';
                return false;
            }

            $params = substr($token, $pos, $close-$pos);
            isset($params) || $params = '';
            $token = substr($token, 0, $pos-1);
        }

        @list($pre, $post) = explode('.', trim($token), 2);
        if (is_null($post)) {
            $leaf['module'] = null;
            if (is_null($params)) {
                if ($pre == '') {
                    $leaf['name'] = null;
                } else {
                    $leaf['name'] = 'html';
                    $params = $pre;
                }
            } else {
                $pre == '' && $pre = 'tryHtml';
                $leaf['name'] = $pre;
            }
        } else {
            $leaf['module'] =& TIP_Type::getInstance($pre, false);
            $leaf['name'] = $post;
        }

        return true;
    }

    /**
     * Render the specified tree
     *
     * @param  array  &$tree   The tree to render
     * @param  object &$caller The caller TIP_Module
     * @param  int    &$i      The starting tree element to render
     * @return                 true on success, false on errors
     */
    private function _renderer(&$tree, &$caller, &$i)
    {
        while (isset($tree[$i])) {
            if (is_string($tree[$i])) {
                if (!($this->_mode & self::SKIP_TEXT)) {
                    echo $tree[$i];
                }
            } else {
                $done = $this->_render($tree, $caller, $i);
                if (is_null($done)) {
                    break;
                } elseif ($done === false) {
                    return false;
                }
            }
            ++ $i;
        }

        return true;
    }

    private function _render(&$tree, &$caller, &$i)
    {
        $tag =& $tree[$i];

        if (!array_key_exists('name', $tag)) {
            ob_start();
            $old_mode = $this->_mode;
            $this->_mode &= ~self::SKIP_TEXT;
            $dummy = 0;
            $done = $this->_renderer($tag, $caller, $dummy);
            $this->_mode = $old_mode;
            if (!$done) {
                ob_clean();
                return false;
            }
            $data[0] = ob_get_clean();
            if (!$this->_parse($data)) {
                return false;
            }
            $tag_module =& $data['module'];
            $name       =& $data['name'];
            $params     =& $data['params'];
        } else {
            $tag_module =& $tag['module'];
            $name       =& $tag['name'];
            $params     =& $tag['params'];
        }

        if (is_null($name)) {
            return null;
        }

        if (is_null($tag_module)) {
            $module =& $caller;
        } else {
            $module =& $tag_module;
        }

        switch (strtolower($name)) {
        case 'if':
            ++ $i;
            $old_mode = $this->_mode;
            if ($this->_mode & self::SKIP_TAGS) {
                $this->_mode |= self::SKIP_ELSE;
            } elseif (!eval("return $params;")) {
                $this->_mode |= self::SKIP_TEXT|self::SKIP_TAGS;
            }
            $done = $this->_renderer($tree, $module, $i);
            $this->_mode = $old_mode;
            return $done;

        case 'else':
            if (!($this->_mode & self::SKIP_ELSE)) {
                $this->_mode ^= self::SKIP_TEXT|self::SKIP_TAGS;
            }
            return true;

        case 'select':
            ++ $i;
            $old_mode = $this->_mode;
            $view = null;
            if ($this->_mode & self::SKIP_TAGS) {
                $this->_mode |= self::SKIP_ELSE;
            } else {
                $view =& $module->startDataView($params);
                if (!$view || !$view->isValid() || !$view->rewind()) {
                    $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                }
            }
            $done = $this->_renderer($tree, $module, $i);
            $this->_mode = $old_mode;
            $view && $module->endView();
            return $done;

        case 'selectrow':
            ++ $i;
            $old_mode = $this->_mode;
            $view = null;
            if ($this->_mode & self::SKIP_TAGS) {
                $this->_mode |= self::SKIP_ELSE;
            } else {
                $view =& $module->startDataView($module->getProperty('data')->rowFilter($params));
                if (!$view || !$view->isValid() || !$view->rewind()) {
                    $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                }
            }
            $done = $this->_renderer($tree, $module, $i);
            $this->_mode = $old_mode;
            $view && $module->endView();
            return $done;

        case 'forselect':
            ++ $i;
            $old_mode = $this->_mode;
            $view = null;
            if ($this->_mode & self::SKIP_TAGS) {
                $this->_mode |= self::SKIP_ELSE;
                $done = $this->_renderer($tree, $module, $i);
            } else {
                $view =& $module->startDataView($params);
                if (!$view || !$view->isValid() || !$view->rewind()) {
                    $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                    $done = $this->_renderer($tree, $module, $i);
                } else {
                    $first_i = $i;
                    do {
                        $i = $first_i;
                        $done = $this->_renderer($tree, $module, $i);
                    } while ($done && $view->next());
                }
            }
            $this->_mode = $old_mode;
            $view && $module->endView();
            return $done;

        case 'foreach':
            ++ $i;
            $old_mode = $this->_mode;
            $view = null;

            if ($this->_mode & self::SKIP_TAGS) {
                $this->_mode |= self::SKIP_ELSE;
                $done = $this->_renderer($tree, $module, $i);
            } elseif ($params == '') {
                $view =& $module->getCurrentView();
                if (!$view || !$view->isValid() || !$view->rewind()) {
                    $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                    $done = $this->_renderer($tree, $module, $i);
                } else {
                    $first_i = $i;
                    do {
                        $i = $first_i;
                        $done = $this->_renderer($tree, $module, $i);
                    } while ($done && $view->next());
                }
            } elseif ($params > 0) {
                $first_i = $i;
                for ($module->keys['CNT'] = 1; $module->keys['CNT'] <= $params; ++ $module->keys['CNT']) {
                    $i = $first_i;
                    $done = $this->_renderer($tree, $module, $i);
                    if (!$done) {
                        break;
                    }
                }
            } else {
                $view =& $module->startView($params);
                if (!$view || !$view->isValid() || !$view->rewind()) {
                    $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                    $done = $this->_renderer($tree, $module, $i);
                } else {
                    $first_i = $i;
                    do {
                        $i = $first_i;
                        $done = $this->_renderer($tree, $module, $i);
                    } while ($done && $view->next());
                }
            }

            $this->_mode = $old_mode;
            $view && $module->endView();
            return $done;
        }

        if (!($this->_mode & self::SKIP_TAGS)) {
            $module->callTag($name, $params);
        }

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
 
    public function run(&$source, &$module)
    {
        if (is_null($source->_implementation)) {
            $source->_implementation =& new TIP_RcbtNG_Instance($source->_buffer);
        }

        if (!$source->_implementation->run($module)) {
            die('Errore: ' . $source->_implementation->error);
            return false;
        }

        return true;
    }

    //}}}
}
?>
