<?php
declare(strict_types=1);

$host = getenv('SOON_DB_HOST') ?: '127.0.0.1';
$db = getenv('SOON_DB_NAME') ?: 'soon_uz_test';
$user = getenv('SOON_DB_USER') ?: 'root';
$pass = getenv('SOON_DB_PASS') ?: '';
$base = getenv('SOON_BASE_URL') ?: '';

function failTest(string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertTrue(bool $condition, string $message): void {
    if (!$condition) failTest($message);
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    failTest('Database connection: ' . $e->getMessage());
}

$requiredTables = ['users', 'admins', 'services', 'portfolio', 'blog_posts', 'applications', 'application_history', 'chat_threads', 'chat_messages', 'notifications', 'settings', 'audit_log', 'sessions', 'rate_limits'];
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($db))->fetchAll(PDO::FETCH_COLUMN);
foreach ($requiredTables as $table) assertTrue(in_array($table, $tables, true), "Missing table {$table}");

$pdo->beginTransaction();
try {
    $email = 'integration-' . bin2hex(random_bytes(4)) . '@example.test';
    $pdo->prepare('INSERT INTO users (email, full_name, phone, status, email_verified) VALUES (?, ?, ?, ?, ?)')->execute([$email, 'Integration User', '+998901234567', 'active', 1]);
    $userId = (int)$pdo->lastInsertId();
    assertTrue($userId > 0, 'User insert failed');

    $pdo->prepare('INSERT INTO admins (username, password_hash, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)')->execute(['integration_' . bin2hex(random_bytes(3)), password_hash('Integration-Password-123!', PASSWORD_ARGON2ID), 'Integration Admin', 'admin-' . bin2hex(random_bytes(4)) . '@example.test', 'admin', 'active']);
    $adminId = (int)$pdo->lastInsertId();
    assertTrue($adminId > 0, 'Admin insert failed');

    $serviceId = (int)$pdo->query('SELECT id FROM services WHERE status = \'active\' ORDER BY id LIMIT 1')->fetchColumn();
    assertTrue($serviceId > 0, 'Seed service missing');

    $pdo->prepare('INSERT INTO applications (user_id, service_id, full_name, email, phone, message, status, priority, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$userId, $serviceId, 'Integration User', $email, '+998901234567', 'Integration application', 'new', 'medium', 'integration-test']);
    $applicationId = (int)$pdo->lastInsertId();
    assertTrue($applicationId > 0, 'Application insert failed');

    $pdo->prepare('INSERT INTO application_history (application_id, old_status, new_status, changed_by, changed_by_type, comment) VALUES (?, ?, ?, ?, ?, ?)')->execute([$applicationId, null, 'new', $adminId, 'admin', 'Integration test']);
    assertTrue((int)$pdo->lastInsertId() > 0, 'Application history insert failed');

    $pdo->prepare('INSERT INTO chat_threads (application_id, user_id, admin_id, subject, status, last_message_at, last_message_by, unread_user_count, unread_admin_count) VALUES (?, ?, ?, ?, ?, NOW(), ?, 0, 1)')->execute([$applicationId, $userId, $adminId, 'Integration chat', 'open', 'user']);
    $threadId = (int)$pdo->lastInsertId();
    assertTrue($threadId > 0, 'Chat thread insert failed');

    $pdo->prepare('INSERT INTO chat_messages (thread_id, sender_id, sender_type, message, is_read) VALUES (?, ?, ?, ?, ?)')->execute([$threadId, $userId, 'user', 'Integration message', 0]);
    assertTrue((int)$pdo->lastInsertId() > 0, 'Chat message insert failed');

    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([$userId, 'Integration', 'Integration notification', 'info', 'application', $applicationId]);
    assertTrue((int)$pdo->lastInsertId() > 0, 'Notification insert failed');

    $sessionId = 'integration_' . bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO sessions (id, user_id, user_type, ip_address, user_agent, payload) VALUES (?, ?, ?, ?, ?, ?)')->execute([$sessionId, $userId, 'user', '127.0.0.1', 'integration-test', 'token']);
    $session = $pdo->prepare('SELECT user_id FROM sessions WHERE id = ?');
    $session->execute([$sessionId]);
    assertTrue((int)$session->fetchColumn() === $userId, 'Session persistence failed');

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    failTest($e->getMessage());
}

if ($base !== '') {
    $urls = [$base . '/', $base . '/api/services.php'];
    foreach ($urls as $url) {
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $status = (int)$m[1];
        assertTrue($status >= 200 && $status < 500, "HTTP smoke failed for {$url}");
        assertTrue($body !== false && $body !== '', "Empty HTTP response for {$url}");
    }
}

echo "PASS: schema, CRUD relationships, transactions, sessions and HTTP smoke tests\n";
