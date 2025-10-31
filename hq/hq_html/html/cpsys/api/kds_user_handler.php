<?php
/**
 * Toptea HQ - cpsys
 * API Handler for KDS User Management
 * Engineer: Gemini | Date: 2025-10-30 | Revision: 6.0 (Complete Functional Fix)
 */

require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';
require_once APP_PATH . '/helpers/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

@session_start();
// Security check: Only Super Admins can manage KDS users
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== ROLE_SUPER_ADMIN) {
    http_response_code(403);
    send_json_response('error', '权限不足。');
}

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    if (isset($json_data['action'])) {
        $action = $json_data['action'];
    }
}

try {
    switch ($action) {
        case 'get':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) { send_json_response('error', '无效的用户ID。'); }
            
            $user_data = getKdsUserById($pdo, $id);
            if ($user_data) {
                send_json_response('success', '用户数据加载成功。', $user_data);
            } else {
                http_response_code(404);
                send_json_response('error', '未找到指定用户。');
            }
            break;

        case 'save':
            $data = $json_data['data'];
            $id = $data['id'] ? (int)$data['id'] : null;

            $params = [
                ':store_id' => (int)$data['store_id'],
                ':username' => trim($data['username']),
                ':display_name' => trim($data['display_name']),
                ':is_active' => (int)($data['is_active'] ?? 0),
            ];

            if (empty($params[':username']) || empty($params[':display_name'])) {
                send_json_response('error', '用户名和显示名称为必填项。');
            }

            // Check for duplicate username within the same store
            $sql_check = "SELECT id FROM kds_users WHERE username = :username AND store_id = :store_id AND deleted_at IS NULL";
            if ($id) { $sql_check .= " AND id != :id"; $params_check = array_merge($params, [':id' => $id]); } else { $params_check = $params; }
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':username' => $params[':username'], ':store_id' => $params[':store_id'], ':id' => $id]);
            if ($stmt_check->fetch()) {
                http_response_code(409);
                send_json_response('error', '该门店下已存在相同的用户名。');
            }

            if ($id) { // Update
                if (!empty($data['password'])) {
                    $params[':password_hash'] = hash('sha256', $data['password']);
                    $sql = "UPDATE kds_users SET display_name = :display_name, password_hash = :password_hash, is_active = :is_active WHERE id = :id";
                     unset($params[':username'], $params[':store_id']); // Cannot change username or store
                } else {
                    $sql = "UPDATE kds_users SET display_name = :display_name, is_active = :is_active WHERE id = :id";
                     unset($params[':password_hash'], $params[':username'], $params[':store_id']);
                }
                $params[':id'] = $id;
                $message = 'KDS账户已成功更新！';
            } else { // Create
                if (empty($data['password'])) { send_json_response('error', '创建新账户时必须设置密码。'); }
                $params[':password_hash'] = hash('sha256', $data['password']);
                $sql = "INSERT INTO kds_users (store_id, username, display_name, password_hash, is_active) VALUES (:store_id, :username, :display_name, :password_hash, :is_active)";
                $message = '新KDS账户已成功创建！';
            }
            
            $pdo->prepare($sql)->execute($params);
            send_json_response('success', $message);
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) { send_json_response('error', '无效的ID。'); }
            $stmt = $pdo->prepare("UPDATE kds_users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', 'KDS账户已成功删除。');
            break;

        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}