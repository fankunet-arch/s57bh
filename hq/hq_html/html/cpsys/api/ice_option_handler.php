<?php
/**
 * Toptea HQ - cpsys
 * Unified API Handler for Ice Option Management (Bilingual)
 * Engineer: Gemini | Date: 2025-10-25 | Revision: 6.6 (SOP Required)
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
        case 'get_next_code':
            $next_code = getNextAvailableCustomCode($pdo, 'kds_ice_options', 'ice_code');
            send_json_response('success', 'ok', ['next_code' => $next_code]);
            break;
        case 'get':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            $data = getIceOptionById($pdo, $id);
            if ($data) { send_json_response('success', 'ok', $data); } else { http_response_code(404); send_json_response('error', 'not found'); }
            break;
        case 'save':
            $data = $json_data['data']; $id = $data['id'] ? (int)$data['id'] : null; $code = $data['code'];
            $name_zh = trim($data['name_zh']);
            $name_es = trim($data['name_es']);
            $sop_zh = trim($data['sop_zh']);
            $sop_es = trim($data['sop_es']);

            // --- CORE FIX: Enforce required fields validation ---
            if (empty($code) || empty($name_zh) || empty($name_es) || empty($sop_zh) || empty($sop_es)) {
                send_json_response('error', '所有字段（编号、双语名称、双语操作说明）均为必填项。');
            }

            // The rest of the logic remains the same
            $pdo->beginTransaction();
            if ($id) { // UPDATE LOGIC
                $stmt_check = $pdo->prepare("SELECT id FROM kds_ice_options WHERE ice_code = ? AND deleted_at IS NULL AND id != ?");
                $stmt_check->execute([$code, $id]);
                if ($stmt_check->fetch()) {
                    $pdo->rollBack(); http_response_code(409);
                    send_json_response('error', '自定义编号 "' . htmlspecialchars($code) . '" 已被一个有效的选项使用。');
                }
                $stmt = $pdo->prepare("UPDATE kds_ice_options SET ice_code = ? WHERE id = ?");
                $stmt->execute([$code, $id]);
                $stmt_trans = $pdo->prepare("UPDATE kds_ice_option_translations SET ice_option_name = ?, sop_description = ? WHERE ice_option_id = ? AND language_code = ?");
                $stmt_trans->execute([$name_zh, $sop_zh, $id, 'zh-CN']);
                $stmt_trans->execute([$name_es, $sop_es, $id, 'es-ES']);
                $pdo->commit();
                send_json_response('success', '冰量选项已成功更新！');
            } else { // CREATE LOGIC (with reclaim)
                $stmt_active = $pdo->prepare("SELECT id FROM kds_ice_options WHERE ice_code = ? AND deleted_at IS NULL");
                $stmt_active->execute([$code]);
                if ($stmt_active->fetch()) {
                    $pdo->rollBack(); http_response_code(409);
                    send_json_response('error', '自定义编号 "' . htmlspecialchars($code) . '" 已被一个有效的选项使用。');
                }
                $stmt_inactive = $pdo->prepare("SELECT id FROM kds_ice_options WHERE ice_code = ? AND deleted_at IS NOT NULL");
                $stmt_inactive->execute([$code]);
                $reclaimable_row = $stmt_inactive->fetch();
                if ($reclaimable_row) {
                    $reclaim_id = $reclaimable_row['id'];
                    $stmt_reclaim = $pdo->prepare("UPDATE kds_ice_options SET deleted_at = NULL WHERE id = ?");
                    $stmt_reclaim->execute([$reclaim_id]);
                    $stmt_trans = $pdo->prepare("UPDATE kds_ice_option_translations SET ice_option_name = ?, sop_description = ? WHERE ice_option_id = ? AND language_code = ?");
                    $stmt_trans->execute([$name_zh, $sop_zh, $reclaim_id, 'zh-CN']);
                    $stmt_trans->execute([$name_es, $sop_es, $reclaim_id, 'es-ES']);
                    $message = '新选项已创建 (一个已删除的记录被恢复使用)。';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO kds_ice_options (ice_code) VALUES (?)");
                    $stmt->execute([$code]);
                    $new_id = $pdo->lastInsertId();
                    $stmt_trans = $pdo->prepare("INSERT INTO kds_ice_option_translations (ice_option_id, language_code, ice_option_name, sop_description) VALUES (?, ?, ?, ?)");
                    $stmt_trans->execute([$new_id, 'zh-CN', $name_zh, $sop_zh]);
                    $stmt_trans->execute([$new_id, 'es-ES', $name_es, $sop_es]);
                    $message = '新冰量选项已成功创建！';
                }
                $pdo->commit();
                send_json_response('success', $message);
            }
            break;
        case 'delete':
            $id = (int)$json_data['id'];
            if (!$id) { send_json_response('error', '无效的ID。'); }
            $stmt = $pdo->prepare("UPDATE kds_ice_options SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '冰量选项已成功删除。');
            break;
        default:
            http_response_code(400); send_json_response('error', '无效的操作请求。');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '服务器内部错误。', 'debug' => $e->getMessage()]);
    exit;
}