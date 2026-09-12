<?php
/**
 * GET /api/chat/{thread_id} - Get messages in user's thread (paginated)
 * POST /api/chat/{thread_id} - Send message (with optional base64 attachment)
 * Rate limit: 60 requests/minute per token
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

// Get thread_id from URL path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));
$threadId = isset($parts[count($parts) - 1]) ? (int)$parts[count($parts) - 1] : null;

if (!$threadId) {
    apiError('Thread ID talab qilinadi.', 400);
}

// Verify ownership of thread
$thread = dbFetchOne("SELECT id, user_id FROM chat_threads WHERE id = ?", [$threadId]);

if (!$thread) {
    apiError('Chat topilmadi.', 404);
}

if ((int)$thread['user_id'] !== $userId) {
    apiError('Ushbu chatga kirish huquqingiz yo\'q.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get messages
    $sql = "SELECT m.id, m.sender_type, m.sender_id, m.message, m.file_path, 
                   m.is_read, m.created_at,
                   u.name as sender_name, u.avatar as sender_avatar,
                   a.name as admin_name
            FROM chat_messages m
            LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
            LEFT JOIN admins a ON m.sender_type = 'admin' AND m.sender_id = a.id
            WHERE m.thread_id = ?
            ORDER BY m.id DESC";
    
    $countSql = "SELECT COUNT(*) as total FROM chat_messages WHERE thread_id = ?";
    
    $result = getPaginatedResults($sql, [$threadId], $countSql, [$threadId]);
    
    $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    // Reverse to get chronological order
    $messages = array_reverse($result['data']);
    
    apiResponse([
        'success' => true,
        'data' => array_map(function($msg) use ($baseUrl) {
            return [
                'id' => (int)$msg['id'],
                'sender_type' => $msg['sender_type'],
                'sender_name' => $msg['sender_type'] === 'admin' ? ($msg['admin_name'] ?? 'Admin') : ($msg['sender_name'] ?? 'Foydalanuvchi'),
                'message' => $msg['message'],
                'file_path' => $msg['file_path'] ? $baseUrl . '/' . $msg['file_path'] : null,
                'is_read' => (bool)$msg['is_read'],
                'created_at' => $msg['created_at']
            ];
        }, $messages),
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total']
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send message
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        apiError('Noto\'g\'ri so\'rov formati.', 400);
    }
    
    $errors = [];
    
    // Validate message or attachment
    $message = trim($data['message'] ?? '');
    $attachment = isset($data['attachment']) && is_array($data['attachment']) ? $data['attachment'] : null;
    
    if ($message === '' && !$attachment) {
        $errors['message'] = 'Xabar yoki fayl ilovasi talab qilinadi.';
    }
    
    // Process attachment if provided
    $filePath = null;
    if ($attachment) {
        // Validate attachment fields
        if (!isset($attachment['filename']) || !isset($attachment['mime_type']) || !isset($attachment['data_base64'])) {
            $errors['attachment'] = 'Fayl ma\'lumotlari noto\'g\'ri.';
        } else {
            // Validate MIME type
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'];
            if (!in_array($attachment['mime_type'], $allowedMimes)) {
                $errors['attachment'] = 'Fayl turi ruxsat etilmagan.';
            }
            
            // Decode and validate size
            $fileData = base64_decode($attachment['data_base64']);
            if ($fileData === false) {
                $errors['attachment'] = 'Fayl ma\'lumotlari noto\'g\'ri.';
            } else {
                $maxSize = (int)getSiteSetting('max_upload_doc_mb', 20) * 1024 * 1024;
                if (strlen($fileData) > $maxSize) {
                    $errors['attachment'] = 'Fayl hajmi juda katta.';
                } else {
                    // Save file
                    $uploadDir = __DIR__ . '/../uploads/chat/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $ext = pathinfo($attachment['filename'], PATHINFO_EXTENSION);
                    $safeFilename = uniqid('chat_') . '.' . $ext;
                    $filePath = 'uploads/chat/' . $safeFilename;
                    
                    if (file_put_contents($uploadDir . $safeFilename, $fileData) === false) {
                        apiError('Faylni saqlashda xatolik.', 500);
                    }
                }
            }
        }
    }
    
    if (!empty($errors)) {
        apiError('Validatsiya xatosi.', 422, $errors);
    }
    
    try {
        // Insert message
        $msgId = dbInsert('chat_messages', [
            'thread_id' => $threadId,
            'sender_type' => 'user',
            'sender_id' => $userId,
            'message' => $message === '' ? null : $message,
            'file_path' => $filePath,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Mark thread notifications as read for this user
        $pdo = getDbConnection();
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND title LIKE '%chat%'")
            ->execute([$userId]);
        
        apiResponse([
            'success' => true,
            'data' => [
                'id' => (int)$msgId,
                'sender_type' => 'user',
                'message' => $message,
                'file_path' => $filePath,
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ],
            'message' => 'Xabar yuborildi'
        ], 201);
        
    } catch (Exception $e) {
        error_log("Chat message error: " . $e->getMessage());
        apiError('Xabar yuborishda xatolik.', 500);
    }
    
} else {
    apiError('Faqat GET va POST so\'rovlari qabul qilinadi.', 405);
}
