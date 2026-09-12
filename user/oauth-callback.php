<?php
/**
 * Google OAuth Callback Handler
 * Processes the OAuth response from Google
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

// Check if config exists
if (!file_exists(__DIR__ . '/../includes/config.php')) {
    die('Tizim hali o\'rnatilmagan.');
}

require_once __DIR__ . '/../includes/config.php';

// Verify state token
if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    $_SESSION['login_error'] = 'Xavfsizlik xatosi. Iltimos qayta urinib ko\'ring.';
    redirect('login.php');
}
unset($_SESSION['oauth_state']);

// Check for error from Google
if (isset($_GET['error'])) {
    $_SESSION['login_error'] = 'Google autentifikatsiyasi xato bilan yakunlandi.';
    redirect('login.php');
}

// Get authorization code
$code = $_GET['code'] ?? null;
if (!$code) {
    $_SESSION['login_error'] = 'Avtorizatsiya kodi olinmadi.';
    redirect('login.php');
}

// Exchange code for tokens
$tokenUrl = 'https://oauth2.googleapis.com/token';
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'code' => $code,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $_SESSION['login_error'] = 'Token olishda xatolik yuz berdi.';
    redirect('login.php');
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    $_SESSION['login_error'] = 'Noto\'g\'ri token javobi.';
    redirect('login.php');
}

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$userInfo = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userInfo, true);
if (!isset($userData['sub'])) {
    $_SESSION['login_error'] = 'Foydalanuvchi ma\'lumotlarini olishda xatolik.';
    redirect('login.php');
}

// Find or create user
$googleId = $userData['sub'];
$email = $userData['email'] ?? null;
$name = $userData['name'] ?? 'Foydalanuvchi';
$avatar = $userData['picture'] ?? null;

// Check if user exists by Google ID
$user = dbFetchOne("SELECT * FROM users WHERE google_id = :google_id", ['google_id' => $googleId]);

if (!$user && $email) {
    // Try to find by email
    $user = dbFetchOne("SELECT * FROM users WHERE email = :email", ['email' => $email]);
    
    if ($user) {
        // Link Google ID to existing user
        dbUpdate('users', ['google_id' => $googleId], 'id = :id', ['id' => $user['id']]);
    }
}

if (!$user) {
    // Create new user
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

// Update avatar if changed
if ($avatar && $user['avatar'] !== $avatar) {
    dbUpdate('users', ['avatar' => $avatar], 'id = :id', ['id' => $user['id']]);
}

// Log in user
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_login_time'] = time();

// Ensure chat thread exists
getOrCreateChatThread($user['id']);

redirect('dashboard.php');
