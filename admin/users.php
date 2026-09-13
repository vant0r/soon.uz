<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active', 'blocked', 'deleted'], true)) {
            $message = 'Noto‘g‘ri holat';
            $messageType = 'error';
        } else {
            $user = dbFetchOne('SELECT * FROM users WHERE id = :id', ['id' => $id]);
            if (!$user) {
                $message = 'Foydalanuvchi topilmadi';
                $messageType = 'error';
            } else {
                dbUpdate('users', ['status' => $status], 'id = :id', ['id' => $id]);
                logAdminAction($admin['id'], 'user_status_update', 'user', $id, ['old_status' => $user['status'], 'new_status' => $status]);
                $message = 'Foydalanuvchi holati yangilandi';
                $messageType = 'success';
            }
        }
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$totalUsers = (int) (dbFetchOne('SELECT COUNT(*) AS count FROM users')['count'] ?? 0);
$pagination = getPaginationData($totalUsers, $page, $perPage);
$users = dbFetchAll("SELECT u.*, (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) AS app_count, (SELECT COUNT(*) FROM chat_messages cm JOIN chat_threads ct ON cm.thread_id = ct.id WHERE ct.user_id = u.id) AS message_count FROM users u ORDER BY u.created_at DESC LIMIT {$pagination['offset']}, {$pagination['per_page']}");
$statusLabels = ['active' => 'Faol', 'blocked' => 'Bloklangan', 'deleted' => 'O‘chirilgan'];
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Foydalanuvchilar — SOON Admin</title><link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:var(--bg-primary);border-right:1px solid var(--border-color);padding:24px;position:sticky;top:0;height:100vh;box-sizing:border-box;overflow:auto}.logo{display:block;color:var(--primary);font-size:1.5rem;font-weight:700;text-decoration:none;margin-bottom:32px}.nav-menu{display:flex;flex-direction:column;gap:8px}.nav-link{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;color:var(--text-secondary);text-decoration:none}.nav-link:hover,.nav-link.active{background:var(--bg-tertiary);color:var(--text-primary)}.nav-link.active{color:var(--primary);background:rgba(59,130,246,.1)}.main-content{padding:32px}.header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:32px}.card{background:var(--bg-primary);border-radius:16px;padding:24px}.table-responsive{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:12px 16px;text-align:left;border-bottom:1px solid var(--border-color);white-space:nowrap}th{font-size:.85rem;color:var(--text-muted)}tr:hover{background:var(--bg-secondary)}.user-cell{display:flex;align-items:center;gap:12px}.user-avatar{width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:none}.status-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.8rem}.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px}.alert-success{background:#D1FAE5;color:#047857}.alert-error{background:#FEE2E2;color:#B91C1C}.pagination{display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap}.page-btn{padding:8px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-secondary);color:var(--text-primary);text-decoration:none}.page-btn.active,.page-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:20px;z-index:1000}.modal{background:var(--bg-primary);border-radius:18px;padding:24px;max-width:520px;width:100%;box-sizing:border-box}.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.close-modal{border:0;background:transparent;color:var(--text-muted);font-size:28px;cursor:pointer}.detail-row{display:flex;gap:16px;padding:12px 0;border-bottom:1px solid var(--border-color)}.detail-label{width:120px;flex:none;color:var(--text-muted);font-weight:600}.detail-value{overflow-wrap:anywhere}@media(max-width:1024px){.admin-layout{grid-template-columns:1fr}.sidebar{position:relative;height:auto}.main-content{padding:20px}}@media(max-width:640px){.main-content{padding:14px}.card{padding:16px}.detail-row{display:block}.detail-label{width:auto;margin-bottom:4px}}
</style>
</head>
<body>
<div class="admin-layout"><aside class="sidebar"><a href="dashboard.php" class="logo">SOON Admin</a><nav class="nav-menu"><a href="dashboard.php" class="nav-link">📊 Boshqaruv</a><a href="services.php" class="nav-link">🛠 Xizmatlar</a><a href="portfolio.php" class="nav-link">📁 Portfolio</a><a href="blog.php" class="nav-link">📝 Blog</a><a href="applications.php" class="nav-link">📋 Arizalar</a><a href="users.php" class="nav-link active">👥 Foydalanuvchilar</a><a href="chat.php" class="nav-link">💬 Chat</a><a href="settings.php" class="nav-link">⚙ Sozlamalar</a><hr style="border:0;border-top:1px solid var(--border-color);margin:8px 0"><a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko‘rish</a><a href="logout.php" class="nav-link" style="color:var(--error)">🚪 Chiqish</a></nav></aside>
<main class="main-content"><div class="header"><div><h1 style="margin:0 0 4px">Foydalanuvchilar</h1><p style="margin:0;color:var(--text-muted)">Jami: <?php echo $totalUsers; ?> nafar</p></div><button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button></div>
<?php if ($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?>
<div class="card"><div class="table-responsive"><table><thead><tr><th>Foydalanuvchi</th><th>Email</th><th>Telefon</th><th>Arizalar</th><th>Xabarlar</th><th>Holat</th><th>Ro‘yxat</th><th></th></tr></thead><tbody>
<?php if (!$users): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">Foydalanuvchilar yo‘q</td></tr><?php else: foreach ($users as $user): ?><tr><td><div class="user-cell"><div class="user-avatar"><?php echo e(mb_strtoupper(mb_substr($user['full_name'] ?: 'A', 0, 1))); ?></div><span><?php echo e($user['full_name']); ?></span></div></td><td><?php echo e($user['email']); ?></td><td><?php echo e($user['phone'] ?: '—'); ?></td><td><?php echo (int)$user['app_count']; ?></td><td><?php echo (int)$user['message_count']; ?></td><td><span class="status-badge" style="background:<?php echo $user['status']==='active'?'#D1FAE5':($user['status']==='blocked'?'#FEF3C7':'#FEE2E2'); ?>;color:<?php echo $user['status']==='active'?'#047857':($user['status']==='blocked'?'#92400E':'#B91C1C'); ?>"><?php echo e($statusLabels[$user['status']] ?? $user['status']); ?></span></td><td><?php echo e(date('d.m.Y', strtotime($user['created_at']))); ?></td><td><button class="btn btn-sm btn-secondary" onclick='openModal(<?php echo e(json_encode($user, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)); ?>)'>Ko‘rish</button></td></tr><?php endforeach; endif; ?></tbody></table></div>
<?php if ($pagination['total_pages'] > 1): ?><div class="pagination"><?php if ($pagination['has_prev']): ?><a class="page-btn" href="?page=<?php echo $page-1; ?>">← Oldingi</a><?php endif; ?><?php for($i=1;$i<=$pagination['total_pages'];$i++): ?><a class="page-btn <?php echo $i===$page?'active':''; ?>" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endfor; ?><?php if ($pagination['has_next']): ?><a class="page-btn" href="?page=<?php echo $page+1; ?>">Keyingi →</a><?php endif; ?></div><?php endif; ?></div></main></div>
<div id="userModal" class="modal-overlay" role="dialog" aria-modal="true"><div class="modal"><div class="modal-header"><h3 style="margin:0">Foydalanuvchi ma’lumotlari</h3><button class="close-modal" type="button" onclick="closeModal()">×</button></div><div id="modalContent"></div><form method="POST" style="margin-top:20px"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" id="modalUserId"><label class="form-label">Holat</label><select name="status" id="modalUserStatus" class="form-select" onchange="this.form.submit()"><option value="active">Faol</option><option value="blocked">Bloklangan</option><option value="deleted">O‘chirilgan</option></select></form></div></div>
<script src="../assets/js/main.js"></script><script>
function openModal(user){document.getElementById('modalUserId').value=user.id;document.getElementById('modalUserStatus').value=user.status;document.getElementById('modalContent').innerHTML='<div class="detail-row"><div class="detail-label">Ism</div><div class="detail-value">'+escapeHtml(user.full_name||'—')+'</div></div><div class="detail-row"><div class="detail-label">Email</div><div class="detail-value">'+escapeHtml(user.email||'—')+'</div></div><div class="detail-row"><div class="detail-label">Telefon</div><div class="detail-value">'+escapeHtml(user.phone||'—')+'</div></div><div class="detail-row"><div class="detail-label">Ro‘yxat</div><div class="detail-value">'+escapeHtml(user.created_at||'—')+'</div></div><div class="detail-row"><div class="detail-label">Oxirgi kirish</div><div class="detail-value">'+escapeHtml(user.last_login_at||'—')+'</div></div>';document.getElementById('userModal').style.display='flex'}
function closeModal(){document.getElementById('userModal').style.display='none'}
function escapeHtml(value){return String(value).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]})}
document.getElementById('userModal').addEventListener('click',function(e){if(e.target===this)closeModal()});document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal()});
</script></body></html>