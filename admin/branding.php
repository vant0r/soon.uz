<?php
/**
 * SOON Admin — Branding & Media
 * All core visual identity assets are managed here instead of hard-coded in templates.
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();
startSecureSession();
$message = '';
$error = '';

$assetMap = [
    'brand_logo' => ['label' => 'Logo', 'accept' => 'image/png,image/jpeg,image/webp,image/svg+xml', 'max' => 5],
    'brand_banner' => ['label' => 'Hero / Banner', 'accept' => 'image/jpeg,image/png,image/webp', 'max' => 12],
    'brand_favicon' => ['label' => 'Favicon', 'accept' => 'image/png,image/x-icon,image/svg+xml', 'max' => 2],
    'brand_og_image' => ['label' => 'Social / OG image', 'accept' => 'image/jpeg,image/png,image/webp', 'max' => 8],
];

function brandingUpload($key, $definition) {
    if (empty($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$key];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fayl yuklashda xatolik yuz berdi.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Noto‘g‘ri upload so‘rovi.');
    }
    if ((int)$file['size'] > $definition['max'] * 1024 * 1024) {
        throw new RuntimeException($definition['label'] . ' hajmi ' . $definition['max'] . ' MB dan oshmasin.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/svg+xml' => 'svg',
    ];
    $mime = validateMimeType($file['tmp_name'], array_keys($allowed));
    if (!$mime || !isset($allowed[$mime])) {
        throw new RuntimeException($definition['label'] . ' uchun ruxsat etilmagan format.');
    }

    $dir = dirname(__DIR__) . '/uploads/branding';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Branding papkasini yaratib bo‘lmadi.');
    }

    $filename = $key . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Faylni saqlab bo‘lmadi.');
    }

    return 'uploads/branding/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto‘g‘ri.';
    } else {
        try {
            foreach ($assetMap as $key => $definition) {
                $path = brandingUpload($key, $definition);
                if ($path) {
                    updateSiteSetting($key, $path, 'string', 'branding');
                }
            }

            updateSiteSetting('site_title', sanitizeInput($_POST['site_title'] ?? 'SOON'), 'string', 'branding');
            updateSiteSetting('site_tagline', sanitizeInput($_POST['site_tagline'] ?? ''), 'string', 'branding');
            updateSiteSetting('brand_primary', sanitizeInput($_POST['brand_primary'] ?? '#2563EB'), 'string', 'branding');
            updateSiteSetting('brand_accent', sanitizeInput($_POST['brand_accent'] ?? '#111827'), 'string', 'branding');
            logAdminAction($admin['id'], 'branding_update', 'branding');
            $message = 'SOON branding sozlamalari saqlandi.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$settings = getAllSiteSettings();
?>
<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branding — SOON Admin</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body{background:var(--bg-secondary)}.wrap{max-width:1100px;margin:auto;padding:32px 20px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:28px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px}.card{background:var(--bg-primary);border:1px solid var(--border-color);border-radius:20px;padding:22px}.label{display:block;font-weight:700;margin-bottom:8px}.hint{color:var(--text-muted);font-size:.9rem;margin:0 0 14px}.input{width:100%;padding:12px 14px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-secondary);color:var(--text-primary)}.preview{height:150px;border:1px dashed var(--border-color);border-radius:14px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:14px}.preview img{max-width:90%;max-height:130px;object-fit:contain}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}.alert{padding:13px 16px;border-radius:12px;margin-bottom:18px}.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}@media(max-width:640px){.wrap{padding:20px 14px}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1>SOON Branding</h1><p class="hint">Logo, banner, favicon va boshqa asosiy vizual aktivlar.</p></div>
        <a class="btn btn-secondary" href="dashboard.php">← Admin panel</a>
    </div>

    <?php if ($message): ?><div class="alert ok"><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert bad"><?php echo e($error); ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
        <div class="grid">
            <?php foreach ($assetMap as $key => $definition): ?>
                <div class="card">
                    <span class="label"><?php echo e($definition['label']); ?></span>
                    <p class="hint">Max <?php echo (int)$definition['max']; ?> MB</p>
                    <div class="preview">
                        <?php if (!empty($settings[$key])): ?><img src="../<?php echo e($settings[$key]); ?>" alt="<?php echo e($definition['label']); ?>">
                        <?php else: ?><span class="hint">Hali yuklanmagan</span><?php endif; ?>
                    </div>
                    <input class="input" type="file" name="<?php echo e($key); ?>" accept="<?php echo e($definition['accept']); ?>">
                </div>
            <?php endforeach; ?>

            <div class="card"><span class="label">Sayt nomi</span><p class="hint">Brauzer title va umumiy branding.</p><input class="input" name="site_title" value="<?php echo e($settings['site_title'] ?? 'SOON'); ?>" maxlength="100"></div>
            <div class="card"><span class="label">Tagline</span><p class="hint">Brend ostida ko‘rinadigan qisqa jumla.</p><input class="input" name="site_tagline" value="<?php echo e($settings['site_tagline'] ?? ''); ?>" maxlength="180"></div>
            <div class="card"><span class="label">Primary color</span><input class="input" type="color" name="brand_primary" value="<?php echo e($settings['brand_primary'] ?? '#2563EB'); ?>"></div>
            <div class="card"><span class="label">Accent color</span><input class="input" type="color" name="brand_accent" value="<?php echo e($settings['brand_accent'] ?? '#111827'); ?>"></div>
        </div>
        <div class="actions"><button class="btn btn-primary" type="submit">Saqlash</button><a class="btn btn-secondary" href="settings.php">Umumiy sozlamalar</a></div>
    </form>
</div>
</body>
</html>
