<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$pdo = getDbConnection();
$allowedKeys = [
    'site_name',
    'site_tagline',
    'contact_phone',
    'contact_telegram',
    'contact_instagram',
    'contact_email',
    'address',
    'seo_meta_description',
    'seo_keywords',
    'primary_color',
    'accent_color',
    'logo_path',
    'favicon_path',
    'hero_banner_path',
    'og_image_path',
    'stat_projects_override',
    'stat_clients_override',
    'max_upload_image_mb',
    'max_upload_doc_mb'
];

$placeholders = implode(',', array_fill(0, count($allowedKeys), '?'));
$stmt = $pdo->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ($placeholders)");
$stmt->execute($allowedKeys);
$rows = $stmt->fetchAll();
$settings = [];
foreach ($rows as $row) {
    $settings[$row['key']] = $row['value'];
}

$stats = dbFetchOne("SELECT (SELECT COUNT(*) FROM portfolio WHERE status = 'published') AS projects, (SELECT COUNT(*) FROM users WHERE status = 'active') AS clients");
$settings['stat_projects'] = $settings['stat_projects_override'] !== '' && isset($settings['stat_projects_override']) ? (int)$settings['stat_projects_override'] : (int)$stats['projects'];
$settings['stat_clients'] = $settings['stat_clients_override'] !== '' && isset($settings['stat_clients_override']) ? (int)$settings['stat_clients_override'] : (int)$stats['clients'];
unset($settings['stat_projects_override'], $settings['stat_clients_override']);

apiResponse(['success' => true, 'data' => $settings]);
