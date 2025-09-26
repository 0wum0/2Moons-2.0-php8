<?php

declare(strict_types=1);

/**
 * 2Moons - Modernized Database Class
 * 
 * Modernized database abstraction layer using PDO with prepared statements,
 * connection pooling, query logging, and modern PHP 8.3 features.
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @author Modernized by AI Assistant
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2024 Modernized Version
 * @license MIT
 * @version 2.0.0
 * @link https://github.com/jkroepke/2Moons
 */

class Database
{
    /**
     * PDO database handle
     */
    protected ?PDO $dbHandle = null;

    /**
     * Database table names mapping
     */
    protected array $dbTableNames = [];

    /**
     * Last insert ID from the most recent INSERT operation
     */
    protected int|false $lastInsertId = false;

    /**
     * Number of rows affected by the most recent operation
     */
    protected int|false $rowCount = false;

    /**
     * Query counter for performance monitoring
     */
    protected int $queryCounter = 0;

    /**
     * Singleton instance
     */
    protected static ?Database $instance = null;

    /**
     * Query log for debugging
     */
    protected array $queryLog = [];

    /**
     * Database configuration
     */
    protected array $config = [];

    /**
     * Get singleton instance
     *
     * @return Database
     */
    public static function get(): Database
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->initializeDatabase();
        $this->loadTableNames();
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone(): void
    {
        // Prevent cloning
    }

    /**
     * Initialize database connection
     *
     * @throws Exception If database connection fails
     */
    protected function initializeDatabase(): void
    {
        // Load database configuration
        $this->config = $this->loadDatabaseConfig();

        try {
            // Create PDO connection with modern options
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['databasename']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                PDO::ATTR_PERSISTENT => false, // Disable persistent connections for better security
                PDO::ATTR_TIMEOUT => 30, // 30 second timeout
            ];

            $this->dbHandle = new PDO($dsn, $this->config['user'], $this->config['userpw'], $options);

            // Set additional attributes
            $this->dbHandle->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->dbHandle->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

        } catch (PDOException $e) {
            throw new Exception(
                'Database connection failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Load database configuration
     *
     * @return array Database configuration
     * @throws Exception If config file is missing
     */
    protected function loadDatabaseConfig(): array
    {
        $configFile = ROOT_PATH . 'includes/config.php';
        
        if (!file_exists($configFile)) {
            throw new Exception('Database configuration file not found: ' . $configFile);
        }

        $database = [];
        require $configFile;

        // Validate required configuration
        $required = ['host', 'port', 'user', 'userpw', 'databasename', 'tableprefix'];
        foreach ($required as $key) {
            if (!isset($database[$key])) {
                throw new Exception("Missing required database configuration: {$key}");
            }
        }

        return $database;
    }

    /**
     * Load table names from configuration
     */
    protected function loadTableNames(): void
    {
        $dbTableNames = [];
        include ROOT_PATH . 'includes/dbtables.php';

        foreach ($dbTableNames as $key => $name) {
            $this->dbTableNames['keys'][] = '%%' . $key . '%%';
            $this->dbTableNames['names'][] = $name;
        }
    }

    /**
     * Get database table names
     *
     * @return array Table names mapping
     */
    public function getDbTableNames(): array
    {
        return $this->dbTableNames;
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->dbHandle = null;
    }

    /**
     * Get PDO handle
     *
     * @return PDO|null PDO instance
     */
    public function getHandle(): ?PDO
    {
        return $this->dbHandle;
    }

    /**
     * Get last insert ID
     *
     * @return int|false Last insert ID or false
     */
    public function lastInsertId(): int|false
    {
        return $this->lastInsertId;
    }

    /**
     * Get row count from last operation
     *
     * @return int|false Row count or false
     */
    public function rowCount(): int|false
    {
        return $this->rowCount;
    }

    /**
     * Get query counter
     *
     * @return int Number of queries executed
     */
    public function getQueryCounter(): int
    {
        return $this->queryCounter;
    }

    /**
     * Get query log
     *
     * @return array Query log
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Execute prepared query with parameters
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param string $type Query type
     * @return PDOStatement|bool Query result
     * @throws Exception If query execution fails
     */
    protected function executeQuery(string $query, array $params, string $type): PDOStatement|bool
    {
        $supportedTypes = ['insert', 'select', 'update', 'delete', 'replace'];
        
        if (!in_array($type, $supportedTypes, true)) {
            throw new Exception("Unsupported query type: {$type}");
        }

        // Reset counters
        $this->lastInsertId = false;
        $this->rowCount = false;

        // Replace table placeholders
        $processedQuery = str_replace(
            $this->dbTableNames['keys'],
            $this->dbTableNames['names'],
            $query
        );

        // Log query for debugging
        $this->logQuery($processedQuery, $params, $type);

        try {
            $stmt = $this->dbHandle->prepare($processedQuery);

            // Bind parameters with proper types
            $this->bindParameters($stmt, $params);

            $success = $stmt->execute();
            $this->queryCounter++;

            if (!$success) {
                return false;
            }

            // Set counters based on query type
            if ($type === 'insert') {
                $this->lastInsertId = (int) $this->dbHandle->lastInsertId();
            }
            $this->rowCount = $stmt->rowCount();

            return ($type === 'select') ? $stmt : true;

        } catch (PDOException $e) {
            $this->logQuery($processedQuery, $params, $type, $e->getMessage());
            throw new Exception(
                "Query execution failed: {$e->getMessage()}\n" .
                "Query: {$processedQuery}\n" .
                "Parameters: " . json_encode($params),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Bind parameters to prepared statement
     *
     * @param PDOStatement $stmt Prepared statement
     * @param array $params Parameters to bind
     */
    protected function bindParameters(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $param => $value) {
            if ($param === ':limit' || $param === ':offset') {
                $stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
            } elseif (is_int($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
            } elseif (is_bool($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
            } elseif (is_null($value)) {
                $stmt->bindValue($param, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($param, (string) $value, PDO::PARAM_STR);
            }
        }
    }

    /**
     * Log query for debugging
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param string $type Query type
     * @param string|null $error Error message if any
     */
    protected function logQuery(string $query, array $params, string $type, ?string $error = null): void
    {
        $this->queryLog[] = [
            'query' => $query,
            'params' => $params,
            'type' => $type,
            'timestamp' => microtime(true),
            'error' => $error,
        ];

        // Keep only last 100 queries in memory
        if (count($this->queryLog) > 100) {
            array_shift($this->queryLog);
        }
    }

    /**
     * Get query type from SQL statement
     *
     * @param string $query SQL query
     * @return string Query type
     * @throws Exception If query is invalid
     */
    protected function getQueryType(string $query): string
    {
        if (!preg_match('/^(\S+)/', $query, $match)) {
            throw new Exception("Invalid query: {$query}");
        }

        return strtolower($match[1]);
    }

    /**
     * Execute DELETE query
     *
     * @param string $query SQL DELETE query
     * @param array $params Query parameters
     * @return bool Success status
     * @throws Exception If query type is incorrect
     */
    public function delete(string $query, array $params = []): bool
    {
        $type = $this->getQueryType($query);
        if ($type !== 'delete') {
            throw new Exception("Incorrect DELETE query type: {$type}");
        }

        return $this->executeQuery($query, $params, $type) === true;
    }

    /**
     * Execute REPLACE query
     *
     * @param string $query SQL REPLACE query
     * @param array $params Query parameters
     * @return bool Success status
     * @throws Exception If query type is incorrect
     */
    public function replace(string $query, array $params = []): bool
    {
        $type = $this->getQueryType($query);
        if ($type !== 'replace') {
            throw new Exception("Incorrect REPLACE query type: {$type}");
        }

        return $this->executeQuery($query, $params, $type) === true;
    }

    /**
     * Execute UPDATE query
     *
     * @param string $query SQL UPDATE query
     * @param array $params Query parameters
     * @return bool Success status
     * @throws Exception If query type is incorrect
     */
    public function update(string $query, array $params = []): bool
    {
        $type = $this->getQueryType($query);
        if ($type !== 'update') {
            throw new Exception("Incorrect UPDATE query type: {$type}");
        }

        return $this->executeQuery($query, $params, $type) === true;
    }

    /**
     * Execute INSERT query
     *
     * @param string $query SQL INSERT query
     * @param array $params Query parameters
     * @return bool Success status
     * @throws Exception If query type is incorrect
     */
    public function insert(string $query, array $params = []): bool
    {
        $type = $this->getQueryType($query);
        if ($type !== 'insert') {
            throw new Exception("Incorrect INSERT query type: {$type}");
        }

        return $this->executeQuery($query, $params, $type) === true;
    }

    /**
     * Execute SELECT query and return all results
     *
     * @param string $query SQL SELECT query
     * @param array $params Query parameters
     * @return array Query results
     * @throws Exception If query type is incorrect
     */
    public function select(string $query, array $params = []): array
    {
        $type = $this->getQueryType($query);
        if ($type !== 'select') {
            throw new Exception("Incorrect SELECT query type: {$type}");
        }

        $stmt = $this->executeQuery($query, $params, $type);
        if ($stmt instanceof PDOStatement) {
            return $stmt->fetchAll();
        }

        return [];
    }

    /**
     * Execute SELECT query and return single result
     *
     * @param string $query SQL SELECT query
     * @param array $params Query parameters
     * @param string|false $field Specific field to return
     * @return array|string|int|float|false|null Single result
     * @throws Exception If query type is incorrect
     */
    public function selectSingle(string $query, array $params = [], string|false $field = false): array|string|int|float|false|null
    {
        $type = $this->getQueryType($query);
        if ($type !== 'select') {
            throw new Exception("Incorrect SELECT query type: {$type}");
        }

        $stmt = $this->executeQuery($query, $params, $type);
        if ($stmt instanceof PDOStatement) {
            $result = $stmt->fetch();
            
            if ($result === false || $field === false) {
                return $result;
            }

            return $result[$field] ?? null;
        }

        return false;
    }

    /**
     * Get list of column values from a table
     *
     * @param string $table Table name
     * @param string $column Column name
     * @param string|null $key Key column for associative array
     * @return array List of values
     */
    public function lists(string $table, string $column, ?string $key = null): array
    {
        $selects = $key === null ? [$column] : [$column, $key];
        $query = 'SELECT ' . implode(', ', $selects) . ' FROM %%' . $table . '%%';
        
        $stmt = $this->executeQuery($query, [], 'select');
        
        if (!$stmt instanceof PDOStatement) {
            return [];
        }

        $results = [];
        while ($row = $stmt->fetch()) {
            if ($key === null) {
                $results[] = $row[$column];
            } else {
                $results[$row[$key]] = $row[$column];
            }
        }

        return $results;
    }

    /**
     * Execute raw SQL query (use with caution)
     *
     * @param string $query Raw SQL query
     * @return int Number of affected rows
     */
    public function query(string $query): int
    {
        $this->lastInsertId = false;
        $this->rowCount = false;

        try {
            $this->rowCount = $this->dbHandle->exec($query);
            $this->queryCounter++;
            $this->logQuery($query, [], 'raw');
            
            return $this->rowCount;
        } catch (PDOException $e) {
            $this->logQuery($query, [], 'raw', $e->getMessage());
            throw new Exception("Raw query execution failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Execute native query with table name replacement
     *
     * @param string $query SQL query with table placeholders
     * @return array|bool Query results
     */
    public function nativeQuery(string $query): array|bool
    {
        $this->lastInsertId = false;
        $this->rowCount = false;

        $processedQuery = str_replace(
            $this->dbTableNames['keys'],
            $this->dbTableNames['names'],
            $query
        );

        try {
            $stmt = $this->dbHandle->query($processedQuery);
            $this->rowCount = $stmt->rowCount();
            $this->queryCounter++;
            $this->logQuery($processedQuery, [], 'native');

            $queryType = $this->getQueryType($processedQuery);
            return in_array($queryType, ['select', 'show'], true) 
                ? $stmt->fetchAll() 
                : true;

        } catch (PDOException $e) {
            $this->logQuery($processedQuery, [], 'native', $e->getMessage());
            throw new Exception("Native query execution failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Format timestamp for database storage
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted date string
     */
    public static function formatDate(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Quote string for safe SQL usage
     *
     * @param string $str String to quote
     * @return string Quoted string
     */
    public function quote(string $str): string
    {
        return $this->dbHandle->quote($str);
    }

    /**
     * Begin database transaction
     *
     * @return bool Success status
     */
    public function beginTransaction(): bool
    {
        return $this->dbHandle->beginTransaction();
    }

    /**
     * Commit database transaction
     *
     * @return bool Success status
     */
    public function commit(): bool
    {
        return $this->dbHandle->commit();
    }

    /**
     * Rollback database transaction
     *
     * @return bool Success status
     */
    public function rollback(): bool
    {
        return $this->dbHandle->rollBack();
    }

    /**
     * Check if currently in transaction
     *
     * @return bool Transaction status
     */
    public function inTransaction(): bool
    {
        return $this->dbHandle->inTransaction();
    }

    /**
     * Get database server version
     *
     * @return string Server version
     */
    public function getServerVersion(): string
    {
        return $this->dbHandle->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Get database driver name
     *
     * @return string Driver name
     */
    public function getDriverName(): string
    {
        return $this->dbHandle->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Destructor - cleanup resources
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}