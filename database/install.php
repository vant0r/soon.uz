<?php
/**
 * WebHub.uz - To'liq O'rnatish Skripti
 * Ma'lumotlar bazasini yaratish va boshlang'ich ma'lumotlarni yuklash
 */

// Xatoliklarni ko'rsatish (production'da o'chiring)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebHub.uz - Ma'lumotlar Bazasini O'rnatish</title>
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
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .step.success { border-left-color: #10b981; }
        .step.error { border-left-color: #ef4444; }
        .step.pending { border-left-color: #f59e0b; }
        .step h3 {
            margin-bottom: 10px;
            color: #333;
            font-size: 18px;
        }
        .step p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }
        .code {
            background: #1e293b;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #ef4444;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        ul {
            margin-left: 20px;
            color: #666;
            font-size: 14px;
            line-height: 1.8;
        }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 WebHub.uz - Ma'lumotlar Bazasini O'rnatish</h1>
        <p class="subtitle">database/install.php skripti orqali avtomatik o'rnatish</p>

        <?php
        // Konfiguratsiya faylini tekshirish
        $configFile = __DIR__ . '/../includes/config.php';
        $schemaFile = __DIR__ . '/schema.sql';
        
        echo '<div class="warning">';
        echo '⚠️ <strong>Diqqat!</strong> Bu skript ma\'lumotlar bazasini to\'liq qayta yaratadi. Agar baza mavjud bo\'lsa, barcha ma\'lumotlar o\'chiriladi.';
        echo '</div>';

        if (!file_exists($configFile)) {
            echo '<div class="step error">';
            echo '<h3>❌ Xatolik: config.php fayli topilmadi</h3>';
            echo '<p>Avval <code>includes/config.php.dist</code> faylini <code>includes/config.php</code> ga nomlang va ma\'lumotlarni to\'ldiring.</p>';
            echo '<a href="../install.php" class="btn">Asosiy O\'rnatish Sahifasiga Qaytish</a>';
            echo '</div>';
            exit;
        }

        require_once $configFile;

        if (!file_exists($schemaFile)) {
            echo '<div class="step error">';
            echo '<h3>❌ Xatolik: schema.sql fayli topilmadi</h3>';
            echo '<p><code>database/schema.sql</code> faylini tekshiring.</p>';
            echo '</div>';
            exit;
        }

        // MySQL ulanishni tekshirish
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            echo '<div class="step success">';
            echo '<h3>✅ MySQL serverga ulanish muvaffaqiyatli</h3>';
            echo '<p>Host: ' . htmlspecialchars($db_host) . ', User: ' . htmlspecialchars($db_user) . '</p>';
            echo '</div>';
        } catch (PDOException $e) {
            echo '<div class="step error">';
            echo '<h3>❌ MySQL serverga ulanib bo\'lmadi</h3>';
            echo '<p>Xatolik: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>config.php faylidagi ma\'lumotlarni tekshiring.</p>';
            echo '<a href="../install.php" class="btn">Asosiy O\'rnatish Sahifasiga Qaytish</a>';
            echo '</div>';
            exit;
        }

        // SQL faylni o'qish
        $sql = file_get_contents($schemaFile);
        
        if ($sql === false) {
            echo '<div class="step error">';
            echo '<h3>❌ Xatolik: schema.sql faylini o\'qib bo\'lmadi</h3>';
            echo '</div>';
            exit;
        }

        // Baza yaratish
        try {
            echo '<div class="step pending">';
            echo '<h3>⏳ Ma\'lumotlar bazasini yaratish...</h3>';
            
            // Baza yaratish
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_name}`");
            
            echo '<p>✅ Baza yaratildi: <strong>' . htmlspecialchars($db_name) . '</strong></p>';
            echo '</div>';

            // Triggerlarni alohida bajarish uchun SQL ni bo'lish
            $statements = preg_split('/;\s*(?=(?:[^\'"]*\'[^\']*\'[^\'"]*\')*(?:[^\'"]*"[^"]*"[^\'"]*\')*$)/', $sql);
            $statements = array_filter(array_map('trim', $statements));
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                    $successCount++;
                } catch (PDOException $e) {
                    // DELIMITER xatolarini e'tiborsiz qoldirish
                    if (strpos($e->getMessage(), 'DELIMITER') === false) {
                        $errorCount++;
                        $errors[] = $e->getMessage();
                    }
                }
            }

            echo '<div class="step ' . ($errorCount > 0 ? 'error' : 'success') . '">';
            echo '<h3>' . ($errorCount > 0 ? '⚠️ Qisman muvaffaqiyatli' : '✅ Muvaffaqiyatli') . '</h3>';
            echo '<p>Bajarilgan operatsiyalar: <strong>' . $successCount . '</strong></p>';
            
            if ($errorCount > 0 && !empty($errors)) {
                echo '<p>Xatoliklar soni: <strong>' . $errorCount . '</strong></p>';
                echo '<details><summary>Xatoliklarni ko\'rish</summary><div class="code">';
                foreach (array_slice($errors, 0, 5) as $err) {
                    echo htmlspecialchars($err) . "<br>";
                }
                echo '</div></details>';
            }
            
            echo '</div>';

            // Ma'lumotlarni tekshirish
            echo '<div class="step success">';
            echo '<h3>📊 Yaratilgan jadvallar va ma\'lumotlar</h3>';
            
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo '<p>Jadvallar soni: <strong>' . count($tables) . '</strong></p>';
            echo '<ul>';
            foreach ($tables as $table) {
                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                echo '<li>' . htmlspecialchars($table) . ' - ' . $count . ' yozuv</li>';
            }
            echo '</ul>';
            echo '</div>';

            // Admin hisobini tekshirish
            $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            $serviceCount = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
            $portfolioCount = $pdo->query("SELECT COUNT(*) FROM portfolio")->fetchColumn();
            $blogCount = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();

            echo '<div class="step success">';
            echo '<h3>📋 Boshlang\'ich ma\'lumotlar</h3>';
            echo '<ul>';
            echo '<li>Adminlar: <strong>' . $adminCount . '</strong></li>';
            echo '<li>Xizmatlar: <strong>' . $serviceCount . '</strong></li>';
            echo '<li>Portfolio: <strong>' . $portfolioCount . '</strong></li>';
            echo '<li>Blog postlar: <strong>' . $blogCount . '</strong></li>';
            echo '</ul>';
            echo '</div>';

            echo '<div class="step success">';
            echo '<h3>🎉 O\'rnatish muvaffaqiyatli yakunlandi!</h3>';
            echo '<p>Endi quyidagi amallarni bajaring:</p>';
            echo '<ul>';
            echo '<li>✅ <code>install.php</code> faylini o\'chiring (xavfsizlik uchun)</li>';
            echo '<li>✅ Admin panelga kiring: <code>/admin/login.php</code></li>';
            echo '<li>✅ Default admin: <strong>admin / admin123</strong> (parolni o\'zgartiring!)</li>';
            echo '<li>✅ Google OAuth sozlamalarini kiriting</li>';
            echo '<li>✅ Sayt sozlamalarini o\'z ehtiyojlaringizga moslang</li>';
            echo '</ul>';
            echo '<a href="../admin/login.php" class="btn">Admin Panelga Kirish</a>';
            echo '<a href="../index.php" class="btn btn-danger">Bosh Sahifaga O\'tish</a>';
            echo '</div>';

        } catch (PDOException $e) {
            echo '<div class="step error">';
            echo '<h3>❌ Kritikal xatolik</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999;">
            <p>WebHub.uz v1.0.0 | © 2024 Barcha huquqlar himoyalangan.</p>
        </div>
    </div>
</body>
</html>
