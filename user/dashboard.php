<?php
/**
 * User Dashboard - Personal Cabinet
 */

require_once __DIR__ . '/includes/functions.php';
requireUser();

$user = getCurrentUser();

// Get user's applications
$applications = dbFetchAll(
    "SELECT a.*, s.title as service_title, s.price as service_price
     FROM applications a
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.user_id = :user_id
     ORDER BY a.created_at DESC",
    ['user_id' => $user['id']]
);

// Get unread notifications count
$unreadNotifications = dbFetchOne(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = FALSE",
    ['user_id' => $user['id']]
)['count'];

// Get recent notifications
$notifications = dbFetchAll(
    "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5",
    ['user_id' => $user['id']]
);

// Get chat thread
$chatThread = getOrCreateChatThread($user['id']);
$unreadMessages = dbFetchOne(
    "SELECT COUNT(*) as count FROM chat_messages WHERE thread_id = :thread_id AND sender_type = 'admin' AND is_read = FALSE",
    ['thread_id' => $chatThread['id']]
)['count'];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kabinet - WebHub</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { background: var(--bg-secondary); }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        @media (max-width: 1024px) { .dashboard-layout { grid-template-columns: 1fr; } }
        .sidebar { background: var(--bg-primary); border-right: 1px solid var(--border-color); padding: 24px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: 32px; display: block; text-decoration: none; }
        .nav-menu { display: flex; flex-direction: column; gap: 8px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: var(--text-secondary); text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: var(--bg-tertiary); color: var(--text-primary); }
        .nav-link.active { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        .main-content { padding: 32px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: var(--primary); }
        .card { background: var(--bg-primary); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.85rem; }
        tr:hover { background: var(--bg-secondary); }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-new { background: #DBEAFE; color: #1D4ED8; }
        .status-in_review { background: #FEF3C7; color: #B45309; }
        .status-approved { background: #D1FAE5; color: #047857; }
        .status-completed { background: #E0E7FF; color: #4338CA; }
        .status-cancelled { background: #FEE2E2; color: #B91C1C; }
        .badge { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
        .badge-red { background: #EF4444; color: white; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-size: 16px; border: none; border-radius: 12px; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: transparent; color: var(--text-primary); border: 1px solid var(--border-color); }
        .notification-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .notification-item:last-child { border-bottom: none; }
        .notification-unread { background: rgba(59, 130, 246, 0.05); margin: 0 -24px; padding: 12px 24px; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <a href="dashboard.php" class="logo">WebHub</a>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link active">📊 Mening arizalarim</a>
                <a href="chat.php" class="nav-link">
                    💬 Chat
                    <?php if ($unreadMessages > 0): ?>
                    <span class="badge badge-red"><?php echo $unreadMessages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" class="nav-link">👤 Profil</a>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
                <a href="../index.php" class="nav-link">🌐 Saytga qaytish</a>
                <a href="logout.php" class="nav-link" style="color: var(--error);">🚪 Chiqish</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="header">
                <h1>Mening kabinetim</h1>
                <div class="user-info">
                    <?php if ($user['avatar']): ?>
                    <img src="<?php echo e($user['avatar']); ?>" alt="<?php echo e($user['name']); ?>" class="avatar">
                    <?php else: ?>
                    <div class="avatar" style="display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                        <?php echo strtoupper(mb_substr($user['name'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-weight: 500;"><?php echo e($user['name']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e($user['email'] ?? $user['phone'] ?? ''); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
            <div class="card">
                <h3 style="margin-bottom: 16px;">Oxirgi bildirishnomalar</h3>
                <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo !$notif['is_read'] ? 'notification-unread' : ''; ?>">
                    <div style="flex: 1;">
                        <strong><?php echo e($notif['title']); ?></strong>
                        <p style="color: var(--text-secondary); margin: 4px 0 0;"><?php echo e($notif['message']); ?></p>
                        <small style="color: var(--text-muted);"><?php echo formatDateTimeUz($notif['created_at']); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Applications -->
            <div class="card">
                <div class="card-header">
                    <h3>Mening arizalarim</h3>
                    <a href="../index.php#contact" class="btn btn-primary">+ Yangi ariza</a>
                </div>
                
                <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <p style="color: var(--text-muted); margin-bottom: 20px;">Sizda hali arizalar yo'q</p>
                    <a href="../index.php#contact" class="btn btn-primary">Ariza yuborish</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Xizmat</th>
                                <th>Holat</th>
                                <th>Sana</th>
                                <th>Amal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($app['service_title'] ?? $app['service_name_snapshot']); ?></strong>
                                </td>
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
                                <td><?php echo date('d.m.Y', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <a href="application-view.php?id=<?php echo $app['id']; ?>" class="btn btn-secondary btn-sm">Ko'rish</a>
                                </td>
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
