<?php
/**
 * Toptea HQ - Correct Invoice API
 * Implements the Factura Rectificativa process (R5) as required by Spanish SIF regulations.
 * Engineer: Gemini | Date: 2025-10-26
 */
require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); send_json_response('error', 'Invalid request method.'); }

$json_data = json_decode(file_get_contents('php://input'), true);
$original_invoice_id = (int)($json_data['id'] ?? 0);
$correction_type = $json_data['type'] ?? '';
$new_total_str = $json_data['new_total'] ?? null;
$reason = trim($json_data['reason'] ?? '');

if ($original_invoice_id <= 0 || !in_array($correction_type, ['S', 'I']) || empty($reason)) {
    http_response_code(400); send_json_response('error', '请求参数无效 (ID, 类型, 原因)。');
}
if ($correction_type === 'I' && ($new_total_str === null || !is_numeric($new_total_str) || (float)$new_total_str < 0)) {
    http_response_code(400); send_json_response('error', '按差额更正时，必须提供一个有效的、非负的最终总额。');
}

try {
    $pdo->beginTransaction();

    $stmt_original = $pdo->prepare("SELECT * FROM pos_invoices WHERE id = ? FOR UPDATE");
    $stmt_original->execute([$original_invoice_id]);
    $original_invoice = $stmt_original->fetch();

    if (!$original_invoice) { throw new Exception("原始票据不存在。"); }
    if ($original_invoice['status'] === 'CANCELLED') { throw new Exception("已作废的票据不能被更正。"); }

    $compliance_system = $original_invoice['compliance_system'];
    $store_id = $original_invoice['store_id'];

    $handler_path = realpath(__DIR__ . "/../../../../../store/store_html/pos_backend/compliance/{$compliance_system}Handler.php");
    if (!$handler_path || !file_exists($handler_path)) { throw new Exception("合规处理器 '{$compliance_system}' 未找到。"); }
    require_once $handler_path;
    $handler_class = "{$compliance_system}Handler";
    $handler = new $handler_class();

    $stmt_store = $pdo->prepare("SELECT tax_id, default_vat_rate FROM kds_stores WHERE id = ?");
    $stmt_store->execute([$store_id]);
    $store_config = $stmt_store->fetch();
    $issuer_nif = $store_config['tax_id'];
    $vat_rate = $store_config['default_vat_rate'];

    // Calculate financials for the new corrective invoice
    if ($correction_type === 'S') { // Full Replacement
        $final_total = -$original_invoice['final_total'];
    } else { // By Difference
        $new_total = (float)$new_total_str;
        $final_total = $new_total - (float)$original_invoice['final_total'];
    }
    $taxable_base = round($final_total / (1 + ($vat_rate / 100)), 2);
    $vat_amount = $final_total - $taxable_base;

    // Prepare data for the new RF-alta (R5) record
    $series = $original_invoice['series'];
    $issued_at = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s.u');
    
    $stmt_prev = $pdo->prepare("SELECT compliance_data FROM pos_invoices WHERE compliance_system = ? AND series = ? AND issuer_nif = ? ORDER BY `number` DESC LIMIT 1");
    $stmt_prev->execute([$compliance_system, $series, $issuer_nif]);
    $prev_invoice = $stmt_prev->fetch();
    $previous_hash = $prev_invoice ? (json_decode($prev_invoice['compliance_data'], true)['hash'] ?? null) : null;
    
    $next_number = 1 + ($pdo->query("SELECT IFNULL(MAX(number), 0) FROM pos_invoices WHERE compliance_system = '{$compliance_system}' AND series = '{$series}' AND issuer_nif = '{$issuer_nif}'")->fetchColumn());
    
    $invoiceData = ['series' => $series, 'number' => $next_number, 'issued_at' => $issued_at, 'final_total' => $final_total];
    $compliance_data = $handler->generateComplianceData($pdo, $invoiceData, $previous_hash);

    // Insert the new corrective invoice
    $sql_corrective = "
        INSERT INTO pos_invoices (
            invoice_uuid, store_id, user_id, issuer_nif, series, `number`, issued_at, 
            invoice_type, status, correction_type, references_invoice_id, 
            compliance_system, compliance_data, taxable_base, vat_amount, final_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'R5', 'ISSUED', ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt_corrective = $pdo->prepare($sql_corrective);
    $stmt_corrective->execute([
        uniqid('cor-', true), $store_id, $_SESSION['user_id'] ?? 1, $issuer_nif,
        $series, $next_number, $issued_at,
        $correction_type, $original_invoice_id,
        $compliance_system, json_encode($compliance_data),
        $taxable_base, $vat_amount, $final_total
    ]);
    $corrective_invoice_id = $pdo->lastInsertId();

    // Note: The original invoice status remains 'ISSUED'.
    $pdo->commit();

    send_json_response('success', '更正票据已成功生成。', ['corrective_invoice_id' => $corrective_invoice_id]);

} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    send_json_response('error', '生成更正票据失败。', ['debug' => $e->getMessage()]);
}