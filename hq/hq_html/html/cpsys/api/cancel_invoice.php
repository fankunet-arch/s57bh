<?php
/**
 * Toptea HQ - Cancel Invoice API
 * Implements the RF-Anulación process as required by Spanish SIF regulations.
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 1.5 (Reason Sync Fix)
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); send_json_response('error', 'Invalid request method.'); }

$json_data = json_decode(file_get_contents('php://input'), true);
$original_invoice_id = (int)($json_data['id'] ?? 0);
$cancellation_reason = trim($json_data['reason'] ?? 'Error en la emisión');

if ($original_invoice_id <= 0) { http_response_code(400); send_json_response('error', '无效的原始票据ID。'); }

try {
    $pdo->beginTransaction();

    $stmt_original = $pdo->prepare("SELECT * FROM pos_invoices WHERE id = ? FOR UPDATE");
    $stmt_original->execute([$original_invoice_id]);
    $original_invoice = $stmt_original->fetch();

    if (!$original_invoice) { throw new Exception("原始票据不存在。"); }
    if ($original_invoice['status'] === 'CANCELLED') { throw new Exception("此票据已被作废，无法重复操作。"); }

    $compliance_system = $original_invoice['compliance_system'];
    $store_id = $original_invoice['store_id'];

    $handler_path = realpath(__DIR__ . "/../../../../../store/store_html/pos_backend/compliance/{$compliance_system}Handler.php");
    if (!$handler_path || !file_exists($handler_path)) {
        throw new Exception("Compliance handler for '{$compliance_system}' not found at expected path. Calculated path: " . $handler_path);
    }
    require_once $handler_path;
    $handler_class = "{$compliance_system}Handler";
    $handler = new $handler_class();

    $series = $original_invoice['series'];
    $issued_at = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s.u');
    
    $stmt_store = $pdo->prepare("SELECT tax_id FROM kds_stores WHERE id = ?");
    $stmt_store->execute([$store_id]);
    $store_config = $stmt_store->fetch();
    $issuer_nif = $store_config['tax_id'];

    $stmt_prev = $pdo->prepare("SELECT compliance_data FROM pos_invoices WHERE compliance_system = ? AND series = ? AND issuer_nif = ? ORDER BY `number` DESC LIMIT 1");
    $stmt_prev->execute([$compliance_system, $series, $issuer_nif]);
    $prev_invoice = $stmt_prev->fetch();
    $previous_hash = $prev_invoice ? (json_decode($prev_invoice['compliance_data'], true)['hash'] ?? null) : null;
    
    $cancellationData = ['cancellation_reason' => $cancellation_reason, 'issued_at' => $issued_at];
    $compliance_data = $handler->generateCancellationData($pdo, $original_invoice, $cancellationData, $previous_hash);
    
    $next_number = 1 + ($pdo->query("SELECT IFNULL(MAX(number), 0) FROM pos_invoices WHERE compliance_system = '{$compliance_system}' AND series = '{$series}' AND issuer_nif = '{$issuer_nif}'")->fetchColumn());

    $sql_cancel = "
        INSERT INTO pos_invoices (
            invoice_uuid, store_id, user_id, issuer_nif, series, `number`, issued_at, 
            invoice_type, status, cancellation_reason, references_invoice_id, 
            compliance_system, compliance_data, taxable_base, vat_amount, final_total
        ) VALUES ( ?, ?, ?, ?, ?, ?, ?, 'R5', 'ISSUED', ?, ?, ?, ?, 0.00, 0.00, 0.00 )
    ";
    
    $stmt_cancel = $pdo->prepare($sql_cancel);
    $stmt_cancel->execute([
        uniqid('can-', true),
        $store_id,
        $_SESSION['user_id'] ?? 1,
        $issuer_nif,
        $series,
        $next_number,
        $issued_at,
        $cancellation_reason,
        $original_invoice_id,
        $compliance_system,
        json_encode($compliance_data)
    ]);
    $cancellation_invoice_id = $pdo->lastInsertId();

    // CORE FIX: The UPDATE statement now also sets the 'cancellation_reason' on the original invoice.
    $stmt_update_original = $pdo->prepare("UPDATE pos_invoices SET status = 'CANCELLED', cancellation_reason = ? WHERE id = ?");
    $stmt_update_original->execute([$cancellation_reason, $original_invoice_id]);

    $pdo->commit();

    send_json_response('success', '票据已成功作废并生成作废记录。', ['cancellation_invoice_id' => $cancellation_invoice_id]);

} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    send_json_response('error', '作废票据失败。', ['debug' => $e->getMessage()]);
}