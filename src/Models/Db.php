<?php

namespace App\Models;

use \PDO;
use PDOException;

class Db
{
    private $host = '127.0.0.1';
    private $user = 'medai';
    private $pass = 'medaipass';
    private $dbname = 'medai';

    const IDLE_TIMEOUT = 59;
    // Return no results
    const QUERY_RESULTS_NONE = 0;

    // Return all the rows as array of associative arrays
    const QUERY_RESULTS_ROWS = 1;

    // Return only the first row as an assciative array
    const QUERY_RESULTS_ROW = 2;

    // Return the value with the alias "v" from the first row
    const QUERY_RESULTS_SINGLE_VALUE = 3;

    // Return all the rows as array of objects
    const QUERY_RESULTS_OBJECT_ROWS = 4;

    // Return first row as array of objects
    const QUERY_RESULTS_OBJECT_ROW = 5;

    // For each row, build an array of $row['k'] => $row['v']
    const QUERY_RESULTS_DICTIONARY = 6;

    // Return all rows after filtering then with a callback
    const QUERY_RESULTS_ROWS_CALLBACK = 7;

    // Return the last insert id(auto increment)
    const QUERY_RESULTS_LAST_INSERT_ID = 8;

    // Return the number of rows affected by the operation
    const QUERY_RESULTS_ROWS_AFFECTED = 9;

    const LAST_INSERT_ID_PLACE_HOLDER = '##LAST_INSERT_ID##';


    private $_nullableFields;


    private $_connection;
    private static $instance = null;

    private function __construct()
    {
        $this->_connection = NULL;
    }


    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Db();
            try {
                self::$instance->connect();
            } catch (\Exception $e) {
                error_log('unable to connect to db - ' . $e->getMessage());
                self::$instance = null;
            }
        }
        return self::$instance;
    }

    private function connect()
    {
        if (isset($this->_connection))
            return $this->_connection;

        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;

            $this->_connection = new PDO($dsn, $this->user, $this->pass);
            $this->_connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            //$this->_connection->query('SET NAMES utf8');
            return $this->_connection;
        } catch (PDOException $e) {
            error_log('unable to connect to db');
            $this->_connection = null;
            throw $e;
        }
    }

    public function isConnected()
    {
        return (isset($this->_connection) && $this->_connection instanceof PDO);
    }

    public function connectionHasExpired()
    {
        return (!empty($this->_expirationTime) && $this->_expirationTime < time());
    }

    public function query($query, $params = array())
    {
        $stmt = NULL;
        if (($this->connectionHasExpired())) {
            $this->_connection = NULL;
            error_log('open connection timeout - reconnecting ');
            $this->connect();
        }
        if (!$this->isConnected()) {
            error_log( 'unable to execute query on uninitialized connection');
            return false;
        }

        $db = &$this->_connection;
        error_log($query . ' --- params: '. json_encode($params));
        $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        $result = $stmt->execute($params);
        if ($stmt->errorCode() != '00000') {
            $errorInfo = $stmt->errorInfo();
            error_log('error in sql - ' . $query . ' - ' . $errorInfo[2] . 'params: ' . json_encode($params));
            return false;
        }
        return $stmt;
    }

    public function select($query, array $params, $resultType, $callback = array(), $pagination = NULL)
    {
        if (!empty($pagination)) {
            $stmt = $this->query($query, $params);
            $pagination->records($stmt->rowCount());
            $query .= " LIMIT " . (($pagination->get_page() - 1) * $pagination->get_per_page()) . ", " . $pagination->get_per_page();
        }

        $stmt = $this->query($query, $params);
        $result = false;
        if ($stmt) {
            switch ($resultType) {
                case self::QUERY_RESULTS_ROWS:
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;

                case self::QUERY_RESULTS_ROW:
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;

                case self::QUERY_RESULTS_ROWS_CALLBACK:
                    if (is_callable($callback)) {
                        $ret = array();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $res = call_user_func($callback, $row);
                            if ($res)
                                $ret[] = $res;
                        }
                        $result = $ret;
                    } else
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;


                case self::QUERY_RESULTS_OBJECT_ROWS:
                    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
                    break;

                case self::QUERY_RESULTS_OBJECT_ROW:
                    $result = $stmt->fetch(PDO::FETCH_OBJ);
                    break;

                case self::QUERY_RESULTS_DICTIONARY:
                    $result = array();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $result[$row['k']] = $row['v'];
                    }
                    break;

                case self::QUERY_RESULTS_SINGLE_VALUE:
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result = ($row !== FALSE) ? $row['v'] : NULL;
                    break;

                case self::QUERY_RESULTS_LAST_INSERT_ID:
                    $result = $this->_connection->lastInsertId();
                    break;

                case self::QUERY_RESULTS_ROWS_AFFECTED:
                    $result = $stmt->rowCount();
                    break;

                case self::QUERY_RESULTS_NONE:
                    $result = null;
                    break;
            }
        }
        return $result;
    }

    public function insert($table, $data)
    {
        $query = " INSERT INTO $table SET ";
        $params = array();
        $sets = array();
        $data = self::filterData($table, $data);
        foreach ($data as $key => $value) {
            $sets[] = " `$key` = :$key";
            $params[$key] = $value;
        }
        if (empty($sets)) {
            error_log('unable to insert empty set to table ' . $table, );
        }

        $query .= implode(',', $sets);
        $stmt = $this->query($query, $params);
        $lastInsertId = $this->_connection->lastInsertId();
        return isset($lastInsertId) ? $lastInsertId : $stmt;
    }

    public function replace($table, $data)
    {
        $query = " REPLACE INTO $table SET ";
        $params = array();
        $sets = array();
        $data = self::filterData($table, $data);
        foreach ($data as $key => $value) {
            $sets[] = " `$key` = :$key";
            $params[$key] = $value;
        }
        if (empty($sets)) {
            error_log('unable to insert empty set to table ' . $table);
        }

        $query .= implode(',', $sets);
        $stmt = $this->query($query, $params);
        return ($stmt) ? $stmt->rowCount() : $stmt;
    }

    public function update($table, $data, $baseWhere = '', array $match = array(), $limit = NULL)
    {
        if (count($data) == 0)
            return false;
        $query = " UPDATE $table SET ";
        $params = array();
        $sets = array();
        $data = self::filterData($table, $data);
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $sets[] = " `$key` = NULL";
            } else {
                $sets[] = " `$key` = :$key";
                $params[$key] = $value;
            }
        }

        $where = array();
        $where[] = (!empty($baseWhere)) ? $baseWhere : ' 1=1  ';
        foreach ($match as $key => $value) {
            $tmp = preg_split('/\./', $key);
            $param = '__match_' . (isset($tmp[1]) ? $tmp[1] : $tmp[0]);
            if (is_null($value)) {
                $where[] = "AND $key IS NULL";
            } else {
                $where[] = "AND $key = :$param";
                $params[$param] = $value;
            }
        }
        if (empty($sets)) {
            error_log('unable to update empty set in table ' . $table);
        }

        $query .= implode(',', $sets) . " WHERE " . implode(' ', $where);

        if (!empty($limit))
            $query .= ' LIMIT ' . intval($limit);
        $stmt = $this->query($query, $params);
        return ($stmt) ? $stmt->rowCount() : $stmt;
    }

    public function delete($table, $whereStr = '', array $whereParams = array())
    {
        $query = " DELETE FROM $table WHERE ";
        $params = array();
        $sets = array();
        if (empty($whereStr) && empty($whereParams)) {
            error_log( 'DELETE statement with no condition is not allowed');
        }


        if (!empty($whereStr)) {
            $sets[] = $whereStr;
        }

        if (!empty($whereParams)) {
            foreach ($whereParams as $key => $value) {
                if (is_null($value)) {
                    $sets[] = "$key IS NULL";
                } elseif (is_array($value)) {
                    $in = '(' . implode(',', $value) . ')';
                    $sets[] = " `$key` IN $in";
                } else {
                    $sets[] = " `$key` = :$key";
                    $params[$key] = $value;
                }

            }
        }

        $query .= implode(' AND ', $sets);
        $stmt = $this->query($query, $params);
        return ($stmt) ? $stmt->rowCount() : $stmt;
    }

    public function filterData($table_name, $data)
    {

        $splTable = explode(' ', $table_name);
        if (count($splTable) > 1)
            $table_name = $splTable[0];

        if (empty($this->_allowedFields[$table_name])) {
            $sql = "SHOW FIELDS FROM $table_name ";
            $tableFields = $this->select($sql, array(), self::QUERY_RESULTS_ROWS);
            $fields = $nullableFields = array();
            foreach ($tableFields as $row) {
                $fields[$row['Field']] = $row['Field'];
                if ($row['Null'] == 'YES')
                    $nullableFields[$row['Field']] = $row['Field'];
            }
            $this->_nullableFields[$table_name] = $nullableFields;
            $this->_allowedFields[$table_name] = $fields;
        } else {
            $fields = $this->_allowedFields[$table_name];
            $nullableFields = $this->_nullableFields[$table_name];
        }
        //var_dump($data);
        $result = array();
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($data[$field] == self::LAST_INSERT_ID_PLACE_HOLDER)
                    $data[$field] = $this->_connection->lastInsertId();
                if (!is_null($data[$field]) || isset($nullableFields[$field]))
                    $result[$field] = $data[$field];
            }
        }
        //var_dump($result);
        return $result;
    }



    function disconnect()
    {

        $this->_connection = null;
    }
}

