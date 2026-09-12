<?php
/**
 * GET /api/user/applications - List current user's applications (paginated)
 * POST /api/user/applications - Submit new application
 * Rate limit: 60 requests/minute per token
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List user's applications
    $sql = "SELECT a.id, a.service_id, a.service_name_snapshot, a.customization_json, 
                   a.description, a.status, a.created_at,
                   s.title as service_title, s.price as service_price
            FROM applications a
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.user_id = ?
            ORDER BY a.id DESC";
    
    $countSql = "SELECT COUNT(*) as total FROM applications WHERE user_id = ?";
    
    $result = getPaginatedResults($sql, [$userId], $countSql, [$userId]);
    
    apiResponse([
        'success' => true,
        'data' => array_map(function($app) {
            return [
                'id' => (int)$app['id'],
                'service_id' => $app['service_id'] ? (int)$app['service_id'] : null,
                'service_name' => $app['service_name_snapshot'] ?? $app['service_title'],
                'service_price' => $app['service_price'] ? (int)$app['service_price'] : null,
                'customization' => $app['customization_json'] ? json_decode($app['customization_json'], true) : null,
                'description' => $app['description'],
                'status' => $app['status'],
                'created_at' => $app['created_at']
            ];
        }, $result['data']),
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total']
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submit new application
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        apiError('Noto\'g\'ri so\'rov formati.', 400);
    }
    
    $errors = [];
    
    // Validate service_id or service_name
    $serviceId = isset($data['service_id']) ? (int)$data['service_id'] : null;
    $serviceName = trim($data['service_name'] ?? '');
    
    if ($serviceId === null && $serviceName === '') {
        $errors['service_id'] = 'Xizmat ID yoki nomi talab qilinadi.';
    }
    
    // Get service name snapshot
    $serviceNameSnapshot = $serviceName;
    if ($serviceId !== null) {
        $service = dbFetchOne("SELECT title FROM services WHERE id = ?", [$serviceId]);
        if ($service) {
            $serviceNameSnapshot = $service['title'];
        } elseif ($serviceName === '') {
            $errors['service_id'] = 'Xizmat topilmadi.';
        }
    }
    
    // Validate description
    $description = trim($data['description'] ?? '');
    if ($description === '') {
        $errors['description'] = 'Tavsif talab qilinadi.';
    }
    
    // Get customization if provided
    $customizationJson = null;
    if (isset($data['customization']) && is_array($data['customization'])) {
        $customizationJson = json_encode($data['customization'], JSON_UNESCAPED_UNICODE);
    }
    
    if (!empty($errors)) {
        apiError('Validatsiya xatosi.', 422, $errors);
    }
    
    $pdo = getDbConnection();
    dbBeginTransaction();
    
    try {
        // Insert application
        $appId = dbInsert('applications', [
            'user_id' => $userId,
            'service_id' => $serviceId,
            'service_name_snapshot' => $serviceNameSnapshot,
            'customization_json' => $customizationJson,
            'description' => $description,
            'status' => 'new',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create notification for admin
        $adminCount = dbFetchOne("SELECT COUNT(*) as count FROM admins")['count'];
        if ($adminCount > 0) {
            // Send to first admin (or broadcast logic could be added)
            dbInsert('notifications', [
                'user_id' => $userId,
                'title' => 'Yangi ariza qabul qilindi',
                'message' => 'Sizning "' . e($serviceNameSnapshot) . '" xizmati bo\'yicha arizangiz qabul qilindi.',
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        dbCommit();
        
        apiResponse([
            'success' => true,
            'data' => [
                'id' => (int)$appId,
                'service_id' => $serviceId,
                'service_name' => $serviceNameSnapshot,
                'customization' => $customizationJson ? json_decode($customizationJson, true) : null,
                'description' => $description,
                'status' => 'new',
                'created_at' => date('Y-m-d H:i:s')
            ],
            'message' => 'Ariza muvaffaqiyatli yuborildi'
        ], 201);
        
    } catch (Exception $e) {
        dbRollback();
        error_log("Application submission error: " . $e->getMessage());
        apiError('Ariza yuborishda xatolik yuz berdi.', 500);
    }
    
} else {
    apiError('Faqat GET va POST so\'rovlari qabul qilinadi.', 405);
}
