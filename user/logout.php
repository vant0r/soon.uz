<?php
/**
 * User Logout
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

session_destroy();
redirect('../index.php');
