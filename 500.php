<?php
/**
 * Custom 500 Error Page
 * WebHub.uz - Professional IT Services
 */

require_once __DIR__ . '/includes/functions.php';
startSecureSession();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xatolik yuz berdi - 500 | WebHub.uz</title>
    <meta name="description" content="Serverda xatolik yuz berdi">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="error-page">
    <div class="error-container glass-card">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/>
            </svg>
        </div>
        <h1 class="error-title">500</h1>
        <h2 class="error-subtitle">Serverda xatolik</h2>
        <p class="error-message">
            Kechirasiz, serverda kutilmagan xatolik yuz berdi.
            Birozdan so'ng qayta urinib ko'ring yoki adminstratorga murojaat qiling.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Bosh sahifa
            </a>
            <a href="mailto:info@webhub.uz" class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
                Administratorga yozish
            </a>
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
            color: var(--error);
            margin-bottom: 1.5rem;
            opacity: 0.8;
        }
        
        .error-title {
            font-size: 6rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--error) 0%, #dc2626 100%);
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
http_response_code(500);
?>
