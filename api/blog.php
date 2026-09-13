<?php
/**
 * GET /api/blog - List published blog posts (paginated)
 * Rate limit: 120 requests/minute per IP
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$sql = "SELECT id, title, body, image, created_at 
        FROM blog_posts 
        WHERE status = 'published'
        ORDER BY id DESC";

$countSql = "SELECT COUNT(*) as total FROM blog_posts WHERE status = 'published'";

$result = getPaginatedResults($sql, [], $countSql);

$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

apiResponse([
    'success' => true,
    'data' => array_map(function($post) use ($baseUrl) {
        return [
            'id' => (int)$post['id'],
            'title' => $post['title'],
            'body' => $post['body'],
            'image' => $post['image'] ? $baseUrl . '/' . $post['image'] : null,
            'created_at' => $post['created_at']
        ];
    }, $result['data']),
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total']
]);
