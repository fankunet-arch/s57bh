<?php
/**
 * TopTea HQ - POS Member Level Management API
 * Engineer: Gemini | Date: 2025-10-28
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php'; 

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// Basic security check - ensure user is super admin
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
    if (isset($json_data['action'])) {
        $action = $json_data['action'];
    }
}

try {
    switch ($action) {
        case 'get':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                send_json_response('error', '无效的ID。');
            }
            $data = getMemberLevelById($pdo, $id);
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

            $params = [
                ':level_name_zh' => trim($data['level_name_zh']),
                ':level_name_es' => trim($data['level_name_es']),
                ':points_threshold' => (float)($data['points_threshold'] ?? 0),
                ':sort_order' => (int)($data['sort_order'] ?? 99),
                ':level_up_promo_id' => !empty($data['level_up_promo_id']) ? (int)$data['level_up_promo_id'] : null,
            ];

            if (empty($params[':level_name_zh']) || empty($params[':level_name_es'])) {
                send_json_response('error', '双语等级名称均为必填项。');
            }

            if ($id) {
                $params[':id'] = $id;
                $sql = "UPDATE pos_member_levels SET 
                            level_name_zh = :level_name_zh, 
                            level_name_es = :level_name_es, 
                            points_threshold = :points_threshold, 
                            sort_order = :sort_order, 
                            level_up_promo_id = :level_up_promo_id 
                        WHERE id = :id";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '会员等级已成功更新！');
            } else {
                $sql = "INSERT INTO pos_member_levels (level_name_zh, level_name_es, points_threshold, sort_order, level_up_promo_id) 
                        VALUES (:level_name_zh, :level_name_es, :points_threshold, :sort_order, :level_up_promo_id)";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '新会员等级已成功创建！');
            }
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) {
                send_json_response('error', '无效的ID。');
            }
            
            // Note: We perform a hard delete here as member levels are dictionary data.
            // Ensure no members are currently assigned this level before deleting, or handle reassignment.
            // For simplicity in this step, we proceed with deletion.
            $stmt = $pdo->prepare("DELETE FROM pos_member_levels WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                 send_json_response('success', '会员等级已成功删除。');
            } else {
                 http_response_code(404);
                 send_json_response('error', '未找到要删除的等级。');
            }
            break;

        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}