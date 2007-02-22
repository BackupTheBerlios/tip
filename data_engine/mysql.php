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
            $this->setError(mysql_error($this->_connection));
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
            $this->setError(mysql_error ($this->_connection) . " ($query)");
        }
        return $result;
    }

    function prepareFieldset(&$row)
    {
        $fieldset = array();
        foreach ($row as $id => $value) {
            $fieldset[] = $this->prepareName($id) . '=' . $this->prepareValue($value);
        }

        return empty($fieldset) ? '' : 'SET ' . implode(',', $fieldset);
    }

    function tryFillFields(&$fields, &$resource)
    {
        if (! is_null($fields)) {
            return true;
        }

        if (! is_resource($resource)) {
            return false;
        }

        $n_fields = mysql_num_fields($resource);
        for ($n = 0; $n < $n_fields; ++ $n) {
            $name = mysql_field_name($resource, $n);
            $type = mysql_field_type($resource, $n);
            $flags = mysql_field_flags($resource, $n);

            $fields[$name] = array('id' => $name);
            $field =& $fields[$name];

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
                $this->logWarning("field type not supported ($type)");
            }

            $field['can_be_null'] = (bool) (strpos($flags, 'not_null') === false);
        }

        return !is_null($fields);
    }

    /**#@-*/


    /**#@+ @access public */

    function prepareName($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    function prepareValue($value)
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_string($value)) {
            return "'" . mysql_real_escape_string($value, $this->_connection) . "'";
        }
        return (string) $value;
    }

    function fillFields(&$data)
    {
        $result = mysql_list_fields($this->_database, $data->path, $this->_connection);
        if (! $result) {
            $data->setError(mysql_error($this->_connection));
            return false;
        }

        return $this->tryFillFields($data->_fields, $result);
    }

    function fillDetails(&$data)
    {
        $result =& $this->runQuery('SELECT COLUMN_NAME,COLUMN_DEFAULT,COLUMN_TYPE,EXTRA,COLUMN_COMMENT',
                                   'FROM information_schema.COLUMNS',
                                   'WHERE `TABLE_SCHEMA`=' . $this->prepareValue($this->_database),
                                   'AND `TABLE_NAME`=' . $this->prepareValue($data->path));
        if (! $result) {
            return false;
        }

        while ($row = mysql_fetch_assoc($result)) {
            $id = $row['COLUMN_NAME'];
            if (! array_key_exists($id, $data->_fields)) {
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
        if (($result =& $this->runQuery('SELECT * FROM', $this->prepareName($data->path), $filter)) === false) {
            $result = null;
            return $result;
        }

        $this->tryFillFields($data->_fields, $result);
        $rows = array();

        while ($row = mysql_fetch_assoc($result)) {
            $rows[$row[$data->primary_key]] = $row;
        }

        // To free or not to free
        mysql_free_result($result);
        return $rows;
    }

    function insert(&$data, &$row)
    {
        if ($this->runQuery('INSERT INTO',
                            $this->prepareName($data->path),
                            $this->prepareFieldset($row)) === false) {
            return null;
        }

        return mysql_insert_id($this->_connection);
    }

    function update(&$data, $filter, &$row)
    {
        $fieldset = $this->prepareFieldset($row);
        if (empty($fieldset)) {
            return true;
        }

        return $this->runQuery('UPDATE', $this->prepareName($data->path), $fieldset, $filter);
    }

    function delete(&$data, $filter)
    {
        return $this->runQuery('DELETE FROM', $this->prepareName($data->path), $filter);
    }

    /**#@-*/
}

return 'TIP_Mysql';

?>
