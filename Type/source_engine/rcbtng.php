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

        $this->_mode = 0;
        $this->_pos = 0;
        $this->_branch =& $this->_tree;
        if (!$this->_renderer($caller)) {
            return false;
        }

        return true;
    }

    //}}}
    //{{{ Predefined tags

    /**#@+
     * @param      TIP_Module   &$module The module to use
     * @param      string       &$params Parameters of the tag
     * @return     bool                  true on success or false on errors
     * @subpackage SourceEngine
     */

    protected function tagCache(&$module, &$params)
    {
        $this->cache = true;
        return true;
    }

    protected function tagIf(&$module, &$params)
    {
        ++ $this->_pos;
        $old_mode = $this->_mode;
        if ($this->_mode & self::SKIP_TAGS) {
            $this->_mode |= self::SKIP_ELSE;
        } elseif (!eval("return $params;")) {
            $this->_mode |= self::SKIP_TEXT|self::SKIP_TAGS;
        }
        $done = $this->_renderer($module);
        $this->_mode = $old_mode;
        return $done;
    }

    protected function tagElse(&$module, &$params)
    {
        if (!($this->_mode & self::SKIP_ELSE)) {
            $this->_mode ^= self::SKIP_TEXT|self::SKIP_TAGS;
        }
        return true;
    }

    protected function tagSelect(&$module, &$params)
    {
        ++ $this->_pos;
        $old_mode = $this->_mode;
        $view =& $this->_startDataView($module, $params);
        $done = $this->_renderer($module);
        $this->_mode = $old_mode;
        $view && $module->endView();
        return $done;
    }

    protected function tagSelectRow(&$module, &$params)
    {
        ++ $this->_pos;
        $old_mode = $this->_mode;
        $view =& $this->_startDataView($module, $module->getProperty('data')->rowFilter($params));
        $done = $this->_renderer($module);
        $this->_mode = $old_mode;
        $view && $module->endView();
        return $done;
    }

    protected function tagForSelect(&$module, &$params)
    {
        ++ $this->_pos;
        $old_mode = $this->_mode;
        $view =& $this->_startDataView($module, $params);
        $start_pos = $this->_pos;

        do {
            $this->_pos = $start_pos;
            $done = $this->_renderer($module);
        } while ($done && $view && $view->next());

        $this->_mode = $old_mode;
        $view && $module->endView();
        return $done;
    }

    protected function tagForEach(&$module, &$params)
    {
        ++ $this->_pos;
        $old_mode = $this->_mode;
        $view = null;

        if ($this->_mode & self::SKIP_TAGS) {
            $this->_mode |= self::SKIP_ELSE;
            $done = $this->_renderer($module);
        } elseif ($params == '') {
            $view =& $module->getCurrentView();
            if (!$view || !$view->isValid() || !$view->rewind()) {
                $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                $done = $this->_renderer($module);
            } else {
                $start_pos = $this->_pos;
                do {
                    $this->_pos = $start_pos;
                    $done = $this->_renderer($module);
                } while ($done && $view->next());
            }
        } elseif ($params > 0) {
            $start_pos = $this->_pos;
            for ($module->keys['CNT'] = 1; $module->keys['CNT'] <= $params; ++ $module->keys['CNT']) {
                $this->_pos = $start_pos;
                $done = $this->_renderer($module);
                if (!$done) {
                    break;
                }
            }
        } else {
            $view =& $module->startView($params);
            if (!$view || !$view->isValid() || !$view->rewind()) {
                $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
                $done = $this->_renderer($module);
            } else {
                $start_pos = $this->_pos;
                do {
                    $this->_pos = $start_pos;
                    $done = $this->_renderer($module);
                } while ($done && $view->next());
            }
        }

        $this->_mode = $old_mode;
        $view && $module->endView();
        return $done;
    }

    /**#@-*/

    //}}}
    //{{{ Private properties

    private $_text = null;
    private $_len = null;

    private $_pos = 0;
    private $_open = null;
    private $_close = null;

    private $_tree = array();
    private $_branch = null;

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
     * @param  object &$caller The caller TIP_Module
     * @return                 true on success, false on errors
     */
    private function _renderer(&$caller)
    {
        while (isset($this->_branch[$this->_pos])) {
            $tag =& $this->_branch[$this->_pos];

            if (is_string($tag)) {
                if (!($this->_mode & self::SKIP_TEXT)) {
                    echo $tag;
                }
            } else {
                if (!array_key_exists('name', $tag)) {
                    // Tag recursion
                    ob_start();
                    $old_pos = $this->_pos;
                    $old_mode = $this->_mode;
                    $old_branch =& $this->_branch;
                    $this->_pos = 0;
                    $this->_mode &= ~self::SKIP_TEXT;
                    $this->_branch =& $tag;
                    if (!$this->_renderer($caller)) {
                        ob_clean();
                        return false;
                    }
                    $this->_pos = $old_pos;
                    $this->_mode = $old_mode;
                    $this->_branch =& $old_branch;
                    $data[0] = ob_get_clean();
                    if (!$this->_parse($data)) {
                        return false;
                    }
                    $module =& $data['module'];
                    $name   =& $data['name'];
                    $params =& $data['params'];
                    unset($data);
                } else {
                    $module =& $tag['module'];
                    $name   =& $tag['name'];
                    $params =& $tag['params'];
                }

                if (is_null($name)) {
                    break;
                }

                isset($module) || $module =& $caller;

                if (method_exists($this, 'tag' . $name)) {
                    // Call a predefined tag (a tag defined in this source engine)
                    if (!$this->{'tag' . $name}($module, $params)) {
                        return false;
                    }
                } elseif (!($this->_mode & self::SKIP_TAGS)) {
                    // Call a module tag
                    $module->callTag($name, $params);
                }
            }

            ++ $this->_pos;
        }

        return true;
    }

    private function& _startDataView(&$module, $query)
    {
        $view = null;

        if ($this->_mode & self::SKIP_TAGS) {
            $this->_mode |= self::SKIP_ELSE;
        } else {
            $view =& $module->startDataView($query);
            if (!$view || !$view->isValid() || !$view->rewind()) {
                $this->_mode |= self::SKIP_TAGS|self::SKIP_TEXT;
            }
        }

        return $view;
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
