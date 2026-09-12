<?php
/**
 * API Configuration and Common Functions
 * Handles authentication, rate limiting, and JSON responses
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Set JSON content type for all API responses
header('Content-Type: application/json');

// Disable session for API (uses token auth only)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

/**
 * Send JSON response
 */
function apiResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function apiError($message, $statusCode, $fields = null) {
    $response = ['success' => false, 'error' => $message];
    if ($fields !== null) {
        $response['fields'] = $fields;
    }
    apiResponse($response, $statusCode);
}

/**
 * Get rate limit key (by IP or token)
 */
function getRateLimitKey($useToken = false, $tokenUserId = null) {
    if ($useToken && $tokenUserId) {
        return 'token_' . $tokenUserId;
    }
    return 'ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * Check rate limit
 * @return bool true if allowed, false if exceeded
 */
function checkRateLimit($limit, $windowSeconds = 60, $key = null) {
    if ($key === null) {
        $key = getRateLimitKey();
    }
    
    $pdo = getDbConnection();
    $now = time();
    $windowStart = $now - $windowSeconds;
    
    // Clean old entries
    $pdo->prepare("DELETE FROM rate_limits WHERE created_at < FROM_UNIXTIME(?)")
        ->execute([$windowStart]);
    
    // Count recent requests
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE `key` = ? AND created_at > FROM_UNIXTIME(?)");
    $stmt->execute([$key, $windowStart]);
    $count = $stmt->fetchColumn();
    
    if ($count >= $limit) {
        return false;
    }
    
    // Record this request
    $pdo->prepare("INSERT INTO rate_limits (`key`, created_at) VALUES (?, FROM_UNIXTIME(?))")
        ->execute([$key, $now]);
    
    return true;
}

/**
 * Validate and decode JWT-like token from api_tokens table
 * @return array|false ['user_id' => int] or false on failure
 */
function validateApiToken($tokenHeader) {
    if (empty($tokenHeader)) {
        return false;
    }
    
    $parts = explode(' ', $tokenHeader);
    if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') {
        return false;
    }
    
    $token = $parts[1];
    
    // Look up token in database
    $tokenData = dbFetchOne(
        "SELECT user_id, expires_at FROM api_tokens WHERE token = ?",
        [$token]
    );
    
    if (!$tokenData) {
        return false;
    }
    
    // Check expiration
    if (strtotime($tokenData['expires_at']) < time()) {
        // Delete expired token
        dbDelete('api_tokens', 'token = ?', [$token]);
        return false;
    }
    
    return ['user_id' => (int)$tokenData['user_id']];
}

/**
 * Require authentication
 * @return int user_id
 */
function requireAuth() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $tokenData = validateApiToken($authHeader);
    
    if (!$tokenData) {
        apiError('Autentifikatsiya talab qilinadi. Token topilmadi yoki muddati tugagan.', 401);
    }
    
    return $tokenData['user_id'];
}

/**
 * Verify ownership of a resource
 * @param string $table Table name
 * @param int $id Resource ID
 * @param int $userId Current user ID
 */
function verifyOwnership($table, $id, $userId) {
    $resource = dbFetchOne("SELECT user_id FROM {$table} WHERE id = ?", [$id]);
    
    if (!$resource) {
        apiError('Resurs topilmadi.', 404);
    }
    
    if ((int)$resource['user_id'] !== $userId) {
        apiError('Ushbu resursga kirish huquqingiz yo\'q.', 403);
    }
}

/**
 * Get pagination parameters
 * @return array ['page' => int, 'per_page' => int]
 */
function getPaginationParams() {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    
    return ['page' => $page, 'per_page' => $perPage];
}

/**
 * Get paginated results
 */
function getPaginatedResults($sql, $params = [], $countSql = null, $countParams = []) {
    $pagination = getPaginationParams();
    $offset = ($pagination['page'] - 1) * $pagination['per_page'];
    
    $fullSql = $sql . " LIMIT {$pagination['per_page']} OFFSET {$offset}";
    $data = dbFetchAll($fullSql, $params);
    
    if ($countSql === null) {
        $total = count($data);
    } else {
        $total = (int)dbFetchOne($countSql, $countParams)['total'];
    }
    
    return [
        'data' => $data,
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $total
    ];
}

/**
 * Validate phone number (Uzbek format: +998XXXXXXXXX)
 */
function isValidPhone($phone) {
    return preg_match('/^\+998\d{9}$/', $phone);
}

/**
 * Get site setting value
 */
function getSiteSetting($key, $default = null) {
    $result = dbFetchOne("SELECT `value` FROM site_settings WHERE `key` = ?", [$key]);
    return $result ? $result['value'] : $default;
}
