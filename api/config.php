<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

function apiResponse($data, $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError($message, $statusCode, $fields = null): void
{
    $response = ['success' => false, 'error' => $message];
    if ($fields !== null) $response['fields'] = $fields;
    apiResponse($response, $statusCode);
}

function getRateLimitKey($useToken = false, $tokenUserId = null): string
{
    return $useToken && $tokenUserId ? 'token_' . (int)$tokenUserId : 'ip_' . getClientIp();
}

function checkRateLimit($limit, $windowSeconds = 60, $key = null): bool
{
    $limit = max(1, (int)$limit);
    $windowSeconds = max(1, (int)$windowSeconds);
    $key = $key ?? getRateLimitKey();
    $pdo = getDbConnection();
    $nowTs = time();
    $now = date('Y-m-d H:i:s', $nowTs);
    $cutoff = date('Y-m-d H:i:s', $nowTs - $windowSeconds);
    $stmt = $pdo->prepare('SELECT id, attempts, last_attempt_at, blocked_until FROM rate_limits WHERE identifier = :identifier AND action = :action LIMIT 1');
    $stmt->execute(['identifier' => $key, 'action' => 'api']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['blocked_until']) && strtotime($row['blocked_until']) > $nowTs) return false;

    if (!$row || empty($row['last_attempt_at']) || strtotime($row['last_attempt_at']) < strtotime($cutoff)) {
        if ($row) {
            $update = $pdo->prepare('UPDATE rate_limits SET attempts = 1, last_attempt_at = :now, blocked_until = NULL WHERE id = :id');
            $update->execute(['now' => $now, 'id' => $row['id']]);
        } else {
            $insert = $pdo->prepare('INSERT INTO rate_limits (identifier, action, attempts, last_attempt_at, blocked_until) VALUES (:identifier, :action, 1, :now, NULL)');
            $insert->execute(['identifier' => $key, 'action' => 'api', 'now' => $now]);
        }
        return true;
    }

    if ((int)$row['attempts'] >= $limit) {
        $blockedUntil = date('Y-m-d H:i:s', $nowTs + $windowSeconds);
        $update = $pdo->prepare('UPDATE rate_limits SET blocked_until = :blocked WHERE id = :id');
        $update->execute(['blocked' => $blockedUntil, 'id' => $row['id']]);
        return false;
    }

    $update = $pdo->prepare('UPDATE rate_limits SET attempts = attempts + 1, last_attempt_at = :now WHERE id = :id');
    $update->execute(['now' => $now, 'id' => $row['id']]);
    return true;
}

function getAuthorizationHeader(): string
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) return trim((string)$value);
        }
    }
    return '';
}

function validateApiToken($tokenHeader)
{
    if (!$tokenHeader) return false;
    $parts = preg_split('/\s+/', trim($tokenHeader));
    if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer' || !preg_match('/^[a-f0-9]{64}$/i', $parts[1])) return false;

    $tokenHash = hash('sha256', $parts[1]);
    $sessionId = 'api_' . $tokenHash;
    $tokenData = dbFetchOne('SELECT user_id, last_activity FROM sessions WHERE id = :id AND user_type = :type LIMIT 1', ['id' => $sessionId, 'type' => 'user']);
    if (!$tokenData) return false;

    $lastActivity = strtotime($tokenData['last_activity']);
    $lifetime = defined('SESSION_LIFETIME') ? max(60, (int)SESSION_LIFETIME) : 1800;
    if ($lastActivity === false || $lastActivity < time() - $lifetime) {
        dbDelete('sessions', 'id = :id', ['id' => $sessionId]);
        return false;
    }

    dbQuery('UPDATE sessions SET last_activity = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $sessionId]);
    return ['user_id' => (int)$tokenData['user_id']];
}

function requireAuth(): int
{
    $tokenData = validateApiToken(getAuthorizationHeader());
    if (!$tokenData) apiError('Autentifikatsiya talab qilinadi.', 401);
    return $tokenData['user_id'];
}

function verifyOwnership($table, $id, $userId): void
{
    $allowedTables = ['applications', 'chat_threads'];
    if (!in_array($table, $allowedTables, true)) apiError('Noto‘g‘ri resurs turi.', 400);
    $resource = dbFetchOne("SELECT user_id FROM `{$table}` WHERE id = :id", ['id' => $id]);
    if (!$resource) apiError('Resurs topilmadi.', 404);
    if ((int)$resource['user_id'] !== (int)$userId) apiError('Ushbu resursga kirish huquqingiz yo‘q.', 403);
}

function getPaginationParams(): array
{
    return [
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => min(100, max(1, (int)($_GET['per_page'] ?? 20)))
    ];
}

function getPaginatedResults($sql, $params = [], $countSql = null, $countParams = []): array
{
    $pagination = getPaginationParams();
    $offset = ($pagination['page'] - 1) * $pagination['per_page'];
    $data = dbFetchAll($sql . " LIMIT {$pagination['per_page']} OFFSET {$offset}", $params);
    $total = $countSql === null ? count($data) : (int)(dbFetchOne($countSql, $countParams)['total'] ?? 0);
    return ['data' => $data, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'], 'total' => $total];
}
