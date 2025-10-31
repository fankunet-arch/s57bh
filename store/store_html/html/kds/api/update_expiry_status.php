<?php
/**
 * Toptea Store - KDS API
 * API Endpoint to update the status of an expiry item
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 8.0 (Path & Auth Fix)
 */

// --- CORE FIX: Path now relative to the KDS environment ---
require_once realpath(__DIR__ . '/../../../kds/core/config.php');

header('Content-Type: application/json; charset=utf-8');
@session_start();

function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response('error', 'Invalid request method.');
}

// --- AUTHENTICATION: This now works correctly ---
if (!isset($_SESSION['kds_user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: No user session found.']);
    exit;
}

$json_data = json_decode(file_get_contents('php://input'), true);
$item_id = (int)($json_data['item_id'] ?? 0);
$new_status = (string)($json_data['status'] ?? '');
$handler_id = (int)($_SESSION['kds_user_id']);
$store_id = (int)($_SESSION['kds_store_id']);

if ($item_id <= 0 || !in_array($new_status, ['USED', 'DISCARDED'])) {
    http_response_code(400);
    send_json_response('error', '无效的项目ID或状态。');
}

try {
    $pdo->beginTransaction();

    // --- SECURITY FIX: Ensure the item belongs to the current store ---
    $stmt = $pdo->prepare(
        "UPDATE kds_material_expiries SET status = ?, handler_id = ?, handled_at = CURRENT_TIMESTAMP WHERE id = ? AND store_id = ? AND status = 'ACTIVE'"
    );
    $stmt->execute([$new_status, $handler_id, $item_id, $store_id]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        send_json_response('success', '状态更新成功。');
    } else {
        $pdo->rollBack();
        http_response_code(404);
        send_json_response('error', '未找到项目、项目已被更新或不属于本店。');
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    send_json_response('error', '服务器内部错误。', ['debug' => $e->getMessage()]);
}