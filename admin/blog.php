<?php
/**
 * Admin - Blog Management
 * CRUD operations for blog posts
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
                $body = $_POST['body'] ?? ''; // Allow HTML in blog content
                $status = $_POST['status'] ?? 'draft';
                $id = (int) ($_POST['id'] ?? 0);
                
                if (empty($title)) {
                    $message = 'Nomi majburiy maydon';
                    $messageType = 'error';
                    break;
                }
                
                if (!in_array($status, ['draft', 'published'])) {
                    $status = 'draft';
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
                    $uploadPath = __DIR__ . '/../uploads/blog/' . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        $imagePath = 'uploads/blog/' . $filename;
                        
                        // Log media upload
                        dbInsert('media', [
                            'filename' => $_FILES['image']['name'],
                            'path' => $imagePath,
                            'mime_type' => $mimeType,
                            'size_bytes' => $_FILES['image']['size'],
                            'context' => 'blog',
                            'uploaded_by' => $admin['id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                if ($action === 'create') {
                    dbInsert('blog_posts', [
                        'title' => $title,
                        'body' => $body,
                        'image' => $imagePath,
                        'status' => $status,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    logAdminAction($admin['id'], 'blog_create', 'blog_post', null, ['title' => $title]);
                    $message = 'Blog qo\'shildi';
                    $messageType = 'success';
                } else {
                    $updateData = [
                        'title' => $title,
                        'body' => $body,
                        'status' => $status
                    ];
                    
                    if ($imagePath) {
                        $updateData['image'] = $imagePath;
                    }
                    
                    dbUpdate('blog_posts', $updateData, 'id = :id', ['id' => $id]);
                    
                    logAdminAction($admin['id'], 'blog_update', 'blog_post', $id, ['title' => $title]);
                    $message = 'Blog yangilandi';
                    $messageType = 'success';
                }
                break;
                
            case 'delete':
                $id = (int) ($_POST['id'] ?? 0);
                $item = dbFetchOne("SELECT * FROM blog_posts WHERE id = :id", ['id' => $id]);
                
                if ($item) {
                    dbDelete('blog_posts', 'id = :id', ['id' => $id]);
                    logAdminAction($admin['id'], 'blog_delete', 'blog_post', $id, ['title' => $item['title']]);
                    $message = 'Blog o\'chirildi';
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Get all blog posts
$blogPosts = dbFetchAll("SELECT * FROM blog_posts ORDER BY created_at DESC");

// Get item for editing
$editItem = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $editItem = dbFetchOne("SELECT * FROM blog_posts WHERE id = :id", ['id' => $editId]);
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - WebHub Admin</title>
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
        
        .blog-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .blog-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .blog-image {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            background: var(--bg-tertiary);
            flex-shrink: 0;
        }
        
        .blog-content {
            flex: 1;
        }
        
        .blog-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .blog-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-draft { background: #E5E7EB; color: #374151; }
        .status-published { background: #D1FAE5; color: #047857; }
        
        .blog-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
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
            min-height: 300px;
            resize: vertical;
            font-family: monospace;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        
        .hidden { display: none; }
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
                <a href="blog.php" class="nav-link active">📝 Blog</a>
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
                    <h1 style="margin-bottom: 4px;">Blog</h1>
                    <p style="color: var(--text-muted);">Blog postlarni boshqarish</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button>
                    <?php if (!$editItem): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('createForm').classList.toggle('hidden')">➕ Yangi blog</button>
                    <?php else: ?>
                    <a href="blog.php" class="btn btn-secondary">✕ Bekor qilish</a>
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
                    <h3>Yangi blog qo'shish</h3>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label">Nomi *</label>
                        <input type="text" name="title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Matn</label>
                        <textarea name="body" class="form-textarea" placeholder="HTML kodini yozishingiz mumkin"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Holat</label>
                        <select name="status" class="form-select">
                            <option value="draft">Qoralama</option>
                            <option value="published">Chop etilgan</option>
                        </select>
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
                    <h3>Blogni tahrirlash</h3>
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
                        <label class="form-label">Matn</label>
                        <textarea name="body" class="form-textarea"><?php echo e($editItem['body']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Holat</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?php echo $editItem['status'] === 'draft' ? 'selected' : ''; ?>>Qoralama</option>
                            <option value="published" <?php echo $editItem['status'] === 'published' ? 'selected' : ''; ?>>Chop etilgan</option>
                        </select>
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
                        <a href="blog.php" class="btn btn-secondary">Bekor qilish</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Blog List -->
            <div class="card">
                <div class="card-header">
                    <h3>Barcha blog postlar</h3>
                </div>
                
                <?php if (empty($blogPosts)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px 0;">
                    Hozircha blog postlar yo'q
                </p>
                <?php else: ?>
                <div class="blog-list">
                    <?php foreach ($blogPosts as $post): ?>
                    <div class="blog-item">
                        <?php if ($post['image']): ?>
                        <img src="../<?php echo e($post['image']); ?>" alt="" class="blog-image">
                        <?php else: ?>
                        <div class="blog-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 0.85rem;">
                            Rasm yo'q
                        </div>
                        <?php endif; ?>
                        
                        <div class="blog-content">
                            <div class="blog-title"><?php echo e($post['title']); ?></div>
                            <div class="blog-meta">
                                <span class="status-badge status-<?php echo $post['status']; ?>">
                                    <?php echo $post['status'] === 'published' ? 'Chop etilgan' : 'Qoralama'; ?>
                                </span>
                                • <?php echo date('d.m.Y', strtotime($post['created_at'])); ?>
                            </div>
                            <div class="blog-actions">
                                <a href="?edit=<?php echo $post['id']; ?>" class="btn btn-sm btn-secondary">Tahrirlash</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
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
