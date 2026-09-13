<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$pdo = getDbConnection();
$requiredRoles = ['super_admin', 'admin', 'manager'];
$statement = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
$row = $statement->fetch(PDO::FETCH_ASSOC);
$type = (string)($row['Type'] ?? '');
$ok = true;
foreach ($requiredRoles as $role) {
    if (strpos($type, "'{$role}'") === false) {
        $ok = false;
        echo "FAIL role {$role}\n";
    } else {
        echo "PASS role {$role}\n";
    }
}
if ($ok) echo "RESULT RBAC role model present\n";
exit($ok ? 0 : 1);
