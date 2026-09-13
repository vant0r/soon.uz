<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';
$statuses = [
    'new' => 'Yangi',
    'in_review' => 'Ko‘rib chiqilmoqda',
    'contacted' => 'Bog‘lanildi',
    'approved' => 'Tasdiqlandi',
    'rejected' => 'Rad etildi',
    'completed' => 'Yakunlandi',
    'cancelled' => 'Bekor qilindi'
];
$priorities = ['low'=>'Past','medium'=>'O‘rta','high'=>'Yuqori','urgent'=>'Shoshilinch'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } else {
        try {
            $action = $_POST['action'];
            $id = (int) ($_POST['id'] ?? 0);
            $app = dbFetchOne('SELECT * FROM applications WHERE id = :id', ['id' => $id]);
            if (!$app) throw new RuntimeException('Ariza topilmadi');

            if ($action === 'update_status') {
                $status = $_POST['status'] ?? '';
                if (!isset($statuses[$status])) throw new RuntimeException('Ariza holati noto‘g‘ri');
                $oldStatus = $app['status'];
                $data = ['status' => $status, 'reviewed_at' => date('Y-m-d H:i:s')];
                if ($status === 'completed') $data['completed_at'] = date('Y-m-d H:i:s');
                dbUpdate('applications', $data, 'id = :id', ['id' => $id]);
                dbInsert('application_history', [
                    'application_id' => $id,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'changed_by' => $admin['id'],
                    'changed_by_type' => 'admin',
                    'comment' => null
                ]);
                if (!empty($app['user_id'])) {
                    dbInsert('notifications', [
                        'user_id' => $app['user_id'],
                        'title' => 'Ariza holati yangilandi',
                        'message' => 'Arizangiz holati «' . $statuses[$status] . '» ga o‘zgartirildi',
                        'type' => 'application_update',
                        'related_type' => 'application',
                        'related_id' => $id,
                        'is_read' => 0
                    ]);
                }
                logAdminAction($admin['id'], 'application_status_update', 'application', $id, ['old_status'=>$oldStatus,'new_status'=>$status]);
                $message = 'Ariza holati yangilandi';
                $messageType = 'success';
            } elseif ($action === 'update_priority') {
                $priority = $_POST['priority'] ?? '';
                if (!isset($priorities[$priority])) throw new RuntimeException('Muhimlik darajasi noto‘g‘ri');
                dbUpdate('applications', ['priority'=>$priority], 'id = :id', ['id'=>$id]);
                logAdminAction($admin['id'], 'application_priority_update', 'application', $id, ['priority'=>$priority]);
                $message = 'Muhimlik darajasi yangilandi';
                $messageType = 'success';
            } elseif ($action === 'delete') {
                dbDelete('applications', 'id = :id', ['id'=>$id]);
                logAdminAction($admin['id'], 'application_delete', 'application', $id, ['full_name'=>$app['full_name']]);
                $message = 'Ariza o‘chirildi';
                $messageType = 'success';
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$where = '1=1';
$params = [];
if (isset($statuses[$statusFilter])) {$where .= ' AND a.status = :status'; $params['status'] = $statusFilter;}
if (isset($priorities[$priorityFilter])) {$where .= ' AND a.priority = :priority'; $params['priority'] = $priorityFilter;}
$total = (int) (dbFetchOne("SELECT COUNT(*) AS count FROM applications a WHERE {$where}", $params)['count'] ?? 0);
$pagination = getPaginationData($total, $page, $perPage);
$applications = dbFetchAll("SELECT a.*, u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone, s.title_uz AS service_title FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN services s ON a.service_id = s.id WHERE {$where} ORDER BY a.created_at DESC LIMIT {$pagination['offset']}, {$pagination['per_page']}", $params);
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Arizalar — SOON Admin</title><link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}.admin-layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:var(--bg-primary);border-right:1px solid var(--border-color);padding:24px;position:sticky;top:0;height:100vh;overflow-y:auto}.logo{font-size:1.5rem;font-weight:700;color:var(--primary);margin-bottom:32px;display:block;text-decoration:none}.nav-menu{display:flex;flex-direction:column;gap:8px}.nav-link{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;color:var(--text-secondary);text-decoration:none}.nav-link:hover,.nav-link.active{background:var(--bg-tertiary);color:var(--text-primary)}.nav-link.active{background:rgba(59,130,246,.1);color:var(--primary)}.main-content{padding:32px}.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;gap:16px;flex-wrap:wrap}.card{background:var(--bg-primary);border-radius:16px;padding:24px;margin-bottom:24px}.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}.filter-btn{padding:8px 14px;border-radius:20px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-secondary);text-decoration:none;font-size:.85rem}.filter-btn.active,.filter-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}.table-responsive{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:12px 14px;text-align:left;border-bottom:1px solid var(--border-color);white-space:nowrap}th{color:var(--text-muted);font-size:.85rem;font-weight:500}.status-badge,.priority-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.78rem}.status-new{background:#DBEAFE}.status-in_review{background:#FEF3C7}.status-contacted{background:#E0E7FF}.status-approved{background:#D1FAE5}.status-rejected,.status-cancelled{background:#FEE2E2}.status-completed{background:#EDE9FE}.priority-low{background:#E5E7EB}.priority-medium{background:#FEF3C7}.priority-high{background:#FED7AA}.priority-urgent{background:#FECACA}.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px}.alert-success{background:#D1FAE5;color:#047857}.alert-error{background:#FEE2E2;color:#B91C1C}.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px}.modal{background:var(--bg-primary);border-radius:18px;padding:24px;max-width:680px;width:100%;max-height:90vh;overflow:auto}.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.close-modal{border:0;background:transparent;font-size:28px;color:var(--text-muted);cursor:pointer}.detail-row{display:grid;grid-template-columns:150px 1fr;gap:12px;padding:11px 0;border-bottom:1px solid var(--border-color)}.detail-label{color:var(--text-muted);font-weight:500}.form-group{margin:14px 0}.form-select{width:100%;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-secondary);color:var(--text-primary)}.actions{display:flex;gap:8px;align-items:center}.pagination{display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap}.page-btn{padding:8px 14px;border:1px solid var(--border-color);border-radius:8px;text-decoration:none;color:var(--text-primary)}.page-btn.active{background:var(--primary);color:#fff}@media(max-width:1024px){.admin-layout{grid-template-columns:1fr}.sidebar{position:relative;height:auto}.main-content{padding:20px}}@media(max-width:600px){.detail-row{grid-template-columns:1fr}}
</style>
</head>
<body><div class="admin-layout"><aside class="sidebar"><a href="dashboard.php" class="logo">SOON Admin</a><nav class="nav-menu"><a href="dashboard.php" class="nav-link">📊 Boshqaruv</a><a href="services.php" class="nav-link">🛠 Xizmatlar</a><a href="portfolio.php" class="nav-link">📁 Portfolio</a><a href="blog.php" class="nav-link">📝 Blog</a><a href="applications.php" class="nav-link active">📋 Arizalar</a><a href="users.php" class="nav-link">👥 Foydalanuvchilar</a><a href="chat.php" class="nav-link">💬 Chat</a><a href="settings.php" class="nav-link">⚙ Sozlamalar</a><hr style="border:0;border-top:1px solid var(--border-color);margin:8px 0"><a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko‘rish</a><a href="logout.php" class="nav-link" style="color:var(--error)">🚪 Chiqish</a></nav></aside><main class="main-content">
<div class="header"><div><h1 style="margin-bottom:4px">Arizalar</h1><p style="color:var(--text-muted)">Kelgan arizalarni boshqarish</p></div><button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button></div>
<?php if($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?><div class="card"><div class="filters"><a class="filter-btn <?php echo $statusFilter===''?'active':''; ?>" href="?">Barchasi</a><?php foreach($statuses as $key=>$label): ?><a class="filter-btn <?php echo $statusFilter===$key?'active':''; ?>" href="?status=<?php echo e($key); ?>"><?php echo e($label); ?></a><?php endforeach; ?></div><div class="filters"><a class="filter-btn <?php echo $priorityFilter===''?'active':''; ?>" href="?status=<?php echo e($statusFilter); ?>">Barcha muhimlik</a><?php foreach($priorities as $key=>$label): ?><a class="filter-btn <?php echo $priorityFilter===$key?'active':''; ?>" href="?status=<?php echo e($statusFilter); ?>&priority=<?php echo e($key); ?>"><?php echo e($label); ?></a><?php endforeach; ?></div>
<?php if(!$applications): ?><p style="text-align:center;color:var(--text-muted);padding:40px">Arizalar topilmadi</p><?php else: ?><div class="table-responsive"><table><thead><tr><th>ID</th><th>Mijoz</th><th>Xizmat</th><th>Muhimlik</th><th>Holat</th><th>Sana</th><th></th></tr></thead><tbody><?php foreach($applications as $app): ?><tr><td>#<?php echo (int)$app['id']; ?></td><td><strong><?php echo e($app['full_name']); ?></strong><br><small><?php echo e($app['email']); ?></small></td><td><?php echo e($app['service_title'] ?? '—'); ?></td><td><span class="priority-badge priority-<?php echo e($app['priority']); ?>"><?php echo e($priorities[$app['priority']] ?? $app['priority']); ?></span></td><td><span class="status-badge status-<?php echo e($app['status']); ?>"><?php echo e($statuses[$app['status']] ?? $app['status']); ?></span></td><td><?php echo date('d.m.Y H:i',strtotime($app['created_at'])); ?></td><td><button class="btn btn-sm btn-secondary" onclick='openApplication(<?php echo json_encode($app, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>)'>Ko‘rish</button></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if($pagination['total_pages']>1): ?><div class="pagination"><?php for($i=1;$i<=$pagination['total_pages'];$i++): ?><a class="page-btn <?php echo $i===$page?'active':''; ?>" href="?page=<?php echo $i; ?>&status=<?php echo e($statusFilter); ?>&priority=<?php echo e($priorityFilter); ?>"><?php echo $i; ?></a><?php endfor; ?></div><?php endif; ?></div></main></div>
<div id="appModal" class="modal-overlay"><div class="modal"><div class="modal-header"><h3>Ariza tafsilotlari</h3><button class="close-modal" onclick="closeApplication()">×</button></div><div id="applicationDetails"></div><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" id="applicationId"><div class="form-group"><label>Holat</label><select name="status" id="applicationStatus" class="form-select"><?php foreach($statuses as $key=>$label): ?><option value="<?php echo e($key); ?>"><?php echo e($label); ?></option><?php endforeach; ?></select></div><button class="btn btn-primary" type="submit">Holatni saqlash</button></form><form method="POST" style="margin-top:10px"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="update_priority"><input type="hidden" name="id" id="priorityApplicationId"><div class="form-group"><label>Muhimlik</label><select name="priority" id="applicationPriority" class="form-select"><?php foreach($priorities as $key=>$label): ?><option value="<?php echo e($key); ?>"><?php echo e($label); ?></option><?php endforeach; ?></select></div><button class="btn btn-secondary" type="submit">Muhimlikni saqlash</button></form><form method="POST" style="margin-top:12px" onsubmit="return confirm('Arizani o‘chirishni tasdiqlaysizmi?')"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteApplicationId"><button class="btn btn-danger" type="submit" style="width:100%">Arizani o‘chirish</button></form></div></div>
<script src="../assets/js/main.js"></script><script>function esc(v){return String(v??'').replace(/[&<>'"]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'}[s]))}function openApplication(a){document.getElementById('appModal').style.display='flex';document.getElementById('applicationId').value=a.id;document.getElementById('priorityApplicationId').value=a.id;document.getElementById('deleteApplicationId').value=a.id;document.getElementById('applicationStatus').value=a.status;document.getElementById('applicationPriority').value=a.priority;document.getElementById('applicationDetails').innerHTML='<div class="detail-row"><span class="detail-label">Mijoz</span><span>'+esc(a.full_name)+'</span></div><div class="detail-row"><span class="detail-label">Email</span><span>'+esc(a.email)+'</span></div><div class="detail-row"><span class="detail-label">Telefon</span><span>'+esc(a.phone)+'</span></div><div class="detail-row"><span class="detail-label">Kompaniya</span><span>'+esc(a.company_name||'—')+'</span></div><div class="detail-row"><span class="detail-label">Xizmat</span><span>'+esc(a.service_id||'—')+'</span></div><div class="detail-row"><span class="detail-label">Byudjet</span><span>'+esc(a.budget_min||'—')+' — '+esc(a.budget_max||'—')+'</span></div><div class="detail-row"><span class="detail-label">Muddat</span><span>'+esc(a.deadline_date||'—')+'</span></div><div class="detail-row"><span class="detail-label">Xabar</span><span>'+esc(a.message||'—')+'</span></div><div class="detail-row"><span class="detail-label">IP</span><span>'+esc(a.ip_address||'—')+'</span></div><div class="detail-row"><span class="detail-label">Sana</span><span>'+esc(a.created_at)+'</span></div>'}function closeApplication(){document.getElementById('appModal').style.display='none'}document.getElementById('appModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeApplication()})</script></body></html>
