<?php
/**
 * GET /api/settings - Get public site settings
 * Rate limit: 120 requests/minute per IP
 * Never returns OAuth secrets or sensitive data
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(120, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$pdo = getDbConnection();

// Get all public settings
$allowedKeys = [
    'contact_phone',
    'contact_telegram',
    'contact_instagram',
    'seo_meta_description',
    'seo_keywords',
    'theme_color_primary',
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

// Compute live stats if no override
if (!isset($settings['stat_projects_override']) || $settings['stat_projects_override'] === '') {
    $stats = dbFetchOne("SELECT 
        (SELECT COUNT(*) FROM portfolio) as projects,
        (SELECT COUNT(*) FROM users WHERE status = 'active') as clients
    ");
    $settings['stat_projects'] = (int)$stats['projects'];
    $settings['stat_clients'] = (int)$stats['clients'];
} else {
    $settings['stat_projects'] = (int)$settings['stat_projects_override'];
    $settings['stat_clients'] = (int)$settings['stat_clients_override'];
}

// Remove override keys from response (only return final values)
unset($settings['stat_projects_override']);
unset($settings['stat_clients_override']);

apiResponse([
    'success' => true,
    'data' => $settings
]);
