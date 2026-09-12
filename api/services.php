<?php
/**
 * GET /api/services - List all services (paginated)
 * Rate limit: 120 requests/minute per IP
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$sql = "SELECT id, title, description, price, addons_json, features_json, sort_order, created_at 
        FROM services 
        ORDER BY sort_order ASC, id DESC";

$countSql = "SELECT COUNT(*) as total FROM services";

$result = getPaginatedResults($sql, [], $countSql);

apiResponse([
    'success' => true,
    'data' => array_map(function($service) {
        return [
            'id' => (int)$service['id'],
            'title' => $service['title'],
            'description' => $service['description'],
            'price' => (int)$service['price'],
            'addons' => $service['addons_json'] ? json_decode($service['addons_json'], true) : null,
            'features' => $service['features_json'] ? json_decode($service['features_json'], true) : null,
            'sort_order' => (int)$service['sort_order'],
            'created_at' => $service['created_at']
        ];
    }, $result['data']),
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total']
]);
