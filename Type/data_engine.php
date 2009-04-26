<?php
/* vim: set expandtab shiftwidth=4 softtabstop=4 tabstop=4 foldmethod=marker: */

/**
 * TIP_Data_Engine definition file
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
 * Base class for data engines
 *
 * Provides a common interface to access any data requested by TIP.
 *
 * @package  TIP
 */
abstract class TIP_Data_Engine extends TIP_Type
{
    //{{{ Methods

    /**
     * Start a transaction
     *
     * If a transaction is yet started, simply returns true without
     * starting a new transaction (nested transactions are not allowed).
     *
     * @return bool true on success or false on errors
     */
    public function startTransaction()
    {
        ++ $this->_transaction_ref;
        if ($this->_transaction_ref > 1) {
            return true;
        }

        return $this->transaction(TIP_TRANSACTION_START);
    }

    /**
     * End a transaction
     *
     * End the current transaction. If there's no active transaction,
     * raise an error and return false.
     *
     * The transaction is really ended when the last started transaction
     * is closed. For instance, if you call startTransaction() twice and
     * endTransaction() once, the data will be never committed.
     * 
     * The error flag indicates if the transaction must be rolled back
     * ($commit = true) of committed ($commit = false).
     *
     * @param  bool $commit Commit (if true) or rollback (if false)
     * @return bool         true on success or false on errors
     */
    public function endTransaction($commit)
    {
        if ($this->_transaction_ref == 0) {
            TIP::error('ending a never started transaction');
            return false;
        }

        -- $this->_transaction_ref;
        if ($this->_transaction_ref > 0) {
            return true;
        }

        $action = $commit ? TIP_TRANSACTION_COMMIT : TIP_TRANSACTION_ROLLBACK;
        return $this->transaction($action);
    }

    //}}}
    //{{{ Interface

    /**
     * Prepare names for a query
     *
     * Prepares one or more identifiers to be inserted in a query.
     * The TIP_Mysql::preparedName() method, for example, backticks
     * $name if $name does not start yet with a backtick.
     *
     * If $name is an array, the preparedName() process is applied
     * recursively for every item and a comma separated string of
     * prepared names is returned.
     *
     * The $domain value is a engine depended feature to identify
     * the context where $name is significant. In SQL based engines,
     * for instance, it is the table name.
     *
     * @param  string|array $name   The name or array of names to prepare
     * @param  string|null  $domain The domain $name is part of
     * @return string               $name prepared for the query
     */
    abstract public function preparedName($name, $domain = null);

    /**
     * Prepare values for a query
     *
     * Prepares one or more values to be inserted in a query.
     * The TIP_Mysql engine, for instance, escapes the values throught
     * mysql_real_escape_string(). Also, if $value is null, 'NULL' is returned.
     *
     * If $value is an array, a comma separated string of prepared values is
     * returned.
     *
     * @param  mixed|array $value The value or array of values to prepare
     * @return string             $value prepared for the query
     */
    abstract public function preparedValue($value);

    /**
     * Perform a query
     *
     * This is the core function of a database engine. Any list
     * of arguments should be accepted, usually imploding their
     * string representation using a space as glue.
     *
     * @return resource  The result of the query (engine dependent)
     */
    abstract public function query();

    /**
     * Fill the fields structure
     *
     * Fills the $_fields internal property of the TIP_Data object: read the
     * TIP_Data documentation for further details.
     *
     * @param  TIP_Data $data The data context
     * @return bool           true on success or false on errors
     */
    abstract public function fillFields(&$data);

    /**
     * Read data
     *
     * Gets the rows that satisfy the $filter conditions. Only the fields
     * specified by $data->_fieldset must be read. If this fieldset is
     * null, all the fields are assumed.
     *
     * The result must be an empty array if there's no matching rows.
     * The type of the field values is not important: the resulting array will
     * be casted to the proper type in the TIP_Data object.
     *
     * @param TIP_Data   &$data   The data context
     * @param string      $filter The filter conditions
     * @param array|null  $fields An array of field ids to get, or null for all fields
     * @return array|null         A reference to an array of rows matching the
     *                            specified conditions or null on errors
     */
    abstract public function &select(&$data, $filter, $fields);

    /**
     * Insert new rows
     *
     * Inserts new rows. The autoincrement value of the last row is returned.
     * If the primary keys are specified in $rows and a row with any primary
     * key yet exists, this function must fail.
     *
     * The rows must be homogeneus: if the first row has five fields, all the
     * other rows must have the same five fields (obviously, with different
     * values).
     *
     * Notice $rows can be an empty array, in which case a new empty row must
     * be added without errors. In this case, the row must be filled with
     * its default values.
     *
     * @param  TIP_Data &$data The data context
     * @param  array    &$rows An array of rows
     * @return int|null        The last autoincrement value or null on errors
     */
    abstract public function insert(&$data, &$rows);

    /**
     * Update data
     *
     * Updates the rows that match the $filter conditions using the new $row
     * contents. To leave the fields untouched, simply do not specify these
     * fields in $row.
     *
     * @param  TIP_Data &$data   The data context
     * @param  string    $filter The filter conditions
     * @param  array    &$row    The field=>value pairs to update
     * @return bool              true on success or false on errors
     */
    abstract public function update(&$data, $filter, &$row);

    /**
     * Delete data
     *
     * Removes the rows that match the $filter conditions.
     *
     * @param  TIP_Data &$data   The data context
     * @param  string    $filter The filter conditions
     * @return bool              true on success or false on errors
     */
    abstract public function delete (&$data, $filter);

    /**
     * Dump a whole database content
     *
     * @param  string $root The base path where to store the result
     * @return bool         true on success or false otherwise
     */
    abstract public function dump($root);

    /**
     * Start a transaction
     *
     * @param  TIP_TRANSACTION_... $action The action to perform
     * @return bool                        true on success or false on errors
     */
    abstract protected function transaction($action);

    //}}}
    //{{{ Internal properties

    /**
     * Transaction reference counter
     * @var int
     * @internal
     */
    private $_transaction_ref = 0;

    //}}}
}
?>
