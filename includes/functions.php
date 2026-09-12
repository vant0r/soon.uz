<?php
/**
 * Core Helper Functions
 * Common utilities used throughout the application
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/database.php';

/**
 * Start session with secure settings
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

/**
 * Redirect to a URL safely
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Get site setting by key
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function getSiteSetting($key, $default = null) {
    static $cache = [];
    
    if (!isset($cache[$key])) {
        $result = dbFetchOne(
            "SELECT value FROM site_settings WHERE `key` = :key",
            ['key' => $key]
        );
        $cache[$key] = $result ? $result['value'] : $default;
    }
    
    return $cache[$key];
}

/**
 * Update site setting
 * @param string $key Setting key
 * @param mixed $value Setting value
 */
function updateSiteSetting($key, $value) {
    $existing = dbFetchOne(
        "SELECT id FROM site_settings WHERE `key` = :key",
        ['key' => $key]
    );
    
    if ($existing) {
        dbUpdate('site_settings', ['value' => $value], '`key` = :key', ['key' => $key]);
    } else {
        dbInsert('site_settings', ['key' => $key, 'value' => $value]);
    }
    
    // Clear cache
    global $settingCache;
    unset($cache[$key]);
}

/**
 * Get all site settings as array
 * @return array Key-value pairs
 */
function getAllSiteSettings() {
    $results = dbFetchAll("SELECT `key`, value FROM site_settings");
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

/**
 * Get computed stat count with optional override
 * @param string $table Table name to count
 * @param string $overrideKey Site settings key for override
 * @return int Stat count
 */
function getStatCount($table, $overrideKey = null) {
    // Check for override first
    if ($overrideKey) {
        $override = getSiteSetting($overrideKey);
        if ($override !== null && $override !== '') {
            return (int) $override;
        }
    }
    
    // Compute from database
    $result = dbFetchOne("SELECT COUNT(*) as count FROM {$table}");
    return $result ? (int) $result['count'] : 0;
}

/**
 * Log admin action to audit_log
 * @param int $adminId Admin user ID
 * @param string $action Action description
 * @param string $targetType Target entity type
 * @param int|null $targetId Target entity ID
 * @param array|null $details Additional details
 */
function logAdminAction($adminId, $action, $targetType, $targetId = null, $details = null) {
    dbInsert('audit_log', [
        'admin_id' => $adminId,
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details_json' => $details ? json_encode($details) : null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get current logged in admin
 * @return array|null Admin data or null
 */
function getCurrentAdmin() {
    startSecureSession();
    
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    
    return dbFetchOne("SELECT * FROM admins WHERE id = :id", ['id' => $_SESSION['admin_id']]);
}

/**
 * Check if admin is logged in
 * @return bool
 */
function isAdminLoggedIn() {
    return getCurrentAdmin() !== null;
}

/**
 * Require admin authentication
 */
function requireAdmin() {
    startSecureSession();
    
    if (!isAdminLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

/**
 * Get current logged in user
 * @return array|null User data or null
 */
function getCurrentUser() {
    startSecureSession();
    
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    
    return dbFetchOne("SELECT * FROM users WHERE id = :id AND status = 'active'", ['id' => $_SESSION['user_id']]);
}

/**
 * Check if user is logged in
 * @return bool
 */
function isUserLoggedIn() {
    return getCurrentUser() !== null;
}

/**
 * Require user authentication
 */
function requireUser() {
    startSecureSession();
    
    if (!isUserLoggedIn()) {
        redirect(SITE_URL . '/user/login.php');
    }
}

/**
 * Get or create chat thread for a user
 * @param int $userId User ID
 * @return array Thread data
 */
function getOrCreateChatThread($userId) {
    // Check if thread exists
    $thread = dbFetchOne(
        "SELECT * FROM chat_threads WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if (!$thread) {
        // Create new thread
        $threadId = dbInsert('chat_threads', [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $thread = dbFetchOne("SELECT * FROM chat_threads WHERE id = :id", ['id' => $threadId]);
    }
    
    return $thread;
}

/**
 * Format datetime for display in Uzbek
 * @param string $datetime DateTime string
 * @return string Formatted datetime
 */
function formatDateTimeUz($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'Hozirgina';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' daqiqa oldin';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' soat oldin';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' kun oldin';
    } else {
        return date('d.m.Y H:i', $timestamp);
    }
}

/**
 * Format file size for display
 * @param int $bytes File size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Generate pagination data
 * @param int $total Total number of items
 * @param int $page Current page
 * @param int $perPage Items per page
 * @return array Pagination data
 */
function getPaginationData($total, $page, $perPage) {
    $page = max(1, (int) $page);
    $perPage = min(max(1, (int) $perPage), 100);
    $totalPages = ceil($total / $perPage);
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
        'offset' => ($page - 1) * $perPage
    ];
}

/**
 * JSON response helper
 * @param mixed $data Data to encode
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Error JSON response
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param array|null $fields Field-specific errors
 */
function errorResponse($message, $statusCode = 400, $fields = null) {
    $response = ['success' => false, 'error' => $message];
    if ($fields) {
        $response['fields'] = $fields;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Success JSON response
 * @param mixed $data Response data
 * @param int $statusCode HTTP status code
 */
function successResponse($data, $statusCode = 200) {
    jsonResponse(['success' => true, 'data' => $data], $statusCode);
}

/**
 * Paginated JSON response
 * @param array $items Items for current page
 * @param array $pagination Pagination data
 * @return void
 */
function paginatedResponse($items, $pagination) {
    jsonResponse([
        'success' => true,
        'data' => $items,
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $pagination['total']
    ]);
}
