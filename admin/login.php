<?php
/**
 * Admin Login Page
 * Separate from user OAuth authentication.
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

if (isAdminLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Sessiya xavfsizlik tokeni yaroqsiz. Sahifani yangilang.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = getClientIp();

        if (!checkBruteForceLockout($ip)) {
            $error = 'Juda ko\'p urinishlar. Iltimos keyinroq qayta urinib ko\'ring.';
        } elseif (empty($login) || empty($password)) {
            $error = 'Login va parolni kiriting';
            recordFailedLogin($ip);
        } else {
            $admin = dbFetchOne("SELECT * FROM admins WHERE login = :login", ['login' => $login]);

            if ($admin && verifyPassword($password, $admin['password_hash'])) {
                clearBruteForceRecord($ip);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_login_time'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                logAdminAction($admin['id'], 'Login', 'admin', $admin['id']);
                redirect('dashboard.php');
            }

            $error = 'Login yoki parol noto\'g\'ri';
            recordFailedLogin($ip);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin kirish — Soon</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .login-container { max-width:400px; width:100%; }
        .login-card { padding:40px; }
        .logo { text-align:center; margin-bottom:32px; font-size:2rem; font-weight:700; color:var(--primary); }
        h1 { text-align:center; margin-bottom:8px; }
        .subtitle { text-align:center; color:var(--text-muted); margin-bottom:32px; }
        .error-message { background:#FEE2E2; border:1px solid #FCA5A5; color:#DC2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="glass-card login-card">
            <div class="logo">Soon Admin</div>
            <h1>Xush kelibsiz</h1>
            <p class="subtitle">Boshqaruv paneliga xavfsiz kirish</p>

            <?php if ($error): ?>
                <div class="error-message"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <div style="margin-bottom:20px;">
                    <label for="login">Login</label>
                    <input type="text" id="login" name="login" required autofocus autocomplete="username">
                </div>
                <div style="margin-bottom:20px;">
                    <label for="password">Parol</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Kirish</button>
            </form>

            <p style="text-align:center; margin-top:24px; color:var(--text-muted); font-size:.85rem;">
                <a href="../index.php">← Bosh sahifaga qaytish</a>
            </p>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
