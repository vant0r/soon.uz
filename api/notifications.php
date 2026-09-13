<?php
/**
 * GET /api/notifications - List current user's notifications (paginated)
 * POST /api/notifications/read - Mark notifications as read
 * Rate limit: 60 requests/minute per token
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List user's notifications
    $sql = "SELECT id, title, message, is_read, created_at 
            FROM notifications 
            WHERE user_id = ?
            ORDER BY id DESC";
    
    $countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    
    $result = getPaginatedResults($sql, [$userId], $countSql, [$userId]);
    
    apiResponse([
        'success' => true,
        'data' => array_map(function($notif) {
            return [
                'id' => (int)$notif['id'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'is_read' => (bool)$notif['is_read'],
                'created_at' => $notif['created_at']
            ];
        }, $result['data']),
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total']
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark notifications as read
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        apiError('Noto\'g\'ri so\'rov formati.', 400);
    }
    
    // Accept either single ID or array of IDs
    $ids = [];
    if (isset($data['id'])) {
        $ids = [(int)$data['id']];
    } elseif (isset($data['ids']) && is_array($data['ids'])) {
        $ids = array_map('intval', $data['ids']);
    }
    
    if (empty($ids)) {
        apiError('Bildirisnoma ID talab qilinadi.', 400);
    }
    
    $pdo = getDbConnection();
    
    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Update only notifications belonging to this user
    $updateSql = "UPDATE notifications SET is_read = 1 
                  WHERE id IN ($placeholders) AND user_id = ?";
    
    $params = array_merge($ids, [$userId]);
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($params);
    
    $updatedCount = $stmt->rowCount();
    
    apiResponse([
        'success' => true,
        'data' => [
            'marked_count' => $updatedCount
        ],
        'message' => "$updatedCount ta bildirisnoma o'qilgan deb belgilandi"
    ]);
    
} else {
    apiError('Faqat GET va POST so\'rovlari qabul qilinadi.', 405);
}
