<?php
/**
 * API Configuration and Common Functions
 * Handles authentication, rate limiting, and JSON responses.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

function apiResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError($message, $statusCode, $fields = null) {
    $response = ['success' => false, 'error' => $message];
    if ($fields !== null) {
        $response['fields'] = $fields;
    }
    apiResponse($response, $statusCode);
}

function getRateLimitKey($useToken = false, $tokenUserId = null) {
    if ($useToken && $tokenUserId) {
        return 'token_' . (int)$tokenUserId;
    }
    return 'ip_' . getClientIp();
}

function checkRateLimit($limit, $windowSeconds = 60, $key = null) {
    if ($key === null) {
        $key = getRateLimitKey();
    }

    $pdo = getDbConnection();
    $now = time();
    $windowStart = $now - $windowSeconds;

    $pdo->prepare("DELETE FROM rate_limits WHERE created_at < FROM_UNIXTIME(?)")
        ->execute([$windowStart]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE `key` = ? AND created_at > FROM_UNIXTIME(?)");
    $stmt->execute([$key, $windowStart]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $limit) {
        return false;
    }

    $pdo->prepare("INSERT INTO rate_limits (`key`, created_at) VALUES (?, FROM_UNIXTIME(?))")
        ->execute([$key, $now]);

    return true;
}

function validateApiToken($tokenHeader) {
    if (empty($tokenHeader)) {
        return false;
    }

    $parts = preg_split('/\s+/', trim($tokenHeader));
    if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer' || $parts[1] === '') {
        return false;
    }

    $token = $parts[1];
    $tokenData = dbFetchOne(
        "SELECT user_id, expires_at FROM api_tokens WHERE token = ?",
        [$token]
    );

    if (!$tokenData) {
        return false;
    }

    if (strtotime($tokenData['expires_at']) < time()) {
        dbDelete('api_tokens', 'token = ?', [$token]);
        return false;
    }

    return ['user_id' => (int)$tokenData['user_id']];
}

function requireAuth() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $tokenData = validateApiToken($authHeader);

    if (!$tokenData) {
        apiError('Autentifikatsiya talab qilinadi. Token topilmadi yoki muddati tugagan.', 401);
    }

    return $tokenData['user_id'];
}

/**
 * Verify ownership only against known user-owned tables.
 */
function verifyOwnership($table, $id, $userId) {
    $allowedTables = ['applications', 'chat_threads'];
    if (!in_array($table, $allowedTables, true)) {
        apiError('Noto\'g\'ri resurs turi.', 400);
    }

    $resource = dbFetchOne("SELECT user_id FROM `{$table}` WHERE id = ?", [$id]);
    if (!$resource) {
        apiError('Resurs topilmadi.', 404);
    }

    if ((int)$resource['user_id'] !== (int)$userId) {
        apiError('Ushbu resursga kirish huquqingiz yo\'q.', 403);
    }
}

function getPaginationParams() {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    return ['page' => $page, 'per_page' => $perPage];
}

function getPaginatedResults($sql, $params = [], $countSql = null, $countParams = []) {
    $pagination = getPaginationParams();
    $offset = ($pagination['page'] - 1) * $pagination['per_page'];
    $fullSql = $sql . " LIMIT {$pagination['per_page']} OFFSET {$offset}";
    $data = dbFetchAll($fullSql, $params);

    if ($countSql === null) {
        $total = count($data);
    } else {
        $countRow = dbFetchOne($countSql, $countParams);
        $total = $countRow ? (int)$countRow['total'] : 0;
    }

    return [
        'data' => $data,
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $total
    ];
}
