<?php
/**
 * Toptea HQ - cpsys
 * Role-Based Access Control (RBAC) Helper (Final Production Version)
 * Date: 2025-10-23 | Revision: 3.9
 */

define('ROLE_SUPER_ADMIN', 1);
define('ROLE_PRODUCT_MANAGER', 2);
define('ROLE_STORE_MANAGER', 3);

function getRolePermissions(): array {
    return [
        ROLE_PRODUCT_MANAGER => [ 'product_list', 'product_management', 'product_edit' ],
        ROLE_STORE_MANAGER => [ 'product_list' ],
    ];
}

function hasPermission(int $role_id, string $page): bool {
    if ($role_id === ROLE_SUPER_ADMIN) {
        return true;
    }
    $permissions = getRolePermissions();
    if (isset($permissions[$role_id])) {
        return in_array($page, $permissions[$role_id]);
    }
    return false;
}

function getAllRoles(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, role_name FROM cpsys_roles ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getAllUsers(PDO $pdo): array {
    $sql = "
        SELECT 
            u.id, u.username, u.display_name, u.email, u.is_active, u.last_login_at, r.role_name
        FROM cpsys_users u
        JOIN cpsys_roles r ON u.role_id = r.id
        WHERE u.deleted_at IS NULL
        ORDER BY u.id ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getUserById(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("SELECT id, username, display_name, email, role_id, is_active FROM cpsys_users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return $stmt->fetch();
}