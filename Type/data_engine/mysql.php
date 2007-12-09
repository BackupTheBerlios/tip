<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * @package TIP
 * @subpackage DataEngine
 */

/**
 * The MySql data engine
 *
 * Interface to the MySql database.
 *
 * Some methods could be static, but I preferred to define them all as
 * non-static to avoid confusion (for instance, preparedName() could be
 * static but preparedValue not, because the connection property is needed).
 *
 * @package    TIP
 * @subpackage DataEngine
 * @tutorial   TIP/DataEngine/TIP_Mysql.cls
 */
class TIP_Mysql extends TIP_Data_Engine
{
    //{{{ Properties

    /**
     * The server address: it defaults to 'localhost'
     * @var string
     */
    protected $server = 'localhost';

    /**
     * The database name
     * @var string
     */
    protected $database = null;

    /**
     * The user name
     * @var string
     */
    protected $user = null;

    /**
     * The password of $user
     * @var string
     */
    protected $password = null;

    /**
     * The import/export format of dump()
     * @var string
     */
    protected $dump_format = '';

    //}}}
    //{{{ Costructor/destructor

    /**
     * Check the options
     *
     * Overridable static method that checks $options for missing or invalid
     * values and eventually corrects its content.
     *
     * @param  array &$options Properties values
     * @return bool            true on success or false on error
     */
    static protected function checkOptions(&$options)
    {
        return parent::checkOptions($options) && isset($options['database'], $options['user'], $options['password']);
    }

    /**
     * Constructor
     *
     * Initializes a TIP_Mysql instance.
     *
     * $options inherits the TIP_Type properties, and add the following:
     * - $options['server']:   server address
     * - $options['database']: database name (required)
     * - $options['user']:     user name (required)
     * - $options['password']: user password (required)
     *
     * @param array $options Properties values
     */
    protected function __construct($options)
    {
        parent::__construct($options);
        $this->_connection = mysql_pconnect($this->server, $this->user, $this->password);

        if (!$this->_connection || !mysql_select_db($this->database, $this->_connection)) {
            TIP::error(mysql_error($this->_connection));
        } else {
            $this->_query('SET CHARACTER SET utf8');
        }
    }

    //}}}
    //{{{ TIP_Data_Engine implementation

    public function preparedName($name)
    {
        if (is_array($name)) {
            $self = array(&$this, __FUNCTION__);
            return implode(',', array_map($self, $name));
        } elseif ($name == '*') {
            // These special names must not be backticked
            return $name;
        }
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function preparedValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        } elseif (is_string($value)) {
            return "'" . mysql_real_escape_string($value, $this->_connection) . "'";
        } elseif (is_array($value)) {
            $self = array(&$this, __FUNCTION__);
            return implode(',', array_map($self, $value));
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        $type = gettype($value);
        TIP::error("type not recognized ($type)");
        return null;
    }

    public function fillFields(&$data)
    {
        $result = $this->_query('SHOW FULL COLUMNS FROM', $this->preparedName($data->getProperty('path')));
        if (!$result) {
            return false;
        }

        $fields =& $data->getFieldsRef();
        while ($row = mysql_fetch_assoc($result)) {
            $name = $row['Field'];
            $field = array('id' => $name);
            $type = $row['Type'];

            $open_brace = strpos($type, '(');
            if ($open_brace !== false) {
                $flags = substr($type, $open_brace+1, -1);
                $type = substr($type, 0, $open_brace);
            }

            $this->_mapType($field, $type);
            if ($field['type'] == 'string' && !empty($flags)) {
                $field['length'] = (int) $flags;
            }

            $field['default'] = $row['Default'];
            settype($field['default'], $field['type']);

            $field['automatic'] = strpos($row['Extra'], 'auto_increment') !== false;

            if (($field['widget'] == 'set' || $field['widget'] == 'enum') &&
                is_array($field['choices'] = @explode(',', $flags))) {
                array_walk($field['choices'], create_function('&$v', '$v=trim($v,"\'");'));
            }

            $field['info'] = $row['Comment'];
            $field['can_be_null'] = $row['Null'] == 'YES';
            $fields[$name] = $field;
        }

        return true;
    }

    public function &select(&$data, $filter, $fields)
    {
        $table = $data->getProperty('path');
        $joins = $data->getProperty('joins');

        if (isset($joins)) {
            // Compute the joins (the main table is manually prepended)
            array_walk($joins, create_function('&$v,$s', '$v["slave_table"]=$s; $v["master_table"]="'.$table.'";'));

            // Get the joined tables and prepend the main one
            $tables = array_keys($joins);
            $sets = array_map(create_function('$v', 'return @$v["fieldset"];'), $joins);
            array_unshift($tables, $table);
            array_unshift($sets, $fields);

            $fieldset = $this->_preparedFieldset($sets, $tables);
            $source = $this->preparedName($table) . $this->_preparedJoin($joins);
        } else {
            $fieldset = $this->_preparedFieldset($fields);
            $source = $this->preparedName($table);
        }

        $result = $this->_query('SELECT', $fieldset, 'FROM', $source, $filter);
        if ($result === false) {
            $result = null;
            return $result;
        }

        $this->_tryFillFields($data, $result);
        $primary_key = $data->getProperty('primary_key');
        $rows = array();
        while ($row = mysql_fetch_assoc($result)) {
            $rows[$row[$primary_key]] =& $row;
            unset($row);
        }

        // To free or not to free
        mysql_free_result($result);
        return $rows;
    }

    public function insert(&$data, &$rows)
    {
        $result = $this->_query('INSERT INTO', $this->preparedName($data->getProperty('path')),
                                '(' . $this->preparedName(array_keys($rows[0])) . ')',
                                'VALUES', $this->_preparedContent($rows));
        if ($result === false) {
            return null;
        }

        return mysql_insert_id($this->_connection);
    }

    public function update(&$data, $filter, &$row)
    {
        return $this->_query('UPDATE', $this->preparedName($data->getProperty('path')),
                             'SET', $this->_preparedSet(array_keys($row), $row),
                             $filter);
    }

    public function delete(&$data, $filter)
    {
        return $this->_query('DELETE FROM', $this->preparedName($data->getProperty('path')),
                             $filter);
    }

    public function dump($root)
    {
        $result =& $this->_query('SHOW TABLES');
        if ($result === false) {
            return false;
        }

        if (substr($root, -1) != DIRECTORY_SEPARATOR) {
            $root .= DIRECTORY_SEPARATOR;
        }

        if (!is_writable($root)) {
            return false;
        }

        while ($row = mysql_fetch_row($result)) {
            $table = $row[0];
            $file = $root . $table;
            if (file_exists($file)) {
                unlink($file);
            }
            if ($this->_query("SELECT * INTO OUTFILE '$file' $this->dump_format FROM `$table`") === false) {
                return false;
            }
        }

        return true;
    }

    //}}}
    //{{{ Internal properties

    /**
     * The connection resource created in the constructor
     * @var resource
     */
    private $_connection;

    //}}}
    //{{{ Internal methods

    private function _query()
    {
        $pieces = func_get_args();
        $query = implode(' ', $pieces);
        $result = mysql_query($query, $this->_connection);
        if ($result === false) {
            TIP::error(mysql_error($this->_connection) . " ($query)");
        }
        return $result;
    }

    private function _mapType(&$field, $type)
    {
        // Fallback values
        $field['type'] = 'string';
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
    }

    private function _tryFillFields(&$data, &$resource)
    {
        $fields =& $data->getFieldsRef();
        if (isset($fields)) {
            return true;
        }

        if (!is_resource($resource)) {
            return false;
        }

        $n_fields = mysql_num_fields($resource);
        for ($n = 0; $n < $n_fields; ++ $n) {
            if (mysql_field_table($resource, $n) != $data->getProperty('path')) {
                continue;
            }

            $name = mysql_field_name($resource, $n);
            $type = strtoupper(mysql_field_type($resource, $n));
            $flags = strtoupper(mysql_field_flags($resource, $n));
            $length = mysql_field_len($resource, $n);

            if (strpos($flags, 'ENUM') !== false) {
                $type = 'ENUM';
            } elseif (strpos($flags, 'SET') !== false) {
                $type = 'SET';
            }

            $fields[$name] = array('id' => $name);
            $field =& $fields[$name];
            $this->_mapType($field, $type);

            if ($type == 'string' && $length > 0) {
                // UTF-8 chars are 3 bytes long
                $field['length'] = mysql_field_len($resource, $n) / 3;
            }

            $field['can_be_null'] = (bool) (strpos($flags, 'NOT_NULL') === false);
        }

        return true;
    }

    /**
     * Prepare one or more rows for an INSERT...VALUES... query
     *
     * Similar to _prepareValue(), but works only on arrays and arrays of
     * array. Furthermore, it encloses the last nested array in braces: this is
     * needed by the multiple insert queries.
     *
     * @param  array  $row A row or an array of rows
     * @return string      $row prepared for an INSERT...VALUES... query
     */
    private function _preparedContent($row)
    {
        // Recurse if the first $row element is an array: this means $row is an
        // array of arrays, that is an array or rows
        if (is_array(reset($row))) {
            $self = array(&$this, __FUNCTION__);
            return implode(',', array_map($self, $row));
        }
        return '(' . $this->preparedValue($row) . ')';
    }

    /**
     * Prepare a set
     *
     * Returns a prepared set, that is an association between fields and values.
     *
     * This is used in UPDATE... queries.
     *
     * @param  array|string $field A field or an array of fields
     * @param  array|string $value A value or an array of values
     * @return string              The prepared set
     */
    private function _preparedSet($field, $value)
    {
        if (is_array($field)) {
            if (!is_array($value)) {
                $value = array_fill(0, count($field), $value);
            }
            $self = array(&$this, __FUNCTION__);
            return implode(',', array_map($self, $field, $value));
        }

        return $this->preparedName($field) . '=' . $this->preparedValue($value);
    }

    /**
     * Prepare a field
     *
     * Returns a prepared field in the format needed by a SELECT... query.
     *
     * If $field is an array, $table can be a string, in which case all these
     * fields are considered part of $table.
     *
     * Instead, $alias must ever be array if $field is array or string if
     * $field is string. In every cases, however, $alias can be null if you
     * don't need such feature.
     *
     * @param  array|string      $field A field or an array of fields
     * @param  array|string|null $table Table where the field is
     * @param  array|string|null $alias Optional alias of the prepared field
     * @return string                   The prepared field
     */
    private function _preparedField($field, $table = null, $alias = null)
    {
        if (is_array($field)) {
            if (!is_array($alias)) {
                $alias = array_fill(0, count($field), $alias);
            }
            if (!is_array($table)) {
                $table = array_fill(0, count($field), $table);
            }
            $self = array(&$this, __FUNCTION__);
            return implode(',', array_map($self, $field, $table, $alias));
        }

        $result = $this->preparedName($field);
        if (!empty($table)) {
            $result = $this->preparedName($table) . '.' . $result;
        }
        if (is_string($alias)) {
            $result .= ' AS ' . $this->preparedName($alias);
        }
        return $result;
    }

    /**
     * Prepare a fieldset
     *
     * Supersets _preparedField(), providing field preparation on a table basis.
     *
     * $fields must be an array of field names of $table (or the master table
     * if $table is null). To provide aliases, you can specify them ever
     * in the $fields argument as key of the field names.
     *
     * Furthermore, $table can be an array of table names, in which case you
     * must specify in $fields an array of array of field names, in the the
     * same order of the $table array.
     *
     * @param  array|string      $fields A field or an array of fields
     * @param  array|string|null $table  Table where the field is
     * @return string                    The prepared fieldset
     */
    private function _preparedFieldset($fields, $table = null)
    {
        if (is_null($fields)) {
            return $this->_preparedField('*', $table);
        } elseif (is_array($table)) {
            $self = array(&$this, __FUNCTION__);
            return implode(',', array_map($self, $fields, $table));
        }

        return $this->_preparedField($fields, $table, array_keys($fields));
    }

    /**
     * Prepare a join definition
     *
     * Prepares a join definition to be used in a SELECT... query.
     *
     * The $join argument is an associative array which has at least for keys:
     *
     * - 'master_table', containing the name of the master table
     * - 'master_field', containing a field name in the master table
     * - 'slave_table',  containing the name of the slave table
     * - 'slave_field',  containing a field name in the slave table
     *
     * Also, $join can be an array of such structure, in which key the return
     * value will include all the join definitions.
     *
     * @param  array  $join An array of join definitions
     * @return string       The prepared join structure
     */
    private function _preparedJoin($join)
    {
        // Recurse if the first $join element is an array: this means $join is
        // an array of arrays, that is an array or join definition
        if (is_array(reset($join))) {
            $self = array(&$this, __FUNCTION__);
            return implode(' ', array_map($self, $join));
        }

        extract($join, EXTR_REFS);
        return 'LEFT JOIN ' . $this->preparedName($slave_table) .
               ' ON ' . $this->_preparedField($master, $master_table) . '=' .
                        $this->_preparedField($slave, $slave_table);
    }

    //}}}
}
?>
