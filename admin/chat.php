<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';
$uploadDir = __DIR__ . '/../uploads/chat';
$uploadUrl = 'uploads/chat/';
$allowedFiles = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function chatUpload(array $file, string $dir, string $url, array $allowed, int $maxSize): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Faylni yuklashda xatolik yuz berdi');
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) throw new RuntimeException('Fayl hajmi ruxsat etilgan chegaradan oshdi');
    $mime = validateMimeType($file['tmp_name'], array_keys($allowed));
    if (!$mime || !isset($allowed[$mime])) throw new RuntimeException('Fayl formati ruxsat etilmagan');
    if (strpos($mime, 'image/') === 0 && !@getimagesize($file['tmp_name'])) throw new RuntimeException('Rasm fayli yaroqsiz');
    $name = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Faylni saqlab bo‘lmadi');
    return rtrim($url, '/') . '/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } else {
        try {
            $threadId = (int) ($_POST['thread_id'] ?? 0);
            $text = trim(sanitizeInput($_POST['message'] ?? ''));
            $thread = dbFetchOne('SELECT * FROM chat_threads WHERE id = :id', ['id'=>$threadId]);
            if (!$thread) throw new RuntimeException('Chat topilmadi');
            $maxMb = max(1, (int) getSiteSetting('max_upload_doc_mb', 20));
            $filePath = chatUpload($_FILES['attachment'] ?? ['error'=>UPLOAD_ERR_NO_FILE], $uploadDir, $uploadUrl, $allowedFiles, $maxMb * 1024 * 1024);
            if ($text === '' && !$filePath) throw new RuntimeException('Xabar yoki fayl yuboring');
            dbInsert('chat_messages', ['thread_id'=>$threadId,'sender_id'=>$admin['id'],'sender_type'=>'admin','message'=>$text !== '' ? $text : 'Fayl yuborildi','file_path'=>$filePath,'file_type'=>$filePath ? pathinfo($filePath,PATHINFO_EXTENSION) : null,'file_size'=>$filePath ? (int) ($_FILES['attachment']['size'] ?? 0) : null,'is_read'=>1]);
            dbUpdate('chat_threads', ['admin_id'=>$admin['id'],'last_message_at'=>date('Y-m-d H:i:s'),'last_message_by'=>'admin','unread_user_count'=>'unread_user_count + 1'], 'id = :id', ['id'=>$threadId]);
            if (!empty($thread['user_id'])) dbInsert('notifications', ['user_id'=>$thread['user_id'],'title'=>'Yangi xabar','message'=>'Sizga yangi xabar keldi','type'=>'chat_message','related_type'=>'chat_thread','related_id'=>$threadId,'is_read'=>0]);
            logAdminAction($admin['id'],'chat_message_sent','chat_message',null,['thread_id'=>$threadId]);
            $message = 'Xabar yuborildi';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

$selectedThreadId = (int) ($_GET['thread'] ?? 0);
$selectedThread = $selectedThreadId ? dbFetchOne('SELECT * FROM chat_threads WHERE id = :id', ['id'=>$selectedThreadId]) : null;
$selectedUser = $selectedThread ? dbFetchOne('SELECT * FROM users WHERE id = :id', ['id'=>$selectedThread['user_id']]) : null;
$messages = $selectedThread ? dbFetchAll("SELECT cm.*, CASE WHEN cm.sender_type='admin' THEN a.full_name ELSE u.full_name END AS sender_name FROM chat_messages cm LEFT JOIN admins a ON cm.sender_type='admin' AND cm.sender_id=a.id LEFT JOIN users u ON cm.sender_type='user' AND cm.sender_id=u.id WHERE cm.thread_id=:thread_id ORDER BY cm.created_at ASC", ['thread_id'=>$selectedThreadId]) : [];
if ($selectedThread) {
    dbUpdate('chat_threads',['unread_admin_count'=>0],'id=:id',['id'=>$selectedThreadId]);
    dbQuery('UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE thread_id = :id AND sender_type = :type', ['id'=>$selectedThreadId,'type'=>'user']);
}
$threads = dbFetchAll("SELECT ct.*,u.full_name AS user_name,u.email AS user_email,(SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id=ct.id AND cm.sender_type='user' AND cm.is_read=0) AS unread_count,(SELECT cm.message FROM chat_messages cm WHERE cm.thread_id=ct.id ORDER BY cm.id DESC LIMIT 1) AS last_message FROM chat_threads ct JOIN users u ON u.id=ct.user_id ORDER BY COALESCE(ct.last_message_at,ct.created_at) DESC");
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="uz"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Chat — SOON Admin</title><link rel="stylesheet" href="../assets/css/main.css"><style>
body{background:var(--bg-secondary)}.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:var(--bg-primary);border-right:1px solid var(--border-color);padding:24px;position:sticky;top:0;height:100vh;overflow-y:auto}.logo{font-size:1.5rem;font-weight:700;color:var(--primary);margin-bottom:32px;display:block;text-decoration:none}.nav-menu{display:flex;flex-direction:column;gap:8px}.nav-link{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;color:var(--text-secondary);text-decoration:none}.nav-link:hover,.nav-link.active{background:var(--bg-tertiary);color:var(--text-primary)}.nav-link.active{background:rgba(59,130,246,.1);color:var(--primary)}.main-content{display:flex;height:100vh;min-height:600px}.chat-list{width:320px;flex:0 0 320px;background:var(--bg-primary);border-right:1px solid var(--border-color);overflow:auto}.chat-list-head{padding:18px;border-bottom:1px solid var(--border-color)}.chat-item{display:block;padding:15px 18px;border-bottom:1px solid var(--border-color);text-decoration:none;color:var(--text-primary)}.chat-item:hover,.chat-item.active{background:var(--bg-secondary)}.chat-name{font-weight:600}.chat-preview{font-size:.82rem;color:var(--text-muted);margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.unread{float:right;background:var(--primary);color:#fff;border-radius:20px;padding:2px 8px;font-size:.72rem}.chat-main{flex:1;display:flex;flex-direction:column;min-width:0;background:var(--bg-primary)}.chat-header{padding:15px 22px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:12px}.avatar{width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}.messages{flex:1;overflow:auto;padding:22px;display:flex;flex-direction:column;gap:12px}.bubble{max-width:min(72%,600px);padding:11px 14px;border-radius:15px}.bubble.admin{align-self:flex-end;background:var(--primary);color:#fff}.bubble.user{align-self:flex-start;background:var(--bg-secondary);color:var(--text-primary)}.time{font-size:.7rem;opacity:.65;margin-top:5px}.bubble img{max-width:280px;border-radius:10px;margin-top:7px}.composer{display:flex;gap:9px;padding:14px 18px;border-top:1px solid var(--border-color)}.composer input[type=text]{flex:1;min-width:0;padding:12px 16px;border:1px solid var(--border-color);border-radius:24px;background:var(--bg-secondary);color:var(--text-primary)}.file-label{padding:11px;border:1px solid var(--border-color);border-radius:50%;cursor:pointer}.file-label input{display:none}.alert{margin:12px 18px 0;padding:10px 14px;border-radius:8px}.alert-success{background:#D1FAE5;color:#047857}.alert-error{background:#FEE2E2;color:#B91C1C}.empty{height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-muted)}@media(max-width:1024px){.admin-layout{grid-template-columns:1fr}.sidebar{position:relative;height:auto}.main-content{height:calc(100vh - 300px)}}@media(max-width:700px){.chat-list{width:38%;flex-basis:38%}.bubble{max-width:88%}}
</style></head><body><div class="admin-layout"><aside class="sidebar"><a href="dashboard.php" class="logo">SOON Admin</a><nav class="nav-menu"><a href="dashboard.php" class="nav-link">📊 Boshqaruv</a><a href="services.php" class="nav-link">🛠 Xizmatlar</a><a href="portfolio.php" class="nav-link">📁 Portfolio</a><a href="blog.php" class="nav-link">📝 Blog</a><a href="applications.php" class="nav-link">📋 Arizalar</a><a href="users.php" class="nav-link">👥 Foydalanuvchilar</a><a href="chat.php" class="nav-link active">💬 Chat</a><a href="settings.php" class="nav-link">⚙ Sozlamalar</a><hr style="border:0;border-top:1px solid var(--border-color);margin:8px 0"><a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko‘rish</a><a href="logout.php" class="nav-link" style="color:var(--error)">🚪 Chiqish</a></nav></aside><main class="main-content"><section class="chat-list"><div class="chat-list-head"><strong>💬 Chatlar</strong></div><?php if(!$threads): ?><div style="padding:30px;text-align:center;color:var(--text-muted)">Chatlar yo‘q</div><?php else: foreach($threads as $thread): ?><a class="chat-item <?php echo $selectedThreadId===(int)$thread['id']?'active':''; ?>" href="?thread=<?php echo (int)$thread['id']; ?>"><span class="chat-name"><?php echo e($thread['user_name']); ?></span><?php if($thread['unread_count']): ?><span class="unread"><?php echo (int)$thread['unread_count']; ?></span><?php endif; ?><div class="chat-preview"><?php echo e($thread['last_message']?:$thread['user_email']); ?></div></a><?php endforeach; endif; ?></section><section class="chat-main"><?php if(!$selectedThread||!$selectedUser): ?><div class="empty">Chatni tanlang</div><?php else: ?><header class="chat-header"><div class="avatar"><?php echo e(mb_strtoupper(mb_substr($selectedUser['full_name'],0,1))); ?></div><div><strong><?php echo e($selectedUser['full_name']); ?></strong><div style="font-size:.82rem;color:var(--text-muted)"><?php echo e($selectedUser['email']); ?></div></div></header><?php if($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?><div class="messages" id="messages"><?php foreach($messages as $msg): ?><div class="bubble <?php echo $msg['sender_type']==='admin'?'admin':'user'; ?>"><?php if($msg['message']): ?><div><?php echo e($msg['message']); ?></div><?php endif; ?><?php if($msg['file_path']): ?><?php $ext=strtolower(pathinfo($msg['file_path'],PATHINFO_EXTENSION)); if(in_array($ext,['jpg','jpeg','png','webp','gif'],true)): ?><img src="../<?php echo e($msg['file_path']); ?>" alt=""><?php else: ?><a href="../<?php echo e($msg['file_path']); ?>" target="_blank" style="color:inherit">📎 Faylni ochish</a><?php endif; ?><?php endif; ?><div class="time"><?php echo date('H:i d.m.Y',strtotime($msg['created_at'])); ?></div></div><?php endforeach; ?></div><form method="POST" enctype="multipart/form-data" class="composer"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="thread_id" value="<?php echo (int)$selectedThreadId; ?>"><label class="file-label">📎<input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,.doc,.docx"></label><input type="text" name="message" maxlength="5000" placeholder="Xabar yozing…" autocomplete="off"><button class="btn btn-primary" type="submit">Yuborish</button></form><?php endif; ?></section></main></div><script src="../assets/js/main.js"></script><script>const m=document.getElementById('messages');if(m)m.scrollTop=m.scrollHeight;</script></body></html>
