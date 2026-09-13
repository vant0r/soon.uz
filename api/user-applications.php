<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT a.id, a.user_id, a.service_id, a.full_name, a.email, a.phone, a.company_name,
                   a.message, a.budget_min, a.budget_max, a.deadline_date, a.status, a.priority,
                   a.source, a.created_at, a.updated_at, a.reviewed_at, a.completed_at,
                   s.title_uz AS service_title
            FROM applications a
            LEFT JOIN services s ON s.id = a.service_id
            WHERE a.user_id = ?
            ORDER BY a.id DESC";

    $countSql = 'SELECT COUNT(*) AS total FROM applications WHERE user_id = ?';
    $result = getPaginatedResults($sql, [$userId], $countSql, [$userId]);

    apiResponse([
        'success' => true,
        'data' => array_map(static function ($app) {
            return [
                'id' => (int)$app['id'],
                'service_id' => $app['service_id'] !== null ? (int)$app['service_id'] : null,
                'service_name' => $app['service_title'] ?: 'Boshqa xizmat',
                'full_name' => $app['full_name'],
                'email' => $app['email'],
                'phone' => $app['phone'],
                'company_name' => $app['company_name'],
                'message' => $app['message'],
                'budget_min' => $app['budget_min'] !== null ? (float)$app['budget_min'] : null,
                'budget_max' => $app['budget_max'] !== null ? (float)$app['budget_max'] : null,
                'deadline_date' => $app['deadline_date'],
                'status' => $app['status'],
                'priority' => $app['priority'],
                'source' => $app['source'],
                'created_at' => $app['created_at'],
                'updated_at' => $app['updated_at'],
                'reviewed_at' => $app['reviewed_at'],
                'completed_at' => $app['completed_at']
            ];
        }, $result['data']),
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total']
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Faqat GET va POST so\'rovlari qabul qilinadi.', 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    apiError('Noto\'g\'ri so\'rov formati.', 400);
}

$user = dbFetchOne(
    'SELECT id, full_name, email, phone FROM users WHERE id = :id LIMIT 1',
    ['id' => $userId]
);

if (!$user) {
    apiError('Foydalanuvchi topilmadi.', 404);
}

$serviceId = null;
if (isset($data['service_id']) && $data['service_id'] !== '' && $data['service_id'] !== null) {
    if (!ctype_digit((string)$data['service_id'])) {
        apiError('Xizmat ID noto\'g\'ri.', 422, ['service_id' => 'Xizmat ID faqat raqam bo\'lishi kerak.']);
    }
    $serviceId = (int)$data['service_id'];
    if ($serviceId < 1) {
        apiError('Xizmat ID noto\'g\'ri.', 422, ['service_id' => 'Xizmat ID musbat bo\'lishi kerak.']);
    }
}

$serviceName = trim((string)($data['service_name'] ?? ''));
$service = null;

if ($serviceId !== null) {
    $service = dbFetchOne(
        'SELECT id, title_uz FROM services WHERE id = :id AND status = :status LIMIT 1',
        ['id' => $serviceId, 'status' => 'active']
    );
    if (!$service) {
        apiError('Xizmat topilmadi.', 422, ['service_id' => 'Tanlangan xizmat mavjud emas.']);
    }
}

if ($serviceId === null && $serviceName === '') {
    apiError('Xizmat tanlanmagan.', 422, ['service_id' => 'Xizmat ID yoki xizmat nomi talab qilinadi.']);
}

$fullName = trim((string)($data['full_name'] ?? $user['full_name'] ?? ''));
$email = trim((string)($data['email'] ?? $user['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? $user['phone'] ?? ''));
$companyName = trim((string)($data['company_name'] ?? ''));
$message = trim((string)($data['message'] ?? $data['description'] ?? ''));
$budgetMin = $data['budget_min'] ?? null;
$budgetMax = $data['budget_max'] ?? null;
$deadlineDate = trim((string)($data['deadline_date'] ?? ''));

$errors = [];

if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 255) {
    $errors['full_name'] = 'Ismni to\'g\'ri kiriting.';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Email manzili noto\'g\'ri.';
}

if ($phone !== '' && !isValidPhone($phone)) {
    $errors['phone'] = 'Telefon raqamini +998XXXXXXXXX formatida kiriting.';
}

if (mb_strlen($companyName) > 255) {
    $errors['company_name'] = 'Kompaniya nomi juda uzun.';
}

if ($message === '' || mb_strlen($message) > 10000) {
    $errors['message'] = 'Xabar 1 dan 10000 belgigacha bo\'lishi kerak.';
}

if ($budgetMin !== null && $budgetMin !== '' && (!is_numeric($budgetMin) || (float)$budgetMin < 0)) {
    $errors['budget_min'] = 'Minimal budjet noto\'g\'ri.';
}

if ($budgetMax !== null && $budgetMax !== '' && (!is_numeric($budgetMax) || (float)$budgetMax < 0)) {
    $errors['budget_max'] = 'Maksimal budjet noto\'g\'ri.';
}

if ($budgetMin !== null && $budgetMin !== '' && $budgetMax !== null && $budgetMax !== '' && (float)$budgetMin > (float)$budgetMax) {
    $errors['budget'] = 'Minimal budjet maksimal budjetdan katta bo\'lishi mumkin emas.';
}

if ($deadlineDate !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $deadlineDate);
    if (!$date || $date->format('Y-m-d') !== $deadlineDate) {
        $errors['deadline_date'] = 'Muddat sanasi noto\'g\'ri.';
    }
}

if ($errors) {
    apiError('Validatsiya xatosi.', 422, $errors);
}

$now = date('Y-m-d H:i:s');
$serviceTitle = $service['title_uz'] ?? ($serviceName !== '' ? $serviceName : 'Boshqa xizmat');

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $applicationId = dbInsert('applications', [
        'user_id' => $userId,
        'service_id' => $serviceId,
        'full_name' => $fullName,
        'email' => $email !== '' ? $email : null,
        'phone' => $phone !== '' ? $phone : null,
        'company_name' => $companyName !== '' ? $companyName : null,
        'message' => $message,
        'budget_min' => $budgetMin !== null && $budgetMin !== '' ? (float)$budgetMin : null,
        'budget_max' => $budgetMax !== null && $budgetMax !== '' ? (float)$budgetMax : null,
        'deadline_date' => $deadlineDate !== '' ? $deadlineDate : null,
        'status' => 'new',
        'priority' => 'medium',
        'assigned_admin_id' => null,
        'source' => 'api',
        'ip_address' => getClientIp(),
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        'created_at' => $now,
        'updated_at' => $now,
        'reviewed_at' => null,
        'completed_at' => null
    ]);

    $thread = dbFetchOne(
        'SELECT id FROM chat_threads WHERE user_id = :user_id AND status = :status ORDER BY id DESC LIMIT 1',
        ['user_id' => $userId, 'status' => 'open']
    );

    if ($thread) {
        $threadId = (int)$thread['id'];
        dbExecute(
            'UPDATE chat_threads SET application_id = :application_id, subject = :subject, updated_at = :updated_at WHERE id = :id',
            [
                'application_id' => $applicationId,
                'subject' => 'Ariza: ' . $serviceTitle,
                'updated_at' => $now,
                'id' => $threadId
            ]
        );
    } else {
        $threadId = dbInsert('chat_threads', [
            'application_id' => $applicationId,
            'user_id' => $userId,
            'admin_id' => null,
            'subject' => 'Ariza: ' . $serviceTitle,
            'status' => 'open',
            'last_message_at' => $now,
            'last_message_by' => 'user',
            'unread_user_count' => 0,
            'unread_admin_count' => 0,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    dbInsert('notifications', [
        'user_id' => $userId,
        'title' => 'Arizangiz qabul qilindi',
        'message' => '“' . mb_substr($serviceTitle, 0, 180) . '” bo\'yicha arizangiz qabul qilindi.',
        'type' => 'application',
        'related_type' => 'application',
        'related_id' => $applicationId,
        'is_read' => 0,
        'read_at' => null,
        'created_at' => $now
    ]);

    $pdo->commit();

    apiResponse([
        'success' => true,
        'data' => [
            'id' => (int)$applicationId,
            'thread_id' => (int)$threadId,
            'service_id' => $serviceId,
            'service_name' => $serviceTitle,
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'company_name' => $companyName !== '' ? $companyName : null,
            'message' => $message,
            'budget_min' => $budgetMin !== null && $budgetMin !== '' ? (float)$budgetMin : null,
            'budget_max' => $budgetMax !== null && $budgetMax !== '' ? (float)$budgetMax : null,
            'deadline_date' => $deadlineDate !== '' ? $deadlineDate : null,
            'status' => 'new',
            'priority' => 'medium',
            'source' => 'api',
            'created_at' => $now
        ],
        'message' => 'Ariza muvaffaqiyatli yuborildi'
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Application API error: ' . $e->getMessage());
    apiError('Ariza yuborishda xatolik yuz berdi.', 500);
}
