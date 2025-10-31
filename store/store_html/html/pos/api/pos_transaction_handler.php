<?php
/**
 * TopTea POS - Transaction Query API
 * Provides endpoints for listing recent transactions and fetching details.
 * Engineer: Gemini | Date: 2025-10-29 | Revision: 1.1 (API Auth Integration)
 */
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
// CORE FIX: Use the API-specific authentication core.
require_once realpath(__DIR__ . '/../../../pos_backend/core/api_auth_core.php');

header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

$action = $_GET['action'] ?? null;

// CORE FIX: Get store_id from the secure session instead of hardcoding
$store_id = (int)$_SESSION['pos_store_id'];

try {
    switch ($action) {
        case 'list':
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;

            $sql = "
                SELECT id, series, number, issued_at, final_total, status
                FROM pos_invoices
                WHERE store_id = :store_id
            ";
            $params = [':store_id' => $store_id];

            if ($start_date && $end_date) {
                // To include the whole end day, we go to the start of the next day
                $end_date_obj = new DateTime($end_date);
                $end_date_obj->modify('+1 day');
                $end_date_exclusive = $end_date_obj->format('Y-m-d');
                
                $sql .= " AND issued_at >= :start_date AND issued_at < :end_date_exclusive";
                $params[':start_date'] = $start_date;
                $params[':end_date_exclusive'] = $end_date_exclusive;
            }

            $sql .= " ORDER BY issued_at DESC LIMIT 200"; // Increased limit for filtered queries

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_json_response('success', 'Transactions retrieved.', $transactions);
            break;

        case 'get_details':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                send_json_response('error', 'Invalid Invoice ID.');
            }

            // Fetch main invoice data
            $stmt_invoice = $pdo->prepare("
                SELECT pi.*, cu.display_name AS cashier_name
                FROM pos_invoices pi
                LEFT JOIN cpsys_users cu ON pi.user_id = cu.id
                WHERE pi.id = ? AND pi.store_id = ?
            ");
            $stmt_invoice->execute([$id, $store_id]);
            $invoice = $stmt_invoice->fetch();

            if (!$invoice) {
                http_response_code(404);
                send_json_response('error', 'Invoice not found.');
            }

            // Fetch invoice items
            $stmt_items = $pdo->prepare("SELECT * FROM pos_invoice_items WHERE invoice_id = ?");
            $stmt_items->execute([$id]);
            $invoice['items'] = $stmt_items->fetchAll();

            // Decode JSON fields for easier frontend consumption
            $invoice['payment_summary_decoded'] = json_decode($invoice['payment_summary'] ?? '[]', true);
            $invoice['compliance_data_decoded'] = json_decode($invoice['compliance_data'] ?? '[]', true);

            send_json_response('success', 'Invoice details retrieved.', $invoice);
            break;

        default:
            http_response_code(400);
            send_json_response('error', 'Invalid action requested.');
    }
} catch (Exception $e) {
    http_response_code(500);
    send_json_response('error', 'An error occurred.', ['debug' => $e->getMessage()]);
}