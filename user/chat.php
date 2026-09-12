<?php
/**
 * User Chat Page - /user/chat.php
 * One-on-one chat with admin
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user || $user['status'] === 'deleted') {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['status'] === 'blocked') {
    $error = 'Sizning hisobingiz bloklangan.';
}

// Get or create chat thread
$thread = dbFetchOne("SELECT id FROM chat_threads WHERE user_id = ?", [$userId]);

if (!$thread) {
    $threadId = dbInsert('chat_threads', [
        'user_id' => $userId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $thread = ['id' => $threadId];
} else {
    $threadId = $thread['id'];
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    verifyCSRFToken($_POST['csrf_token'] ?? '');
    
    $message = trim($_POST['message'] ?? '');
    $filePath = null;
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxSize = (int)getSiteSetting('max_upload_doc_mb', 20) * 1024 * 1024;
        
        $tmpName = $_FILES['attachment']['tmp_name'];
        $fileSize = $_FILES['attachment']['size'];
        
        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            $error = 'Fayl turi ruxsat etilmagan.';
        } elseif ($fileSize > $maxSize) {
            $error = 'Fayl hajmi juda katta.';
        } else {
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $safeFilename = uniqid('chat_') . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/chat/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($tmpName, $uploadDir . $safeFilename)) {
                $filePath = 'uploads/chat/' . $safeFilename;
            } else {
                $error = 'Faylni yuklashda xatolik.';
            }
        }
    }
    
    if (!isset($error) && ($message !== '' || $filePath !== null)) {
        dbInsert('chat_messages', [
            'thread_id' => $threadId,
            'sender_type' => 'user',
            'sender_id' => $userId,
            'message' => $message === '' ? null : $message,
            'file_path' => $filePath,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Redirect to prevent form resubmission
        header('Location: chat.php');
        exit;
    } elseif (!isset($error)) {
        $error = 'Xabar yoki fayl kiriting.';
    }
}

// Get messages
$messages = dbFetchAll(
    "SELECT m.*, 
            u.name as sender_name,
            a.name as admin_name
     FROM chat_messages m
     LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
     LEFT JOIN admins a ON m.sender_type = 'admin' AND m.sender_id = a.id
     WHERE m.thread_id = ?
     ORDER BY m.id DESC
     LIMIT 50",
    [$threadId]
);

$messages = array_reverse($messages);

$pageTitle = 'Chat';
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - WebHub.uz</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .chat-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .chat-messages {
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message-user {
            align-self: flex-end;
            background: var(--primary-color);
            color: white;
        }
        .message-admin {
            align-self: flex-start;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }
        .message-meta {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 5px;
        }
        .message-file {
            margin-top: 8px;
        }
        .message-file img {
            max-width: 200px;
            border-radius: 8px;
        }
        .message-file a {
            color: inherit;
            text-decoration: underline;
        }
        .chat-form {
            padding: 20px;
            border-top: 1px solid var(--glass-border);
        }
        .chat-input-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .chat-input-row input[type="text"] {
            flex: 1;
        }
        .chat-input-row input[type="file"] {
            max-width: 200px;
        }
    </style>
</head>
<body class="user-page">
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <main class="user-main">
        <div class="container">
            <div class="chat-container glass-card">
                <h1><?= e($pageTitle) ?></h1>
                <p class="chat-subtitle">Admin bilan bog'lanish</p>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= e($error) ?></div>
                <?php endif; ?>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <p class="no-messages">Hali xabarlar yo'q. Xabar yozing!</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message message-<?= e($msg['sender_type']) ?>">
                                <?php if ($msg['message']): ?>
                                    <div class="message-text"><?= nl2br(e($msg['message'])) ?></div>
                                <?php endif; ?>
                                
                                <?php if ($msg['file_path']): ?>
                                    <div class="message-file">
                                        <?php 
                                        $ext = strtolower(pathinfo($msg['file_path'], PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])):
                                        ?>
                                            <img src="/<?= e($msg['file_path']) ?>" alt="Yuklangan rasm">
                                        <?php else: ?>
                                            <a href="/<?= e($msg['file_path']) ?>" download>📎 Faylni yuklab olish</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message-meta">
                                    <?= e($msg['sender_type'] === 'admin' ? ($msg['admin_name'] ?? 'Admin') : 'Siz') ?>
                                    · <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="chat-form">
                    <?= csrfField() ?>
                    <div class="chat-input-row">
                        <input type="text" name="message" placeholder="Xabar yozing..." maxlength="2000">
                        <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                        <button type="submit" class="btn btn-primary">Yuborish</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        // Auto-scroll to bottom
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Auto-refresh every 10 seconds
        setTimeout(() => location.reload(), 10000);
    </script>
</body>
</html>
