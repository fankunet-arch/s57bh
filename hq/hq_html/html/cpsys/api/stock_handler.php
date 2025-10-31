<?php
/**
 * Toptea HQ - cpsys
 * API Handler for Stock Management
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 7.9 (Final Review & Polish)
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';
header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    if (isset($json_data['action'])) { $action = $json_data['action']; }
}

try {
    switch ($action) {
        case 'add_warehouse_stock':
            handleAddWarehouseStock($pdo, $json_data['data']);
            break;
        case 'allocate_to_store':
            handleAllocateToStore($pdo, $json_data['data']);
            break;
        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    send_json_response('error', '服务器内部错误。', ['debug' => $e->getMessage()]);
}

function handleAddWarehouseStock($pdo, $data) {
    $material_id = (int)($data['material_id'] ?? 0);
    $quantity_to_add = (float)($data['quantity'] ?? 0);
    $unit_id = (int)($data['unit_id'] ?? 0);

    if ($material_id <= 0 || $quantity_to_add <= 0 || $unit_id <= 0) {
        send_json_response('error', '物料、数量和单位均为必填项。');
    }

    $material = getMaterialById($pdo, $material_id);
    if (!$material) {
        http_response_code(404);
        send_json_response('error', '找不到指定的物料。');
    }

    $final_quantity_to_add = $quantity_to_add;

    if ($material['large_unit_id'] == $unit_id) {
        if (empty($material['conversion_rate']) || $material['conversion_rate'] <= 0) {
            send_json_response('error', '该物料的大单位换算率未设置或无效。');
        }
        $final_quantity_to_add = $quantity_to_add * (float)$material['conversion_rate'];
    }

    $pdo->beginTransaction();
    $sql = "
        INSERT INTO expsys_warehouse_stock (material_id, quantity) 
        VALUES (:material_id, :quantity)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':material_id' => $material_id, ':quantity' => $final_quantity_to_add]);
    $pdo->commit();
    send_json_response('success', '总仓入库成功！');
}

function handleAllocateToStore($pdo, $data) {
    $store_id = (int)($data['store_id'] ?? 0);
    $material_id = (int)($data['material_id'] ?? 0);
    $quantity_to_allocate = (float)($data['quantity'] ?? 0);
    $unit_id = (int)($data['unit_id'] ?? 0);

    if ($store_id <= 0 || $material_id <= 0 || $quantity_to_allocate <= 0 || $unit_id <= 0) {
        send_json_response('error', '门店、物料、数量和单位均为必填项。');
    }

    $material = getMaterialById($pdo, $material_id);
    if (!$material) {
        http_response_code(404);
        send_json_response('error', '找不到指定的物料。');
    }

    $final_quantity_to_allocate = $quantity_to_allocate;
    if ($material['large_unit_id'] == $unit_id) {
        if (empty($material['conversion_rate']) || $material['conversion_rate'] <= 0) {
            send_json_response('error', '该物料的大单位换算率未设置或无效。');
        }
        $final_quantity_to_allocate = $quantity_to_allocate * (float)$material['conversion_rate'];
    }

    $pdo->beginTransaction();

    // 1. Decrement warehouse stock
    $stmt_warehouse = $pdo->prepare("
        INSERT INTO expsys_warehouse_stock (material_id, quantity) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity - ?;
    ");
    $stmt_warehouse->execute([$material_id, -$final_quantity_to_allocate, $final_quantity_to_allocate]);

    // 2. Increment store stock
    $stmt_store = $pdo->prepare("
        INSERT INTO expsys_store_stock (store_id, material_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + ?;
    ");
    $stmt_store->execute([$store_id, $material_id, $final_quantity_to_allocate, $final_quantity_to_allocate]);

    $pdo->commit();
    send_json_response('success', '库存调拨成功！');
}