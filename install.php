<?php
/**
 * WebHub.uz Installer
 * Phase 1: Database schema + installation wizard
 */

// Prevent direct access after installation
if (file_exists(__DIR__ . '/includes/install.lock')) {
    die('O\'rnatish jarayoni allaqachon yakunlangan. install.lock fayli mavjud.');
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('Xatolik: PHP 8.0 yoki undan yuqori versiya talab qilinadi. Sizning versiyangiz: ' . PHP_VERSION);
}

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'fileinfo', 'json'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die('Xatolik: Quyidagi PHP kengaytmalari o\'rnatilmagan: ' . implode(', ', $missingExtensions));
}

// Start session for multi-step form
session_start();

// Determine current step
$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$totalSteps = 5;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (empty($_SESSION['installer_csrf']) || !hash_equals($_SESSION['installer_csrf'], $csrfToken)) {
        die('CSRF token xatosi. Iltimos sahifani yangilab qayta urinib ko\'ring.');
    }
    
    switch ($step) {
        case 2: // Database configuration
            $_SESSION['db_host'] = trim($_POST['db_host'] ?? '');
            $_SESSION['db_name'] = trim($_POST['db_name'] ?? '');
            $_SESSION['db_user'] = trim($_POST['db_user'] ?? '');
            $_SESSION['db_pass'] = $_POST['db_pass'] ?? '';
            
            // Test database connection
            try {
                $dsn = "mysql:host=" . $_SESSION['db_host'] . ";charset=utf8mb4";
                $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if database exists
                $stmt = $pdo->query("SHOW DATABASES LIKE '" . $_SESSION['db_name'] . "'");
                if ($stmt->rowCount() === 0) {
                    // Create database
                    $pdo->exec("CREATE DATABASE `" . $_SESSION['db_name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                
                $_SESSION['db_test_passed'] = true;
                header('Location: ?step=3');
                exit;
            } catch (PDOException $e) {
                $error = 'Ma\\'lumotlar bazasiga ulanish xatosi: ' . $e->getMessage();
            }
            break;
            
        case 3: // Admin account
            $login = trim($_POST['admin_login'] ?? '');
            $password = $_POST['admin_password'] ?? '';
            $name = trim($_POST['admin_name'] ?? '');
            
            // Validate
            $errors = [];
            if (strlen($login) < 3) {
                $errors[] = 'Login kamida 3 belgidan iborat bo\\'lishi kerak';
            }
            
            $passwordValidation = validatePasswordStrength($password);
            if (!$passwordValidation['valid']) {
                $errors = array_merge($errors, $passwordValidation['errors']);
            }
            
            if (empty($name)) {
                $errors[] = 'Ismni kiriting';
            }
            
            if (empty($errors)) {
                $_SESSION['admin_login'] = $login;
                $_SESSION['admin_password'] = password_hash($password, PASSWORD_ARGON2ID);
                $_SESSION['admin_name'] = $name;
                header('Location: ?step=4');
                exit;
            } else {
                $error = implode('<br>', $errors);
            }
            break;
            
        case 4: // Google OAuth
            $_SESSION['google_client_id'] = trim($_POST['google_client_id'] ?? '');
            $_SESSION['google_client_secret'] = trim($_POST['google_client_secret'] ?? '');
            $_SESSION['google_redirect_uri'] = trim($_POST['google_redirect_uri'] ?? '');
            
            // Basic validation
            if (empty($_SESSION['google_client_id']) || empty($_SESSION['google_client_secret'])) {
                $error = 'Google OAuth ma\\'lumotlarini to\\'ldiring';
            } else {
                header('Location: ?step=5');
                exit;
            }
            break;
            
        case 5: // Final installation
            // Generate config.php
            $siteUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . 
                       '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/');
            
            $configContent = file_get_contents(__DIR__ . '/includes/config.php.dist');
            $configContent = str_replace([
                '{{DB_HOST}}',
                '{{DB_NAME}}',
                '{{DB_USER}}',
                '{{DB_PASS}}',
                '{{GOOGLE_CLIENT_ID}}',
                '{{GOOGLE_CLIENT_SECRET}}',
                '{{GOOGLE_REDIRECT_URI}}',
                '{{SITE_URL}}'
            ], [
                $_SESSION['db_host'],
                $_SESSION['db_name'],
                $_SESSION['db_user'],
                $_SESSION['db_pass'],
                $_SESSION['google_client_id'],
                $_SESSION['google_client_secret'],
                $_SESSION['google_redirect_uri'],
                $siteUrl
            ], $configContent);
            
            // Write config.php
            if (!file_put_contents(__DIR__ . '/includes/config.php', $configContent)) {
                die('config.php faylini yaratishda xatolik yuz berdi');
            }
            
            // Protect config.php with .htaccess
            $htaccessContent = "Order deny,allow\nDeny from all\n";
            file_put_contents(__DIR__ . '/includes/.htaccess', $htaccessContent);
            
            // Create database tables
            require_once __DIR__ . '/includes/config.php';
            
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Execute schema
                $schema = getDatabaseSchema();
                foreach ($schema as $sql) {
                    $pdo->exec($sql);
                }
                
                // Create admin user
                $stmt = $pdo->prepare("INSERT INTO admins (login, password_hash, name, created_at) VALUES (:login, :password, :name, NOW())");
                $stmt->execute([
                    'login' => $_SESSION['admin_login'],
                    'password' => $_SESSION['admin_password'],
                    'name' => $_SESSION['admin_name']
                ]);
                
                // Insert default site settings
                $defaultSettings = [
                    ['contact_phone', ''],
                    ['contact_telegram', ''],
                    ['contact_instagram', ''],
                    ['seo_meta_description', 'WebHub - Professional IT xizmatlar'],
                    ['seo_keywords', 'veb-sayt, telegram bot, AI yechimlar, mobil ilovalar'],
                    ['theme_color_primary', '#3B82F6'],
                    ['stat_projects_override', null],
                    ['stat_clients_override', null],
                    ['max_upload_image_mb', '10'],
                    ['max_upload_doc_mb', '20'],
                    ['hero_headline', 'Kelajak Texnologiyalari Bugun'],
                    ['hero_description', 'Biznesingizni raqamli dunyoda rivojlantirish uchun professional IT yechimlar'],
                    ['how_we_work_step1_title', 'G\'oya'],
                    ['how_we_work_step1_desc', 'Loyihangiz g\'oyasi va talablarini muhokama qilamiz'],
                    ['how_we_work_step2_title', 'Reja'],
                    ['how_we_work_step2_desc', 'Texnik topshiriq va ish rejasi tuzamiz'],
                    ['how_we_work_step3_title', 'Rivojlantirish'],
                    ['how_we_work_step3_desc', 'Loyihani sifatli va o\'z vaqtida bajarib beramiz'],
                    ['how_we_work_step4_title', 'Natija'],
                    ['how_we_work_step4_desc', 'Tayyor mahsulotni taqdim etamiz va qo\'llab-quvvatlaymiz']
                ];
                
                $stmt = $pdo->prepare("INSERT INTO site_settings (`key`, value) VALUES (:key, :value)");
                foreach ($defaultSettings as $setting) {
                    $stmt->execute(['key' => $setting[0], 'value' => $setting[1]]);
                }
                
                // Create lock file
                file_put_contents(__DIR__ . '/includes/install.lock', date('Y-m-d H:i:s'));
                
                // Clear session
                session_destroy();
                
                $installationComplete = true;
            } catch (PDOException $e) {
                die('Jadvallarni yaratishda xatolik: ' . $e->getMessage());
            }
            break;
    }
}

// Generate CSRF token for form
if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Get database schema SQL statements
 */
function getDatabaseSchema() {
    return [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            google_id VARCHAR(255) UNIQUE,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            phone VARCHAR(20),
            avatar VARCHAR(500),
            status ENUM('active','blocked','deleted') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS admins (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS services (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            price INT NOT NULL,
            addons_json JSON,
            features_json JSON,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS portfolio (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            image VARCHAR(500),
            client_name VARCHAR(255),
            link VARCHAR(500),
            category VARCHAR(100),
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body LONGTEXT,
            image VARCHAR(500),
            status ENUM('draft','published') DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS applications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED,
            service_name_snapshot VARCHAR(255),
            customization_json JSON,
            description TEXT,
            status ENUM('new','in_review','approved','completed','cancelled') DEFAULT 'new',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_app_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS chat_threads (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_thread_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS chat_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            thread_id BIGINT UNSIGNED NOT NULL,
            sender_type ENUM('admin','user') NOT NULL,
            sender_id BIGINT UNSIGNED NOT NULL,
            message TEXT,
            file_path VARCHAR(500),
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_msg_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS site_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) UNIQUE NOT NULL,
            value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS api_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(128) UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS media (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100),
            size_bytes INT,
            context ENUM('logo','banner','og_image','portfolio','blog','chat_attachment','generic') DEFAULT 'generic',
            uploaded_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_media_admin FOREIGN KEY (uploaded_by) REFERENCES admins(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id BIGINT UNSIGNED,
            action VARCHAR(255) NOT NULL,
            target_type VARCHAR(50),
            target_id BIGINT UNSIGNED,
            details_json JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $errors = [];
    if (strlen($password) < 10) {
        $errors[] = 'Parol kamida 10 belgidan iborat bo\'lishi kerak';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Parolda kamida bitta katta harf bo\'lishi kerak';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Parolda kamida bitta kichik harf bo\'lishi kerak';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Parolda kamida bitta raqam bo\'lishi kerak';
    }
    return ['valid' => empty($errors), 'errors' => $errors];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebHub.uz - O'rnatish</title>
    <style>
        :root {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --bg-light: #F8FAFC;
            --bg-dark: #1E293B;
            --text-light: #1E293B;
            --text-dark: #F1F5F9;
            --glass-light: rgba(255, 255, 255, 0.7);
            --glass-dark: rgba(30, 41, 59, 0.7);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #E0E7FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, var(--bg-dark) 0%, #0F172A 100%);
            }
        }
        
        .installer-container {
            max-width: 600px;
            width: 100%;
            background: var(--glass-light);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @media (prefers-color-scheme: dark) {
            .installer-container {
                background: var(--glass-dark);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
        }
        
        h1 {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
            color: var(--text-light);
        }
        
        @media (prefers-color-scheme: dark) {
            h1 {
                color: var(--text-dark);
            }
        }
        
        .subtitle {
            text-align: center;
            color: #64748B;
            margin-bottom: 30px;
        }
        
        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .progress-bar::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #CBD5E1;
            transform: translateY(-50%);
            z-index: 0;
        }
        
        .progress-step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .progress-step.active {
            background: var(--primary);
            color: white;
        }
        
        .progress-step.completed {
            background: #10B981;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-light);
        }
        
        @media (prefers-color-scheme: dark) {
            label {
                color: var(--text-dark);
            }
        }
        
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #CBD5E1;
            border-radius: 12px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        @media (prefers-color-scheme: dark) {
            input[type="text"],
            input[type="password"],
            input[type="email"] {
                background: rgba(30, 41, 59, 0.5);
                border-color: #475569;
                color: var(--text-dark);
            }
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        
        .error-message {
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #D1FAE5;
            border: 1px solid #6EE7B7;
            color: #059669;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--text-light);
        }
        
        @media (prefers-color-scheme: dark) {
            .info-box {
                color: var(--text-dark);
            }
        }
        
        .password-requirements {
            font-size: 12px;
            color: #64748B;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <?php if (isset($installationComplete) && $installationComplete): ?>
    <div class="installer-container">
        <h1>🎉 Muvaffaqiyatli!</h1>
        <p class="subtitle">O'rnatish jarayoni yakunlandi</p>
        
        <div class="success-message">
            <strong>WebHub.uz muvaffaqiyatli o'rnatildi!</strong><br><br>
            Endi siz admin panelga kirishingiz mumkin:<br>
            <a href="admin/login.php" style="color: #059669; font-weight: 600;">Admin panelga o'tish →</a>
        </div>
        
        <div class="info-box" style="margin-top: 20px;">
            <strong>Muhim:</strong> Xavfsizlik uchun install.php faylini serverdan o'chirib tashlashingiz tavsiya etiladi.
        </div>
    </div>
    <?php else: ?>
    <div class="installer-container">
        <h1>WebHub.uz O'rnatish</h1>
        <p class="subtitle"><?php echo $step; ?>-bosqich / <?php echo $totalSteps; ?></p>
        
        <div class="progress-bar">
            <?php for ($i = 1; $i <= $totalSteps; $i++): ?>
            <div class="progress-step <?php echo $i === $step ? 'active' : ''; ?> <?php echo $i < $step ? 'completed' : ''; ?>">
                <?php echo $i < $step ? '✓' : $i; ?>
            </div>
            <?php endfor; ?>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="?step=<?php echo $step; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['installer_csrf']; ?>">
            
            <?php if ($step === 1): ?>
            <div class="info-box">
                <strong>Servertalablari:</strong><br>
                • PHP 8.0 yoki undan yuqori<br>
                • MySQL / MariaDB<br>
                • PDO MySQL kengaytmasi<br>
                • GD yoki Imagick<br>
                • HTTPS tavsiya etiladi
            </div>
            
            <button type="submit" class="btn">Davom etish →</button>
            
            <?php elseif ($step === 2): ?>
            <div class="form-group">
                <label for="db_host">MySQL Host</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Ma'lumotlar bazasi nomi</label>
                <input type="text" id="db_name" name="db_name" value="webhub" required>
                <small style="color: #64748B;">Agar baza mavjud bo'lmasa, avtomatik yaratiladi</small>
            </div>
            
            <div class="form-group">
                <label for="db_user">MySQL Foydalanuvchi</label>
                <input type="text" id="db_user" name="db_user" value="root" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">MySQL Parol</label>
                <input type="password" id="db_pass" name="db_pass" value="">
            </div>
            
            <button type="submit" class="btn">Ulanishni sinash va davom etish →</button>
            
            <?php elseif ($step === 3): ?>
            <div class="info-box">
                <strong>Admin hisobi yaratish</strong><br>
                Bu hisob bilan admin panelga kirasiz
            </div>
            
            <div class="form-group">
                <label for="admin_login">Login</label>
                <input type="text" id="admin_login" name="admin_login" required minlength="3">
            </div>
            
            <div class="form-group">
                <label for="admin_name">Ism</label>
                <input type="text" id="admin_name" name="admin_name" required>
            </div>
            
            <div class="form-group">
                <label for="admin_password">Parol</label>
                <input type="password" id="admin_password" name="admin_password" required>
                <div class="password-requirements">
                    Kamida 10 belgi, katta harf, kichik harf va raqam bo'lishi kerak
                </div>
            </div>
            
            <button type="submit" class="btn">Davom etish →</button>
            
            <?php elseif ($step === 4): ?>
            <div class="info-box">
                <strong>Google OAuth sozlamalari</strong><br>
                Google Cloud Console'dan OAuth ma'lumotlarini oling:<br>
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color: var(--primary);">https://console.cloud.google.com/apis/credentials</a>
            </div>
            
            <div class="form-group">
                <label for="google_client_id">Google Client ID</label>
                <input type="text" id="google_client_id" name="google_client_id" required>
            </div>
            
            <div class="form-group">
                <label for="google_client_secret">Google Client Secret</label>
                <input type="text" id="google_client_secret" name="google_client_secret" required>
            </div>
            
            <div class="form-group">
                <label for="google_redirect_uri">Redirect URI</label>
                <input type="text" id="google_redirect_uri" name="google_redirect_uri" 
                       value="<?php echo 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/user/oauth-callback.php'; ?>" 
                       required>
                <small style="color: #64748B;">Bu URL'ni Google Cloud Console'da Authorized redirect URI sifatida qo'shing</small>
            </div>
            
            <button type="submit" class="btn">O'rnatishni yakunlash →</button>
            
            <?php elseif ($step === 5): ?>
            <div class="info-box">
                <strong>O'rnatishga tayyor</strong><br>
                Quyidagi ma'lumotlar asosida tizim o'rnatiladi:<br><br>
                <strong>Baza:</strong> <?php echo $_SESSION['db_name']; ?>@<?php echo $_SESSION['db_host']; ?><br>
                <strong>Admin:</strong> <?php echo $_SESSION['admin_login']; ?><br>
                <strong>Google OAuth:</strong> Sozlangan
            </div>
            
            <button type="submit" class="btn">O'rnatishni boshlash</button>
            </form>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>
