<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));
$threadId = isset($parts[count($parts) - 1]) ? (int)$parts[count($parts) - 1] : 0;

if ($threadId < 1) {
    apiError('Thread ID talab qilinadi.', 400);
}

$thread = dbFetchOne(
    'SELECT id, user_id, status FROM chat_threads WHERE id = :id LIMIT 1',
    ['id' => $threadId]
);

if (!$thread) {
    apiError('Chat topilmadi.', 404);
}

if ((int)$thread['user_id'] !== $userId) {
    apiError('Ushbu chatga kirish huquqingiz yo\'q.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT m.id, m.sender_type, m.sender_id, m.message, m.file_path, m.file_type, m.file_size, m.is_read, m.read_at, m.created_at,
                   u.full_name AS user_name, u.avatar_url AS user_avatar,
                   a.full_name AS admin_name
            FROM chat_messages m
            LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
            LEFT JOIN admins a ON m.sender_type = 'admin' AND m.sender_id = a.id
            WHERE m.thread_id = ?
            ORDER BY m.id DESC";

    $countSql = 'SELECT COUNT(*) AS total FROM chat_messages WHERE thread_id = ?';
    $result = getPaginatedResults($sql, [$threadId], $countSql, [$threadId]);
    $messages = array_reverse($result['data']);
    $baseUrl = rtrim(getSiteSetting('site_url', ''), '/');

    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    foreach ($messages as &$msg) {
        $msg['id'] = (int)$msg['id'];
        $msg['sender_id'] = (int)$msg['sender_id'];
        $msg['file_size'] = $msg['file_size'] !== null ? (int)$msg['file_size'] : null;
        $msg['is_read'] = (bool)$msg['is_read'];
        $msg['sender_name'] = $msg['sender_type'] === 'admin'
            ? ($msg['admin_name'] ?: 'Administrator')
            : ($msg['user_name'] ?: 'Foydalanuvchi');
        $msg['sender_avatar'] = $msg['sender_type'] === 'admin'
            ? null
            : ($msg['user_avatar'] ?: null);
        $msg['file_url'] = $msg['file_path'] ? $baseUrl . '/' . ltrim($msg['file_path'], '/') : null;
        unset($msg['user_name'], $msg['admin_name'], $msg['user_avatar']);
    }
    unset($msg);

    apiResponse([
        'success' => true,
        'data' => $messages,
        'thread' => [
            'id' => (int)$thread['id'],
            'status' => $thread['status']
        ],
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

$message = trim((string)($data['message'] ?? ''));
$attachment = isset($data['attachment']) && is_array($data['attachment']) ? $data['attachment'] : null;
$errors = [];

if ($message !== '' && mb_strlen($message) > 10000) {
    $errors['message'] = 'Xabar 10000 belgidan oshmasligi kerak.';
}

if ($message === '' && !$attachment) {
    $errors['message'] = 'Xabar yoki fayl ilovasi talab qilinadi.';
}

$filePath = null;
$fileType = null;
$fileSize = null;
$createdFile = null;

if ($attachment) {
    $filename = trim((string)($attachment['filename'] ?? ''));
    $mimeType = trim((string)($attachment['mime_type'] ?? ''));
    $base64 = (string)($attachment['data_base64'] ?? '');

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'zip'
    ];

    if ($filename === '' || mb_strlen($filename) > 255) {
        $errors['attachment'] = 'Fayl nomi noto\'g\'ri.';
    }

    if (!isset($allowedMimes[$mimeType])) {
        $errors['attachment'] = 'Fayl turi ruxsat etilmagan.';
    }

    if ($base64 === '' || mb_strlen($base64) > 30 * 1024 * 1024) {
        $errors['attachment'] = 'Fayl ma\'lumotlari noto\'g\'ri yoki juda katta.';
    }

    if (!$errors) {
        $fileData = base64_decode($base64, true);
        if ($fileData === false) {
            $errors['attachment'] = 'Fayl ma\'lumotlari noto\'g\'ri.';
        } else {
            $fileSize = strlen($fileData);
            $maxSize = max(1, (int)getSiteSetting('max_upload_doc_mb', 20)) * 1024 * 1024;
            if ($fileSize > $maxSize) {
                $errors['attachment'] = 'Fayl hajmi ruxsat etilgan limitdan oshdi.';
            } else {
                $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($fileData);
                if ($detectedMime !== $mimeType) {
                    $errors['attachment'] = 'Fayl turi haqiqiy MIME turiga mos kelmaydi.';
                }
            }
        }
    }

    if (!$errors) {
        $uploadDir = __DIR__ . '/../uploads/chat/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            apiError('Fayl papkasini yaratishda xatolik.', 500);
        }

        $extension = $allowedMimes[$mimeType];
        $safeFilename = bin2hex(random_bytes(24)) . '.' . $extension;
        $absolutePath = $uploadDir . $safeFilename;
        $filePath = 'uploads/chat/' . $safeFilename;

        if (file_put_contents($absolutePath, $fileData, LOCK_EX) === false) {
            apiError('Faylni saqlashda xatolik.', 500);
        }

        $createdFile = $absolutePath;
        $fileType = $mimeType;
    }
}

if ($errors) {
    apiError('Validatsiya xatosi.', 422, $errors);
}

$now = date('Y-m-d H:i:s');

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $messageId = dbInsert('chat_messages', [
        'thread_id' => $threadId,
        'sender_id' => $userId,
        'sender_type' => 'user',
        'message' => $message !== '' ? $message : null,
        'file_path' => $filePath,
        'file_type' => $fileType,
        'file_size' => $fileSize,
        'is_read' => 0,
        'read_at' => null,
        'created_at' => $now
    ]);

    $pdo->prepare(
        'UPDATE chat_threads
         SET last_message_at = :last_message_at,
             last_message_by = :last_message_by,
             unread_admin_count = unread_admin_count + 1,
             updated_at = :updated_at
         WHERE id = :id'
    )->execute([
        'last_message_at' => $now,
        'last_message_by' => 'user',
        'updated_at' => $now,
        'id' => $threadId
    ]);

    $pdo->commit();

    $baseUrl = rtrim(getSiteSetting('site_url', ''), '/');
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    apiResponse([
        'success' => true,
        'data' => [
            'id' => (int)$messageId,
            'thread_id' => $threadId,
            'sender_id' => $userId,
            'sender_type' => 'user',
            'message' => $message !== '' ? $message : null,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'file_url' => $filePath ? $baseUrl . '/' . ltrim($filePath, '/') : null,
            'is_read' => false,
            'created_at' => $now
        ],
        'message' => 'Xabar yuborildi'
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($createdFile && is_file($createdFile)) {
        @unlink($createdFile);
    }
    error_log('Chat API error: ' . $e->getMessage());
    apiError('Xabar yuborishda xatolik.', 500);
}
