<?php
/**
 * Toptea Store - KDS API
 * API Endpoint for KDS to record a new expiry item
 * Engineer: Gemini | Date: 2025-10-30
 * Revision: 9.0 (Return print data packet as per Plan II-3)
 */

require_once realpath(__DIR__ . '/../../../kds/core/config.php');
require_once realpath(__DIR__ . '/../../../kds/helpers/kds_helper_shim.php');

header('Content-Type: application/json; charset=utf-8');
@session_start();

function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response('error', 'Invalid request method.');
}

if (!isset($_SESSION['kds_store_id']) || !isset($_SESSION['kds_user_id'])) {
    http_response_code(401);
    send_json_response('error', 'Unauthorized: Missing KDS session data.');
}

$json_data = json_decode(file_get_contents('php://input'), true);
$material_id = (int)($json_data['material_id'] ?? 0);
$store_id = (int)$_SESSION['kds_store_id'];
$user_id = (int)$_SESSION['kds_user_id'];
$operator_name = $_SESSION['kds_display_name'] ?? 'KDS User'; // (Plan II-3)

if ($material_id <= 0) {
    http_response_code(400);
    send_json_response('error', '无效的物料ID。');
}

try {
    // (Plan II-3) 实时数据聚合
    $material = getMaterialById($pdo, $material_id);
    if (!$material || !$material['expiry_rule_type']) {
        http_response_code(404);
        send_json_response('error', '找不到该物料或该物料未设置效期规则。');
    }

    $opened_at = new DateTime('now', new DateTimeZone('Europe/Madrid'));
    $expires_at = clone $opened_at;
    $time_left_text = 'N/A';

    switch ($material['expiry_rule_type']) {
        case 'HOURS':
            $duration = (int)$material['expiry_duration'];
            $expires_at->add(new DateInterval('PT' . $duration . 'H'));
            $time_left_text = $duration . '小时';
            break;
        case 'DAYS':
            $duration = (int)$material['expiry_duration'];
            $expires_at->add(new DateInterval('P' . $duration . 'D'));
            $time_left_text = $duration . '天';
            break;
        case 'END_OF_DAY':
            $expires_at->setTime(23, 59, 59);
            $time_left_text = '至当日结束';
            break;
    }

    $pdo->beginTransaction();

    // --- CORE FIX: Removed the non-existent 'opened_by_id' column from the INSERT statement ---
    $stmt_expiry = $pdo->prepare(
        "INSERT INTO kds_material_expiries (material_id, store_id, opened_at, expires_at, status) VALUES (?, ?, ?, ?, 'ACTIVE')"
    );
    $stmt_expiry->execute([
        $material_id,
        $store_id,
        $opened_at->format('Y-m-d H:i:s'),
        $expires_at->format('Y-m-d H:i:s')
    ]);
    
    $pdo->commit();

    // (Plan II-3) 准备打印数据包
    $print_data = [
        'material_name' => $material['name_zh'] ?? 'N/A',
        'material_name_es' => $material['name_es'] ?? ($material['name_zh'] ?? 'N/A'),
        'opened_at_time' => $opened_at->format('Y-m-d H:i'),
        'expires_at_time' => $expires_at->format('Y-m-d H:i'),
        'time_left' => $time_left_text, // 简易的剩余时间文本
        'operator_name' => $operator_name
    ];

    send_json_response('success', '效期记录已生成。', ['print_data' => $print_data]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    // Provide a more detailed error message for debugging
    send_json_response('error', '服务器内部错误。', ['debug' => $e->getMessage()]);
}
