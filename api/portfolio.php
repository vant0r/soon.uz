<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$sql = "SELECT id, title_uz, title_ru, title_en, description_uz, description_ru, description_en,
               image_path, thumbnail_path, project_url, github_url, category, technologies,
               client_name, completed_date, is_featured, sort_order, status, views_count,
               created_at, updated_at
        FROM portfolio
        WHERE status = 'published'
        ORDER BY is_featured DESC, sort_order ASC, completed_date DESC, id DESC";
$countSql = "SELECT COUNT(*) AS total FROM portfolio WHERE status = 'published'";
$result = getPaginatedResults($sql, [], $countSql);

$baseUrl = rtrim(getSiteSetting('site_url', ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

foreach ($result['data'] as &$project) {
    $project['id'] = (int)$project['id'];
    $project['is_featured'] = (bool)$project['is_featured'];
    $project['sort_order'] = (int)$project['sort_order'];
    $project['views_count'] = (int)$project['views_count'];
    $project['technologies'] = $project['technologies'] ? (json_decode($project['technologies'], true) ?: []) : [];
    $project['image_url'] = $project['image_path'] ? $baseUrl . '/' . ltrim($project['image_path'], '/') : null;
    $project['thumbnail_url'] = $project['thumbnail_path'] ? $baseUrl . '/' . ltrim($project['thumbnail_path'], '/') : null;
    unset($project['image_path'], $project['thumbnail_path']);
}
unset($project);

apiResponse([
    'success' => true,
    'data' => $result['data'],
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total']
]);
