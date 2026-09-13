<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto\'g\'ri.';
        $messageType = 'error';
    } else {
        $fields = [
            'site_name' => trim($_POST['site_name'] ?? 'SOON'),
            'site_title' => trim($_POST['site_title'] ?? 'SOON — Raqamli yechimlar'),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'contact_telegram' => trim($_POST['contact_telegram'] ?? ''),
            'contact_instagram' => trim($_POST['contact_instagram'] ?? ''),
            'contact_address' => trim($_POST['contact_address'] ?? ''),
            'theme_color' => trim($_POST['theme_color'] ?? '#2563eb'),
        ];
        foreach ($fields as $key => $value) {
            updateSiteSetting($key, $value);
        }
        updateSiteSetting('stat_projects_override', $_POST['stat_projects_override'] !== '' ? (int)$_POST['stat_projects_override'] : null);
        updateSiteSetting('stat_clients_override', $_POST['stat_clients_override'] !== '' ? (int)$_POST['stat_clients_override'] : null);
        logAdminAction($admin['id'], 'settings_update', 'settings', null);
        $message = 'Sozlamalar muvaffaqiyatli saqlandi.';
        $messageType = 'success';
    }
}

$settings = getAllSiteSettings();
$csrf = generateCsrfToken();
function settingValue(array $settings, string $key, string $default = ''): string {
    return htmlspecialchars((string)($settings[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sozlamalar — SOON Admin</title>
<link rel="stylesheet" href="../assets/css/main.css">
<style>
body{background:var(--bg-secondary)}.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.sidebar{padding:24px;background:var(--bg-primary);border-right:1px solid var(--border-color);position:sticky;top:0;height:100vh}.logo{display:block;font-size:1.5rem;font-weight:800;color:var(--primary);text-decoration:none;margin-bottom:28px}.nav-menu{display:flex;flex-direction:column;gap:6px}.nav-link{padding:11px 14px;border-radius:12px;text-decoration:none;color:var(--text-secondary)}.nav-link:hover,.nav-link.active{background:var(--bg-tertiary);color:var(--text-primary)}.main-content{padding:32px;max-width:1100px;width:100%}.header{margin-bottom:24px}.card{background:var(--bg-primary);border:1px solid var(--border-color);border-radius:18px;padding:24px;margin-bottom:20px}.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.form-group{margin-bottom:16px}.form-label{display:block;margin-bottom:7px;font-weight:600}.form-input,.form-textarea{box-sizing:border-box;width:100%;padding:11px 13px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-secondary);color:var(--text-primary)}.form-textarea{min-height:100px;resize:vertical}.btn{display:inline-block;border:0;border-radius:10px;padding:11px 18px;cursor:pointer}.btn-primary{background:var(--primary);color:#fff}.alert{padding:12px 15px;border-radius:10px;margin-bottom:18px}.alert-success{background:#dcfce7;color:#166534}.alert-error{background:#fee2e2;color:#991b1b}@media(max-width:800px){.admin-layout{grid-template-columns:1fr}.sidebar{position:static;height:auto}.main-content{padding:20px}}
</style>
</head>
<body>
<div class="admin-layout">
<aside class="sidebar">
<a class="logo" href="dashboard.php">SOON Admin</a>
<nav class="nav-menu">
<a href="dashboard.php" class="nav-link">📊 Dashboard</a><a href="services.php" class="nav-link">🛠 Xizmatlar</a><a href="portfolio.php" class="nav-link">📁 Portfolio</a><a href="blog.php" class="nav-link">📝 Blog</a><a href="applications.php" class="nav-link">📋 Arizalar</a><a href="users.php" class="nav-link">👥 Foydalanuvchilar</a><a href="chat.php" class="nav-link">💬 Chat</a><a href="branding.php" class="nav-link">🎨 Branding</a><a href="settings.php" class="nav-link active">⚙ Sozlamalar</a>
</nav>
</aside>
<main class="main-content">
<div class="header"><h1>Sayt sozlamalari</h1><p>SOON platformasining umumiy ma'lumotlari, aloqa va SEO sozlamalari.</p></div>
<?php if ($message): ?><div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<section class="card"><h2>Asosiy ma'lumotlar</h2><div class="form-row">
<div class="form-group"><label class="form-label">Sayt nomi</label><input class="form-input" name="site_name" value="<?= settingValue($settings,'site_name','SOON') ?>" required></div>
<div class="form-group"><label class="form-label">SEO title</label><input class="form-input" name="site_title" value="<?= settingValue($settings,'site_title','SOON — Raqamli yechimlar') ?>"></div>
</div><div class="form-group"><label class="form-label">Meta description</label><textarea class="form-textarea" name="site_description"><?= settingValue($settings,'site_description') ?></textarea></div></section>
<section class="card"><h2>Aloqa</h2><div class="form-row">
<div class="form-group"><label class="form-label">Telefon</label><input class="form-input" name="contact_phone" value="<?= settingValue($settings,'contact_phone') ?>"></div>
<div class="form-group"><label class="form-label">Email</label><input type="email" class="form-input" name="contact_email" value="<?= settingValue($settings,'contact_email') ?>"></div>
<div class="form-group"><label class="form-label">Telegram</label><input class="form-input" name="contact_telegram" value="<?= settingValue($settings,'contact_telegram') ?>"></div>
<div class="form-group"><label class="form-label">Instagram</label><input class="form-input" name="contact_instagram" value="<?= settingValue($settings,'contact_instagram') ?>"></div>
</div><div class="form-group"><label class="form-label">Manzil</label><input class="form-input" name="contact_address" value="<?= settingValue($settings,'contact_address') ?>"></div></section>
<section class="card"><h2>Statistika</h2><div class="form-row">
<div class="form-group"><label class="form-label">Loyihalar override</label><input type="number" min="0" class="form-input" name="stat_projects_override" value="<?= settingValue($settings,'stat_projects_override') ?>"></div>
<div class="form-group"><label class="form-label">Mijozlar override</label><input type="number" min="0" class="form-input" name="stat_clients_override" value="<?= settingValue($settings,'stat_clients_override') ?>"></div>
<div class="form-group"><label class="form-label">Asosiy rang</label><input type="text" class="form-input" name="theme_color" value="<?= settingValue($settings,'theme_color','#2563eb') ?>" pattern="^#[0-9A-Fa-f]{6}$"></div>
</div></section>
<button class="btn btn-primary" type="submit">Saqlash</button>
</form>
</main></div></body></html>
