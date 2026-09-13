<?php
/**
 * Core Helper Functions
 * Common utilities used throughout the application
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/database.php';

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Read a site setting directly from the database.
 * Direct reads avoid stale values after an admin update.
 */
function getSiteSetting($key, $default = null) {
    $result = dbFetchOne(
        "SELECT value FROM site_settings WHERE `key` = :key",
        ['key' => $key]
    );
    return $result ? $result['value'] : $default;
}

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
}

function getAllSiteSettings() {
    $results = dbFetchAll("SELECT `key`, value FROM site_settings");
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

/**
 * Count only known internal tables. This prevents accidental SQL identifier injection.
 */
function getStatCount($table, $overrideKey = null) {
    if ($overrideKey) {
        $override = getSiteSetting($overrideKey);
        if ($override !== null && $override !== '') {
            return (int)$override;
        }
    }

    $allowedTables = ['users', 'services', 'portfolio', 'blog_posts', 'applications', 'chat_threads'];
    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = dbFetchOne("SELECT COUNT(*) as count FROM `{$table}`");
    return $result ? (int)$result['count'] : 0;
}

function logAdminAction($adminId, $action, $targetType, $targetId = null, $details = null) {
    dbInsert('audit_log', [
        'admin_id' => $adminId,
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details_json' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

function getCurrentAdmin() {
    startSecureSession();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return dbFetchOne("SELECT * FROM admins WHERE id = :id", ['id' => $_SESSION['admin_id']]);
}

function isAdminLoggedIn() {
    return getCurrentAdmin() !== null;
}

function requireAdmin() {
    startSecureSession();
    if (!isAdminLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

function getCurrentUser() {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return dbFetchOne("SELECT * FROM users WHERE id = :id AND status = 'active'", ['id' => $_SESSION['user_id']]);
}

function isUserLoggedIn() {
    return getCurrentUser() !== null;
}

function requireUser() {
    startSecureSession();
    if (!isUserLoggedIn()) {
        redirect(SITE_URL . '/user/login.php');
    }
}

function getOrCreateChatThread($userId) {
    $thread = dbFetchOne(
        "SELECT * FROM chat_threads WHERE user_id = :user_id",
        ['user_id' => $userId]
    );

    if (!$thread) {
        $threadId = dbInsert('chat_threads', [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $thread = dbFetchOne("SELECT * FROM chat_threads WHERE id = :id", ['id' => $threadId]);
    }

    return $thread;
}

function formatDateTimeUz($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) return 'Hozirgina';
    if ($diff < 3600) return floor($diff / 60) . ' daqiqa oldin';
    if ($diff < 86400) return floor($diff / 3600) . ' soat oldin';
    if ($diff < 604800) return floor($diff / 86400) . ' kun oldin';
    return date('d.m.Y H:i', $timestamp);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function getPaginationData($total, $page, $perPage) {
    $page = max(1, (int)$page);
    $perPage = min(max(1, (int)$perPage), 100);
    $totalPages = (int)ceil($total / $perPage);
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

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function errorResponse($message, $statusCode = 400, $fields = null) {
    $response = ['success' => false, 'error' => $message];
    if ($fields) $response['fields'] = $fields;
    jsonResponse($response, $statusCode);
}

function successResponse($data, $statusCode = 200) {
    jsonResponse(['success' => true, 'data' => $data], $statusCode);
}

function paginatedResponse($items, $pagination) {
    jsonResponse([
        'success' => true,
        'data' => $items,
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $pagination['total']
    ]);
}
