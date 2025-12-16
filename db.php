<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Database Abstraction Layer
 * 
 * This file provides a secure database interface using prepared statements
 * to prevent SQL injection attacks. All database operations should go through
 * this class.
 */

// Include configuration
if (!defined('CORNERSTONE_APP')) {
    require_once __DIR__ . '/config.php';
}

class Database {
    private static $connection = null;
    private static $instance = null;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Get singleton instance of Database class
     * 
     * @return Database The database instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     * 
     * @return void
     */
    private function connect() {
        if (self::$connection === null) {
            try {
                self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                
                if (self::$connection->connect_error) {
                    throw new Exception("Connection failed: " . self::$connection->connect_error);
                }
                
                // Set charset to prevent encoding issues
                self::$connection->set_charset(DB_CHARSET);
                
            } catch (Exception $e) {
                if (DEV_MODE) {
                    die("Database Connection Error: " . $e->getMessage());
                } else {
                    die("Database connection failed. Please contact support.");
                }
            }
        }
    }
    
    /**
     * Get the database connection
     * 
     * @return mysqli The database connection
     */
    public static function getConnection() {
        if (self::$connection === null) {
            self::getInstance();
        }
        return self::$connection;
    }
    
    /**
     * Execute a prepared statement query
     * 
     * @param string $sql The SQL query with placeholders (?)
     * @param array $params Array of parameters to bind
     * @param string $types String of parameter types (i=integer, d=double, s=string, b=blob)
     * @return mysqli_stmt|false The prepared statement or false on failure
     */
    public static function query($sql, $params = [], $types = '') {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            if (DEV_MODE) {
                die("Prepare failed: " . $conn->error . "\nSQL: " . $sql);
            } else {
                error_log("Prepare failed: " . $conn->error . "\nSQL: " . $sql);
                return false;
            }
        }
        
        // Bind parameters if provided
        if (!empty($params)) {
            if (empty($types)) {
                // Auto-detect types if not provided
                $types = str_repeat('s', count($params)); // Default to string
            }
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute statement
        if (!$stmt->execute()) {
            if (DEV_MODE) {
                die("Execute failed: " . $stmt->error . "\nSQL: " . $sql);
            } else {
                error_log("Execute failed: " . $stmt->error . "\nSQL: " . $sql);
                return false;
            }
        }
        
        return $stmt;
    }
    
    /**
     * Fetch all rows from a query
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters to bind
     * @param string $types Parameter types
     * @return array Array of associative arrays
     */
    public static function fetchAll($sql, $params = [], $types = '') {
        $stmt = self::query($sql, $params, $types);
        
        if (!$stmt) {
            return [];
        }
        
        $result = $stmt->get_result();
        $rows = [];
        
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        $stmt->close();
        return $rows;
    }
    
    /**
     * Fetch a single row from a query
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters to bind
     * @param string $types Parameter types
     * @return array|null Associative array or null if no results
     */
    public static function fetchOne($sql, $params = [], $types = '') {
        $stmt = self::query($sql, $params, $types);
        
        if (!$stmt) {
            return null;
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        return $row;
    }
    
    /**
     * Execute an INSERT, UPDATE, or DELETE query
     * 
     * @param string $sql The SQL query
     * @param array $params Parameters to bind
     * @param string $types Parameter types
     * @return bool True on success, false on failure
     */
    public static function execute($sql, $params = [], $types = '') {
        $stmt = self::query($sql, $params, $types);
        
        if (!$stmt) {
            return false;
        }
        
        $success = $stmt->affected_rows >= 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Get the ID of the last inserted row
     * 
     * @return int The last insert ID
     */
    public static function lastInsertId() {
        return self::getConnection()->insert_id;
    }
    
    /**
     * Get the number of affected rows from the last query
     * 
     * @return int Number of affected rows
     */
    public static function affectedRows() {
        return self::getConnection()->affected_rows;
    }
    
    /**
     * Begin a database transaction
     * 
     * @return bool True on success
     */
    public static function beginTransaction() {
        return self::getConnection()->begin_transaction();
    }
    
    /**
     * Commit a database transaction
     * 
     * @return bool True on success
     */
    public static function commit() {
        return self::getConnection()->commit();
    }
    
    /**
     * Rollback a database transaction
     * 
     * @return bool True on success
     */
    public static function rollback() {
        return self::getConnection()->rollback();
    }
    
    /**
     * Escape a string for use in SQL queries (use prepared statements instead when possible)
     * 
     * @param string $string The string to escape
     * @return string The escaped string
     */
    public static function escape($string) {
        return self::getConnection()->real_escape_string($string);
    }
    
    /**
     * Close the database connection
     * 
     * @return void
     */
    public static function close() {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }
    
    /**
     * Check if a table exists in the database
     * 
     * @param string $tableName The table name to check
     * @return bool True if table exists
     */
    public static function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE ?";
        $result = self::fetchOne($sql, [$tableName], 's');
        return $result !== null;
    }
    
    /**
     * Get count of rows matching a query
     * 
     * @param string $sql The SQL query (should include COUNT(*))
     * @param array $params Parameters to bind
     * @param string $types Parameter types
     * @return int The count
     */
    public static function count($sql, $params = [], $types = '') {
        $result = self::fetchOne($sql, $params, $types);
        if ($result) {
            return (int) reset($result); // Get first column value
        }
        return 0;
    }
}

// Prevent cloning and unserialization
class_alias('Database', 'DB');

?>
