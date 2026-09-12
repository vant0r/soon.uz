<?php
/**
 * Admin - Site Settings Management
 * Manage site-wide settings, SEO, contact info
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto\'g\'ri';
        $messageType = 'error';
    } else {
        // Contact settings
        updateSiteSetting('contact_phone', sanitizeInput($_POST['contact_phone'] ?? ''));
        updateSiteSetting('contact_telegram', sanitizeInput($_POST['contact_telegram'] ?? ''));
        updateSiteSetting('contact_instagram', sanitizeInput($_POST['contact_instagram'] ?? ''));
        
        // SEO settings
        updateSiteSetting('seo_meta_description', sanitizeInput($_POST['seo_meta_description'] ?? ''));
        updateSiteSetting('seo_keywords', sanitizeInput($_POST['seo_keywords'] ?? ''));
        updateSiteSetting('site_title', sanitizeInput($_POST['site_title'] ?? 'WebHub.uz'));
        
        // Theme settings
        updateSiteSetting('theme_color_primary', sanitizeInput($_POST['theme_color_primary'] ?? '#3B82F6'));
        
        // Stat overrides
        updateSiteSetting('stat_projects_override', $_POST['stat_projects_override'] !== '' ? (int) $_POST['stat_projects_override'] : null);
        updateSiteSetting('stat_clients_override', $_POST['stat_clients_override'] !== '' ? (int) $_POST['stat_clients_override'] : null);
        
        // Upload limits
        updateSiteSetting('max_upload_image_mb', max(1, min(50, (int) ($_POST['max_upload_image_mb'] ?? 10))));
        updateSiteSetting('max_upload_doc_mb', max(1, min(100, (int) ($_POST['max_upload_doc_mb'] ?? 20))));
        
        logAdminAction($admin['id'], 'settings_update', 'site_settings', null);
        $message = 'Sozlamalar saqlandi';
        $messageType = 'success';
    }
}

// Get current settings
$settings = getAllSiteSettings();

// Backup action
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    header('Content-Type: text/sql');
    header('Content-Disposition: attachment; filename="webhub_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    
    $tables = ['users', 'admins', 'services', 'portfolio', 'blog_posts', 'applications', 
               'chat_threads', 'chat_messages', 'notifications', 'site_settings', 
               'api_tokens', 'media', 'audit_log'];
    
    foreach ($tables as $table) {
        echo "-- Table: {$table}\n";
        $result = dbQuery("SHOW CREATE TABLE {$table}")->fetch();
        echo $result['Create Table'] . ";\n\n";
        
        $rows = dbFetchAll("SELECT * FROM {$table}");
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function($v) {
                    return $v === null ? 'NULL' : "'" . addslashes($v) . "'";
                }, array_values($row));
                echo "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        echo "\n";
    }
    exit;
}

// JSON export action
if (isset($_GET['action']) && $_GET['action'] === 'export_json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="webhub_content_' . date('Y-m-d_H-i-s') . '.json"');
    
    $export = [
        'services' => dbFetchAll("SELECT * FROM services"),
        'portfolio' => dbFetchAll("SELECT * FROM portfolio"),
        'blog_posts' => dbFetchAll("SELECT * FROM blog_posts WHERE status = 'published'"),
        'site_settings' => getAllSiteSettings()
    ];
    
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sozlamalar - WebHub Admin</title>
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
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: #D1FAE5; color: #047857; }
        .alert-error { background: #FEE2E2; color: #B91C1C; }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
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
                <a href="chat.php" class="nav-link">💬 Chat</a>
                <a href="settings.php" class="nav-link active">⚙ Sozlamalar</a>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
                <a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko'rish</a>
                <a href="logout.php" class="nav-link" style="color: var(--error);">🚪 Chiqish</a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1 style="margin-bottom: 4px;">Sozlamalar</h1>
                    <p style="color: var(--text-muted);">Sayt sozlamalarini boshqarish</p>
                </div>
                <button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo e($message); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <!-- Contact Settings -->
                <div class="card">
                    <h3 class="section-title">📞 Aloqa ma'lumotlari</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Telefon raqam</label>
                            <input type="tel" name="contact_phone" class="form-input" 
                                   value="<?php echo e($settings['contact_phone'] ?? ''); ?>" 
                                   placeholder="+998 XX XXX XX XX">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Telegram</label>
                            <input type="text" name="contact_telegram" class="form-input" 
                                   value="<?php echo e($settings['contact_telegram'] ?? ''); ?>" 
                                   placeholder="@username">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Instagram</label>
                            <input type="text" name="contact_instagram" class="form-input" 
                                   value="<?php echo e($settings['contact_instagram'] ?? ''); ?>" 
                                   placeholder="@username">
                        </div>
                    </div>
                </div>
                
                <!-- SEO Settings -->
                <div class="card">
                    <h3 class="section-title">🔍 SEO sozlamalari</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Sayt nomi</label>
                        <input type="text" name="site_title" class="form-input" 
                               value="<?php echo e($settings['site_title'] ?? 'WebHub.uz'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Meta tavsif (Description)</label>
                        <textarea name="seo_meta_description" class="form-textarea"><?php echo e($settings['seo_meta_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kalit so'zlar (Keywords)</label>
                        <input type="text" name="seo_keywords" class="form-input" 
                               value="<?php echo e($settings['seo_keywords'] ?? ''); ?>" 
                               placeholder="veb-sayt, telegram bot, dasturlash">
                    </div>
                </div>
                
                <!-- Theme Settings -->
                <div class="card">
                    <h3 class="section-title">🎨 Mavzu sozlamalari</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Asosiy rang</label>
                            <input type="color" name="theme_color_primary" class="form-input" 
                                   value="<?php echo e($settings['theme_color_primary'] ?? '#3B82F6'); ?>" 
                                   style="height: 50px;">
                        </div>
                    </div>
                </div>
                
                <!-- Stat Overrides -->
                <div class="card">
                    <h3 class="section-title">📊 Statistika (ixtiyoriy)</h3>
                    <p style="color: var(--text-muted); margin-bottom: 16px; font-size: 0.9rem;">
                        Bo'sh qoldirilsa, haqiqiy ma'lumotlar bazasidan hisoblanadi.
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Loyihalar soni (override)</label>
                            <input type="number" name="stat_projects_override" class="form-input" 
                                   value="<?php echo e($settings['stat_projects_override'] ?? ''); ?>" 
                                   placeholder="Avto">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Mijozlar soni (override)</label>
                            <input type="number" name="stat_clients_override" class="form-input" 
                                   value="<?php echo e($settings['stat_clients_override'] ?? ''); ?>" 
                                   placeholder="Avto">
                        </div>
                    </div>
                </div>
                
                <!-- Upload Limits -->
                <div class="card">
                    <h3 class="section-title">📁 Yuklash limitlari (MB)</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Rasm maksimal hajmi</label>
                            <input type="number" name="max_upload_image_mb" class="form-input" 
                                   value="<?php echo (int) ($settings['max_upload_image_mb'] ?? 10); ?>" 
                                   min="1" max="50">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Hujjat maksimal hajmi</label>
                            <input type="number" name="max_upload_doc_mb" class="form-input" 
                                   value="<?php echo (int) ($settings['max_upload_doc_mb'] ?? 20); ?>" 
                                   min="1" max="100">
                        </div>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">💾 Saqlash</button>
                    <a href="?action=backup" class="btn btn-secondary" target="_blank">📦 SQL Backup</a>
                    <a href="?action=export_json" class="btn btn-secondary" target="_blank">📄 JSON Export</a>
                </div>
            </form>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
