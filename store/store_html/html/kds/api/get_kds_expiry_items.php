<?php
/**
 * Toptea Store - KDS API
 * API Endpoint for KDS to fetch its active expiry items
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 8.0 (Path & Auth Fix)
 */

// --- CORE FIX: Path now relative to the KDS environment ---
require_once realpath(__DIR__ . '/../../../kds/core/config.php');

header('Content-Type: application/json; charset=utf-8');
@session_start();

// --- AUTHENTICATION: This now works because API and KDS share the same session ---
if (!isset($_SESSION['kds_store_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: No store session found.']);
    exit;
}

$store_id = (int)$_SESSION['kds_store_id'];

try {
    $sql = "
        SELECT 
            e.id,
            e.batch_code,
            e.opened_at,
            e.expires_at,
            mt_zh.material_name AS name_zh,
            mt_es.material_name AS name_es
        FROM kds_material_expiries e
        JOIN kds_material_translations mt_zh ON e.material_id = mt_zh.material_id AND mt_zh.language_code = 'zh-CN'
        JOIN kds_material_translations mt_es ON e.material_id = mt_es.material_id AND mt_es.language_code = 'es-ES'
        WHERE e.store_id = ? AND e.status = 'ACTIVE'
        ORDER BY e.expires_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$store_id]);
    $items = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'data' => $items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch expiry items.', 'debug' => $e->getMessage()]);
}