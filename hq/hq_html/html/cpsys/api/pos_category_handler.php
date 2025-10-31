<?php
/**
 * TopTea BMS - POS Category Management API
 * Engineer: Gemini | Date: 2025-10-26
 */
require_once realpath(__DIR__ . '/../../../core/config.php');

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
            $stmt = $pdo->prepare("SELECT * FROM pos_categories WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            if ($data) { send_json_response('success', 'ok', $data); } else { http_response_code(404); send_json_response('error', 'not found'); }
            break;

        case 'save':
            $data = $json_data['data'];
            $id = $data['id'] ? (int)$data['id'] : null;
            $code = trim($data['category_code']);
            $name_zh = trim($data['name_zh']);
            $name_es = trim($data['name_es']);
            $sort = (int)($data['sort_order'] ?? 99);

            if (empty($code) || empty($name_zh) || empty($name_es)) { send_json_response('error', '分类编码和双语名称均为必填项。'); }

            $stmt_check = $pdo->prepare("SELECT id FROM pos_categories WHERE category_code = ? AND deleted_at IS NULL" . ($id ? " AND id != ?" : ""));
            $params_check = $id ? [$code, $id] : [$code];
            $stmt_check->execute($params_check);
            if ($stmt_check->fetch()) { http_response_code(409); send_json_response('error', '分类编码 "' . htmlspecialchars($code) . '" 已被使用。'); }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE pos_categories SET category_code = ?, name_zh = ?, name_es = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$code, $name_zh, $name_es, $sort, $id]);
                send_json_response('success', '分类已成功更新！');
            } else {
                $stmt = $pdo->prepare("INSERT INTO pos_categories (category_code, name_zh, name_es, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $name_zh, $name_es, $sort]);
                send_json_response('success', '新分类已成功创建！');
            }
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) { send_json_response('error', '无效的ID。'); }
            $stmt = $pdo->prepare("UPDATE pos_categories SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '分类已成功删除。');
            break;

        default:
            http_response_code(400); send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}