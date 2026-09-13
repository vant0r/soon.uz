<?php
/**
 * Database Connection Handler
 * Uses PDO with prepared statements for security
 */

require_once __DIR__ . '/config.php';

/**
 * Get PDO database connection
 * @return PDO Database connection
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error but don't expose details to user
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            die('Server xatoligi yuz berdi. Iltimos keyinroq qayta urinib ko\'ring.');
        }
    }
    
    return $pdo;
}

/**
 * Execute a query with parameters safely
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement
 */
function dbQuery($sql, $params = []) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch single row
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array|null
 */
function dbFetchOne($sql, $params = []) {
    $result = dbQuery($sql, $params)->fetch();
    return $result ?: null;
}

/**
 * Fetch all rows
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array
 */
function dbFetchAll($sql, $params = []) {
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Insert and return last insert ID
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int Last insert ID
 */
function dbInsert($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    dbQuery($sql, $data);
    return getDbConnection()->lastInsertId();
}

/**
 * Update records
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $where WHERE clause (e.g., "id = :id")
 * @param array $whereParams Parameters for WHERE clause
 * @return int Number of affected rows
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    $setParts = [];
    foreach (array_keys($data) as $col) {
        $setParts[] = "{$col} = :{$col}";
    }
    $setClause = implode(', ', $setParts);
    $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
    $params = array_merge($data, $whereParams);
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Delete records
 * @param string $table Table name
 * @param string $where WHERE clause
 * @param array $params Parameters
 * @return int Number of affected rows
 */
function dbDelete($table, $where, $params = []) {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Begin transaction
 */
function dbBeginTransaction() {
    getDbConnection()->beginTransaction();
}

/**
 * Commit transaction
 */
function dbCommit() {
    getDbConnection()->commit();
}

/**
 * Rollback transaction
 */
function dbRollback() {
    getDbConnection()->rollBack();
}
