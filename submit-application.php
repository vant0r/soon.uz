<?php
require_once __DIR__ . '/includes/functions.php';
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Xavfsizlik xatosi. Iltimos sahifani yangilab qayta urinib ko\'ring.');
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$serviceType = trim((string)($_POST['service_type'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 255) {
    $errors[] = 'Ismni to\'g\'ri kiriting';
}

if (!isValidPhone($phone)) {
    $errors[] = 'Telefon raqamini to\'g\'ri kiriting (+998XXXXXXXXX)';
}

if ($serviceType === '') {
    $errors[] = 'Xizmat turini tanlang';
}

if (mb_strlen($message) < 3 || mb_strlen($message) > 5000) {
    $errors[] = 'Xabar matnini to\'g\'ri kiriting';
}

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    redirect('index.php#contact');
}

$now = date('Y-m-d H:i:s');
$userId = null;
$serviceId = null;
$serviceName = 'Boshqa';

try {
    dbBeginTransaction();

    $user = dbFetchOne(
        'SELECT id, full_name, email FROM users WHERE phone = :phone LIMIT 1',
        ['phone' => $phone]
    );

    if ($user) {
        $userId = (int)$user['id'];
        dbExecute(
            'UPDATE users SET full_name = :full_name, updated_at = :updated_at WHERE id = :id',
            [
                'full_name' => $name,
                'updated_at' => $now,
                'id' => $userId
            ]
        );
    } else {
        $userId = dbInsert('users', [
            'full_name' => $name,
            'email' => null,
            'phone' => $phone,
            'status' => 'active',
            'email_verified' => 0,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    if ($serviceType !== 'other' && ctype_digit($serviceType)) {
        $service = dbFetchOne(
            'SELECT id, title_uz FROM services WHERE id = :id AND status = :status LIMIT 1',
            [
                'id' => (int)$serviceType,
                'status' => 'active'
            ]
        );

        if (!$service) {
            throw new RuntimeException('Tanlangan xizmat topilmadi.');
        }

        $serviceId = (int)$service['id'];
        $serviceName = (string)$service['title_uz'];
    }

    $applicationId = dbInsert('applications', [
        'user_id' => $userId,
        'service_id' => $serviceId,
        'full_name' => $name,
        'email' => $user['email'] ?? null,
        'phone' => $phone,
        'company_name' => null,
        'message' => $message,
        'budget_min' => null,
        'budget_max' => null,
        'deadline_date' => null,
        'status' => 'new',
        'priority' => 'medium',
        'assigned_admin_id' => null,
        'source' => 'website',
        'ip_address' => getClientIp(),
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        'created_at' => $now,
        'updated_at' => $now,
        'reviewed_at' => null,
        'completed_at' => null
    ]);

    $threadId = getOrCreateChatThread($userId);

    if ($threadId) {
        dbExecute(
            'UPDATE chat_threads SET application_id = :application_id, subject = :subject, last_message_at = :last_message_at, updated_at = :updated_at WHERE id = :id',
            [
                'application_id' => $applicationId,
                'subject' => 'Ariza: ' . $serviceName,
                'last_message_at' => $now,
                'updated_at' => $now,
                'id' => $threadId
            ]
        );
    }

    dbInsert('notifications', [
        'user_id' => $userId,
        'title' => 'Arizangiz qabul qilindi',
        'message' => 'Arizangiz muvaffaqiyatli qabul qilindi. Tez orada operatorimiz siz bilan bog\'lanadi.',
        'type' => 'application',
        'related_type' => 'application',
        'related_id' => $applicationId,
        'is_read' => 0,
        'read_at' => null,
        'created_at' => $now
    ]);

    dbCommit();

    $_SESSION['application_submitted'] = true;
    $_SESSION['application_id'] = $applicationId;
    redirect('thank-you.php');
} catch (Throwable $e) {
    dbRollback();
    error_log('Application submission error: ' . $e->getMessage());
    $_SESSION['form_errors'] = ['Arizani yuborishda xatolik yuz berdi. Iltimos qayta urinib ko\'ring.'];
    $_SESSION['form_data'] = $_POST;
    redirect('index.php#contact');
}
