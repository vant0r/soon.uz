<?php
/**
 * GET /api/portfolio - List portfolio projects (paginated)
 * Rate limit: 120 requests/minute per IP
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$sql = "SELECT id, title, description, image, client_name, link, category, sort_order, created_at 
        FROM portfolio 
        ORDER BY sort_order ASC, id DESC";

$countSql = "SELECT COUNT(*) as total FROM portfolio";

$result = getPaginatedResults($sql, [], $countSql);

$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

apiResponse([
    'success' => true,
    'data' => array_map(function($project) use ($baseUrl) {
        return [
            'id' => (int)$project['id'],
            'title' => $project['title'],
            'description' => $project['description'],
            'image' => $project['image'] ? $baseUrl . '/' . $project['image'] : null,
            'client_name' => $project['client_name'],
            'link' => $project['link'],
            'category' => $project['category'],
            'sort_order' => (int)$project['sort_order'],
            'created_at' => $project['created_at']
        ];
    }, $result['data']),
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total']
]);
