<?php
require_once __DIR__ . '/../includes/functions.php';
requireUser();

$userId = (int)$_SESSION['user_id'];
$user = dbFetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
if (!$user) {
    session_destroy();
    redirect('login.php');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Xavfsizlik tokeni noto‘g‘ri';
        $messageType = 'error';
    } elseif ($user['status'] !== 'active') {
        $message = 'Hisobingiz faol emas';
        $messageType = 'error';
    } else {
        $name = trim(sanitizeInput($_POST['full_name'] ?? ''));
        $phone = trim(sanitizeInput($_POST['phone'] ?? ''));
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 255) $errors[] = 'Ism 2–255 belgi oralig‘ida bo‘lishi kerak';
        if ($phone !== '' && !preg_match('/^\+998\d{9}$/', $phone)) $errors[] = 'Telefon raqami +998XXXXXXXXX formatida bo‘lishi kerak';
        if ($errors) {
            $message = implode('. ', $errors);
            $messageType = 'error';
        } else {
            dbUpdate('users', ['full_name' => $name, 'phone' => $phone !== '' ? $phone : null], 'id = :id', ['id' => $userId]);
            $user = dbFetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
            $message = 'Profil muvaffaqiyatli yangilandi';
            $messageType = 'success';
        }
    }
}

$csrf = generateCsrfToken();
$displayName = $user['full_name'] ?: 'Foydalanuvchi';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Profil — SOON</title>
<link rel="stylesheet" href="../assets/css/main.css">
<style>
body{min-height:100vh;background:var(--bg-secondary)}.profile-wrap{max-width:760px;margin:0 auto;padding:40px 20px}.profile-card{padding:32px}.profile-head{display:flex;align-items:center;gap:16px;margin-bottom:28px}.avatar{width:76px;height:76px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.7rem;font-weight:700}.profile-head h1{margin:0}.profile-head p{margin:5px 0 0;color:var(--text-muted)}.form-group{margin-bottom:18px}.form-group label{display:block;margin-bottom:7px;font-weight:600}.form-input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-secondary);color:var(--text-primary)}.form-input:disabled{opacity:.7}.hint{display:block;margin-top:6px;font-size:.82rem;color:var(--text-muted)}.actions{display:flex;gap:10px;flex-wrap:wrap}.alert{padding:12px 16px;border-radius:12px;margin-bottom:20px}.alert-success{background:#D1FAE5;color:#047857}.alert-error{background:#FEE2E2;color:#B91C1C}.status-badge{display:inline-flex;padding:6px 12px;border-radius:999px;background:#D1FAE5;color:#047857;font-size:.85rem}@media(max-width:600px){.profile-wrap{padding:20px 12px}.profile-card{padding:22px}.profile-head{align-items:flex-start}}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="profile-wrap"><section class="glass-card profile-card">
<div class="profile-head"><div class="avatar"><?php echo e(mb_strtoupper(mb_substr($displayName,0,1))); ?></div><div><h1>Mening profilim</h1><p><?php echo e($user['email']); ?></p></div></div>
<?php if($message): ?><div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div><?php endif; ?>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
<div class="form-group"><label for="full_name">To‘liq ism</label><input class="form-input" id="full_name" name="full_name" value="<?php echo e($user['full_name']); ?>" maxlength="255" required></div>
<div class="form-group"><label for="email">Email</label><input class="form-input" id="email" value="<?php echo e($user['email']); ?>" disabled><span class="hint">Email Google hisobingizdan olinadi.</span></div>
<div class="form-group"><label for="phone">Telefon raqam</label><input class="form-input" id="phone" name="phone" type="tel" value="<?php echo e($user['phone'] ?? ''); ?>" placeholder="+998XXXXXXXXX" maxlength="13"><span class="hint">Ixtiyoriy.</span></div>
<div class="form-group"><label>Hisob holati</label><span class="status-badge"><?php echo $user['status'] === 'active' ? 'Faol' : ($user['status'] === 'blocked' ? 'Bloklangan' : 'O‘chirilgan'); ?></span></div>
<div class="form-group"><label>Ro‘yxatdan o‘tgan sana</label><div><?php echo e(date('d.m.Y H:i', strtotime($user['created_at']))); ?></div></div>
<div class="actions"><button type="submit" class="btn btn-primary">Saqlash</button><a href="dashboard.php" class="btn btn-secondary">Profilga qaytish</a></div>
</form></section></main>
<?php include __DIR__ . '/footer.php'; ?>
<script src="../assets/js/main.js"></script>
</body>
</html>
