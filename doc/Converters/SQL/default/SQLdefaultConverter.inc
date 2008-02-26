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

    function getCData($value)
    {
        return '<pre>' . htmlentities($value) . '</pre>';
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
        fwrite($handle,
            "INSERT INTO `tip_api`\n" .
            "(`id`,`parent`,`content`)\n" .
            "VALUES " . implode(",\n", $this->_rows));
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

    function getDoc(&$element)
    {
        $docblock = $this->prepareDocBlock($element, null, false);
        return mysql_escape_string($docblock['desc']);
    }

    function renderClass(&$element, $parent = null)
    {
        if (is_null($class = $this->getName($element))) {
            return;
        }

        $content = "[[toc]]\n\n++ Introduction\n\n" . $this->getDoc($element);

        $items = $element->getVars($this);
        if (is_array($items)) {
            $content .= "\n\n++ Properties";
            foreach ($items as &$var) {
                if (!is_null($name = $this->getName($var))) {
                    $content .= "\n\n+++ $name\n\n" . $this->getDoc($var);
                }
            }
        }

        $items = $element->getMethods($this);
        if (is_array($items)) {
            $content .= "\n\n++ Methods";
            foreach ($items as &$method) {
                if (!is_null($name = $this->getName($method))) {
                    $content .= "\n\n+++ $name\n\n" . $this->getDoc($method);
                }
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
