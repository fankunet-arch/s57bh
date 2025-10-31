<?php
/**
 * Toptea HQ - cpsys
 * API Handler for User Profile Updates
 * Engineer: Gemini | Date: 2025-10-23
 */

require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

@session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    send_json_response('error', '用户未登录。');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response('error', '无效的请求方法。');
}

$json_data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

// --- Extract data ---
$display_name = trim($json_data['display_name']);
$email = filter_var(trim($json_data['email']), FILTER_VALIDATE_EMAIL) ? trim($json_data['email']) : null;
$current_password = $json_data['current_password'];
$new_password = $json_data['new_password'];

if (empty($display_name)) {
    send_json_response('error', '显示名称不能为空。');
}

try {
    // Fetch current user data for verification
    $stmt = $pdo->prepare("SELECT email, password_hash FROM cpsys_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        send_json_response('error', '找不到当前用户信息。');
    }

    $is_password_change = !empty($new_password);
    $is_sensitive_change = ($is_password_change || $email !== $user['email']);

    // If changing password or email, current password is required
    if ($is_sensitive_change && empty($current_password)) {
        http_response_code(400);
        send_json_response('error', '修改密码或邮箱时，必须提供当前密码。');
    }

    // Verify current password if needed
    if ($is_sensitive_change) {
        $current_password_hash_check = hash('sha256', $current_password);
        if (!hash_equals($user['password_hash'], $current_password_hash_check)) {
            http_response_code(403);
            send_json_response('error', '当前密码不正确。');
        }
    }

    // --- Perform Updates ---
    if ($is_password_change) {
        $new_password_hash = hash('sha256', $new_password);
        $stmt = $pdo->prepare("UPDATE cpsys_users SET display_name = ?, email = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$display_name, $email, $new_password_hash, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE cpsys_users SET display_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$display_name, $email, $user_id]);
    }

    // Update session data
    $_SESSION['display_name'] = $display_name;

    send_json_response('success', '个人资料已成功更新！');

} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    send_json_response('error', '数据库操作失败。');
}