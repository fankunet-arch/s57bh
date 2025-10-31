<?php
/**
 * TopTea HQ - POS Menu Item Management API
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
            $stmt = $pdo->prepare("SELECT * FROM pos_menu_items WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            if ($data) { send_json_response('success', 'ok', $data); } else { http_response_code(404); send_json_response('error', 'not found'); }
            break;

        case 'save':
            $data = $json_data['data'];
            $id = $data['id'] ? (int)$data['id'] : null;
            
            $params = [
                ':name_zh' => trim($data['name_zh']),
                ':name_es' => trim($data['name_es']),
                ':pos_category_id' => (int)$data['pos_category_id'],
                ':description_zh' => trim($data['description_zh']) ?: null,
                ':description_es' => trim($data['description_es']) ?: null,
                ':sort_order' => (int)($data['sort_order'] ?? 99),
                ':is_active' => (int)($data['is_active'] ?? 0)
            ];

            if (empty($params[':name_zh']) || empty($params[':name_es']) || empty($params[':pos_category_id'])) {
                send_json_response('error', '双语名称和POS分类均为必填项。');
            }

            if ($id) {
                $params[':id'] = $id;
                $sql = "UPDATE pos_menu_items SET name_zh = :name_zh, name_es = :name_es, pos_category_id = :pos_category_id, description_zh = :description_zh, description_es = :description_es, sort_order = :sort_order, is_active = :is_active WHERE id = :id";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '商品信息已成功更新！');
            } else {
                $sql = "INSERT INTO pos_menu_items (name_zh, name_es, pos_category_id, description_zh, description_es, sort_order, is_active) VALUES (:name_zh, :name_es, :pos_category_id, :description_zh, :description_es, :sort_order, :is_active)";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '新商品已成功创建！');
            }
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) { send_json_response('error', '无效的ID。'); }
            // Security: Also delete variants
            $pdo->beginTransaction();
            $stmt_variants = $pdo->prepare("UPDATE pos_item_variants SET deleted_at = CURRENT_TIMESTAMP WHERE menu_item_id = ?");
            $stmt_variants->execute([$id]);
            $stmt_item = $pdo->prepare("UPDATE pos_menu_items SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt_item->execute([$id]);
            $pdo->commit();
            send_json_response('success', '商品及其所有规格已成功删除。');
            break;

        default:
            http_response_code(400); send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    if(isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}