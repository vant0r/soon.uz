<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/includes/install.lock')) {
    http_response_code(403);
    exit('O‘rnatish allaqachon yakunlangan.');
}
if (version_compare(PHP_VERSION, '8.0.0', '<')) exit('PHP 8.0 yoki undan yuqori versiya talab qilinadi.');
foreach (['pdo', 'pdo_mysql', 'json', 'fileinfo'] as $ext) if (!extension_loaded($ext)) exit('Yetishmayotgan PHP kengaytmasi: ' . $ext);

session_name('soon_installer');
session_set_cookie_params(['httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite' => 'Lax']);
session_start();
if (empty($_SESSION['installer_csrf'])) $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function postValue(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['installer_csrf']) . '">'; }
function validDbName(string $value): bool { return (bool)preg_match('/^[A-Za-z0-9_]{1,64}$/', $value); }
function passwordErrors(string $value): array {
    $errors = [];
    if (strlen($value) < 12) $errors[] = 'Parol kamida 12 belgidan iborat bo‘lsin.';
    if (!preg_match('/[A-Z]/', $value)) $errors[] = 'Parolda katta harf bo‘lsin.';
    if (!preg_match('/[a-z]/', $value)) $errors[] = 'Parolda kichik harf bo‘lsin.';
    if (!preg_match('/\d/', $value)) $errors[] = 'Parolda raqam bo‘lsin.';
    if (!preg_match('/[^A-Za-z0-9]/', $value)) $errors[] = 'Parolda maxsus belgi bo‘lsin.';
    return $errors;
}
function smtpRead($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
}
function smtpCommand($socket, string $command, array $codes): void {
    fwrite($socket, $command . "\r\n");
    $response = smtpRead($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) throw new RuntimeException('SMTP javobi kutilganidek emas: ' . trim($response));
}
function smtpTest(array $smtp, string $recipient): void {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP test email noto‘g‘ri.');
    $host = $smtp['host'];
    $port = (int)$smtp['port'];
    $transport = $smtp['encryption'] === 'ssl' ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
    $socket = @stream_socket_client($transport, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) throw new RuntimeException('SMTP serverga ulanib bo‘lmadi: ' . $errstr);
    stream_set_timeout($socket, 15);
    try {
        $greeting = smtpRead($socket);
        if ((int)substr($greeting, 0, 3) !== 220) throw new RuntimeException('SMTP server salomi noto‘g‘ri.');
        smtpCommand($socket, 'EHLO soon.uz', [250]);
        if ($smtp['encryption'] === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS ulanishini yoqib bo‘lmadi.');
            smtpCommand($socket, 'EHLO soon.uz', [250]);
        }
        if ($smtp['username'] !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($smtp['username']), [334]);
            smtpCommand($socket, base64_encode($smtp['password']), [235]);
        }
        smtpCommand($socket, 'MAIL FROM:<' . $smtp['from'] . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);
        $body = "From: SOON <{$smtp['from']}>\r\nTo: <{$recipient}>\r\nSubject: SOON SMTP testi\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSOON installer SMTP testi muvaffaqiyatli bajarildi.";
        fwrite($socket, preg_replace('/\r?\n/', "\r\n", $body) . "\r\n.\r\n");
        $response = smtpRead($socket);
        if ((int)substr($response, 0, 3) !== 250) throw new RuntimeException('Test email yuborilmadi: ' . trim($response));
        smtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
function runSchema(PDO $pdo, string $file): void {
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('Schema o‘qilmadi.');
    $sql = preg_replace('/^\s*CREATE DATABASE IF NOT EXISTS[^;]+;\s*/mi', '', $sql);
    $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', $sql);
    $statements = preg_split('/;\s*(?=(?:[^\'"`]*[\'"`][^\'"`]*[\'"`])*[^\'"`]*$)/', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || preg_match('/^(--|#)/', $statement)) continue;
        $pdo->exec($statement);
    }
}
function writeConfig(array $config): void {
    $template = file_get_contents(__DIR__ . '/includes/config.php.dist');
    if ($template === false) throw new RuntimeException('config.php.dist topilmadi.');
    $replacements = [
        '{{DB_HOST}}' => $config['db_host'],
        '{{DB_NAME}}' => $config['db_name'],
        '{{DB_USER}}' => $config['db_user'],
        '{{DB_PASS}}' => $config['db_pass'],
        '{{GOOGLE_CLIENT_ID}}' => $config['google_client_id'],
        '{{GOOGLE_CLIENT_SECRET}}' => $config['google_client_secret'],
        '{{GOOGLE_REDIRECT_URI}}' => $config['google_redirect_uri'],
        '{{SITE_URL}}' => rtrim($config['site_url'], '/')
    ];
    $template = strtr($template, $replacements);
    $defines = [
        'MAIL_HOST' => $config['mail_host'], 'MAIL_PORT' => (int)$config['mail_port'], 'MAIL_USERNAME' => $config['mail_username'],
        'MAIL_PASSWORD' => $config['mail_password'], 'MAIL_ENCRYPTION' => $config['mail_encryption'], 'MAIL_FROM_ADDRESS' => $config['mail_from'],
        'TELEGRAM_BOT_TOKEN' => $config['telegram_token'], 'TELEGRAM_CHAT_ID' => $config['telegram_chat_id'],
        'AI_GEMINI_KEY' => $config['gemini_key'], 'AI_GROQ_KEY' => $config['groq_key'], 'AI_OPENROUTER_KEY' => $config['openrouter_key']
    ];
    foreach ($defines as $key => $value) $template .= "\ndefine(" . var_export($key, true) . ', ' . var_export($value, true) . ");";
    if (file_put_contents(__DIR__ . '/includes/config.php', $template, LOCK_EX) === false) throw new RuntimeException('config.php yozib bo‘lmadi.');
    @chmod(__DIR__ . '/includes/config.php', 0600);
}
function seedSettings(PDO $pdo, array $c): void {
    $rows = [
        ['site_name', $c['site_name'], 'string', 'general', 'Sayt nomi', 1],
        ['site_title', $c['site_name'] . ($c['tagline'] !== '' ? ' — ' . $c['tagline'] : ''), 'string', 'seo', 'SEO sarlavhasi', 1],
        ['site_description', $c['about'], 'text', 'seo', 'Meta tavsifi', 1],
        ['site_keywords', 'SOON, IT, web, mobil ilova, dasturlash, O‘zbekiston', 'string', 'seo', 'Meta kalit so‘zlari', 1],
        ['site_url', rtrim($c['site_url'], '/'), 'string', 'general', 'Canonical URL', 1],
        ['site_tagline', $c['tagline'], 'string', 'general', 'Tagline', 1],
        ['founder_name', $c['founder'], 'string', 'general', 'Asoschi', 1],
        ['contact_phone', $c['phone'], 'string', 'contact', 'Telefon', 1],
        ['contact_email', $c['email'], 'string', 'contact', 'Email', 1],
        ['contact_telegram', $c['telegram'], 'string', 'contact', 'Telegram', 1],
        ['contact_instagram', $c['instagram'], 'string', 'contact', 'Instagram', 1],
        ['contact_address', $c['address'], 'string', 'contact', 'Manzil', 1],
        ['primary_color', '#6366f1', 'string', 'design', 'Asosiy rang', 1],
        ['accent_color', '#06b6d4', 'string', 'design', 'Accent rang', 1],
        ['theme_color', '#6366f1', 'string', 'design', 'Asosiy rang mosligi', 1],
        ['logo_path', '', 'string', 'branding', 'Logo', 1],
        ['favicon_path', '', 'string', 'branding', 'Favicon', 1],
        ['hero_banner_path', '', 'string', 'branding', 'Hero banner', 1],
        ['og_image_path', '', 'string', 'branding', 'OG image', 1],
        ['stat_projects_override', '', 'number', 'stats', 'Portfolio statistikasi', 1],
        ['stat_clients_override', '', 'number', 'stats', 'Mijozlar statistikasi', 1],
        ['max_upload_image_mb', '10', 'number', 'uploads', 'Rasm maksimal hajmi', 0],
        ['max_upload_doc_mb', '20', 'number', 'uploads', 'Hujjat maksimal hajmi', 0]
    ];
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, group_name, description, is_public) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), group_name=VALUES(group_name), description=VALUES(description), is_public=VALUES(is_public)');
    foreach ($rows as $row) $stmt->execute($row);
}

$step = max(1, min(4, (int)($_GET['step'] ?? 1)));
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['installer_csrf'] ?? '', (string)($_POST['csrf_token'] ?? ''))) { http_response_code(419); exit('CSRF xatosi.'); }
    try {
        if ($step === 1) {
            $url = rtrim(postValue('site_url'), '/');
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) throw new RuntimeException('Sayt URL noto‘g‘ri.');
            $_SESSION['site'] = ['site_name' => postValue('site_name', 'SOON'), 'site_url' => $url, 'tagline' => postValue('tagline'), 'about' => postValue('about'), 'founder' => postValue('founder'), 'email' => postValue('email'), 'phone' => postValue('phone'), 'address' => postValue('address'), 'telegram' => postValue('telegram'), 'instagram' => postValue('instagram')];
            if ($_SESSION['site']['site_name'] === '') throw new RuntimeException('Sayt nomini kiriting.');
            header('Location: ?step=2'); exit;
        }
        if ($step === 2) {
            $db = ['db_host' => postValue('db_host', 'localhost'), 'db_name' => postValue('db_name', 'soon_uz'), 'db_user' => postValue('db_user'), 'db_pass' => (string)($_POST['db_pass'] ?? '')];
            if (!validDbName($db['db_name']) || $db['db_user'] === '') throw new RuntimeException('MySQL ma’lumotlari noto‘g‘ri.');
            $pdo = new PDO('mysql:host=' . $db['db_host'] . ';charset=utf8mb4', $db['db_user'], $db['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $_SESSION['db'] = $db;
            header('Location: ?step=3'); exit;
        }
        if ($step === 3) {
            $username = postValue('admin_username'); $name = postValue('admin_name'); $email = postValue('admin_email'); $password = (string)($_POST['admin_password'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) throw new RuntimeException('Admin username noto‘g‘ri.');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Admin ma’lumotlari noto‘g‘ri.');
            $errors = passwordErrors($password); if ($errors) throw new RuntimeException(implode(' ', $errors));
            $_SESSION['admin'] = ['username' => $username, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_ARGON2ID)];
            header('Location: ?step=4'); exit;
        }
        if (!isset($_SESSION['site'], $_SESSION['db'], $_SESSION['admin'])) throw new RuntimeException('Installer sessiyasi to‘liq emas.');
        $smtp = ['host' => postValue('mail_host'), 'port' => (int)postValue('mail_port', '587'), 'username' => postValue('mail_username'), 'password' => (string)($_POST['mail_password'] ?? ''), 'encryption' => postValue('mail_encryption', 'tls'), 'from' => postValue('mail_from')];
        if ($smtp['host'] !== '') {
            if (!in_array($smtp['encryption'], ['tls', 'ssl'], true) || $smtp['port'] < 1 || $smtp['port'] > 65535 || !filter_var($smtp['from'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP ma’lumotlari noto‘g‘ri.');
            if ($smtp['username'] === '') throw new RuntimeException('SMTP username kiriting.');
            $testEmail = postValue('mail_test_email');
            if ($testEmail === '') throw new RuntimeException('SMTP test emailini kiriting.');
            smtpTest($smtp, $testEmail);
        }
        $c = array_merge($_SESSION['site'], $_SESSION['db'], $_SESSION['admin'], ['google_client_id' => postValue('google_client_id'), 'google_client_secret' => postValue('google_client_secret'), 'google_redirect_uri' => postValue('google_redirect_uri'), 'mail_host' => $smtp['host'], 'mail_port' => $smtp['port'], 'mail_username' => $smtp['username'], 'mail_password' => $smtp['password'], 'mail_encryption' => $smtp['encryption'], 'mail_from' => $smtp['from'], 'telegram_token' => (string)($_POST['telegram_token'] ?? ''), 'telegram_chat_id' => postValue('telegram_chat_id'), 'gemini_key' => (string)($_POST['gemini_key'] ?? ''), 'groq_key' => (string)($_POST['groq_key'] ?? ''), 'openrouter_key' => (string)($_POST['openrouter_key'] ?? '')]);
        $pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        runSchema($pdo, __DIR__ . '/database/schema.sql');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, full_name, email, role, status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$c['username'], $c['password_hash'], $c['name'], $c['email'], 'super_admin', 'active']);
        seedSettings($pdo, $c);
        $pdo->commit();
        writeConfig($c);
        foreach (['uploads', 'uploads/blog', 'uploads/portfolio', 'uploads/chat'] as $dir) if (!is_dir(__DIR__ . '/' . $dir)) @mkdir(__DIR__ . '/' . $dir, 0755, true);
        if (file_put_contents(__DIR__ . '/includes/install.lock', date('c'), LOCK_EX) === false) throw new RuntimeException('O‘rnatish qulfini yaratib bo‘lmadi.');
        session_destroy(); $success = true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}
$site = $_SESSION['site'] ?? ['site_name' => 'SOON', 'site_url' => 'https://soon.uz', 'tagline' => '', 'about' => '', 'founder' => '', 'email' => '', 'phone' => '', 'address' => '', 'telegram' => '', 'instagram' => ''];
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SOON — O‘rnatish</title><style>body{margin:0;font:15px system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f4f6f8;color:#111827}.wrap{max-width:820px;margin:32px auto;padding:20px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:28px;box-shadow:0 18px 50px #0000000b}h1{margin:0}.steps{display:flex;gap:7px;margin:18px 0;flex-wrap:wrap}.steps b{padding:7px 11px;border-radius:999px;background:#eef2ff}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin-bottom:14px}.full{grid-column:1/-1}label{display:block;font-weight:650;margin-bottom:6px}input,textarea,select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d1d5db;border-radius:12px;font:inherit}textarea{min-height:100px}.err{padding:12px;background:#fef2f2;color:#991b1b;border-radius:12px;margin-bottom:15px}.ok{padding:18px;background:#ecfdf5;color:#065f46;border-radius:16px}button{border:0;border-radius:12px;padding:13px 18px;background:#111827;color:#fff;font-weight:700;cursor:pointer}@media(max-width:650px){.grid{grid-template-columns:1fr}.full{grid-column:auto}}</style></head><body><div class="wrap"><div class="card"><h1>SOON</h1><p>Bir martalik xavfsiz ishlab chiqarish o‘rnatishi</p><div class="steps"><b>1 Sayt</b><b>2 MySQL</b><b>3 Admin</b><b>4 Integratsiya</b></div><?php if($error):?><div class="err"><?=h($error)?></div><?php endif;?><?php if($success):?><div class="ok"><strong>O‘rnatish muvaffaqiyatli yakunlandi.</strong><p>Admin paneldan foydalanishingiz mumkin.</p></div><?php else:?><form method="post" autocomplete="off"><?=csrfField()?><?php if($step===1):?><div class="grid"><div class="field"><label>Sayt nomi</label><input name="site_name" value="<?=h($site['site_name'])?>" required></div><div class="field"><label>Sayt URL</label><input name="site_url" type="url" value="<?=h($site['site_url'])?>" required></div><div class="field"><label>Tagline</label><input name="tagline"></div><div class="field"><label>Asoschi</label><input name="founder"></div><div class="field"><label>Email</label><input name="email" type="email"></div><div class="field"><label>Telefon</label><input name="phone"></div><div class="field"><label>Telegram</label><input name="telegram"></div><div class="field"><label>Instagram</label><input name="instagram"></div><div class="field full"><label>Manzil</label><input name="address"></div><div class="field full"><label>Haqida</label><textarea name="about"></textarea></div></div><?php elseif($step===2):$db=$_SESSION['db']??['db_host'=>'localhost','db_name'=>'soon_uz','db_user'=>''];?><div class="grid"><div class="field"><label>MySQL host</label><input name="db_host" value="<?=h($db['db_host'])?>" required></div><div class="field"><label>Database nomi</label><input name="db_name" value="<?=h($db['db_name'])?>" required></div><div class="field"><label>MySQL foydalanuvchisi</label><input name="db_user" value="<?=h($db['db_user'])?>" required></div><div class="field"><label>MySQL paroli</label><input name="db_pass" type="password"></div></div><?php elseif($step===3):$a=$_SESSION['admin']??['username'=>'admin','name'=>'','email'=>''];?><div class="grid"><div class="field"><label>Username</label><input name="admin_username" value="<?=h($a['username'])?>" required></div><div class="field"><label>To‘liq ism</label><input name="admin_name" required></div><div class="field"><label>Email</label><input name="admin_email" type="email" required></div><div class="field"><label>Kuchli parol</label><input name="admin_password" type="password" required></div></div><p>Kamida 12 belgi: katta/kichik harf, raqam va maxsus belgi.</p><?php else:?><h3>Google OAuth — ixtiyoriy</h3><div class="grid"><div class="field"><label>Client ID</label><input name="google_client_id"></div><div class="field"><label>Client Secret</label><input name="google_client_secret" type="password"></div><div class="field full"><label>Redirect URI</label><input name="google_redirect_uri"></div></div><h3>SMTP — ixtiyoriy, sozlansa test email majburiy</h3><div class="grid"><div class="field"><label>SMTP host</label><input name="mail_host"></div><div class="field"><label>Port</label><input name="mail_port" value="587"></div><div class="field"><label>Username</label><input name="mail_username"></div><div class="field"><label>Parol</label><input name="mail_password" type="password"></div><div class="field"><label>Shifrlash</label><select name="mail_encryption"><option value="tls">TLS</option><option value="ssl">SSL</option></select></div><div class="field"><label>From email</label><input name="mail_from" type="email"></div><div class="field full"><label>Test email</label><input name="mail_test_email" type="email" placeholder="SMTP ishlashini tekshirish uchun"></div></div><h3>Telegram va AI — ixtiyoriy</h3><div class="grid"><div class="field"><label>Telegram Bot Token</label><input name="telegram_token" type="password"></div><div class="field"><label>Telegram Chat ID</label><input name="telegram_chat_id"></div><div class="field"><label>Gemini API Key</label><input name="gemini_key" type="password"></div><div class="field"><label>Groq API Key</label><input name="groq_key" type="password"></div><div class="field full"><label>OpenRouter API Key</label><input name="openrouter_key" type="password"></div></div><?php endif;?><button type="submit"><?=$step===4?'SOON’ni o‘rnatish':'Davom etish'?></button></form><?php endif;?></div></div></body></html>
