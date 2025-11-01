<?php
/**
 * Toptea Store - KDS API
 * API Endpoint for KDS to fetch preppable materials
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 8.1 (New Classification Logic)
 */
require_once realpath(__DIR__ . '/../../../kds/core/config.php');

header('Content-Type: application/json; charset=utf-8');
@session_start();

if (!isset($_SESSION['kds_store_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: No store session found.']);
    exit;
}

try {
    // --- CORE FIX: The entire query and logic is updated ---
    $sql = "
        SELECT 
            m.id,
            m.material_type,
            mt.material_name AS name_zh,
            mt_es.material_name AS name_es
        FROM kds_materials m
        JOIN kds_material_translations mt ON m.id = mt.material_id AND mt.language_code = 'zh-CN'
        JOIN kds_material_translations mt_es ON m.id = mt_es.material_id AND mt_es.language_code = 'es-ES'
        WHERE m.deleted_at IS NULL
          AND m.expiry_rule_type IS NOT NULL -- Crucial filter: only items needing tracking appear
        ORDER BY m.material_code ASC
    ";
    $stmt = $pdo->query($sql);
    $all_materials = $stmt->fetchAll();

    $response_data = [
        'packaged_goods' => [], // '开封物料' -> Shows PRODUCT type
        'in_store_preps' => []  // '门店现制' -> Shows RAW type
    ];

    foreach ($all_materials as $material) {
        // According to our new plan:
        // '成品/直销品' (PRODUCT) goes to the "Open" list.
        if ($material['material_type'] === 'PRODUCT') {
            $response_data['packaged_goods'][] = $material;
        } 
        // '原料' (RAW) goes to the "Prep" list.
        elseif ($material['material_type'] === 'RAW') {
            $response_data['in_store_preps'][] = $material;
        }
        // SEMI_FINISHED and CONSUMABLE types are ignored on this screen.
    }

    echo json_encode(['status' => 'success', 'data' => $response_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch materials.', 'debug' => $e->getMessage()]);
}