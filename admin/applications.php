<?php
/**
 * Admin - Applications Management
 * View and manage user applications
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto\'g\'ri';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'update_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            $validStatuses = ['new', 'in_review', 'approved', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                $message = 'Noto\'g\'ri holat';
                $messageType = 'error';
            } else {
                $app = dbFetchOne("SELECT * FROM applications WHERE id = :id", ['id' => $id]);
                if ($app) {
                    dbUpdate('applications', ['status' => $status], 'id = :id', ['id' => $id]);
                    logAdminAction($admin['id'], 'application_status_update', 'application', $id, [
                        'old_status' => $app['status'],
                        'new_status' => $status
                    ]);
                    
                    // Create notification for user
                    dbInsert('notifications', [
                        'user_id' => $app['user_id'],
                        'title' => 'Ariza holati o\'zgardi',
                        'message' => 'Sizning arizangiz holati "' . ($status === 'approved' ? 'Tasdiqlandi' : ($status === 'completed' ? 'Yakunlandi' : ($status === 'cancelled' ? 'Bekor qilindi' : 'Yangilandi'))) . '" ga o\'zgartirildi',
                        'is_read' => false,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $message = 'Holat yangilandi';
                    $messageType = 'success';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $app = dbFetchOne("SELECT * FROM applications WHERE id = :id", ['id' => $id]);
            if ($app) {
                dbDelete('applications', 'id = :id', ['id' => $id]);
                logAdminAction($admin['id'], 'application_delete', 'application', $id, ['service' => $app['service_name_snapshot']]);
                $message = 'Ariza o\'chirildi';
                $messageType = 'success';
            }
        }
    }
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$totalApps = dbFetchOne("SELECT COUNT(*) as count FROM applications")['count'];
$pagination = getPaginationData($totalApps, $page, $perPage);

// Get applications with filters
$statusFilter = $_GET['status'] ?? '';
$whereClause = '1=1';
$params = [];

if ($statusFilter && in_array($statusFilter, ['new', 'in_review', 'approved', 'completed', 'cancelled'])) {
    $whereClause .= " AND a.status = :status";
    $params['status'] = $statusFilter;
}

$applications = dbFetchAll("
    SELECT a.*, u.name as user_name, u.email as user_email, u.phone as user_phone, 
           s.title as service_title, s.price as service_price
    FROM applications a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN services s ON a.service_id = s.id
    WHERE {$whereClause}
    ORDER BY a.created_at DESC
    LIMIT {$pagination['offset']}, {$pagination['per_page']}
", $params);

$statusLabels = [
    'new' => 'Yangi',
    'in_review' => 'Ko\'rib chiqilmoqda',
    'approved' => 'Tasdiqlandi',
    'completed' => 'Yakunlandi',
    'cancelled' => 'Bekor qilindi'
];

$statusColors = [
    'new' => '#DBEAFE',
    'in_review' => '#FEF3C7',
    'approved' => '#D1FAE5',
    'completed' => '#E0E7FF',
    'cancelled' => '#FEE2E2'
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arizalar - WebHub Admin</title>
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
        
        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
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
            width: 150px;
            flex-shrink: 0;
        }
        
        .detail-value {
            color: var(--text-primary);
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
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
                <a href="applications.php" class="nav-link active">📋 Arizalar</a>
                <a href="users.php" class="nav-link">👥 Foydalanuvchilar</a>
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
                    <h1 style="margin-bottom: 4px;">Arizalar</h1>
                    <p style="color: var(--text-muted);">Foydalanuvchi arizalarini boshqarish</p>
                </div>
                <button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>Barcha arizalar</h3>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <a href="?status=" class="filter-btn <?php echo !$statusFilter ? 'active' : ''; ?>">Barchasi</a>
                    <?php foreach ($statusLabels as $key => $label): ?>
                    <a href="?status=<?php echo $key; ?>" class="filter-btn <?php echo $statusFilter === $key ? 'active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($applications)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px 0;">
                    Arizalar topilmadi
                </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Foydalanuvchi</th>
                                <th>Xizmat</th>
                                <th>Holat</th>
                                <th>Sana</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>#<?php echo $app['id']; ?></td>
                                <td>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo e($app['user_name'] ?? 'Noma\'lum'); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($app['user_email'] ?? ''); ?></div>
                                    </div>
                                </td>
                                <td><?php echo e($app['service_title'] ?? $app['service_name_snapshot']); ?></td>
                                <td>
                                    <span class="status-badge" style="background: <?php echo $statusColors[$app['status']] ?? '#E5E7EB'; ?>">
                                        <?php echo $statusLabels[$app['status']] ?? $app['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="openModal(<?php echo htmlspecialchars(json_encode($app)); ?>)">
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
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>" class="page-btn">← Oldingi</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>" 
                       class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['has_next']): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>" class="page-btn">Keyingi →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal for viewing application details -->
    <div id="appModal" class="modal-overlay hidden" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>Ariza ma'lumotlari</h3>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            <div id="modalContent"></div>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="modalAppId">
                
                <div class="form-group">
                    <label class="form-label">Holatni o'zgartirish</label>
                    <select name="status" id="modalStatus" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <form method="POST" style="margin-top: 12px;" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?');">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="modalAppIdDelete">
                <button type="submit" class="btn btn-danger" style="width: 100%;">Arizani o'chirish</button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function openModal(app) {
            document.getElementById('appModal').style.display = 'flex';
            document.getElementById('modalAppId').value = app.id;
            document.getElementById('modalAppIdDelete').value = app.id;
            document.getElementById('modalStatus').value = app.status;
            
            const customization = app.customization_json ? JSON.parse(app.customization_json) : null;
            
            document.getElementById('modalContent').innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">ID:</span>
                    <span class="detail-value">#${app.id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Foydalanuvchi:</span>
                    <span class="detail-value">${app.user_name || 'Noma\\'lum'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${app.user_email || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Telefon:</span>
                    <span class="detail-value">${app.user_phone || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Xizmat:</span>
                    <span class="detail-value">${app.service_title || app.service_name_snapshot || 'Noma\\'lum'}</span>
                </div>
                ${customization ? `
                <div class="detail-row">
                    <span class="detail-label">Tanlangan qo'shimchalar:</span>
                    <span class="detail-value">${JSON.stringify(customization)}</span>
                </div>
                ` : ''}
                <div class="detail-row">
                    <span class="detail-label">Tavsif:</span>
                    <span class="detail-value">${app.description || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Holat:</span>
                    <span class="detail-value">${app.status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Yuborilgan:</span>
                    <span class="detail-value">${app.created_at}</span>
                </div>
            `;
        }
        
        function closeModal() {
            document.getElementById('appModal').style.display = 'none';
        }
        
        // Close modal on outside click
        document.getElementById('appModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
