<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = dbFetchOne("SELECT id, google_id, full_name, email, phone, avatar_url, status, email_verified, created_at, updated_at, last_login_at FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        apiError('Foydalanuvchi topilmadi.', 404);
    }
    apiResponse(['success' => true, 'data' => [
        'id' => (int)$user['id'],
        'google_id' => $user['google_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'avatar_url' => $user['avatar_url'],
        'status' => $user['status'],
        'email_verified' => (bool)$user['email_verified'],
        'created_at' => $user['created_at'],
        'updated_at' => $user['updated_at'],
        'last_login_at' => $user['last_login_at']
    ]]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        apiError('Noto\'g\'ri so\'rov formati.', 400);
    }
    $errors = [];
    $updateData = [];
    if (array_key_exists('full_name', $data)) {
        $name = trim((string)$data['full_name']);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['full_name'] = 'Ism 2 dan 120 belgigacha bo\'lishi kerak.';
        } else {
            $updateData['full_name'] = $name;
        }
    }
    if (array_key_exists('phone', $data)) {
        $phone = trim((string)$data['phone']);
        if ($phone !== '' && !isValidPhone($phone)) {
            $errors['phone'] = 'Telefon raqam +998XXXXXXXXX formatida bo\'lishi kerak.';
        } else {
            $updateData['phone'] = $phone === '' ? null : $phone;
        }
    }
    if ($errors) {
        apiError('Validatsiya xatosi.', 422, $errors);
    }
    if (!$updateData) {
        apiError('Yangilanishi kerak bo\'lgan ma\'lumot yo\'q.', 400);
    }
    if (isset($updateData['phone'])) {
        $existing = dbFetchOne('SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1', [$updateData['phone'], $userId]);
        if ($existing) {
            apiError('Bu telefon raqami boshqa akkauntga tegishli.', 409);
        }
    }
    dbUpdate('users', $updateData, 'id = ?', ['id' => $userId]);
    $user = dbFetchOne("SELECT id, google_id, full_name, email, phone, avatar_url, status, email_verified, created_at, updated_at, last_login_at FROM users WHERE id = ?", [$userId]);
    apiResponse(['success' => true, 'data' => [
        'id' => (int)$user['id'],
        'google_id' => $user['google_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'avatar_url' => $user['avatar_url'],
        'status' => $user['status'],
        'email_verified' => (bool)$user['email_verified'],
        'created_at' => $user['created_at'],
        'updated_at' => $user['updated_at'],
        'last_login_at' => $user['last_login_at']
    ], 'message' => 'Profil muvaffaqiyatli yangilandi']);
}

apiError('Faqat GET va PUT so\'rovlari qabul qilinadi.', 405);
