<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(10, 60)) {
    apiError('Juda ko‘p so‘rovlar. Iltimos biroz kuting.', 429);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Faqat POST so‘rovi qabul qilinadi.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data['code']) || !is_string($data['code'])) {
    apiError('Google authorization code talab qilinadi.', 400);
}

$postData = http_build_query([
    'code' => $data['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
]);

$context = stream_context_create([
    'http' => [
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => $postData,
        'timeout' => 15,
        'ignore_errors' => true
    ]
]);
$response = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
if ($response === false) {
    apiError('Google bilan bog‘lanishda xatolik yuz berdi.', 502);
}

$tokenData = json_decode($response, true);
if (!is_array($tokenData) || empty($tokenData['access_token'])) {
    apiError('Google tokenlarini olishda xatolik.', 400);
}

$userContext = stream_context_create([
    'http' => [
        'header' => "Authorization: Bearer {$tokenData['access_token']}\r\n",
        'timeout' => 15,
        'ignore_errors' => true
    ]
]);
$userInfo = @file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, $userContext);
if ($userInfo === false) {
    apiError('Google foydalanuvchi ma’lumotlarini olishda xatolik.', 502);
}

$googleUser = json_decode($userInfo, true);
if (!is_array($googleUser) || empty($googleUser['id']) || empty($googleUser['email'])) {
    apiError('Google foydalanuvchi ma’lumotlari yaroqsiz.', 400);
}

$email = strtolower(trim($googleUser['email']));
$name = trim($googleUser['name'] ?? '');
if ($name === '') {
    $name = trim(($googleUser['given_name'] ?? '') . ' ' . ($googleUser['family_name'] ?? '')) ?: $email;
}

$existingUser = dbFetchOne('SELECT id, status FROM users WHERE google_id = :google_id OR email = :email LIMIT 1', [
    'google_id' => $googleUser['id'],
    'email' => $email
]);

if ($existingUser) {
    if (($existingUser['status'] ?? '') !== 'active') {
        apiError('Foydalanuvchi hisobi faol emas.', 403);
    }
    $userId = (int) $existingUser['id'];
    dbUpdate('users', [
        'google_id' => $googleUser['id'],
        'full_name' => $name,
        'avatar_url' => $googleUser['picture'] ?? null,
        'email_verified' => 1,
        'last_login_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $userId]);
} else {
    $userId = dbInsert('users', [
        'google_id' => $googleUser['id'],
        'email' => $email,
        'full_name' => $name,
        'avatar_url' => $googleUser['picture'] ?? null,
        'status' => 'active',
        'email_verified' => 1,
        'last_login_at' => date('Y-m-d H:i:s')
    ]);
}

dbDelete('api_tokens', 'user_id = :user_id', ['user_id' => $userId]);
$apiToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
dbInsert('api_tokens', [
    'user_id' => $userId,
    'token_hash' => hash('sha256', $apiToken),
    'expires_at' => $expiresAt
]);

$existingThread = dbFetchOne('SELECT id FROM chat_threads WHERE user_id = :user_id AND status = :status LIMIT 1', ['user_id' => $userId, 'status' => 'open']);
if (!$existingThread) {
    dbInsert('chat_threads', ['user_id' => $userId, 'status' => 'open']);
}

apiResponse([
    'success' => true,
    'data' => [
        'token' => $apiToken,
        'expires_at' => $expiresAt,
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'avatar' => $googleUser['picture'] ?? null
        ]
    ],
    'message' => 'Muvaffaqiyatli kirish amalga oshirildi'
], 201);
