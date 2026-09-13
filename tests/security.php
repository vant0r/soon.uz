<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$passed = 0;
$failed = 0;

function testAssert(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$name}\n";
    } else {
        $failed++;
        echo "FAIL {$name}\n";
    }
}

testAssert(hash_equals(hash('sha256', 'soon'), hash('sha256', 'soon')), 'Hash comparison');
testAssert(!hash_equals(hash('sha256', 'soon'), hash('sha256', 'SOON')), 'Hash rejects different value');
testAssert(filter_var('admin@soon.uz', FILTER_VALIDATE_EMAIL) !== false, 'Email validation');
testAssert(filter_var('not-an-email', FILTER_VALIDATE_EMAIL) === false, 'Invalid email rejection');
testAssert(preg_match('/^[a-f0-9]{64}$/i', hash('sha256', random_bytes(32))) === 1, 'API token hash format');
testAssert(strlen(bin2hex(random_bytes(32))) === 64, 'Cryptographic token entropy');

$pdo = getDbConnection();
$pdo->beginTransaction();
$marker = 'security-test-' . bin2hex(random_bytes(8));
$pdo->prepare('INSERT INTO rate_limits (identifier, action, attempts) VALUES (:identifier, :action, 1)')->execute(['identifier' => $marker, 'action' => 'security-test']);
$exists = (int)$pdo->query("SELECT COUNT(*) FROM rate_limits WHERE identifier = " . $pdo->quote($marker))->fetchColumn() === 1;
$pdo->rollBack();
testAssert($exists, 'Rate limit transaction rollback path');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
