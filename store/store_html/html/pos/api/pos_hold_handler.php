<?php
/**
 * TopTea POS - Hold Order Management API
 * Engineer: Gemini | Date: 2025-10-27 | Revision: 2.0 (Forced Note, Sorting & Restore Fix)
 */
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

$action = $_GET['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $json_data['action'] ?? $action;
}

// SIMULATION: In a real system, these would come from the logged-in user's session
$store_id = 1; 
$user_id = 1;

try {
    switch ($action) {
        case 'list':
            $sort_by = $_GET['sort'] ?? 'time_desc';
            $order_clause = 'created_at DESC'; // Default sort
            if ($sort_by === 'amount_desc') {
                $order_clause = 'total_amount DESC';
            }

            // Select new total_amount column
            $stmt = $pdo->prepare("SELECT id, note, created_at, total_amount FROM pos_held_orders WHERE store_id = ? ORDER BY $order_clause");
            $stmt->execute([$store_id]);
            $held_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_json_response('success', 'Held orders retrieved.', $held_orders);
            break;

        case 'save':
            $note = trim($json_data['note'] ?? '');
            $cart_data = $json_data['cart'] ?? [];

            // --- FEATURE: Enforce note ---
            if (empty($note)) {
                http_response_code(400);
                send_json_response('error', '备注/桌号不能为空 (Note cannot be empty).');
            }

            if (empty($cart_data)) {
                http_response_code(400);
                send_json_response('error', '不能挂起一个空的购物车 (Cannot hold an empty cart).');
            }
            
            // --- FEATURE: Calculate and store total amount ---
            $total_amount = 0;
            foreach ($cart_data as $item) {
                $total_amount += ($item['unit_price_eur'] ?? 0) * ($item['qty'] ?? 1);
            }

            $stmt = $pdo->prepare("INSERT INTO pos_held_orders (store_id, user_id, note, cart_data, total_amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$store_id, $user_id, $note, json_encode($cart_data), $total_amount]);
            $new_id = $pdo->lastInsertId();
            send_json_response('success', 'Order held successfully.', ['id' => $new_id]);
            break;

        case 'restore':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { http_response_code(400); send_json_response('error', 'Invalid hold ID.'); }

            $pdo->beginTransaction();
            $stmt_get = $pdo->prepare("SELECT cart_data FROM pos_held_orders WHERE id = ? AND store_id = ? FOR UPDATE");
            $stmt_get->execute([$id, $store_id]);
            $cart_json = $stmt_get->fetchColumn();
            
            if ($cart_json === false || empty($cart_json)) {
                $pdo->rollBack();
                http_response_code(404);
                send_json_response('error', 'Held order not found or is empty.');
            }
            
            $cart_data = json_decode($cart_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $pdo->rollBack();
                http_response_code(500);
                send_json_response('error', 'Failed to parse held cart data.');
            }

            $stmt_delete = $pdo->prepare("DELETE FROM pos_held_orders WHERE id = ?");
            $stmt_delete->execute([$id]);
            $pdo->commit();
            
            // --- FIX: Return a structured response that JS expects ---
            send_json_response('success', 'Order restored.', $cart_data);
            break;

        default:
            http_response_code(400);
            send_json_response('error', 'Invalid action requested.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    send_json_response('error', 'An error occurred.', ['debug' => $e->getMessage()]);
}