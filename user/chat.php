<?php
require_once __DIR__ . '/../includes/functions.php';
requireUser();

$userId = (int)$_SESSION['user_id'];
$user = getCurrentUser();
if (!$user) {
    session_destroy();
    redirect('login.php');
}

$thread = getOrCreateChatThread($userId);
$threadId = (int)$thread['id'];
$message = '';
$messageType = '';
$uploadDir = __DIR__ . '/../uploads/chat';
$uploadUrl = 'uploads/chat/';
$allowedFiles = [
    'image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp',
    'image/gif'=>'gif',
    'application/pdf'=>'pdf',
    'application/msword'=>'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'
];

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function userChatUpload(array $file, string $dir, string $url, array $allowed, int $maxSize): ?array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Faylni yuklashda xatolik yuz berdi');
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) throw new RuntimeException('Fayl hajmi ruxsat etilgan chegaradan oshdi');
    $mime = validateMimeType($file['tmp_name'], array_keys($allowed));
    if (!$mime || !isset($allowed[$mime])) throw new RuntimeException('Fayl formati ruxsat etilmagan');
    if (strpos($mime, 'image/') === 0 && !@getimagesize($file['tmp_name'])) throw new RuntimeException('Rasm fayli yaroqsiz');
    $name = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Faylni saqlab bo‘lmadi');
    return ['path'=>rtrim($url,'/').'/'.$name,'type'=>$allowed[$mime],'size'=>(int)$file['size']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['status'] !== 'active') {
        $message = 'Hisobingiz faol emas';
        $messageType = 'error';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } else {
        try {
            $text = trim(sanitizeInput($_POST['message'] ?? ''));
            $maxMb = max(1, (int)getSiteSetting('max_upload_image_mb', 10));
            $upload = userChatUpload($_FILES['attachment'] ?? ['error'=>UPLOAD_ERR_NO_FILE], $uploadDir, $uploadUrl, $allowedFiles, $maxMb * 1024 * 1024);
            if ($text === '' && !$upload) throw new RuntimeException('Xabar yoki fayl yuboring');
            dbInsert('chat_messages', [
                'thread_id'=>$threadId,
                'sender_type'=>'user',
                'sender_id'=>$userId,
                'message'=>$text !== '' ? $text : 'Fayl yuborildi',
                'file_path'=>$upload['path'] ?? null,
                'file_type'=>$upload['type'] ?? null,
                'file_size'=>$upload['size'] ?? null,
                'is_read'=>0
            ]);
            dbQuery('UPDATE chat_threads SET last_message_at = NOW(), last_message_by = \'user\', unread_admin_count = unread_admin_count + 1, status = \'open\' WHERE id = :id AND user_id = :user_id', ['id'=>$threadId,'user_id'=>$userId]);
            redirect('chat.php');
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

dbQuery('UPDATE chat_threads SET unread_user_count = 0 WHERE id = :id AND user_id = :user_id', ['id'=>$threadId,'user_id'=>$userId]);
dbQuery('UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE thread_id = :id AND sender_type = \'admin\'', ['id'=>$threadId]);
$messages = dbFetchAll("SELECT cm.*, CASE WHEN cm.sender_type='admin' THEN a.full_name ELSE u.full_name END AS sender_name FROM chat_messages cm LEFT JOIN admins a ON cm.sender_type='admin' AND cm.sender_id=a.id LEFT JOIN users u ON cm.sender_type='user' AND cm.sender_id=u.id WHERE cm.thread_id=:thread_id ORDER BY cm.created_at ASC LIMIT 100", ['thread_id'=>$threadId]);
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Chat — SOON</title><link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}.chat-wrap{max-width:900px;margin:0 auto;padding:30px 16px}.chat-card{overflow:hidden}.chat-head{padding:22px;border-bottom:1px solid var(--border-color)}.chat-head h1{margin:0 0 4px}.chat-head p{margin:0;color:var(--text-muted)}.messages{height:560px;overflow:auto;padding:22px;display:flex;flex-direction:column;gap:12px}.bubble{max-width:min(76%,620px);padding:12px 15px;border-radius:16px}.bubble.user{align-self:flex-end;background:var(--primary);color:#fff}.bubble.admin{align-self:flex-start;background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--border-color)}.bubble img{display:block;max-width:280px;max-height:280px;border-radius:10px;margin-top:8px}.bubble a{color:inherit;text-decoration:underline}.meta{font-size:.72rem;opacity:.7;margin-top:6px}.composer{padding:16px;border-top:1px solid var(--border-color);display:flex;gap:9px;align-items:center}.composer input[type=text]{flex:1;min-width:0;padding:12px 16px;border:1px solid var(--border-color);border-radius:24px;background:var(--bg-secondary);color:var(--text-primary)}.file-label{padding:11px;border:1px solid var(--border-color);border-radius:50%;cursor:pointer}.file-label input{display:none}.alert{margin:14px 16px 0;padding:11px 14px;border-radius:10px}.alert-error{background:#FEE2E2;color:#B91C1C}@media(max-width:650px){.messages{height:calc(100vh - 300px);min-height:380px;padding:15px}.bubble{max-width:88%}.composer{flex-wrap:wrap}.composer input[type=text]{order:1;flex-basis:calc(100% - 65px)}.composer button{order:2}.file-label{order:3}}
</style></head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="chat-wrap"><section class="glass-card chat-card"><header class="chat-head"><h1>Admin bilan chat</h1><p>Savolingizni yozing yoki fayl yuboring.</p></header>
<?php if($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?>
<div class="messages" id="messages">
<?php if(!$messages): ?><div style="margin:auto;color:var(--text-muted)">Hali xabarlar yo‘q.</div><?php else: foreach($messages as $msg): ?><div class="bubble <?php echo $msg['sender_type']==='user'?'user':'admin'; ?>"><?php if($msg['message']): ?><div><?php echo nl2br(e($msg['message'])); ?></div><?php endif; ?><?php if($msg['file_path']): ?><?php $ext=strtolower(pathinfo($msg['file_path'],PATHINFO_EXTENSION)); if(in_array($ext,['jpg','jpeg','png','webp','gif'],true)): ?><img src="../<?php echo e($msg['file_path']); ?>" alt="Yuborilgan rasm" loading="lazy"><?php else: ?><a href="../<?php echo e($msg['file_path']); ?>" target="_blank" rel="noopener noreferrer">📎 Faylni ochish</a><?php endif; ?><?php endif; ?><div class="meta"><?php echo e($msg['sender_type']==='admin'?($msg['sender_name']?:'Admin'):'Siz'); ?> · <?php echo e(date('d.m.Y H:i',strtotime($msg['created_at']))); ?></div></div><?php endforeach; endif; ?>
</div>
<form method="POST" enctype="multipart/form-data" class="composer"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><label class="file-label">📎<input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,.doc,.docx"></label><input type="text" name="message" maxlength="5000" placeholder="Xabar yozing…" autocomplete="off"><button class="btn btn-primary" type="submit">Yuborish</button></form>
</section></main>
<?php include __DIR__ . '/footer.php'; ?><script src="../assets/js/main.js"></script><script>const messages=document.getElementById('messages');if(messages)messages.scrollTop=messages.scrollHeight;</script>
</body></html>
