<?php
/**
 * Admin - Portfolio Management
 * CRUD operations for portfolio items
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto\'g\'ri';
        $messageType = 'error';
    } else {
        switch ($action) {
            case 'create':
            case 'update':
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $clientName = sanitizeInput($_POST['client_name'] ?? '');
                $link = sanitizeInput($_POST['link'] ?? '');
                $category = sanitizeInput($_POST['category'] ?? '');
                $sortOrder = (int) ($_POST['sort_order'] ?? 0);
                $id = (int) ($_POST['id'] ?? 0);
                
                if (empty($title)) {
                    $message = 'Nomi majburiy maydon';
                    $messageType = 'error';
                    break;
                }
                
                // Handle image upload
                $imagePath = null;
                if (!empty($_FILES['image']['name'])) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $maxSize = (int) getSiteSetting('max_upload_image_mb', 10) * 1024 * 1024;
                    
                    if ($_FILES['image']['size'] > $maxSize) {
                        $message = 'Rasm hajmi juda katta (maksimum ' . getSiteSetting('max_upload_image_mb', 10) . ' MB)';
                        $messageType = 'error';
                        break;
                    }
                    
                    $mimeType = validateMimeType($_FILES['image']['tmp_name'], $allowedTypes);
                    if (!$mimeType) {
                        $message = 'Noto\'g\'ri rasm formati';
                        $messageType = 'error';
                        break;
                    }
                    
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $ext;
                    $uploadPath = __DIR__ . '/../uploads/portfolio/' . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        $imagePath = 'uploads/portfolio/' . $filename;
                        
                        // Log media upload
                        dbInsert('media', [
                            'filename' => $_FILES['image']['name'],
                            'path' => $imagePath,
                            'mime_type' => $mimeType,
                            'size_bytes' => $_FILES['image']['size'],
                            'context' => 'portfolio',
                            'uploaded_by' => $admin['id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                if ($action === 'create') {
                    dbInsert('portfolio', [
                        'title' => $title,
                        'description' => $description,
                        'image' => $imagePath,
                        'client_name' => $clientName,
                        'link' => $link,
                        'category' => $category,
                        'sort_order' => $sortOrder,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    logAdminAction($admin['id'], 'portfolio_create', 'portfolio', null, ['title' => $title]);
                    $message = 'Portfolio qo\'shildi';
                    $messageType = 'success';
                } else {
                    $updateData = [
                        'title' => $title,
                        'description' => $description,
                        'client_name' => $clientName,
                        'link' => $link,
                        'category' => $category,
                        'sort_order' => $sortOrder
                    ];
                    
                    if ($imagePath) {
                        $updateData['image'] = $imagePath;
                    }
                    
                    dbUpdate('portfolio', $updateData, 'id = :id', ['id' => $id]);
                    
                    logAdminAction($admin['id'], 'portfolio_update', 'portfolio', $id, ['title' => $title]);
                    $message = 'Portfolio yangilandi';
                    $messageType = 'success';
                }
                break;
                
            case 'delete':
                $id = (int) ($_POST['id'] ?? 0);
                $item = dbFetchOne("SELECT * FROM portfolio WHERE id = :id", ['id' => $id]);
                
                if ($item) {
                    dbDelete('portfolio', 'id = :id', ['id' => $id]);
                    logAdminAction($admin['id'], 'portfolio_delete', 'portfolio', $id, ['title' => $item['title']]);
                    $message = 'Portfolio o\'chirildi';
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Get all portfolio items
$portfolioItems = dbFetchAll("SELECT * FROM portfolio ORDER BY sort_order ASC, created_at DESC");

// Get item for editing
$editItem = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $editItem = dbFetchOne("SELECT * FROM portfolio WHERE id = :id", ['id' => $editId]);
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - WebHub Admin</title>
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
        
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .portfolio-item {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .portfolio-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--bg-tertiary);
        }
        
        .portfolio-body {
            padding: 16px;
        }
        
        .portfolio-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .portfolio-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        
        .portfolio-actions {
            display: flex;
            gap: 8px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #047857;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #B91C1C;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 16px;
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
                <a href="portfolio.php" class="nav-link active">📁 Portfolio</a>
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
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1 style="margin-bottom: 4px;">Portfolio</h1>
                    <p style="color: var(--text-muted);">Bajarilgan loyihalarni boshqarish</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button>
                    <?php if (!$editItem): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('createForm').classList.toggle('hidden')">➕ Yangi portfolio</button>
                    <?php else: ?>
                    <a href="portfolio.php" class="btn btn-secondary">✕ Bekor qilish</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <!-- Create/Edit Form -->
            <?php if (!$editItem): ?>
            <div id="createForm" class="card hidden">
                <div class="card-header">
                    <h3>Yangi portfolio qo'shish</h3>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label">Nomi *</label>
                        <input type="text" name="title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tavsif</label>
                        <textarea name="description" class="form-textarea"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mijoz nomi</label>
                        <input type="text" name="client_name" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategoriya</label>
                        <input type="text" name="category" class="form-input" placeholder="Masalan: Veb-sayt, Telegram bot">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Veb-sayt havolasi</label>
                        <input type="url" name="link" class="form-input" placeholder="https://...">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tartib raqami</label>
                        <input type="number" name="sort_order" class="form-input" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rasm</label>
                        <input type="file" name="image" class="form-input" accept="image/*">
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Saqlash</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3>Portfolioni tahrirlash</h3>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Nomi *</label>
                        <input type="text" name="title" class="form-input" value="<?php echo e($editItem['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tavsif</label>
                        <textarea name="description" class="form-textarea"><?php echo e($editItem['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mijoz nomi</label>
                        <input type="text" name="client_name" class="form-input" value="<?php echo e($editItem['client_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategoriya</label>
                        <input type="text" name="category" class="form-input" value="<?php echo e($editItem['category']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Veb-sayt havolasi</label>
                        <input type="url" name="link" class="form-input" value="<?php echo e($editItem['link']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tartib raqami</label>
                        <input type="number" name="sort_order" class="form-input" value="<?php echo (int) $editItem['sort_order']; ?>">
                    </div>
                    
                    <?php if ($editItem['image']): ?>
                    <div class="form-group">
                        <label class="form-label">Joriy rasm</label>
                        <img src="../<?php echo e($editItem['image']); ?>" alt="" style="max-width: 300px; border-radius: 8px;">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Yangi rasm yuklash</label>
                        <input type="file" name="image" class="form-input" accept="image/*">
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Saqlash</button>
                        <a href="portfolio.php" class="btn btn-secondary">Bekor qilish</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Portfolio List -->
            <div class="card">
                <div class="card-header">
                    <h3>Barcha portfoliolar</h3>
                </div>
                
                <?php if (empty($portfolioItems)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px 0;">
                    Hozircha portfolio loyihalari yo'q
                </p>
                <?php else: ?>
                <div class="portfolio-grid">
                    <?php foreach ($portfolioItems as $item): ?>
                    <div class="portfolio-item">
                        <?php if ($item['image']): ?>
                        <img src="../<?php echo e($item['image']); ?>" alt="<?php echo e($item['title']); ?>" class="portfolio-image">
                        <?php else: ?>
                        <div class="portfolio-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                            Rasm yo'q
                        </div>
                        <?php endif; ?>
                        
                        <div class="portfolio-body">
                            <div class="portfolio-title"><?php echo e($item['title']); ?></div>
                            <?php if ($item['client_name']): ?>
                            <div class="portfolio-meta">Mijoz: <?php echo e($item['client_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($item['category']): ?>
                            <div class="portfolio-meta">Kategoriya: <?php echo e($item['category']); ?></div>
                            <?php endif; ?>
                            
                            <div class="portfolio-actions">
                                <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary">Tahrirlash</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">O'chirish</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
