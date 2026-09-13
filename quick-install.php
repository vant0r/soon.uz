<?php
/**
 * WebHub.uz - Bitta Fayl O'rnatish (Quick Install)
 * Barcha kerakli operatsiyalarni bajaradi: config yaratish, baza yaratish, ma'lumotlarni yuklash
 */

// Xatoliklarni ko'rsatish (production'da o'chiring)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

header('Content-Type: text/html; charset=utf-8');

// Konfiguratsiya shabloni
$configTemplate = '<?php
/**
 * WebHub.uz - Ma\'lumotlar Bazasi Konfiguratsiyasi
 * Ushbu faylni xavfsiz joyda saqlang va brauzerda to\'g\'ridan-to\'g\'ri ochmang.
 */

// Ma\'lumotlar bazasi sozlamalari
$db_host = \'%s\';
$db_name = \'%s\';
$db_user = \'%s\';
$db_pass = \'%s\';
$db_charset = \'utf8mb4\';

// PDO sozlamalari
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    if (defined(\'APP_DEBUG\') && APP_DEBUG) {
        throw $e;
    }
    http_response_code(500);
    exit(\'Ma\\\'lumotlar bazasiga ulanishda xatolik yuz berdi.\');
}
';

// O'rnatish holati
$installComplete = false;
$errors = [];
$steps = [];

// Form yuborilganda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'install') {
        // Form ma'lumotlarini olish
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? 'webhub_uz');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        
        $admin_username = trim($_POST['admin_username'] ?? 'admin');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_fullname = trim($_POST['admin_fullname'] ?? 'Bosh Admin');
        
        $google_client_id = trim($_POST['google_client_id'] ?? '');
        $google_client_secret = trim($_POST['google_client_secret'] ?? '');
        $google_redirect_uri = trim($_POST['google_redirect_uri'] ?? 'https://saytingiz.uz/user/oauth-callback.php');
        
        // Validatsiya
        if (empty($db_user)) {
            $errors[] = "MySQL foydalanuvchi nomi kiritilmagan";
        }
        if (empty($admin_username)) {
            $errors[] = "Admin login kiritilmagan";
        }
        if (empty($admin_password) || strlen($admin_password) < 6) {
            $errors[] = "Admin parol kamida 6 belgidan iborat bo'lishi kerak";
        }
        if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Admin email noto'g'ri formatda";
        }
        
        if (empty($errors)) {
            try {
                // 1-qadam: MySQL serverga ulanish
                $steps[] = ['name' => 'MySQL serverga ulanish', 'status' => 'pending'];
                
                try {
                    $tempPdo = new PDO(
                        "mysql:host={$db_host};charset=utf8mb4",
                        $db_user,
                        $db_pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $steps[count($steps)-1]['status'] = 'success';
                } catch (PDOException $e) {
                    $steps[count($steps)-1]['status'] = 'error';
                    $steps[count($steps)-1]['message'] = $e->getMessage();
                    throw new Exception("MySQL serverga ulanib bo'lmadi: " . $e->getMessage());
                }
                
                // 2-qadam: Config fayl yaratish
                $steps[] = ['name' => 'Konfiguratsiya faylini yaratish', 'status' => 'pending'];
                
                $configContent = sprintf($configTemplate, $db_host, $db_name, $db_user, $db_pass);
                $configPath = __DIR__ . '/includes/config.php';
                
                if (file_put_contents($configPath, $configContent) === false) {
                    $steps[count($steps)-1]['status'] = 'error';
                    throw new Exception("config.php faylini yaratib bo'lmadi. Fayl ruxsatlarini tekshiring.");
                }
                $steps[count($steps)-1]['status'] = 'success';
                
                // 3-qadam: Ma'lumotlar bazasini yaratish
                $steps[] = ['name' => 'Ma\'lumotlar bazasini yaratish', 'status' => 'pending'];
                
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $tempPdo->exec("USE `{$db_name}`");
                $steps[count($steps)-1]['status'] = 'success';
                
                // 4-qadam: Jadvallarni yaratish
                $steps[] = ['name' => 'Jadvallar va ma\'lumotlarni yuklash', 'status' => 'pending'];
                
                $schemaFile = __DIR__ . '/database/schema.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("schema.sql fayli topilmadi");
                }
                
                $sql = file_get_contents($schemaFile);
                $statements = preg_split('/;\s*(?=(?:[^\'"]*\'[^\']*\'[^\'"]*\')*(?:[^\'"]*"[^"]*"[^\'"]*\')*$)/', $sql);
                $statements = array_filter(array_map('trim', $statements));
                
                $createCount = 0;
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'DELIMITER') === 0) {
                        continue;
                    }
                    try {
                        $tempPdo->exec($statement);
                        $createCount++;
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'DELIMITER') === false) {
                            // Xatolikni log qilish, lekin davom etish
                            error_log("SQL Error: " . $e->getMessage());
                        }
                    }
                }
                $steps[count($steps)-1]['status'] = 'success';
                $steps[count($steps)-1]['message'] = "{$createCount} operatsiya bajarildi";
                
                // 5-qadam: Admin parolini yangilash
                $steps[] = ['name' => 'Admin hisobini sozlash', 'status' => 'pending'];
                
                require_once $configPath;
                
                $passwordHash = password_hash($admin_password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost' => 4,
                    'threads' => 1
                ]);
                
                // Adminni yangilash yoki yaratish
                $stmt = $pdo->prepare("
                    INSERT INTO admins (username, password_hash, full_name, email, role, status, created_at)
                    VALUES (?, ?, ?, ?, 'super_admin', 'active', NOW())
                    ON DUPLICATE KEY UPDATE 
                        password_hash = VALUES(password_hash),
                        full_name = VALUES(full_name),
                        email = VALUES(email)
                ");
                $stmt->execute([$admin_username, $passwordHash, $admin_fullname, $admin_email]);
                $steps[count($steps)-1]['status'] = 'success';
                
                // 6-qadam: Google OAuth sozlamalarini saqlash
                $steps[] = ['name' => 'Google OAuth sozlamalari', 'status' => 'pending'];
                
                if (!empty($google_client_id)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO settings (setting_key, setting_value, setting_type, group_name)
                        VALUES 
                            ('google_client_id', ?, 'string', 'oauth'),
                            ('google_client_secret', ?, 'string', 'oauth'),
                            ('google_redirect_uri', ?, 'string', 'oauth')
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$google_client_id, $google_client_secret, $google_redirect_uri]);
                }
                $steps[count($steps)-1]['status'] = 'success';
                
                // 7-qadam: Upload papkalarini yaratish
                $steps[] = ['name' => 'Upload papkalarini yaratish', 'status' => 'pending'];
                
                $uploadDirs = [
                    __DIR__ . '/uploads/blog',
                    __DIR__ . '/uploads/portfolio',
                    __DIR__ . '/uploads/chat',
                    __DIR__ . '/uploads/banner',
                    __DIR__ . '/uploads/logo',
                    __DIR__ . '/uploads/og_image'
                ];
                
                foreach ($uploadDirs as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    // .htaccess yaratish
                    $htaccessFile = $dir . '/.htaccess';
                    if (!file_exists($htaccessFile)) {
                        file_put_contents($htaccessFile, "Options -Indexes\nDeny from all");
                    }
                }
                $steps[count($steps)-1]['status'] = 'success';
                
                // Yakunlash
                $installComplete = true;
                
                // Xavfsizlik ogohlantirishi
                $_SESSION['install_complete'] = true;
                
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebHub.uz - Tezkor O'rnatish</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
            text-align: center;
        }
        .logo {
            text-align: center;
            font-size: 48px;
            margin-bottom: 20px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="url"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .row {
            display: flex;
            gap: 15px;
        }
        .row .form-group {
            flex: 1;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .step {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .step-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
        }
        .step.pending .step-icon {
            background: #fbbf24;
            color: white;
        }
        .step.success .step-icon {
            background: #10b981;
            color: white;
        }
        .step.error .step-icon {
            background: #ef4444;
            color: white;
        }
        .step-name {
            flex: 1;
            font-size: 14px;
            color: #333;
        }
        .step-message {
            font-size: 12px;
            color: #666;
        }
        .success-box {
            background: #f0fdf4;
            border: 2px solid #10b981;
            color: #065f46;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }
        .success-box h2 {
            color: #10b981;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .success-box p {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .success-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .success-actions a {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #333;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #92400e;
        }
        details {
            margin-top: 15px;
        }
        summary {
            cursor: pointer;
            color: #667eea;
            font-weight: 500;
        }
        pre {
            background: #1e293b;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚀</div>
        <h1>WebHub.uz</h1>
        <p class="subtitle">Professional Web Dasturlash Platformasi - O'rnatish Sehrkori</p>

        <?php if ($installComplete): ?>
            <div class="success-box">
                <h2>🎉 Tabriklaymiz!</h2>
                <p>WebHub.uz platformasi muvaffaqiyatli o'rnatildi.</p>
                
                <div style="text-align: left; background: white; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <strong>Admin ma'lumotlari:</strong><br>
                    📧 Login: <code><?php echo htmlspecialchars($admin_username); ?></code><br>
                    🔑 Parol: <code><?php echo htmlspecialchars($admin_password); ?></code><br>
                    📍 Kirish: <code>/admin/login.php</code>
                </div>
                
                <p style="font-size: 13px; color: #dc2626;">
                    ⚠️ <strong>Muhim!</strong> Xavfsizlik uchun quyidagi fayllarni o'chiring:
                </p>
                <pre>rm install.php quick-install.php database/install.php</pre>
                
                <div class="success-actions">
                    <a href="admin/login.php" class="btn-primary">Admin Panelga Kirish</a>
                    <a href="index.php" class="btn-secondary">Bosh Sahifa</a>
                </div>
            </div>
            
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <strong>⛔ Xatoliklar yuz berdi:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($steps)): ?>
                <div class="section">
                    <h3 class="section-title">📋 O'rnatish jarayoni</h3>
                    <?php foreach ($steps as $step): ?>
                        <div class="step <?php echo $step['status']; ?>">
                            <div class="step-icon">
                                <?php if ($step['status'] === 'pending'): ?>
                                    ⏳
                                <?php elseif ($step['status'] === 'success'): ?>
                                    ✓
                                <?php else: ?>
                                    ✗
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="step-name"><?php echo htmlspecialchars($step['name']); ?></div>
                                <?php if (!empty($step['message'])): ?>
                                    <div class="step-message"><?php echo htmlspecialchars($step['message']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="install">
                
                <div class="section">
                    <h3 class="section-title">🗄️ Ma'lumotlar Bazasi Sozlamalari</h3>
                    
                    <div class="row">
                        <div class="form-group">
                            <label for="db_host">MySQL Host *</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        <div class="form-group">
                            <label for="db_name">Baza Nomi *</label>
                            <input type="text" id="db_name" name="db_name" value="webhub_uz" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="form-group">
                            <label for="db_user">MySQL Foydalanuvchi *</label>
                            <input type="text" id="db_user" name="db_user" required>
                        </div>
                        <div class="form-group">
                            <label for="db_pass">MySQL Parol</label>
                            <input type="password" id="db_pass" name="db_pass">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h3 class="section-title">👨‍💼 Admin Hisobi</h3>
                    
                    <div class="row">
                        <div class="form-group">
                            <label for="admin_username">Login *</label>
                            <input type="text" id="admin_username" name="admin_username" value="admin" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_fullname">To'liq Ism</label>
                            <input type="text" id="admin_fullname" name="admin_fullname" value="Bosh Admin">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="form-group">
                            <label for="admin_email">Email *</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_password">Parol *</label>
                            <input type="password" id="admin_password" name="admin_password" required minlength="6">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h3 class="section-title">🔐 Google OAuth (Ixtiyoriy)</h3>
                    
                    <div class="form-group">
                        <label for="google_client_id">Google Client ID</label>
                        <input type="text" id="google_client_id" name="google_client_id" placeholder="123456789-abc123...apps.googleusercontent.com">
                        <div class="hint">Google Cloud Console'dan oling</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="google_client_secret">Google Client Secret</label>
                        <input type="password" id="google_client_secret" name="google_client_secret">
                    </div>
                    
                    <div class="form-group">
                        <label for="google_redirect_uri">Redirect URI</label>
                        <input type="url" id="google_redirect_uri" name="google_redirect_uri" value="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'saytingiz.uz'); ?>/user/oauth-callback.php">
                    </div>
                </div>
                
                <div class="warning">
                    ⚠️ <strong>Diqqat!</strong> "O'rnatishni boshlash" tugmasini bosganingizdan keyin, agar baza mavjud bo'lsa, barcha ma'lumotlar qayta yoziladi.
                </div>
                
                <button type="submit" class="btn" onclick="return confirm('Haqiqatan ham o\'rnatishni boshlashni xohlaysizmi?')">
                    🚀 O'rnatishni Boshlash
                </button>
            </form>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 12px; color: #999;">
            <p>WebHub.uz v1.0.0 | © 2024 Barcha huquqlar himoyalangan.</p>
        </div>
    </div>
</body>
</html>
