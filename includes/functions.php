<?php
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

function getSiteSetting($key, $default = null) {
    $result = dbFetchOne('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1', ['key' => $key]);
    return $result ? $result['setting_value'] : $default;
}

function updateSiteSetting($key, $value, $type = 'string', $group = 'general') {
    $existing = dbFetchOne('SELECT id FROM settings WHERE setting_key = :key LIMIT 1', ['key' => $key]);
    $data = ['setting_key' => $key, 'setting_value' => $value, 'setting_type' => $type, 'group_name' => $group];
    if ($existing) {
        dbUpdate('settings', ['setting_value' => $value, 'setting_type' => $type, 'group_name' => $group], 'id = :id', ['id' => $existing['id']]);
    } else {
        dbInsert('settings', $data);
    }
}

function getAllSiteSettings() {
    $results = dbFetchAll('SELECT setting_key, setting_value FROM settings ORDER BY group_name, setting_key');
    $settings = [];
    foreach ($results as $row) $settings[$row['setting_key']] = $row['setting_value'];
    return $settings;
}

function getStatCount($table, $overrideKey = null) {
    if ($overrideKey) {
        $override = getSiteSetting($overrideKey);
        if ($override !== null && $override !== '') return max(0, (int)$override);
    }
    $allowedTables = ['users', 'services', 'portfolio', 'blog_posts', 'applications', 'chat_threads'];
    if (!in_array($table, $allowedTables, true)) return 0;
    $result = dbFetchOne("SELECT COUNT(*) AS count FROM `{$table}`");
    return $result ? (int)$result['count'] : 0;
}

function logAdminAction($adminId, $action, $targetType, $targetId = null, $details = null) {
    dbInsert('audit_log', [
        'user_id' => $adminId,
        'user_type' => 'admin',
        'action' => $action,
        'entity_type' => $targetType,
        'entity_id' => $targetId,
        'new_values' => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        'ip_address' => getClientIp(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function getCurrentAdmin() {
    startSecureSession();
    if (empty($_SESSION['admin_id'])) return null;
    return dbFetchOne("SELECT * FROM admins WHERE id = :id AND status = 'active'", ['id' => $_SESSION['admin_id']]);
}

function isAdminLoggedIn() {
    return getCurrentAdmin() !== null;
}

function getAdminPermissions($admin = null) {
    $admin = $admin ?: getCurrentAdmin();
    if (!$admin) return [];
    $role = $admin['role'] ?? '';
    if ($role === 'super_admin') return ['*'];
    try {
        $rows = dbFetchAll('SELECT p.permission_key FROM admin_role_permissions rp INNER JOIN admin_permissions p ON p.id = rp.permission_id WHERE rp.role = :role', ['role' => $role]);
        $permissions = array_values(array_unique(array_column($rows, 'permission_key')));
        if ($permissions) return $permissions;
    } catch (Throwable $e) {
    }
    $fallback = [
        'admin' => ['dashboard.view','applications.view','applications.manage','users.view','users.manage','services.view','services.manage','portfolio.view','portfolio.manage','blog.view','blog.manage','chat.view','chat.manage','branding.manage'],
        'manager' => ['dashboard.view','applications.view','applications.manage','users.view','services.view','portfolio.view','blog.view','chat.view','chat.manage']
    ];
    return $fallback[$role] ?? [];
}

function adminHasPermission($permission, $admin = null) {
    $permissions = getAdminPermissions($admin);
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function requireAdmin() {
    startSecureSession();
    $admin = getCurrentAdmin();
    if (!$admin) redirect(SITE_URL . '/admin/login.php');
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $viewMap = [
        'dashboard.php' => 'dashboard.view',
        'applications.php' => 'applications.view',
        'users.php' => 'users.view',
        'services.php' => 'services.view',
        'portfolio.php' => 'portfolio.view',
        'blog.php' => 'blog.view',
        'chat.php' => 'chat.view',
        'branding.php' => 'branding.manage',
        'settings.php' => 'settings.manage'
    ];
    $permission = $viewMap[$page] ?? null;
    if ($permission && !adminHasPermission($permission, $admin)) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $permission) {
        $manageMap = [
            'applications.php' => 'applications.manage', 'users.php' => 'users.manage', 'services.php' => 'services.manage',
            'portfolio.php' => 'portfolio.manage', 'blog.php' => 'blog.manage', 'chat.php' => 'chat.manage'
        ];
        $managePermission = $manageMap[$page] ?? $permission;
        if (!adminHasPermission($managePermission, $admin)) {
            http_response_code(403);
            exit('403 Forbidden');
        }
    }
}

function requireAdminPermission($permission) {
    requireAdmin();
    if (!adminHasPermission($permission)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>403 — SOON</title><style>body{font-family:system-ui,sans-serif;margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f7fb;color:#111827}.box{max-width:520px;padding:40px;text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:24px;box-shadow:0 20px 60px #0001}a{display:inline-block;margin-top:20px;padding:11px 18px;border-radius:12px;background:#111827;color:#fff;text-decoration:none}</style></head><body><main class="box"><div style="font-size:52px;font-weight:800">403</div><h1>Ruxsat yo‘q</h1><p>Bu amal uchun sizning administrator rolingizda yetarli huquq mavjud emas.</p><a href="dashboard.php">Dashboardga qaytish</a></main></body></html>';
        exit;
    }
}

function getCurrentUser() {
    startSecureSession();
    if (empty($_SESSION['user_id'])) return null;
    return dbFetchOne("SELECT * FROM users WHERE id = :id AND status = 'active'", ['id' => $_SESSION['user_id']]);
}

function isUserLoggedIn() {
    return getCurrentUser() !== null;
}

function requireUser() {
    startSecureSession();
    if (!isUserLoggedIn()) redirect(SITE_URL . '/user/login.php');
}

function getOrCreateChatThread($userId, $applicationId = null) {
    $params = ['user_id' => (int)$userId];
    $sql = 'SELECT * FROM chat_threads WHERE user_id = :user_id';
    if ($applicationId !== null) {
        $sql .= ' AND application_id = :application_id';
        $params['application_id'] = (int)$applicationId;
    } else {
        $sql .= ' AND application_id IS NULL';
    }
    $sql .= ' ORDER BY updated_at DESC LIMIT 1';
    $thread = dbFetchOne($sql, $params);
    if ($thread) return $thread;
    $threadId = dbInsert('chat_threads', ['application_id' => $applicationId !== null ? (int)$applicationId : null, 'user_id' => (int)$userId, 'status' => 'open', 'unread_user_count' => 0, 'unread_admin_count' => 0]);
    return dbFetchOne('SELECT * FROM chat_threads WHERE id = :id', ['id' => $threadId]);
}

function formatDateTimeUz($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Hozirgina';
    if ($diff < 3600) return floor($diff / 60) . ' daqiqa oldin';
    if ($diff < 86400) return floor($diff / 3600) . ' soat oldin';
    if ($diff < 604800) return floor($diff / 86400) . ' kun oldin';
    return date('d.m.Y H:i', $timestamp);
}

function formatFileSize($bytes) {
    $bytes = max(0, (int)$bytes);
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, 2) . ' ' . $units[$i];
}

function getPaginationData($total, $page, $perPage) {
    $total = max(0, (int)$total);
    $perPage = min(max(1, (int)$perPage), 100);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)$page), $totalPages);
    return ['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$totalPages,'has_prev'=>$page>1,'has_next'=>$page<$totalPages,'offset'=>($page-1)*$perPage];
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function errorResponse($message, $statusCode = 400, $fields = null) {
    $response = ['success'=>false,'error'=>$message];
    if ($fields) $response['fields'] = $fields;
    jsonResponse($response, $statusCode);
}

function successResponse($data, $statusCode = 200) {
    jsonResponse(['success'=>true,'data'=>$data], $statusCode);
}

function paginatedResponse($items, $pagination) {
    jsonResponse(['success'=>true,'data'=>$items,'page'=>$pagination['page'],'per_page'=>$pagination['per_page'],'total'=>$pagination['total']]);
}
