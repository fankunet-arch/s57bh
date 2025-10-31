<?php
/**
 * Toptea HQ - cpsys
 * Unified API Handler for Cup Management
 * Engineer: Gemini | Date: 2025-10-25 | Revision: 6.8 (Cup SOP Enhancement)
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';
header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) { $action = $_GET['action']; }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $json_data = json_decode(file_get_contents('php://input'), true); if (isset($json_data['action'])) { $action = $json_data['action']; } }

switch ($action) {
    case 'get':
        handleGet($pdo);
        break;
    case 'save':
        handleSave($pdo, $json_data['data']);
        break;
    case 'delete':
        handleDelete($pdo, $json_data['id']);
        break;
    default:
        http_response_code(400);
        send_json_response('error', '无效的操作请求。');
}

function handleGet($pdo) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) { send_json_response('error', '无效的ID。'); }
    $cup = getCupById($pdo, $id);
    if ($cup) { send_json_response('success', '数据获取成功。', $cup); } else { http_response_code(404); send_json_response('error', '未找到指定的杯型。'); }
}

function handleSave($pdo, $data) {
    $id = $data['id'] ? (int)$data['id'] : null;
    $cup_code = trim($data['cup_code']);
    $cup_name = trim($data['cup_name']);
    $sop_zh = trim($data['sop_zh']);
    $sop_es = trim($data['sop_es']);

    if (empty($cup_code) || empty($cup_name) || empty($sop_zh) || empty($sop_es)) {
        send_json_response('error', '所有字段均为必填项。');
    }

    $sql_check = "SELECT id FROM kds_cups WHERE cup_code = ? AND deleted_at IS NULL";
    $params_check = [$cup_code];
    if ($id) {
        $sql_check .= " AND id != ?";
        $params_check[] = $id;
    }
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) {
        http_response_code(409);
        send_json_response('error', '自定义编号 "' . htmlspecialchars($cup_code) . '" 已被使用。');
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE kds_cups SET cup_code = ?, cup_name = ?, sop_description_zh = ?, sop_description_es = ? WHERE id = ?");
        $stmt->execute([$cup_code, $cup_name, $sop_zh, $sop_es, $id]);
        send_json_response('success', '杯型已成功更新！');
    } else {
        $stmt = $pdo->prepare("INSERT INTO kds_cups (cup_code, cup_name, sop_description_zh, sop_description_es) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cup_code, $cup_name, $sop_zh, $sop_es]);
        send_json_response('success', '新杯型已成功创建！');
    }
}

function handleDelete($pdo, $id) {
    $id = (int)$id;
    if (!$id) { send_json_response('error', '无效的ID。'); }
    $stmt = $pdo->prepare("UPDATE kds_cups SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$id]);
    send_json_response('success', '杯型已成功删除。');
}