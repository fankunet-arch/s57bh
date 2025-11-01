<?php
/**
 * Toptea HQ - cpsys
 * API Handler for Product Status Management
 * Engineer: Gemini | Date: 2025-11-01 | Revision: 1.2 (Fix GET query to respect soft delete)
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';
header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) { $action = $_GET['action']; }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $json_data = json_decode(file_get_contents('php://input'), true); if (isset($json_data['action'])) { $action = $json_data['action']; } }

try {
    switch ($action) {
        case 'get':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) { send_json_response('error', '无效的ID。'); }
            // FIX: Added 'AND deleted_at IS NULL' to prevent fetching soft-deleted items
            $stmt = $pdo->prepare("SELECT id, status_code, status_name_zh, status_name_es FROM kds_product_statuses WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) { send_json_response('success', 'ok', $data); } else { http_response_code(404); send_json_response('error', 'not found'); }
            break;

        case 'save':
            $data = $json_data['data'];
            $id = $data['id'] ? (int)$data['id'] : null;
            $code = trim($data['status_code']);
            $name_zh = trim($data['status_name_zh']);
            $name_es = trim($data['status_name_es']);

            if (empty($code) || empty($name_zh) || empty($name_es)) { send_json_response('error', '状态编号和双语名称均为必填项。'); }

            $stmt_check = $pdo->prepare("SELECT id FROM kds_product_statuses WHERE status_code = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : ""));
            $params_check = $id ? [$code, $id] : [$code];
            $stmt_check->execute($params_check);
            if ($stmt_check->fetch()) { http_response_code(409); send_json_response('error', '状态编号 "' . htmlspecialchars($code) . '" 已被使用。'); }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE kds_product_statuses SET status_code = ?, status_name_zh = ?, status_name_es = ? WHERE id = ?");
                $stmt->execute([$code, $name_zh, $name_es, $id]);
                send_json_response('success', '状态已成功更新！');
            } else {
                $stmt = $pdo->prepare("INSERT INTO kds_product_statuses (status_code, status_name_zh, status_name_es) VALUES (?, ?, ?)");
                $stmt->execute([$code, $name_zh, $name_es]);
                send_json_response('success', '新状态已成功创建！');
            }
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) { send_json_response('error', '无效的ID。'); }
            
            // 检查是否被 kds_products 引用
            $stmt_check = $pdo->prepare("SELECT 1 FROM kds_products WHERE status_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetch()) {
                http_response_code(409); // Conflict
                send_json_response('error', '删除失败：此状态正在被一个或多个产品使用。');
            }
            
            // 执行软删除
            $stmt = $pdo->prepare("UPDATE kds_product_statuses SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '状态已成功删除。');
            break;

        default:
            http_response_code(400); send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    // FIX A3: 使用 SQLSTATE '23000' (Integrity Constraint Violation) 来捕获所有外键约束失败
    if ($e->getCode() == '23000') {
        http_response_code(409); // Conflict
        send_json_response('error', '删除失败：此状态正在被一个或多个产品使用。');
    }
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}
}