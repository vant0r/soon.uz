<?php
/**
 * Admin - Chat Management
 * View and respond to user messages
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

// Handle message send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto\'g\'ri';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'send_message') {
            $threadId = (int) ($_POST['thread_id'] ?? 0);
            $msgText = sanitizeInput($_POST['message'] ?? '');
            
            $thread = dbFetchOne("SELECT * FROM chat_threads WHERE id = :id", ['id' => $threadId]);
            if (!$thread) {
                $message = 'Chat topilmadi';
                $messageType = 'error';
            } else {
                // Handle file upload if present
                $filePath = null;
                if (!empty($_FILES['attachment']['name'])) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $maxSize = (int) getSiteSetting('max_upload_doc_mb', 20) * 1024 * 1024;
                    
                    if ($_FILES['attachment']['size'] <= $maxSize) {
                        $mimeType = validateMimeType($_FILES['attachment']['tmp_name'], $allowedTypes);
                        if ($mimeType) {
                            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                            $filename = uniqid() . '.' . $ext;
                            $uploadPath = __DIR__ . '/../uploads/chat/' . $filename;
                            
                            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
                                $filePath = 'uploads/chat/' . $filename;
                                
                                dbInsert('media', [
                                    'filename' => $_FILES['attachment']['name'],
                                    'path' => $filePath,
                                    'mime_type' => $mimeType,
                                    'size_bytes' => $_FILES['attachment']['size'],
                                    'context' => 'chat_attachment',
                                    'uploaded_by' => $admin['id'],
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }
                }
                
                if (empty($msgText) && !$filePath) {
                    $message = 'Xabar yoki fayl yuklang';
                    $messageType = 'error';
                } else {
                    dbInsert('chat_messages', [
                        'thread_id' => $threadId,
                        'sender_type' => 'admin',
                        'sender_id' => $admin['id'],
                        'message' => $msgText ?: null,
                        'file_path' => $filePath,
                        'is_read' => false,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    logAdminAction($admin['id'], 'chat_message_sent', 'chat_message', null, ['thread_id' => $threadId]);
                    $message = 'Xabar yuborildi';
                    $messageType = 'success';
                }
            }
        }
    }
}

// Get selected thread
$selectedThreadId = isset($_GET['thread']) ? (int) $_GET['thread'] : null;
$selectedUser = null;
$messages = [];

if ($selectedThreadId) {
    $thread = dbFetchOne("SELECT * FROM chat_threads WHERE id = :id", ['id' => $selectedThreadId]);
    if ($thread) {
        $selectedUser = dbFetchOne("SELECT * FROM users WHERE id = :id", ['id' => $thread['user_id']]);
        $messages = dbFetchAll("
            SELECT cm.*, 
                   CASE WHEN cm.sender_type = 'admin' THEN a.name ELSE u.name END as sender_name
            FROM chat_messages cm
            LEFT JOIN admins a ON cm.sender_type = 'admin' AND cm.sender_id = a.id
            LEFT JOIN users u ON cm.sender_type = 'user' AND cm.sender_id = u.id
            WHERE cm.thread_id = :thread_id
            ORDER BY cm.created_at ASC
        ", ['thread_id' => $selectedThreadId]);
        
        // Mark messages as read
        dbQuery("UPDATE chat_messages SET is_read = TRUE WHERE thread_id = :thread_id AND sender_type = 'user'", ['thread_id' => $selectedThreadId]);
    }
}

// Get all threads with unread count
$threads = dbFetchAll("
    SELECT ct.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar,
           (SELECT COUNT(*) FROM chat_messages WHERE thread_id = ct.id AND sender_type = 'user' AND is_read = FALSE) as unread_count,
           (SELECT created_at FROM chat_messages WHERE thread_id = ct.id ORDER BY created_at DESC LIMIT 1) as last_message_at
    FROM chat_threads ct
    JOIN users u ON ct.user_id = u.id
    ORDER BY last_message_at DESC NULLS LAST
");
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - WebHub Admin</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { background: var(--bg-secondary); }
        
        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        
        @media (max-width: 1024px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .sidebar {
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            padding: 24px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 32px;
            display: block;
            text-decoration: none;
        }
        
        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .nav-link.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }
        
        .main-content {
            padding: 0;
            display: flex;
            height: calc(100vh - 60px);
        }
        
        .chat-list {
            width: 320px;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            background: var(--bg-primary);
        }
        
        .chat-item {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .chat-item:hover, .chat-item.active {
            background: var(--bg-secondary);
        }
        
        .chat-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .chat-item-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .chat-item-unread {
            background: var(--primary);
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .chat-item-preview {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
        }
        
        .chat-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
        }
        
        .message-admin {
            align-self: flex-end;
            background: var(--primary);
            color: white;
        }
        
        .message-user {
            align-self: flex-start;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        
        .message-image {
            max-width: 300px;
            border-radius: 8px;
            margin-top: 8px;
        }
        
        .chat-input {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .chat-input input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .chat-input input[type="file"] {
            display: none;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 24px 0;
        }
        
        .alert-success { background: #D1FAE5; color: #047857; }
        .alert-error { background: #FEE2E2; color: #B91C1C; }
        
        .no-chat-selected {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            font-size: 1.2rem;
        }
        
        .attachment-btn {
            padding: 10px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            color: var(--text-secondary);
        }
        
        .attachment-btn:hover {
            background: var(--bg-tertiary);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="dashboard.php" class="logo">WebHub Admin</a>
            
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                <a href="services.php" class="nav-link">🛠 Xizmatlar</a>
                <a href="portfolio.php" class="nav-link">📁 Portfolio</a>
                <a href="blog.php" class="nav-link">📝 Blog</a>
                <a href="applications.php" class="nav-link">📋 Arizalar</a>
                <a href="users.php" class="nav-link">👥 Foydalanuvchilar</a>
                <a href="chat.php" class="nav-link active">💬 Chat</a>
                <a href="settings.php" class="nav-link">⚙ Sozlamalar</a>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
                <a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko'rish</a>
                <a href="logout.php" class="nav-link" style="color: var(--error);">🚪 Chiqish</a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Chat List -->
            <div class="chat-list">
                <div style="padding: 16px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="margin: 0;">💬 Chatlar</h3>
                </div>
                
                <?php if (empty($threads)): ?>
                <div style="padding: 24px; text-align: center; color: var(--text-muted);">
                    Hozircha chatlar yo'q
                </div>
                <?php else: ?>
                <?php foreach ($threads as $thread): ?>
                <a href="?thread=<?php echo $thread['id']; ?>" class="chat-item <?php echo $selectedThreadId === $thread['id'] ? 'active' : ''; ?>">
                    <div class="chat-item-header">
                        <span class="chat-item-name"><?php echo e($thread['user_name']); ?></span>
                        <?php if ($thread['unread_count'] > 0): ?>
                        <span class="chat-item-unread"><?php echo $thread['unread_count']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="chat-item-preview">
                        <?php echo e($thread['user_email']); ?>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-main">
                <?php if (!$selectedThreadId || !$selectedUser): ?>
                <div class="no-chat-selected">
                    Chatni tanlang
                </div>
                <?php else: ?>
                <div class="chat-header">
                    <div class="user-avatar">
                        <?php echo strtoupper(mb_substr($selectedUser['name'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo e($selectedUser['name']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($selectedUser['email']); ?></div>
                    </div>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
                <?php endif; ?>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                        Hozircha xabarlar yo'q
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="message message-<?php echo $msg['sender_type']; ?>">
                        <?php if ($msg['message']): ?>
                        <div><?php echo e($msg['message']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($msg['file_path']): ?>
                        <?php 
                        $ext = strtolower(pathinfo($msg['file_path'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                        ?>
                        <?php if ($isImage): ?>
                        <img src="../<?php echo e($msg['file_path']); ?>" alt="" class="message-image">
                        <?php else: ?>
                        <a href="../<?php echo e($msg['file_path']); ?>" target="_blank" style="color: inherit; text-decoration: underline; font-size: 0.85rem;">
                            📎 Faylni yuklab olish
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="message-time"><?php echo date('H:i d.m.Y', strtotime($msg['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="chat-input">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="thread_id" value="<?php echo $selectedThreadId; ?>">
                    
                    <label class="attachment-btn" title="Fayl yuklash">
                        📎
                        <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                    </label>
                    
                    <input type="text" name="message" placeholder="Xabar yozing..." autocomplete="off">
                    
                    <button type="submit" class="btn btn-primary" style="border-radius: 24px; padding: 12px 24px;">
                        ➤ Yuborish
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Auto-scroll to bottom of chat
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Auto-refresh chat every 10 seconds
        <?php if ($selectedThreadId): ?>
        setInterval(function() {
            location.reload();
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>
