<?php
/**
 * Custom 404 Error Page
 * WebHub.uz - Professional IT Services
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();
$pdo = getDbConnection();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sahifa topilmadi - 404 | WebHub.uz</title>
    <meta name="description" content="So'ralgan sahifa topilmadi">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="error-page">
    <div class="error-container glass-card">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/>
            </svg>
        </div>
        <h1 class="error-title">404</h1>
        <h2 class="error-subtitle">Sahifa topilmadi</h2>
        <p class="error-message">
            Kechirasiz, siz qidirayotgan sahifa mavjud emas yoki ko'chirilgan.
            Bosh sahifaga qaytib, qayta urinib ko'ring.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Bosh sahifa
            </a>
            <a href="/#contact" class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                Aloqa
            </a>
        </div>
        <div class="error-links">
            <a href="/#services">Xizmatlar</a>
            <a href="/#portfolio">Portfolio</a>
            <a href="/#blog">Blog</a>
            <?php if (isLoggedIn()): ?>
                <a href="/user/dashboard.php">Kabinet</a>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
        }
        
        .error-container {
            text-align: center;
            max-width: 500px;
            padding: 3rem 2rem;
            border-radius: 24px;
        }
        
        .error-icon {
            color: var(--primary);
            margin-bottom: 1.5rem;
            opacity: 0.8;
        }
        
        .error-title {
            font-size: 6rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .error-subtitle {
            font-size: 2rem;
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            color: var(--text-primary);
        }
        
        .error-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.7;
            margin: 1.5rem 0 2rem;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .error-links {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .error-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .error-links a:hover {
            color: var(--primary);
        }
        
        @media (max-width: 480px) {
            .error-title {
                font-size: 4rem;
            }
            
            .error-subtitle {
                font-size: 1.5rem;
            }
            
            .error-actions {
                flex-direction: column;
            }
            
            .error-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>
</html>
<?php
// Set 404 header
http_response_code(404);
?>
