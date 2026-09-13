<?php
/**
 * Admin Dashboard
 * Main admin panel overview
 */

require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$admin = getCurrentAdmin();

// Get stats
$totalUsers = getStatCount('users');
$totalApplications = getStatCount('applications');
$newApplications = dbFetchOne("SELECT COUNT(*) as count FROM applications WHERE status = 'new'")['count'];
$totalPortfolio = getStatCount('portfolio');
$totalBlog = dbFetchOne("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'")['count'];

// Recent applications
$recentApplications = dbFetchAll("
    SELECT a.*, u.name as user_name, u.phone as user_phone, s.title as service_title
    FROM applications a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN services s ON a.service_id = s.id
    ORDER BY a.created_at DESC
    LIMIT 10
");

// Quick stats for dashboard cards
$statusCounts = dbFetchAll("
    SELECT status, COUNT(*) as count 
    FROM applications 
    GROUP BY status
");
$statusMap = [];
foreach ($statusCounts as $row) {
    $statusMap[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WebHub Admin</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { background: var(--bg-secondary); }
        
        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        
        @media (max-width: 1024px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .sidebar {
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            padding: 24px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 32px;
            display: block;
            text-decoration: none;
        }
        
        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .nav-link.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }
        
        .main-content {
            padding: 32px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            padding: 24px;
            border-radius: 16px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 4px;
        }
        
        .card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        tr:hover {
            background: var(--bg-secondary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-new { background: #DBEAFE; color: #1D4ED8; }
        .status-in_review { background: #FEF3C7; color: #B45309; }
        .status-approved { background: #D1FAE5; color: #047857; }
        .status-completed { background: #E0E7FF; color: #4338CA; }
        .status-cancelled { background: #FEE2E2; color: #B91C1C; }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="dashboard.php" class="logo">WebHub Admin</a>
            
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link active">📊 Dashboard</a>
                <a href="services.php" class="nav-link">🛠 Xizmatlar</a>
                <a href="portfolio.php" class="nav-link">📁 Portfolio</a>
                <a href="blog.php" class="nav-link">📝 Blog</a>
                <a href="applications.php" class="nav-link">📋 Arizalar</a>
                <a href="users.php" class="nav-link">👥 Foydalanuvchilar</a>
                <a href="chat.php" class="nav-link">💬 Chat</a>
                <a href="settings.php" class="nav-link">⚙ Sozlamalar</a>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
                <a href="../index.php" target="_blank" class="nav-link">🌐 Saytni ko'rish</a>
                <a href="logout.php" class="nav-link" style="color: var(--error);">🚪 Chiqish</a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1 style="margin-bottom: 4px;">Dashboard</h1>
                    <p style="color: var(--text-muted);">Xush kelibsiz, <?php echo e($admin['name']); ?>!</p>
                </div>
                <button data-theme-toggle class="btn btn-secondary">🌓 Mavzu</button>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Foydalanuvchilar</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalApplications; ?></div>
                    <div class="stat-label">Jami arizalar</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--warning);"><?php echo $newApplications; ?></div>
                    <div class="stat-label">Yangi arizalar</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalPortfolio; ?></div>
                    <div class="stat-label">Portfolio loyihalar</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalBlog; ?></div>
                    <div class="stat-label">Blog postlar</div>
                </div>
            </div>
            
            <!-- Recent Applications -->
            <div class="card">
                <div class="card-header">
                    <h3>Oxirgi arizalar</h3>
                    <a href="applications.php" class="btn btn-secondary btn-sm">Barchasini ko'rish →</a>
                </div>
                
                <?php if (empty($recentApplications)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px 0;">
                    Hozircha arizalar yo'q
                </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Foydalanuvchi</th>
                                <th>Xizmat</th>
                                <th>Holat</th>
                                <th>Sana</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApplications as $app): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(mb_substr($app['user_name'] ?? 'A', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo e($app['user_name'] ?? 'Noma\'lum'); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($app['user_phone'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo e($app['service_title'] ?? $app['service_name_snapshot']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $app['status']; ?>">
                                        <?php
                                        $statusLabels = [
                                            'new' => 'Yangi',
                                            'in_review' => 'Ko\'rib chiqilmoqda',
                                            'approved' => 'Tasdiqlandi',
                                            'completed' => 'Yakunlandi',
                                            'cancelled' => 'Bekor qilindi'
                                        ];
                                        echo $statusLabels[$app['status']] ?? $app['status'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
