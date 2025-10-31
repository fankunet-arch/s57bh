<?php
/**
 * TopTea HQ - POS Item Variant Management API
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
            $stmt = $pdo->prepare("SELECT * FROM pos_item_variants WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            if ($data) { send_json_response('success', 'ok', $data); } else { http_response_code(404); send_json_response('error', 'not found'); }
            break;

        case 'save':
            $data = $json_data['data'];
            $id = $data['id'] ? (int)$data['id'] : null;
            
            $params = [
                ':menu_item_id' => (int)$data['menu_item_id'],
                ':product_id' => (int)$data['product_id'],
                ':variant_name_zh' => trim($data['variant_name_zh']),
                ':variant_name_es' => trim($data['variant_name_es']),
                ':price_eur' => (float)$data['price_eur'],
                ':sort_order' => (int)($data['sort_order'] ?? 99),
                ':is_default' => (int)($data['is_default'] ?? 0)
            ];

            if (empty($params[':variant_name_zh']) || empty($params[':variant_name_es']) || empty($params[':product_id']) || $params[':price_eur'] <= 0) {
                send_json_response('error', '双语名称、价格和关联配方均为必填项。');
            }

            if ($id) {
                $params[':id'] = $id;
                $sql = "UPDATE pos_item_variants SET menu_item_id = :menu_item_id, product_id = :product_id, variant_name_zh = :variant_name_zh, variant_name_es = :variant_name_es, price_eur = :price_eur, sort_order = :sort_order, is_default = :is_default WHERE id = :id";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '规格信息已成功更新！');
            } else {
                $sql = "INSERT INTO pos_item_variants (menu_item_id, product_id, variant_name_zh, variant_name_es, price_eur, sort_order, is_default) VALUES (:menu_item_id, :product_id, :variant_name_zh, :variant_name_es, :price_eur, :sort_order, :is_default)";
                $pdo->prepare($sql)->execute($params);
                send_json_response('success', '新规格已成功创建！');
            }
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) { send_json_response('error', '无效的ID。'); }
            $stmt = $pdo->prepare("UPDATE pos_item_variants SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '规格已成功删除。');
            break;

        default:
            http_response_code(400); send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}