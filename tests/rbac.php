<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$pdo = getDbConnection();
$ok = true;
$requiredRoles = ['super_admin', 'admin', 'manager'];
$statement = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
$row = $statement->fetch(PDO::FETCH_ASSOC);
$type = (string)($row['Type'] ?? '');
foreach ($requiredRoles as $role) {
    $passed = strpos($type, "'{$role}'") !== false;
    echo ($passed ? 'PASS ' : 'FAIL ') . "role {$role}\n";
    $ok = $ok && $passed;
}
$permissionCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_permissions')->fetchColumn();
$rolePermissionCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_role_permissions')->fetchColumn();
$passed = $permissionCount >= 17;
echo ($passed ? 'PASS ' : 'FAIL ') . "permission catalog {$permissionCount}\n";
$ok = $ok && $passed;
$passed = $rolePermissionCount >= $permissionCount * 2;
echo ($passed ? 'PASS ' : 'FAIL ') . "role permission assignments {$rolePermissionCount}\n";
$ok = $ok && $passed;
$managerForbidden = (int)$pdo->query("SELECT COUNT(*) FROM admin_role_permissions rp INNER JOIN admin_permissions p ON p.id = rp.permission_id WHERE rp.role = 'manager' AND p.permission_key IN ('settings.manage','admins.manage','audit.view','branding.manage')")->fetchColumn() === 0;
echo ($managerForbidden ? 'PASS ' : 'FAIL ') . "manager restricted permissions\n";
$ok = $ok && $managerForbidden;
$adminForbidden = (int)$pdo->query("SELECT COUNT(*) FROM admin_role_permissions rp INNER JOIN admin_permissions p ON p.id = rp.permission_id WHERE rp.role = 'admin' AND p.permission_key IN ('settings.manage','admins.manage','audit.view')")->fetchColumn() === 0;
echo ($adminForbidden ? 'PASS ' : 'FAIL ') . "admin restricted permissions\n";
$ok = $ok && $adminForbidden;
echo 'RESULT ' . ($ok ? 'RBAC checks passed' : 'RBAC checks failed') . "\n";
exit($ok ? 0 : 1);
