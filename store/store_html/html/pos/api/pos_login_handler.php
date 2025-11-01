<?php
/**
 * Toptea Store - POS
 * Backend Login Handler for POS
 * Engineer: Gemini | Date: 2025-10-30
 * Revision: 1.1 (Add user role to session)
 */

@session_start();
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');

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

        // 3. Verify password
        if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
            // --- Login Successful ---
            session_regenerate_id(true);
            $_SESSION['pos_logged_in'] = true;
            $_SESSION['pos_user_id'] = $user['id'];
            $_SESSION['pos_display_name'] = $user['display_name'];
            $_SESSION['pos_store_id'] = $store['id'];
            $_SESSION['pos_store_name'] = $store['store_name'];
            $_SESSION['pos_user_role'] = $user['role']; // Add user role to session
            
            // Update last login timestamp
            $pdo->prepare("UPDATE kds_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

            // Redirect to POS main page
            header('Location: ../index.php');
            exit;
        }
    }

    // If any step fails, redirect back with an error
    header('Location: ../login.php?error=1');
    exit;

} catch (PDOException $e) {
    error_log("POS Login Error: " . $e->getMessage());
    header('Location: ../login.php?error=1');
    exit;
}