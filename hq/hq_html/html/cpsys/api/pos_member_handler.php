<?php
/**
 * TopTea HQ - POS Member Management API
 * Engineer: Gemini | Date: 2025-10-28
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

session_start();
if (($_SESSION['role_id'] ?? null) !== 1) { // ROLE_SUPER_ADMIN
    http_response_code(403);
    send_json_response('error', '权限不足 (Permission denied)');
}

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $json_data['action'] ?? '';
}

try {
    switch ($action) {
        case 'get':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) send_json_response('error', '无效的ID。');
            
            $data = getMemberById($pdo, $id);
            if ($data) {
                send_json_response('success', 'ok', $data);
            } else {
                http_response_code(404);
                send_json_response('error', 'not found');
            }
            break;

        case 'save':
            $data = $json_data['data'];
            $id = $data['id'] ? (int)$data['id'] : null;
            $phone = trim($data['phone_number'] ?? '');

            if (empty($phone)) {
                send_json_response('error', '手机号为必填项。');
            }

            // Check for duplicate phone number
            $stmt_check = $pdo->prepare("SELECT id FROM pos_members WHERE phone_number = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : ""));
            $params_check = $id ? [$phone, $id] : [$phone];
            $stmt_check->execute($params_check);
            if ($stmt_check->fetch()) {
                http_response_code(409);
                send_json_response('error', '此手机号已被其他会员使用。');
            }

            $params = [
                ':phone_number' => $phone,
                ':first_name' => !empty($data['first_name']) ? trim($data['first_name']) : null,
                ':last_name' => !empty($data['last_name']) ? trim($data['last_name']) : null,
                ':email' => !empty($data['email']) ? trim($data['email']) : null,
                ':birthdate' => !empty($data['birthdate']) ? trim($data['birthdate']) : null,
                ':points_balance' => (float)($data['points_balance'] ?? 0),
                ':member_level_id' => !empty($data['member_level_id']) ? (int)$data['member_level_id'] : null,
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
            ];

            if ($id) {
                $params[':id'] = $id;
                $sql = "UPDATE pos_members SET phone_number = :phone_number, first_name = :first_name, last_name = :last_name, email = :email, birthdate = :birthdate, points_balance = :points_balance, member_level_id = :member_level_id, is_active = :is_active WHERE id = :id";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '会员信息已成功更新！');
            } else {
                $params[':member_uuid'] = bin2hex(random_bytes(16));
                $sql = "INSERT INTO pos_members (member_uuid, phone_number, first_name, last_name, email, birthdate, points_balance, member_level_id, is_active) VALUES (:member_uuid, :phone_number, :first_name, :last_name, :email, :birthdate, :points_balance, :member_level_id, :is_active)";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '新会员已成功创建！');
            }
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) send_json_response('error', '无效的ID。');

            // Soft delete
            $stmt = $pdo->prepare("UPDATE pos_members SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '会员已成功删除。');
            break;

        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}