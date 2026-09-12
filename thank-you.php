<?php
/**
 * Thank You Page - Shown after successful application submission
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();

$submitted = $_SESSION['application_submitted'] ?? false;
unset($_SESSION['application_submitted']);

if (!$submitted) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rahmat - WebHub.uz</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .thank-you-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .thank-you-card {
            max-width: 500px;
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, var(--success), #34D399);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        h1 {
            margin-bottom: 16px;
        }
        
        p {
            color: var(--text-secondary);
            margin-bottom: 32px;
        }
        
        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="thank-you-container">
        <div class="glass-card thank-you-card scroll-reveal">
            <div class="success-icon">✓</div>
            <h1>Muvaffaqiyatli!</h1>
            <p>Sizning arizangiz qabul qilindi. Tez orada operatorimiz siz bilan bog'lanadi va barcha tafsilotlarni muhokama qiladi.</p>
            
            <div class="actions">
                <a href="index.php" class="btn btn-primary">Bosh sahifaga qaytish</a>
                <?php if (isUserLoggedIn()): ?>
                <a href="user/dashboard.php" class="btn btn-secondary">Kabinetga o'tish</a>
                <?php else: ?>
                <a href="user/login.php" class="btn btn-secondary">Kirish</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
