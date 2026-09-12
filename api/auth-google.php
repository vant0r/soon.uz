<?php
/**
 * POST /api/auth/google - Exchange Google auth code for WebHub API token
 * Rate limit: 10 requests/minute per IP
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(10, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Faqat POST so\'rovi qabul qilinadi.', 405);
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['code'])) {
    apiError('Google authorization code talab qilinadi.', 400);
}

$googleCode = $data['code'];

// Exchange code for Google tokens
$oauthUrl = 'https://oauth2.googleapis.com/token';
$postData = http_build_query([
    'code' => $googleCode,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
]);

$options = [
    'http' => [
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => $postData
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($oauthUrl, false, $context);

if ($response === false) {
    apiError('Google bilan bog\'lanishda xatolik yuz berdi.', 500);
}

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token']) || !isset($tokenData['id_token'])) {
    apiError('Google tokenlarini olishda xatolik.', 400);
}

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$options = [
    'http' => [
        'header' => "Authorization: Bearer " . $tokenData['access_token'] . "\r\n"
    ]
];

$context = stream_context_create($options);
$userInfo = file_get_contents($userInfoUrl, false, $context);

if ($userInfo === false) {
    apiError('Foydalanuvchi ma\'lumotlarini olishda xatolik.', 500);
}

$googleUser = json_decode($userInfo, true);

if (!isset($googleUser['id'])) {
    apiError('Google foydalanuvchi ID topilmadi.', 500);
}

$pdo = getDbConnection();

// Check if user exists or create new
$existingUser = dbFetchOne("SELECT id FROM users WHERE google_id = ?", [$googleUser['id']]);

if ($existingUser) {
    $userId = $existingUser['id'];
} else {
    // Create new user
    $userData = [
        'google_id' => $googleUser['id'],
        'name' => $googleUser['name'] ?? ($googleUser['given_name'] . ' ' . $googleUser['family_name']),
        'email' => $googleUser['email'] ?? null,
        'avatar' => $googleUser['picture'] ?? null,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $userId = dbInsert('users', $userData);
}

// Delete any existing tokens for this user
dbDelete('api_tokens', 'user_id = ?', [$userId]);

// Generate new API token
$apiToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

dbInsert('api_tokens', [
    'user_id' => $userId,
    'token' => $apiToken,
    'created_at' => date('Y-m-d H:i:s'),
    'expires_at' => $expiresAt
]);

// Ensure chat thread exists for this user
$existingThread = dbFetchOne("SELECT id FROM chat_threads WHERE user_id = ?", [$userId]);

if (!$existingThread) {
    dbInsert('chat_threads', [
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

apiResponse([
    'success' => true,
    'data' => [
        'token' => $apiToken,
        'expires_at' => $expiresAt,
        'user' => [
            'id' => $userId,
            'name' => $googleUser['name'] ?? '',
            'email' => $googleUser['email'] ?? '',
            'avatar' => $googleUser['picture'] ?? null
        ]
    ],
    'message' => 'Muvaffaqiyatli kirish amalga oshirildi'
], 201);
