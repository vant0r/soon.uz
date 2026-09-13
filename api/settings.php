<?php
require_once __DIR__ . '/config.php';

if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$pdo = getDbConnection();
$allowedKeys = [
    'site_name',
    'site_title',
    'site_description',
    'site_keywords',
    'site_url',
    'site_tagline',
    'founder_name',
    'contact_phone',
    'contact_email',
    'contact_telegram',
    'contact_instagram',
    'contact_address',
    'primary_color',
    'accent_color',
    'logo_path',
    'favicon_path',
    'hero_banner_path',
    'og_image_path',
    'theme_color',
    'stat_projects_override',
    'stat_clients_override',
    'max_upload_image_mb',
    'max_upload_doc_mb'
];
$placeholders = implode(',', array_fill(0, count($allowedKeys), '?'));
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders) AND is_public = 1");
$stmt->execute($allowedKeys);
$settings = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$stats = dbFetchOne("SELECT (SELECT COUNT(*) FROM portfolio WHERE status = 'active') AS projects, (SELECT COUNT(*) FROM users WHERE status = 'active') AS clients");
$settings['stat_projects'] = isset($settings['stat_projects_override']) && $settings['stat_projects_override'] !== '' ? (int)$settings['stat_projects_override'] : (int)$stats['projects'];
$settings['stat_clients'] = isset($settings['stat_clients_override']) && $settings['stat_clients_override'] !== '' ? (int)$settings['stat_clients_override'] : (int)$stats['clients'];
unset($settings['stat_projects_override'], $settings['stat_clients_override']);
apiResponse(['success' => true, 'data' => $settings]);
