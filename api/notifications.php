<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT id, title, message, type, related_type, related_id, is_read, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC";
    $countSql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?";
    $result = getPaginatedResults($sql, [$userId], $countSql, [$userId]);
    apiResponse([
        'success' => true,
        'data' => array_map(static function ($notif) {
            return [
                'id' => (int)$notif['id'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'type' => $notif['type'],
                'related_type' => $notif['related_type'],
                'related_id' => $notif['related_id'] !== null ? (int)$notif['related_id'] : null,
                'is_read' => (bool)$notif['is_read'],
                'read_at' => $notif['read_at'],
                'created_at' => $notif['created_at']
            ];
        }, $result['data']),
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total']
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        apiError('Noto\'g\'ri so\'rov formati.', 400);
    }
    $ids = [];
    if (isset($data['id']) && filter_var($data['id'], FILTER_VALIDATE_INT) !== false) {
        $ids[] = (int)$data['id'];
    } elseif (isset($data['ids']) && is_array($data['ids'])) {
        foreach ($data['ids'] as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT) !== false && (int)$id > 0) {
                $ids[] = (int)$id;
            }
        }
    }
    $ids = array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    if (!$ids || count($ids) > 100) {
        apiError('Bildirishnoma IDlari noto\'g\'ri.', 400);
    }
    $pdo = getDbConnection();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id IN ($placeholders) AND user_id = ?");
    $stmt->execute(array_merge($ids, [$userId]));
    apiResponse(['success' => true, 'data' => ['marked_count' => $stmt->rowCount()], 'message' => 'Bildirishnomalar o\'qilgan deb belgilandi']);
}

apiError('Faqat GET va POST so\'rovlari qabul qilinadi.', 405);
