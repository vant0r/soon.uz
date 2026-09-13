<?php
/**
 * User Profile Page - /user/profile.php
 * View and edit user profile
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user || $user['status'] === 'deleted') {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['status'] === 'blocked') {
    $error = 'Sizning hisobingiz bloklangan. Ma\'lumot uchun admin bilan bog\'laning.';
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    verifyCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $updateData = [];
    
    // Update name
    if (isset($_POST['name'])) {
        $name = trim($_POST['name']);
        if (strlen($name) < 2) {
            $errors['name'] = 'Ism juda qisqa (kamida 2 belgi).';
        } else {
            $updateData['name'] = $name;
        }
    }
    
    // Update phone
    if (isset($_POST['phone'])) {
        $phone = trim($_POST['phone']);
        if ($phone !== '' && !preg_match('/^\+998\d{9}$/', $phone)) {
            $errors['phone'] = 'Telefon raqam +998XXXXXXXXX formatida bo\'lishi kerak.';
        } else {
            $updateData['phone'] = $phone === '' ? null : $phone;
        }
    }
    
    if (empty($errors)) {
        if (!empty($updateData)) {
            dbUpdate('users', $updateData, 'id = ?', ['id' => $userId]);
            $success = 'Profil muvaffaqiyatli yangilandi';
            // Refresh user data
            $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$pageTitle = 'Mening Profilim';
?>
<!DOCTYPE html>
<html lang="uz" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - WebHub.uz</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="user-page">
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <main class="user-main">
        <div class="container">
            <div class="profile-card glass-card">
                <h1><?= e($pageTitle) ?></h1>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= e($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" class="profile-form">
                    <?= csrfField() ?>
                    
                    <div class="form-group">
                        <label for="google_id">Google ID</label>
                        <input type="text" id="google_id" value="<?= e($user['google_id']) ?>" disabled>
                        <small>Google orqali kirish</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Ism</label>
                        <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required minlength="2">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                        <small>Email Google hisobidan olinadi</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Telefon raqam</label>
                        <input type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+998XXXXXXXXX">
                        <small>Ixtiyoriy. Format: +998XXXXXXXXX</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Holat</label>
                        <div class="status-badge status-<?= e($user['status']) ?>">
                            <?= $user['status'] === 'active' ? 'Faol' : ($user['status'] === 'blocked' ? 'Bloklangan' : 'O\'chirilgan') ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ro\'yxatdan o\'tgan sana</label>
                        <p><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                </form>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>
