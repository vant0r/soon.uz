<?php
/**
 * Submit Application from Homepage Contact Form
 * Creates application in database and redirects to success page
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// Verify CSRF token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Xavfsizlik xatosi. Iltimos sahifani yangilab qayta urinib ko\'ring.');
}

// Get form data
$name = sanitizeInput($_POST['name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$serviceType = sanitizeInput($_POST['service_type'] ?? '');
$message = sanitizeInput($_POST['message'] ?? '');

// Validate required fields
$errors = [];
if (empty($name)) {
    $errors[] = 'Ismni kiriting';
}
if (empty($phone) || !isValidPhone($phone)) {
    $errors[] = 'Telefon raqamini to\'g\'ri kiriting (+998XXXXXXXXX)';
}
if (empty($serviceType)) {
    $errors[] = 'Xizmat turini tanlang';
}
if (empty($message)) {
    $errors[] = 'Xabar matnini kiriting';
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    redirect('index.php#contact');
}

// Find user by phone or create anonymous application
$userId = null;
$user = dbFetchOne("SELECT id FROM users WHERE phone = :phone", ['phone' => $phone]);
if ($user) {
    $userId = $user['id'];
} else {
    // Create anonymous user for this application
    $userId = dbInsert('users', [
        'name' => $name,
        'phone' => $phone,
        'email' => null,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// Get service info if selected
$serviceId = null;
$serviceName = null;
if ($serviceType !== 'other' && is_numeric($serviceType)) {
    $service = dbFetchOne("SELECT id, title FROM services WHERE id = :id", ['id' => (int)$serviceType]);
    if ($service) {
        $serviceId = $service['id'];
        $serviceName = $service['title'];
    }
}

// Create application
try {
    dbBeginTransaction();
    
    $applicationId = dbInsert('applications', [
        'user_id' => $userId,
        'service_id' => $serviceId,
        'service_name_snapshot' => $serviceName ?: ($serviceType === 'other' ? 'Boshqa' : 'Noma\'lum'),
        'customization_json' => null,
        'description' => $message,
        'status' => 'new',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Create chat thread for this user if doesn't exist
    getOrCreateChatThread($userId);
    
    // Send notification to user
    dbInsert('notifications', [
        'user_id' => $userId,
        'title' => 'Arizangiz qabul qilindi',
        'message' => 'Sizning arizangiz qabul qilindi. Tez orada operatorimiz siz bilan bog\'lanadi.',
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    dbCommit();
    
    // Success - redirect to thank you page
    $_SESSION['application_submitted'] = true;
    redirect('thank-you.php');
    
} catch (Exception $e) {
    dbRollback();
    error_log('Application submission error: ' . $e->getMessage());
    $_SESSION['form_errors'] = ['Arizani yuborishda xatolik yuz berdi. Iltimos qayta urinib ko\'ring.'];
    $_SESSION['form_data'] = $_POST;
    redirect('index.php#contact');
}
