<?php
/**
 * SQLdefaultConverter: an SQL renderer for phpDocumentor
 * Copyright (c) 2008 Nicola Fontana
 * 
 * LICENSE:
 * 
 * This library is free software; you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General
 * Public License as published by the Free Software Foundation;
 * either version 2.1 of the License, or (at your option) any
 * later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package    Converters
 * @subpackage SQL
 * @author     Nicola Fontana <ntd@entidi.it>
 * @copyright  2008 Nicola Fontana
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version    1.0
 * @link       http://www.phpdoc.org
 * @link       http://pear.php.net/PhpDocumentor
 * @see        parserDocBlock, parserInclude, parserPage, parserClass
 * @see        parserDefine, parserFunction, parserMethod, parserVar
 */

define('PHPDOC_TYPE_CLASS', 1);
define('PHPDOC_TYPE_VAR', 2);
define('PHPDOC_TYPE_METHOD', 3);
define('PHPDOC_SQL_PREAMBLE', "DROP TABLE IF EXISTS `tip_api`;
CREATE TABLE IF NOT EXISTS `tip_api` (
  `id` char(40) character set latin1 NOT NULL,
  `parent` char(40) character set latin1 NOT NULL,
  `content` text NOT NULL COMMENT 'widget_args=Delimiter,Code,Raw,Anchor,Heading,Toc,Horiz,Blockquote,List,Deflist,Table,Image,Center,Newline,Url,Strong,Emphasis,Tt,Superscript,Subscript,Freelink',
  PRIMARY KEY  (`id`),
  KEY `parent` (`parent`)
) DEFAULT CHARSET=utf8;

INSERT INTO `tip_api`
  (`id`,`parent`,`content`)
  VALUES ");

/**
 * SQL output converter.
 *
 * @package    Converters
 * @subpackage SQL
 * @see        parserDocBlock, parserInclude, parserPage, parserClass
 * @see        parserDefine, parserFunction, parserMethod, parserVar
 */
class SQLdefaultConverter extends Converter
{
    var $name = 'SQL';

    function SQLdefaultConverter(&$allp, &$packp, &$classes, &$procpages, $po, $pp, $qm, $target, $template, $title)
    {
        Converter::Converter($allp, $packp, $classes, $procpages,$po, $pp, $qm, $target, $template, $title);
    }

    function setTemplateDir()
    {
    }

    function setTargetDir()
    {
    }

    function sortVar($a, $b)
    {
        return strnatcasecmp($a->getName(),$b->getName());
    }

    function sortMethod($a, $b)
    {
        if ($a->isConstructor) {
            return -1;
        } elseif ($b->isConstructor) {
            return 1;
        }
        return strnatcasecmp($a->getName(),$b->getName());
    }

    function getPageName(&$element)
    {
        $is_page = phpDocumentor_get_class($element) == 'parserpage';
        return '_' . ($is_page ? $element->getName() : $element->parent->getName());
    }

    function getCData($string)
    {
        return "<code>\n" . htmlentities($string, ENT_QUOTES, 'UTF-8') . "\n</code>";
    }


    function ProgramExample($string, $tutorial = false, $inlinesourceparse = null, $class = null, $linenum = null, $filesourcepath = null)
    {
        if ($inlinesourceparse) {
            return '{{' . $string . '}}';
        }
        return "<code>\n" . $string . "\n</code>";
    }

    function EncloseList($string, $ordered)
    {
        return $string . "\n";
    }

    function ListItem($string)
    {
        return '* ' . $string . "\n";
    }

    function PreserveWhiteSpace($string)
    {
        return $this->getCData($string);
    }

    function EncloseParagraph($string)
    {
        return $string . "\n\n";
    }

    /**
     * Generate the class tree
     * @param parserClass &$class The parser class
     */
    function generateFormattedClassTree(&$class)
    {
        $this->_class[$class->getName()] = &$class;
    }

    function convertClass(&$element)
    {
        $this->class = $element->getName();
        $this->_classes[$this->class] = array();
        $this->class_data =& $this->_classes[$this->class];
    }

    function convertVar(&$element)
    {
        $this->class_data['vars'][] = $element;
    }

    function convertMethod(&$element)
    {
        $this->class_data['methods'][] = $element;
    }

    function output($title)
    {
        $roots = $this->classes->getRoots();
        foreach ($roots as $package => &$classes) {
            foreach ($classes as $class_name) {
                $class =& $this->classes->getClassByPackage($class_name, $package);
                $this->renderClass($class);
            }
        }

        $handle = fopen('tip.sql', 'w');
        fwrite($handle, PHPDOC_SQL_PREAMBLE .implode(",\n", $this->_rows));
        fclose($handle);
    }

    function getName(&$element)
    {
        $name = $element->getName();
        $name{0} == '$' && $name = substr($name, 1);
        if ($name{0} == '_' && $name{1} != '_') {
            return null;
        }

        return $name;
    }

    function renderElement(&$element)
    {
        if (is_null($name = $this->getName($element))) {
            return '';
        }

        $doc = $element->docblock;

        switch ($element->type) {
        case 'class':
            $file = $element->sourceLocation;
            $extends = $element->extends;
            $text = "\n";
            empty($file) || $text .= "\n: Source file : {{{$file}}}";
            empty($extends) || $text .= "\n: Extends : (($extends))";
            break;
        case 'var':
            $type = @$doc->var->returnType;
            $text = "\n\n+++ " . $name;
            empty($type) || $text .= '{{ : ' . $type . '}}';
            break;
        case 'method':
            $type = @$doc->return->returnType;
            $params = array_key_exists('params', $doc) ? $doc->params : array();
            $tags = array_key_exists('tags', $doc) ? $doc->tags : array();

            $args = array();
            foreach($element->listParams() as $param) {
                if ($param[1] !== false) {
                    $args[] = "[$param[0]]";
                } else {
                    $args[] = $param[0];
                }
            }
            $args = implode(', ', $args);

            $text = "\n\n+++ $name {{($args)";
            if (isset($type)) {
                $text .= ' : ';
                if ($element->getReturnsReference()) {
                    $text .= '&';
                }
                $text .= $type;
            }
            $text .= '}}';

            foreach($element->listParams() as $param) {
                $name = $param[0];
                $type = 'mixed';
                $default = '';

                if (array_key_exists($name, $params)) {
                    $pblock = $params[$name];
                    $type = $pblock->returnType;
                }

                if ($param[1] !== false) {
                    $default = " [ = $param[1]]";
                }

                $text .= "\n: $name : {{" . $type . $default . '}}';
            }

            if (isset($element->linenumber)) {
                $range = $element->linenumber . ' - ' . $element->endlinenumber;
                $text .= "\n: Lines : {{{$range}}}";
            }

            break;
        default:
            print_r($element);die();
        }

        if (isset($doc)) {
            $sdesc = $doc->getSDesc($this);
            $desc = $doc->getDesc($this);

            empty($sdesc) || $text .= "\n\n//" . $sdesc . '//';
            empty($desc) || $text .= "\n\n" . $desc;
        }

        return mysql_escape_string($text) . "\n\n";
    }

    function renderClass(&$element, $parent = null)
    {
        if (is_null($class = $this->getName($element))) {
            return;
        }

        $content = "[[toc]]\n\n++ Introduction\n\n";
        $content .= $this->renderElement($element);

        $items = $element->getVars($this);
        if (is_array($items)) {
            $content .= "\n\n++ Properties";
            foreach ($items as &$var) {
                $content .= $this->renderElement($var);
            }
        }

        $items = $element->getMethods($this);
        if (is_array($items)) {
            $content .= "\n\n++ Methods";
            foreach ($items as &$method) {
                $content .= $this->renderElement($method);
            }
        }

        $parent = isset($parent) ? "'$parent'" : 'NULL';
        $this->_rows[$class] = "('$class',$parent,'$content')";

        $items = $element->getChildClassList($this);
        if (is_array($items)) {
            foreach ($items as &$child) {
                $this->renderClass($child, $class);
            }
        }
    }
}

?>
