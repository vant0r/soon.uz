<?php
/**
 * Google OAuth Handler
 * Initiates OAuth flow with Google
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

// Check if config exists
if (!file_exists(__DIR__ . '/../includes/config.php')) {
    die('Tizim hali o\'rnatilmagan. Iltimos install.php orqali o\'rnating.');
}

require_once __DIR__ . '/../includes/config.php';

// Generate state token for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Build OAuth URL
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'state' => $state
];

$oauthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// Redirect to Google
header('Location: ' . $oauthUrl);
exit;
