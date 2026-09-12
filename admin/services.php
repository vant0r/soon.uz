<?php
/**
 * Admin - Services Management
 * Phase 4: Content Management
 */

require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik xatosi';
    } else {
        switch ($action) {
            case 'create':
            case 'update':
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $price = (int) ($_POST['price'] ?? 0);
                $sortOrder = (int) ($_POST['sort_order'] ?? 0);
                $features = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
                $addonsRaw = array_filter(array_map('trim', explode("\n", $_POST['addons'] ?? '')));
                
                // Parse addons JSON format
                $addons = [];
                foreach ($addonsRaw as $addonLine) {
                    $parts = explode(':', $addonLine, 2);
                    if (count($parts) === 2) {
                        $addons[] = [
                            'name' => trim($parts[0]),
                            'price' => (int) trim($parts[1])
                        ];
                    }
                }
                
                $data = [
                    'title' => $title,
                    'description' => $description,
                    'price' => $price,
                    'features_json' => json_encode($features, JSON_UNESCAPED_UNICODE),
                    'addons_json' => json_encode($addons, JSON_UNESCAPED_UNICODE),
                    'sort_order' => $sortOrder
                ];
                
                if ($action === 'create') {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $id = dbInsert('services', $data);
                    logAdminAction($admin['id'], 'Create service', 'service', $id);
                    $message = 'Xizmat muvaffaqiyatli qo\'shildi';
                } else {
                    $id = (int) $_POST['id'];
                    dbUpdate('services', $data, 'id = :id', ['id' => $id]);
                    logAdminAction($admin['id'], 'Update service', 'service', $id);
                    $message = 'Xizmat muvaffaqiyatli yangilandi';
                }
                break;
                
            case 'delete':
                $id = (int) $_POST['id'];
                dbDelete('services', 'id = :id', ['id' => $id]);
                logAdminAction($admin['id'], 'Delete service', 'service', $id);
                $message = 'Xizmat o\'chirildi';
                break;
        }
    }
}

// Fetch services
$services = dbFetchAll("SELECT * FROM services ORDER BY sort_order, id");

// Get service for editing
$editingService = null;
if (isset($_GET['edit'])) {
    $editingService = dbFetchOne("SELECT * FROM services WHERE id = :id", ['id' => (int) $_GET['edit']]);
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xizmatlar - WebHub Admin</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { background: var(--bg-secondary); }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        @media (max-width: 1024px) { .admin-layout { grid-template-columns: 1fr; } }
        .sidebar { background: var(--bg-primary); border-right: 1px solid var(--border-color); padding: 24px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: 32px; display: block; text-decoration: none; }
        .nav-menu { display: flex; flex-direction: column; gap: 8px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: var(--text-secondary); text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: var(--bg-tertiary); color: var(--text-primary); }
        .nav-link.active { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        .main-content { padding: 32px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .card { background: var(--bg-primary); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-size: 16px; border: none; border-radius: 12px; cursor: pointer; text-decoration: none; min-height: 44px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: transparent; color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-sm { padding: 8px 16px; font-size: 14px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.85rem; }
        tr:hover { background: var(--bg-secondary); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-secondary); }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 12px 16px; font-size: 16px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 12px; }
        textarea { resize: vertical; min-height: 100px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #D1FAE5; border: 1px solid #6EE7B7; color: #059669; }
        .alert-error { background: #FEE2E2; border: 1px solid #FCA5A5; color: #DC2626; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--bg-primary); border-radius: 16px; padding: 32px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <a href="dashboard.php" class="logo">WebHub Admin</a>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                <a href="services.php" class="nav-link active">🛠 Xizmatlar</a>
                <a href="portfolio.php" class="nav-link">📁 Portfolio</a>
                <a href="blog.php" class="nav-link">📝 Blog</a>
                <a href="applications.php" class="nav-link">📋 Arizalar</a>
                <a href="users.php" class="nav-link">👥 Foydalanuvchilar</a>
                <a href="chat.php" class="nav-link">💬 Chat</a>
                <a href="settings.php" class="nav-link">⚙ Sozlamalar</a>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
                <a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko'rish</a>
                <a href="logout.php" class="nav-link" style="color: var(--error);">🚪 Chiqish</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="header">
                <h1>Xizmatlar</h1>
                <button class="btn btn-primary" onclick="openModal()">+ Yangi xizmat</button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nomi</th>
                                <th>Narx</th>
                                <th>Tartib</th>
                                <th>Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($service['title']); ?></strong><br>
                                    <small style="color: var(--text-muted);"><?php echo e(mb_substr($service['description'] ?? '', 0, 50)); ?>...</small>
                                </td>
                                <td><?php echo number_format($service['price'], 0, ',', ' '); ?> so'm</td>
                                <td><?php echo $service['sort_order']; ?></td>
                                <td>
                                    <a href="?edit=<?php echo $service['id']; ?>" class="btn btn-secondary btn-sm">Tahrirlash</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('O\'chirishni tasdiqlaysizmi?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--error);">O'chirish</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal -->
    <div class="modal-overlay" id="serviceModal">
        <div class="modal">
            <h2 style="margin-bottom: 24px;"><?php echo $editingService ? 'Xizmatni tahrirlash' : 'Yangi xizmat'; ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editingService ? 'update' : 'create'; ?>">
                <?php if ($editingService): ?>
                <input type="hidden" name="id" value="<?php echo $editingService['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Nomi</label>
                    <input type="text" name="title" required value="<?php echo e($editingService['title'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Tavsif</label>
                    <textarea name="description" rows="3"><?php echo e($editingService['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Narx (so'm)</label>
                    <input type="number" name="price" value="<?php echo $editingService['price'] ?? 0; ?>">
                </div>
                
                <div class="form-group">
                    <label>Tartib raqami</label>
                    <input type="number" name="sort_order" value="<?php echo $editingService['sort_order'] ?? 0; ?>">
                </div>
                
                <div class="form-group">
                    <label>Nimalar kiradi (har bir qator alohida)</label>
                    <textarea name="features" rows="4" placeholder="Veb-sayt yaratish&#10;Dizayn ishlash&#10;SEO optimizatsiya"><?php echo $editingService && $editingService['features_json'] ? implode("\n", json_decode($editingService['features_json'], true)) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Qo'shimcha xizmatlar (format: Nomi: Narx)</label>
                    <textarea name="addons" rows="4" placeholder="Logo dizayn: 500000&#10;SEO paket: 1000000"><?php 
                        if ($editingService && $editingService['addons_json']) {
                            $addons = json_decode($editingService['addons_json'], true);
                            foreach ($addons as $addon) {
                                echo $addon['name'] . ': ' . $addon['price'] . "\n";
                            }
                        }
                    ?></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                    <a href="services.php" class="btn btn-secondary">Bekor qilish</a>
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() { document.getElementById('serviceModal').classList.add('active'); }
        <?php if ($editingService): ?>openModal();<?php endif; ?>
    </script>
</body>
</html>
