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
 * The filter parameters must be specified without the SQL command and the
 * FROM clause and can include everything acceptable by the server.
 *
 * For example, if you want to show the user called 'nicola', you must specify
 * the filter <code>WHERE `user`='nicola'</code> in the appropriate module.
 * This applies also to every method that has a filter parameter on a Mysql
 * based module. Using the tipRcbt engine, for instance, you could create a
 * source file like the following:
 *
 * <code>
 * <h1>List of the first ten users whose name begins with 'c'</h1>
 *
 * {user.ForRows(WHERE `user` LIKE 'c%' LIMIT 10)}
 * <p>{user} ({publicname})</p>
 * {}
 * </code>
 *
 * In the above example, the ForRows is called from the 'user' module. Also,
 * the ForRows is by definition a read method (SELECT, in SQL). This means the
 * command will expand to the real SQL query:
 *
 * <code>SELECT * FROM `tip_user` WHERE `user` LIKE 'c\%' LIMIT 10)</code>
 *
 * @final
 * @package TIP
 * @subpackage DataEngine
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

    function tryFillFields(&$result, &$data)
    {
        if (! is_null($data->fields)) {
            return true;
        }

        if (! is_resource($result)) {
            return false;
        }

        $n_fields = mysql_num_fields($result);
        for ($n = 0; $n < $n_fields; ++ $n) {
            $name = mysql_field_name($result, $n);
            $type = mysql_field_type($result, $n);
            $flags = mysql_field_flags($result, $n);

            $data->fields[$name] = array('id' => $name);
            $field =& $data->fields[$name];

            switch (strtoupper($type)) {
            case 'BOOL':
            case 'BOOLEAN':
                $field['type'] = 'bool';
                $field['subtype'] = null;
                $field['length'] = 0;
                break;

            case 'BIT':
            case 'TINYINT':
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'INTEGER':
            case 'BIGINT':
                $field['type'] = 'int';
                $field['subtype'] = strpos($flags, 'unsigned') !== false ? 'unsigned' : null;
                $field['length'] = 0;
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
                $field['subtype'] = null;
                $field['length'] = 0;
                break;

            case 'STRING':
            case 'CHAR':
            case 'VARCHAR':
            case 'BINARY':
            case 'VARBINARY':
            case 'TINYBLOB':
            case 'TINYTEXT':
            case 'BLOB':
            case 'TEXT':
            case 'MEDIUMBLOB':
            case 'MEDIUMTEXT':
            case 'LONGBLOB':
            case 'LONGTEXT':
                $field['type'] = 'string';
                if (strpos($flags, 'enum') !== false) {
                    $field['subtype'] = 'enum';
                } elseif (strpos($flags, 'set') !== false) {
                    $field['subtype'] = 'set';
                } else {
                    $field['subtype'] = null;
                }
                $field['length'] = mysql_field_len($result, $n) / 3;
                break;

            case 'ENUM':
                $field['type'] = 'string';
                $field['subtype'] = 'enum';
                $field['length'] = 0;
                break;

            case 'SET':
                $field['type'] = 'string';
                $field['subtype'] = 'set';
                $field['length'] = 0;
                break;

            case 'DATE':
                $field['type'] = 'string';
                $field['subtype'] = 'date';
                $field['length'] = 10;
                break;

            case 'TIME':
                $field['type'] = 'string';
                $field['subtype'] = 'time';
                $field['length'] = 8;
                break;

            case 'DATETIME':
                $field['type'] = 'string';
                $field['subtype'] = 'datetime';
                $field['length'] = 19;
                break;

            case 'TIMESTAMP':
                $field['type'] = 'int';
                $field['subtype'] = 'datetime';
                $field['length'] = 0;
                break;

            case 'YEAR':
                $field['type'] = 'string';
                $field['subtype'] = null; // Not implemented
                $field['length'] = 4;
                break;

            default:
                $data->setError("field type not supported ($type)");
            }

            $field['can_be_null'] = (bool) (strpos($flags, 'not_null') === false);
        }

        return ! is_null($data->fields);
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

        return $this->tryFillFields($result, $data);
    }

    function fillDetails(&$data)
    {
        /* Populate $fileds with the fields of 'set' or 'enum' subtype: only
         * this fields have some detail to be filled */
        $only_sets = create_function('$f', '$st=$f["subtype"];return $st=="set" || $st=="enum";');
        $fields = array_filter($data->fields, $only_sets);
        $in_clause = implode("','", array_keys($fields));

        if (empty($in_clause)) {
            // No set/enum fields found: no details to fill
            return true;
        } else {
            $in_clause = "('" . $in_clause . "')";
        }

        $result =& $this->runQuery ('SELECT COLUMN_NAME, COLUMN_TYPE',
                                    'FROM information_schema.COLUMNS',
                                    'WHERE `TABLE_SCHEMA`=' . $this->prepareValue($this->_database),
                                    'AND `TABLE_NAME`=' . $this->prepareValue($data->path),
                                    'AND `COLUMN_NAME` IN ' . $in_clause);
        if (! $result) {
            return false;
        }

        while ($row = mysql_fetch_assoc($result)) {
            $values = $row['COLUMN_TYPE'];
            $open_brace = strpos($values, '(');
            $close_brace = strrpos($values, ')');

            if ($open_brace !== false && $close_brace > $open_brace) {
                $id = $row['COLUMN_NAME'];
                $values = substr($values, $open_brace+1, $close_brace-$open_brace-1);
                $n = 0;
                for ($token = strtok ($values, "',"); $token !== false; $token = strtok ("',")) {
                    ++ $n;
                    $data->fields[$id]['choice' . $n] = $token;
                }

                $data->fields[$id]['choices'] = $n;
            }
        }

        return true;
    }

    function& get($filter, &$data)
    {
        if (($result =& $this->runQuery('SELECT * FROM', $this->prepareName($data->path), $filter)) === false) {
            $fake_null = null;
            return $fake_null;
        }

        $this->tryFillFields($result, $data);
        $rows = array();

        while ($row = mysql_fetch_assoc($result)) {
            $data->forceFieldType($row);
            $rows[$row[$data->primary_key]] =& $row;
            unset($row);
        }

        // To free or not to free
        // mysql_free_result($result);
        return $rows;
    }

    function insert(&$row, &$data)
    {
        if ($this->runQuery('INSERT INTO',
                            $this->prepareName($data->path),
                            $this->prepareFieldset($row)) === false) {
            return null;
        }

        return mysql_insert_id($this->_connection);
    }

    function update($filter, &$row, &$data)
    {
        $fieldset = $this->prepareFieldset($row);
        if (empty($fieldset)) {
            return true;
        }

        return $this->runQuery('UPDATE', $this->prepareName($data->path), $fieldset, $filter);
    }

    function delete($filter, &$data)
    {
        return $this->runQuery('DELETE FROM', $this->prepareName($data->path), $filter);
    }

    /**#@-*/
}

return new TIP_Mysql;

?>
