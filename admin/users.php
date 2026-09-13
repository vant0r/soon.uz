<?php
/**
 * Admin - User Management
 * View and manage users
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto\'g\'ri';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'update_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            $validStatuses = ['active', 'blocked', 'deleted'];
            if (!in_array($status, $validStatuses)) {
                $message = 'Noto\'g\'ri holat';
                $messageType = 'error';
            } else {
                $user = dbFetchOne("SELECT * FROM users WHERE id = :id", ['id' => $id]);
                if ($user) {
                    dbUpdate('users', ['status' => $status], 'id = :id', ['id' => $id]);
                    logAdminAction($admin['id'], 'user_status_update', 'user', $id, [
                        'old_status' => $user['status'],
                        'new_status' => $status
                    ]);
                    $message = 'Foydalanuvchi holati yangilandi';
                    $messageType = 'success';
                }
            }
        }
    }
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$totalUsers = dbFetchOne("SELECT COUNT(*) as count FROM users")['count'];
$pagination = getPaginationData($totalUsers, $page, $perPage);

// Get users
$users = dbFetchAll("
    SELECT u.*, 
           (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count,
           (SELECT COUNT(*) FROM chat_messages cm 
            JOIN chat_threads ct ON cm.thread_id = ct.id 
            WHERE ct.user_id = u.id) as message_count
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT {$pagination['offset']}, {$pagination['per_page']}
");

$statusLabels = [
    'active' => 'Faol',
    'blocked' => 'Bloklangan',
    'deleted' => 'O\'chirilgan'
];

$statusColors = [
    'active' => '#D1FAE5',
    'blocked' => '#FEF3C7',
    'deleted' => '#FEE2E2'
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foydalanuvchilar - WebHub Admin</title>
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
            padding: 32px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        tr:hover {
            background: var(--bg-secondary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: #D1FAE5; color: #047857; }
        .alert-error { background: #FEE2E2; color: #B91C1C; }
        
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
            font-size: 1rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        
        .page-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .page-btn:hover, .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--text-muted);
            width: 120px;
            flex-shrink: 0;
        }
        
        .detail-value {
            color: var(--text-primary);
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
                <a href="users.php" class="nav-link active">👥 Foydalanuvchilar</a>
                <a href="chat.php" class="nav-link">💬 Chat</a>
                <a href="settings.php" class="nav-link">⚙ Sozlamalar</a>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
                <a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko'rish</a>
                <a href="logout.php" class="nav-link" style="color: var(--error);">🚪 Chiqish</a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1 style="margin-bottom: 4px;">Foydalanuvchilar</h1>
                    <p style="color: var(--text-muted);">Jami: <?php echo $totalUsers; ?> nafar</p>
                </div>
                <button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>Barcha foydalanuvchilar</h3>
                </div>
                
                <?php if (empty($users)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px 0;">
                    Foydalanuvchilar yo'q
                </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Foydalanuvchi</th>
                                <th>Email</th>
                                <th>Telefon</th>
                                <th>Arizalar</th>
                                <th>Xabarlar</th>
                                <th>Holat</th>
                                <th>Ro'yxatdan o'tgan</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(mb_substr($user['name'] ?? 'A', 0, 1)); ?>
                                        </div>
                                        <span style="font-weight: 500;"><?php echo e($user['name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo e($user['email']); ?></td>
                                <td><?php echo e($user['phone'] ?? '-'); ?></td>
                                <td><?php echo $user['app_count']; ?></td>
                                <td><?php echo $user['message_count']; ?></td>
                                <td>
                                    <span class="status-badge" style="background: <?php echo $statusColors[$user['status']] ?? '#E5E7EB'; ?>">
                                        <?php echo $statusLabels[$user['status']] ?? $user['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="openModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        Ko'rish
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($pagination['has_prev']): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="page-btn">← Oldingi</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['has_next']): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="page-btn">Keyingi →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal for viewing user details -->
    <div id="userModal" class="modal-overlay hidden" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>Foydalanuvchi ma'lumotlari</h3>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            <div id="modalContent"></div>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="modalUserId">
                
                <div class="form-group">
                    <label class="form-label">Holatni o'zgartirish</label>
                    <select name="status" id="modalUserStatus" class="form-select" onchange="this.form.submit()">
                        <option value="active">Faol</option>
                        <option value="blocked">Bloklangan</option>
                        <option value="deleted">O'chirilgan</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function openModal(user) {
            document.getElementById('userModal').style.display = 'flex';
            document.getElementById('modalUserId').value = user.id;
            document.getElementById('modalUserStatus').value = user.status;
            
            document.getElementById('modalContent').innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">ID:</span>
                    <span class="detail-value">#${user.id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ism:</span>
                    <span class="detail-value">${user.name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${user.email}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Telefon:</span>
                    <span class="detail-value">${user.phone || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Google ID:</span>
                    <span class="detail-value">${user.google_id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Arizalar soni:</span>
                    <span class="detail-value">${user.app_count}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Xabarlar soni:</span>
                    <span class="detail-value">${user.message_count}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Holat:</span>
                    <span class="detail-value">${user.status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ro'yxatdan o'tgan:</span>
                    <span class="detail-value">${user.created_at}</span>
                </div>
            `;
        }
        
        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }
        
        // Close modal on outside click
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
