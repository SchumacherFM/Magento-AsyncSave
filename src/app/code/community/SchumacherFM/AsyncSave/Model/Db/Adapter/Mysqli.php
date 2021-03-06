<?php
/**
 * @category    SchumacherFM_AsyncSave
 * @package     Helper
 * @author      Cyrill at Schumacher dot fm / @SchumacherFM
 * @copyright   Copyright (c)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Maybe implement https://github.com/vrana/php-async/blob/master/MysqliAsync.php
 *
 * Class SchumacherFM_AsyncSave_Model_Db_Adapter_Mysqli
 */
class SchumacherFM_AsyncSave_Model_Db_Adapter_Mysqli extends Varien_Db_Adapter_Mysqli
{
    protected $_timeout = 10;
    protected $_connections = array();

    /**
     * This fails because Magento uses too many connections and one async query can
     * only use one connection. IMHO setting the max connection variable to a higher value
     * cannot be a solution.
     * The guy here http://sysmagazine.com/posts/155377/ stucks in the same situation ...
     *
     * Real async queries with one connection are not possible.
     *
     * Raw async query
     *
     * @param string $sql
     *
     * @return bool|mysqli_result
     * @throws Exception
     */
    public function raw_async_query($sql)
    {
//        $id                      = crc32($sql);
        echo "$sql\n";
        $_connections      = $this->getConnection();
        $this->_connection = null;
        $result            = $_connections->query($sql, MYSQLI_ASYNC);

        $_connections->close();

        if (false === $result) {
            Mage::throwException('Async MySQLi (errno ' . $_connections->errno . '): ' .
                $_connections->error
                . PHP_EOL . $sql);
        }
        if (true !== $result) {
            $result->close();
            //   Mage::throwException('Async MySQLi: no select queries possible');
        }
        $_connections->close();

//        $links = $errors = $reject = array();
//        mysqli_poll($links, $errors, $reject, $this->_timeout);
//        $return = $this->getConnection()->reap_async_query();
//        if (true !== $return) {
//            Zend_Debug::dump([$return,$sql]);
//        }

        return $result;
    }

    /**
     * Close all opened connections
     */
    function xx__destruct()
    {
        Zend_Debug::dump(count($this->_connections));
        foreach ($this->_connections as $connection) {
            $connection->close();
        }
    }

    public function clear_result()
    {
    }

    /**
     * @param null|int|float|string $val
     *
     * @return mixed|string
     */
    public function escapeValue($val)
    {
        if (null === $val) {
            $val = 'NULL';
        } elseif (true === is_numeric($val)) {
            $val = $this->castToNumeric($val);
        } else {
            $val = '\'' . $this->getConnection()->real_escape_string($val) . '\'';
        }
        return $val;
    }

    /**
     * Updates table rows with specified data based on a WHERE clause.
     *
     * @param  mixed $table The table to update.
     * @param  array $bind  Column-value pairs.
     * @param  mixed $where UPDATE WHERE clause(s).
     *
     * @return int          The number of affected rows.
     * @throws Zend_Db_Adapter_Exception
     */
    public function update($table, array $bind, $where = '')
    {
        /**
         * Build "col = ?" pairs for the statement,
         * except for Zend_Db_Expr which is treated literally.
         */
        $set = array();
        foreach ($bind as $col => $val) {
            if ($val instanceof Zend_Db_Expr) {
                $val = $val->__toString();
                unset($bind[$col]);
            }
            $set[] = $this->quoteIdentifier($col, true) . ' = ' . $this->escapeValue($val);
        }

        $where = $this->_whereExpr($where);

        /**
         * Build the UPDATE statement
         */
        $sql = "UPDATE "
            . $this->quoteIdentifier($table, true)
            . ' SET ' . implode(', ', $set)
            . (($where) ? " WHERE $where" : '');

        return $this->raw_async_query($sql);
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param mixed $table  The table to insert data into.
     * @param array $data   Column-value pairs or array of column-value pairs.
     * @param array $fields update fields pairs or values
     *
     * @return int The number of affected rows.
     * @throws Zend_Db_Exception
     */
    public function insertOnDuplicate($table, array $data, array $fields = array())
    {
        // extract and quote col names from the array keys
        $row    = reset($data); // get first element from data array
        $bind   = array(); // SQL bind array
        $values = array();

        if (is_array($row)) { // Array of column-value pairs
            $cols = array_keys($row);
            foreach ($data as $row) {
                if (array_diff($cols, array_keys($row))) {
                    throw new Zend_Db_Exception('Invalid data for insert');
                }
                $values[] = $this->_prepareInsertData($row, $bind);
            }
            unset($row);
        } else { // Column-value pairs
            $cols     = array_keys($data);
            $values[] = $this->_prepareInsertData($data, $bind);
        }

        $updateFields = array();
        if (empty($fields)) {
            $fields = $cols;
        }

        // quote column names
//        $cols = array_map(array($this, 'quoteIdentifier'), $cols);

        // prepare ON DUPLICATE KEY conditions
        foreach ($fields as $k => $v) {
            $field = $value = null;
            if (!is_numeric($k)) {
                $field = $this->quoteIdentifier($k);
                if ($v instanceof Zend_Db_Expr) {
                    $value = $v->__toString();
                } elseif (is_string($v)) {
                    $value = sprintf('VALUES(%s)', $this->quoteIdentifier($v));
                } elseif (is_numeric($v)) {
                    $value = $this->quoteInto('?', $v);
                }
            } elseif (is_string($v)) {
                $value = sprintf('VALUES(%s)', $this->quoteIdentifier($v));
                $field = $this->quoteIdentifier($v);
            }

            if ($field && $value) {
                $updateFields[] = sprintf('%s = %s', $field, $value);
            }
        }

        $insertSql = $this->_getInsertSqlQuery($table, $cols, $values);
        if ($updateFields) {
            $insertSql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateFields);
        }

        Zend_Debug::dump([$insertSql, $bind]);
        exit;

        // execute the statement and return the number of affected rows
        $stmt   = $this->query($insertSql, array_values($bind));
        $result = $stmt->rowCount();

        return $result;
    }

    /**
     * Return insert sql query
     *
     * @param string $tableName
     * @param array  $columns
     * @param array  $values
     *
     * @return string
     */
    protected function _getInsertSqlQuery($tableName, array $columns, array $values)
    {
        $tableName = $this->quoteIdentifier($tableName, true);
        $columns   = array_map(array($this, 'quoteIdentifier'), $columns);
        $columns   = implode(',', $columns);
        $values    = implode(', ', $values);

        $insertSql = sprintf('INSERT INTO %s (%s) VALUES %s', $tableName, $columns, $values);

        return $insertSql;
    }

    /**
     * Prepare insert data
     *
     * @param mixed $row
     * @param array $bind
     *
     * @return string
     */
    protected function _prepareInsertData($row, &$bind)
    {
        if (is_array($row)) {
            $line = array();
            foreach ($row as $value) {
                if ($value instanceof Zend_Db_Expr) {
                    $line[] = $value->__toString();
                } else {
                    $line[] = '?';
                    $bind[] = $value;
                }
            }
            $line = implode(', ', $line);
        } elseif ($row instanceof Zend_Db_Expr) {
            $line = $row->__toString();
        } else {
            $line   = '?';
            $bind[] = $row;
        }

        return sprintf('(%s)', $line);
    }

    /**
     * optimization, turning string values into real int/float
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function castToNumeric($value)
    {
        $value = (float)$value;
        if ($value === ((float)($value | 0))) { // int to float
            return ($value | 0); //int
        }
        return $value;
    }
}
