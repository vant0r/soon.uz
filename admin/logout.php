<?php
/**
 * Admin Logout
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

$admin = getCurrentAdmin();
if ($admin) {
    logAdminAction($admin['id'], 'Logout', 'admin', $admin['id']);
}

session_destroy();
redirect('login.php');
