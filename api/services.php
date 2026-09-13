<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$sql = "SELECT id, title_uz, title_ru, title_en, description_uz, description_ru, description_en,
               icon, price_from, price_to, duration_days, is_popular, sort_order, status,
               created_at, updated_at
        FROM services
        WHERE status = 'active'
        ORDER BY is_popular DESC, sort_order ASC, id DESC";
$countSql = "SELECT COUNT(*) AS total FROM services WHERE status = 'active'";
$result = getPaginatedResults($sql, [], $countSql);

foreach ($result['data'] as &$service) {
    $service['id'] = (int)$service['id'];
    $service['price_from'] = $service['price_from'] !== null ? (float)$service['price_from'] : null;
    $service['price_to'] = $service['price_to'] !== null ? (float)$service['price_to'] : null;
    $service['duration_days'] = $service['duration_days'] !== null ? (int)$service['duration_days'] : null;
    $service['is_popular'] = (bool)$service['is_popular'];
    $service['sort_order'] = (int)$service['sort_order'];
}
unset($service);

apiResponse([
    'success' => true,
    'data' => $result['data'],
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total']
]);
