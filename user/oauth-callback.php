<?php
/**
 * Google OAuth Callback Handler
 * Processes the OAuth response from Google.
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

if (!file_exists(__DIR__ . '/../includes/config.php')) {
    die('Tizim hali o\'rnatilmagan.');
}
require_once __DIR__ . '/../includes/config.php';

if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], (string)$_GET['state'])) {
    $_SESSION['login_error'] = 'Xavfsizlik xatosi. Iltimos qayta urinib ko\'ring.';
    redirect('login.php');
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    $_SESSION['login_error'] = 'Google autentifikatsiyasi xato bilan yakunlandi.';
    redirect('login.php');
}

$code = $_GET['code'] ?? null;
if (!$code) {
    $_SESSION['login_error'] = 'Avtorizatsiya kodi olinmadi.';
    redirect('login.php');
}

$tokenUrl = 'https://oauth2.googleapis.com/token';
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'code' => $code,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $tokenUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    $_SESSION['login_error'] = 'Token olishda xatolik yuz berdi.';
    redirect('login.php');
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    $_SESSION['login_error'] = 'Noto\'g\'ri token javobi.';
    redirect('login.php');
}

$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $userInfoUrl,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenData['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10,
]);
$userInfo = curl_exec($ch);
$userInfoCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userInfo === false || $userInfoCode !== 200) {
    $_SESSION['login_error'] = 'Foydalanuvchi ma\'lumotlarini olishda xatolik.';
    redirect('login.php');
}

$userData = json_decode($userInfo, true);
if (!isset($userData['sub'])) {
    $_SESSION['login_error'] = 'Foydalanuvchi ma\'lumotlarini olishda xatolik.';
    redirect('login.php');
}

$googleId = (string)$userData['sub'];
$email = isset($userData['email']) ? trim((string)$userData['email']) : null;
$name = trim((string)($userData['name'] ?? 'Foydalanuvchi'));
$avatar = isset($userData['picture']) ? trim((string)$userData['picture']) : null;

$user = dbFetchOne("SELECT * FROM users WHERE google_id = :google_id", ['google_id' => $googleId]);

if (!$user && $email) {
    $user = dbFetchOne("SELECT * FROM users WHERE email = :email", ['email' => $email]);
    if ($user) {
        dbUpdate('users', ['google_id' => $googleId], 'id = :id', ['id' => $user['id']]);
    }
}

if (!$user) {
    $userId = dbInsert('users', [
        'google_id' => $googleId,
        'name' => $name,
        'email' => $email,
        'avatar' => $avatar,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $user = dbFetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
} elseif ($user['status'] !== 'active') {
    $_SESSION['login_error'] = 'Hisobingiz bloklangan.';
    redirect('login.php');
}

if ($avatar && $user['avatar'] !== $avatar) {
    dbUpdate('users', ['avatar' => $avatar], 'id = :id', ['id' => $user['id']]);
}

// Prevent session fixation: rotate the session identifier after successful authentication.
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_login_time'] = time();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

getOrCreateChatThread($user['id']);
redirect('dashboard.php');
