<?php
/**
 * Toptea HQ - cpsys
 * Unified API Handler for Material Management (Bilingual)
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 9.5 (UI Message Polish)
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
        $next_code = getNextAvailableCustomCode($pdo, 'kds_materials', 'material_code');
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
    $material = getMaterialById($pdo, $id);
    if ($material) { send_json_response('success', '数据获取成功。', $material); } else { http_response_code(404); send_json_response('error', '未找到指定的物料。'); }
}

function handleSave($pdo, $data) {
    $id = $data['id'] ? (int)$data['id'] : null;
    $code = trim($data['material_code'] ?? '');
    $type = trim($data['material_type'] ?? '');
    $name_zh = trim($data['name_zh'] ?? '');
    $name_es = trim($data['name_es'] ?? '');
    $base_unit_id = (int)($data['base_unit_id'] ?? 0);
    $large_unit_id = (int)($data['large_unit_id'] ?? 0);
    $conversion_rate = (float)($data['conversion_rate'] ?? 0.0);
    $expiry_rule_type = (string)($data['expiry_rule_type'] ?? '');
    $expiry_duration = (int)($data['expiry_duration'] ?? 0);

    if (empty($code) || empty($type) || empty($name_zh) || empty($name_es) || empty($base_unit_id)) { send_json_response('error', '编号、类型、双语名称和基础单位为必填项。'); }
    if ($large_unit_id !== 0 && ($conversion_rate <= 1)) { send_json_response('error', '选择大单位后，换算率必须是一个大于1的数字。'); }
    if ($expiry_rule_type !== '' && in_array($expiry_rule_type, ['HOURS', 'DAYS']) && ($expiry_duration <= 0)) { send_json_response('error', '选择按小时或天计算效期后，必须填写一个大于0的时长。'); }
    if ($expiry_rule_type === 'END_OF_DAY' || $expiry_rule_type === '') { $expiry_duration = 0; }
    if ($large_unit_id === 0) { $conversion_rate = 0.0; }
    
    $pdo->beginTransaction();
    try {
        if ($id) { // --- UPDATE LOGIC ---
            $stmt_check = $pdo->prepare("SELECT id FROM kds_materials WHERE material_code = ? AND id != ? AND deleted_at IS NULL");
            $stmt_check->execute([$code, $id]);
            if ($stmt_check->fetch()) {
                http_response_code(409);
                send_json_response('error', '自定义编号 "' . htmlspecialchars($code) . '" 已被另一个有效物料使用。');
            }

            $stmt = $pdo->prepare("UPDATE kds_materials SET material_code = ?, material_type = ?, base_unit_id = ?, large_unit_id = ?, conversion_rate = ?, expiry_rule_type = ?, expiry_duration = ? WHERE id = ?");
            $stmt->execute([$code, $type, $base_unit_id, $large_unit_id, $conversion_rate, $expiry_rule_type, $expiry_duration, $id]);

            $stmt_trans = $pdo->prepare("UPDATE kds_material_translations SET material_name = ? WHERE material_id = ? AND language_code = ?");
            $stmt_trans->execute([$name_zh, $id, 'zh-CN']);
            $stmt_trans->execute([$name_es, $id, 'es-ES']);
            
            $pdo->commit();
            send_json_response('success', '物料已成功更新！');

        } else { // --- CREATE LOGIC (with RECLAIM) ---
            $stmt_active = $pdo->prepare("SELECT id FROM kds_materials WHERE material_code = ? AND deleted_at IS NULL");
            $stmt_active->execute([$code]);
            if ($stmt_active->fetch()) {
                http_response_code(409);
                send_json_response('error', '自定义编号 "' . htmlspecialchars($code) . '" 已被一个有效物料使用。');
            }

            $stmt_deleted = $pdo->prepare("SELECT id FROM kds_materials WHERE material_code = ? AND deleted_at IS NOT NULL");
            $stmt_deleted->execute([$code]);
            $reclaimable_row = $stmt_deleted->fetch();
            
            // --- START: DEFINITIVE FIX ---
            // The success message is now unified for both reclaim and pure insert.
            $message = '新物料已成功创建！';
            // --- END: DEFINITIVE FIX ---

            if ($reclaimable_row) {
                // --- RECLAIM LOGIC ---
                $reclaim_id = $reclaimable_row['id'];
                $stmt_reclaim = $pdo->prepare(
                    "UPDATE kds_materials SET 
                        material_type = ?, 
                        base_unit_id = ?, 
                        large_unit_id = ?, 
                        conversion_rate = ?, 
                        expiry_rule_type = ?, 
                        expiry_duration = ?, 
                        deleted_at = NULL 
                    WHERE id = ?"
                );
                $stmt_reclaim->execute([$type, $base_unit_id, $large_unit_id, $conversion_rate, $expiry_rule_type, $expiry_duration, $reclaim_id]);

                $stmt_trans = $pdo->prepare("UPDATE kds_material_translations SET material_name = ? WHERE material_id = ? AND language_code = ?");
                $stmt_trans->execute([$name_zh, $reclaim_id, 'zh-CN']);
                $stmt_trans->execute([$name_es, $reclaim_id, 'es-ES']);
            } else {
                // --- PURE INSERT LOGIC ---
                $stmt = $pdo->prepare("INSERT INTO kds_materials (material_code, material_type, base_unit_id, large_unit_id, conversion_rate, expiry_rule_type, expiry_duration) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $type, $base_unit_id, $large_unit_id, $conversion_rate, $expiry_rule_type, $expiry_duration]);
                $new_material_id = $pdo->lastInsertId();

                $stmt_trans = $pdo->prepare("INSERT INTO kds_material_translations (material_id, language_code, material_name) VALUES (?, ?, ?)");
                $stmt_trans->execute([$new_material_id, 'zh-CN', $name_zh]);
                $stmt_trans->execute([$new_material_id, 'es-ES', $name_es]);
            }

            $pdo->commit();
            send_json_response('success', $message);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        send_json_response('error', '数据库操作失败。', ['debug_info' => $e->getMessage()]);
    }
}

function handleDelete($pdo, $id) {
    $id = (int)$id;
    if (!$id) { send_json_response('error', '无效的ID。'); }
    $stmt = $pdo->prepare("UPDATE kds_materials SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$id]);
    send_json_response('success', '物料已成功删除。');
}