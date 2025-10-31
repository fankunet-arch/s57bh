<?php
/**
 * Toptea Store - KDS
 * Backend Login Handler for KDS
 * Engineer: Gemini | Date: 2025-10-23
 */

@session_start();
require_once realpath(__DIR__ . '/../../../kds/core/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$store_code = trim($_POST['store_code'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($store_code) || empty($username) || empty($password)) {
    header('Location: ../login.php?error=1');
    exit;
}

try {
    // 1. Find the store
    $stmt_store = $pdo->prepare("SELECT id, store_name FROM kds_stores WHERE store_code = ? AND is_active = 1 AND deleted_at IS NULL");
    $stmt_store->execute([$store_code]);
    $store = $stmt_store->fetch();

    if ($store) {
        // 2. Find the user within that store
        $stmt_user = $pdo->prepare("SELECT id, username, password_hash, display_name, role FROM kds_users WHERE username = ? AND store_id = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt_user->execute([$username, $store['id']]);
        $user = $stmt_user->fetch();

        if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
            // --- Login Successful ---
            session_regenerate_id(true);
            $_SESSION['kds_logged_in'] = true;
            $_SESSION['kds_user_id'] = $user['id'];
            $_SESSION['kds_username'] = $user['username'];
            $_SESSION['kds_display_name'] = $user['display_name'];
            $_SESSION['kds_store_id'] = $store['id'];
            $_SESSION['kds_store_name'] = $store['store_name'];
            
            // Update last login
            $pdo->prepare("UPDATE kds_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

            // Redirect to KDS main page
            header('Location: ../index.php');
            exit;
        }
    }

    // If store or user not found, or password mismatch
    header('Location: ../login.php?error=1');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ../login.php?error=1');
    exit;
}