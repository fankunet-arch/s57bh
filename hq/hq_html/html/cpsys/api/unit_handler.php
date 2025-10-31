<?php
/**
 * Toptea HQ - cpsys
 * Unified API Handler for Unit Management (Bilingual)
 * Engineer: Gemini | Date: 2025-10-23 | Revision: 3.9 (Final Syntax Correction)
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';
header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) { $action = $_GET['action']; }
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { $json_data = json_decode(file_get_contents('php://input'), true); if (isset($json_data['action'])) { $action = $json_data['action']; } }
switch ($action) {
    case 'get_next_code':
        $next_code = getNextAvailableCustomCode($pdo, 'kds_units', 'unit_code');
        send_json_response('success', '下一个可用编号已找到。', ['next_code' => $next_code]);
        break;
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
    $unit = getUnitById($pdo, $id);
    if ($unit) { send_json_response('success', '数据获取成功。', $unit); } else { http_response_code(404); send_json_response('error', '未找到指定的单位。'); }
}
function handleSave($pdo, $data) {
    $id = $data['id'] ? (int)$data['id'] : null; $code = $data['unit_code'];
    $name_zh = trim($data['name_zh']); $name_es = trim($data['name_es']);
    if (empty($code) || empty($name_zh) || empty($name_es)) { send_json_response('error', '所有字段均为必填项。'); }
    $sql_check = "SELECT id FROM kds_units WHERE unit_code = ? AND deleted_at IS NULL";
    $params_check = [$code]; if ($id) { $sql_check .= " AND id != ?"; $params_check[] = $id; }
    $stmt_check = $pdo->prepare($sql_check); $stmt_check->execute($params_check);
    if ($stmt_check->fetch()) { http_response_code(409); send_json_response('error', '自定义编号 "' . htmlspecialchars($code) . '" 已被使用。'); }
    $pdo->beginTransaction();
    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE kds_units SET unit_code = ? WHERE id = ?"); $stmt->execute([$code, $id]);
            $stmt_trans = $pdo->prepare("UPDATE kds_unit_translations SET unit_name = ? WHERE unit_id = ? AND language_code = ?");
            $stmt_trans->execute([$name_zh, $id, 'zh-CN']); $stmt_trans->execute([$name_es, $id, 'es-ES']);
            $pdo->commit(); send_json_response('success', '单位已成功更新！');
        } else {
            $stmt = $pdo->prepare("INSERT INTO kds_units (unit_code) VALUES (?)"); $stmt->execute([$code]);
            $new_unit_id = $pdo->lastInsertId();
            $stmt_trans = $pdo->prepare("INSERT INTO kds_unit_translations (unit_id, language_code, unit_name) VALUES (?, ?, ?)");
            $stmt_trans->execute([$new_unit_id, 'zh-CN', $name_zh]); $stmt_trans->execute([$new_unit_id, 'es-ES', $name_es]);
            $pdo->commit(); send_json_response('success', '新单位已成功创建！');
        }
    } catch (Exception $e) { $pdo->rollBack(); http_response_code(500); send_json_response('error', '数据库操作失败。', ['error' => $e->getMessage()]); }
}
function handleDelete($pdo, $id) {
    $id = (int)$id; if (!$id) { send_json_response('error', '无效的ID。'); }
    $stmt = $pdo->prepare("UPDATE kds_units SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?"); $stmt->execute([$id]);
    send_json_response('success', '单位已成功删除。');
}