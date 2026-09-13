<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$fields = [
    'id',
    'title_uz',
    'title_ru',
    'title_en',
    'slug',
    'excerpt_uz',
    'excerpt_ru',
    'excerpt_en',
    'content_uz',
    'content_ru',
    'content_en',
    'featured_image',
    'category',
    'tags',
    'meta_title',
    'meta_description',
    'published_at',
    'views_count',
    'is_featured',
    'sort_order',
    'created_at',
    'updated_at'
];

$sql = 'SELECT ' . implode(', ', $fields) . " FROM blog_posts WHERE status = 'published' ORDER BY is_featured DESC, sort_order ASC, published_at DESC, id DESC";
$countSql = "SELECT COUNT(*) AS total FROM blog_posts WHERE status = 'published'";
$result = getPaginatedResults($sql, [], $countSql);

$baseUrl = rtrim(getSiteSetting('site_url', ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

foreach ($result['data'] as &$post) {
    $post['id'] = (int)$post['id'];
    $post['views_count'] = (int)$post['views_count'];
    $post['is_featured'] = (bool)$post['is_featured'];
    $post['sort_order'] = (int)$post['sort_order'];
    $post['tags'] = $post['tags'] ? (json_decode($post['tags'], true) ?: []) : [];
    $post['featured_image_url'] = $post['featured_image'] ? $baseUrl . '/' . ltrim($post['featured_image'], '/') : null;
    unset($post['featured_image']);
}
unset($post);

apiResponse([
    'success' => true,
    'data' => $result['data'],
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total']
]);
