<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4: */

/**
 * @package TIP
 * @subpackage DataEngine
 */

/**
 * The MySql data engine
 *
 * Interface to the MySql database.
 *
 * @final
 * @package    TIP
 * @subpackage DataEngine
 * @tutorial   TIP/DataEngine/TIP_Mysql.cls
 */
class TIP_Mysql extends TIP_Data_Engine
{
    /**#@+ @access private */

    var $_connection;
    var $_database;


    function TIP_Mysql()
    {
        $this->TIP_Type();

        $server   = $this->getOption('server');
        $user     = $this->getOption('user');
        $password = $this->getOption('password');

        $this->_connection = mysql_connect($server, $user, $password);
        $this->_database = $this->getOption('database');

        if (! $this->_connection || ! mysql_select_db($this->_database, $this->_connection)) {
            TIP::error(mysql_error($this->_connection));
        } else {
            $this->runQuery('SET CHARACTER SET utf8');
        }
    }

    function& runQuery()
    {
        $pieces = func_get_args();
        $query = implode(' ', $pieces);
        $result = mysql_query($query, $this->_connection);
        if ($result === false) {
            TIP::error(mysql_error($this->_connection) . " ($query)");
        }
        return $result;
    }

    function prepareFieldset(&$data, &$row)
    {
        $keys = $this->prepareName(array_keys($row));
        $values = $this->prepareValue(array_values($row));
        $callback = create_function('$k,$v', 'return $k . \'=\' . $v;');
        $fieldset = array_map($callback, $keys, $values);

        /*
        $fieldset = array();
        foreach ($row as $id => $value) {
            if (!$data->_is_subset || in_array($id, $data->_fieldset[$data->_path])) {
                $fieldset[] = $this->prepareName($id) . '=' . $this->prepareValue($value);
            }
        }
        */

        return empty($fieldset) ? '' : 'SET ' . implode(',', $fieldset);
    }

    function tryFillFields(&$data, &$resource)
    {
        if (!empty($data->fields)) {
            return true;
        }

        if (!is_resource($resource)) {
            return false;
        }

        $n_fields = mysql_num_fields($resource);
        for ($n = 0; $n < $n_fields; ++ $n) {
            if (mysql_field_table($resource, $n) != $data->_path) {
                continue;
            }

            $name = mysql_field_name($resource, $n);
            $type = mysql_field_type($resource, $n);
            $flags = mysql_field_flags($resource, $n);

            $data->_fields[$name] = array('id' => $name);
            $field =& $data->_fields[$name];

            // Default fallbacks
            $field['widget'] = null;
            $field['length'] = 0;

            switch (strtoupper($type)) {
            case 'BOOL':
            case 'BOOLEAN':
                $field['type'] = 'bool';
                $field['widget'] = 'set';
                break;

            case 'BIT':
            case 'TINYINT':
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'INTEGER':
            case 'BIGINT':
                $field['type'] = 'int';
                break;

            case 'FLOAT':
            case 'DOUBLE':
            case 'DOUBLE PRECISION':
            case 'REAL':
            case 'DECIMAL':
            case 'DEC':
            case 'NUMERIC':
            case 'FIXED':
                $field['type'] = 'float';
                break;

            case 'STRING':
            case 'CHAR':
            case 'VARCHAR':
            case 'BINARY':
            case 'VARBINARY':
                $field['type'] = 'string';
                if (strpos($flags, 'enum') !== false) {
                    $field['widget'] = 'enum';
                } elseif (strpos($flags, 'set') !== false) {
                    $field['widget'] = 'set';
                }

                // UTF-8 chars are 3 bytes long
                $field['length'] = mysql_field_len($resource, $n) / 3;
                break;

            case 'TINYBLOB':
            case 'TINYTEXT':
                $field['type'] = 'string';
                $field['widget'] = 'textarea';
                $field['length'] = 255;
                break;

            case 'BLOB':
            case 'TEXT':
                $field['type'] = 'string';
                $field['widget'] = 'textarea';
                $field['length'] = 65535;
                break;

            case 'MEDIUMBLOB':
            case 'MEDIUMTEXT':
                $field['type'] = 'string';
                $field['widget'] = 'textarea';
                $field['length'] = 16777215;
                break;

            case 'LONGBLOB':
            case 'LONGTEXT':
                $field['type'] = 'string';
                $field['widget'] = 'textarea';
                $field['length'] = 4294967295;
                break;

            case 'ENUM':
                $field['type'] = 'string';
                $field['widget'] = 'enum';
                break;

            case 'SET':
                $field['type'] = 'string';
                $field['widget'] = 'set';
                break;

            case 'DATE':
                $field['type'] = 'string';
                $field['widget'] = 'date';
                $field['length'] = 10;
                break;

            case 'TIME':
                $field['type'] = 'string';
                $field['widget'] = 'time';
                $field['length'] = 8;
                break;

            case 'DATETIME':
                $field['type'] = 'string';
                $field['widget'] = 'datetime';
                $field['length'] = 19;
                break;

            case 'TIMESTAMP':
                $field['type'] = 'int';
                $field['widget'] = 'datetime';
                break;

            case 'YEAR':
                $field['type'] = 'string';
                $field['length'] = 4;
                break;

            default:
                $field['type'] = 'string';
                TIP::warning("field type not supported ($type)");
            }

            $field['can_be_null'] = (bool) (strpos($flags, 'not_null') === false);
        }

        return !is_null($data->_fields);
    }

    /**
     * @static
     */
    function _groupedNames($names, $enclosed = true)
    {
        $result = implode(',', TIP_Mysql::prepareName($names));
        return $enclosed ? '(' . $result . ')' : $result;
    }

    /**
     * @static
     */
    function _groupedValues($values, $enclosed = true)
    {
        $result = implode(',', TIP_Mysql::prepareValue($values));
        return $enclosed ? '(' . $result . ')' : $result;
    }

    function _prepareField(&$id, $as, $table)
    {
        $result = TIP_Mysql::prepareName($table) . '.' . TIP_Mysql::prepareName($id);
        if (is_string($as)) {
            $result .= ' AS ' . TIP_Mysql::prepareName($as);
        }
        $id = $result;
    }

    /**#@-*/


    /**#@+ @access public */

    /**
     * @static
     */
    function prepareName($name)
    {
        if (is_array($name)) {
            return array_map(array('TIP_Mysql', 'prepareName'), $name);
        //} elseif ($name{0} == '`' || $name{0} == '*') {
        } elseif ($name == '*') {
            return $name;
        }
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * @static
     */
    function prepareValue($value)
    {
        if (is_array($value)) {
            return array_map(array('TIP_Mysql', 'prepareValue'), $value);
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_string($value)) {
            return "'" . mysql_real_escape_string($value) . "'";
        }
        return (string) $value;
    }

    function fillFields(&$data)
    {
        $result = mysql_list_fields($this->_database, $data->_path, $this->_connection);
        if (! $result) {
            TIP::error(mysql_error($this->_connection));
            return false;
        }

        return $this->tryFillFields($data, $result);
    }

    function fillDetails(&$data)
    {
        $result =& $this->runQuery('SELECT COLUMN_NAME,COLUMN_DEFAULT,COLUMN_TYPE,EXTRA,COLUMN_COMMENT',
                                   'FROM information_schema.COLUMNS',
                                   'WHERE `TABLE_SCHEMA`=' . $this->prepareValue($this->_database),
                                   'AND `TABLE_NAME`=' . $this->prepareValue($data->_path));
        if (! $result) {
            return false;
        }

        while ($row = mysql_fetch_assoc($result)) {
            $id = $row['COLUMN_NAME'];
            if (!array_key_exists($id, $data->_fields)) {
                continue;
            }

            $field =& $data->_fields[$id];

            // $field['default']
            $field['default'] = @$row['COLUMN_DEFAULT'];
            settype($field['default'], $field['type']);

            // $field['automatic']
            $field['automatic'] = strpos($row['EXTRA'], 'auto_increment') !== FALSE;

            // $field['choices']
            if ($field['widget'] == 'set' || $field['widget'] == 'enum') {
                preg_match_all("/[(,]\s*'?((?(?<=')[^']*|[0-9.]*))[^,]*/", $row['COLUMN_TYPE'], $regex);
                $field['choices'] = $regex[1];
            } else {
                $field['choices'] = null;
            }

            // $field['info']
            $field['info'] = $row['COLUMN_COMMENT'];
        }

        return true;
    }

    function& get(&$data, $filter)
    {
        $prepared_fields = array();
        foreach(array_keys($data->_fieldset) as $table) {
            $fields = $data->_fieldset[$table];
            array_walk($fields, array('TIP_Mysql', '_prepareField'), $table);
            $prepared_fields = array_merge($prepared_fields, $fields);
        }
        
        if (($result =& $this->runQuery('SELECT', implode(',', $prepared_fields),
                                        'FROM', $this->prepareName($data->_path), $data->_joins,
                                        $filter)) === false) {
            $result = null;
            return $result;
        }

        $this->tryFillFields($data, $result);
        $rows = array();

        while ($row = mysql_fetch_assoc($result)) {
            $rows[$row[$data->primary_key]] = $row;
        }

        // To free or not to free
        mysql_free_result($result);
        return $rows;
    }

    function insert(&$data, &$rows)
    {
        $prepared_fields = $this->_groupedNames(array_keys($rows[0]));
        $prepared_values = implode(',', array_map(array('TIP_Mysql', '_groupedValues'), $rows));
        if ($this->runQuery('INSERT INTO', $this->prepareName($data->_path),
                            $prepared_fields, 'VALUES', $prepared_values) === false) {
            return null;
        }

        return mysql_insert_id($this->_connection);
    }

    function update(&$data, $filter, &$row)
    {
        $fieldset = $this->prepareFieldset($data, $row);
        if (empty($fieldset)) {
            return true;
        }

        return $this->runQuery('UPDATE', $this->prepareName($data->_path), $fieldset, $filter);
    }

    function delete(&$data, $filter)
    {
        return $this->runQuery('DELETE FROM', $this->prepareName($data->_path), $filter);
    }

    /**#@-*/
}

return 'TIP_Mysql';

?>
