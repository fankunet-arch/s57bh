<?php
/**
 * POS · EOD 状态检测 (稳健版 V2)
 * - 仅检查“昨日业务日”(Europe/Madrid) 是否已结。
 * - 这是一个简化的检查，依赖于 EOD 处理器能正确处理补结日期。
 * - 不依赖 CONVERT_TZ。
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../pos_backend/core/config.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('DB connection ($pdo) not initialized.');
    }

    $tzMadrid = new DateTimeZone('Europe/Madrid');
    $utc = new DateTimeZone('UTC');

    // 我们要检查的日期是“昨天”（相对于马德里时区）
    $yesterday_date_str = (new DateTime('yesterday', $tzMadrid))->format('Y-m-d');

    $store_id = (int)($_GET['store_id'] ?? 1);

    // 检查是否已存在昨天的日结报告
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM pos_eod_reports WHERE store_id = :store_id AND report_date = :report_date");
    $stmt_check->execute([
        ':store_id' => $store_id,
        ':report_date' => $yesterday_date_str
    ]);
    $report_exists = (int)$stmt_check->fetchColumn() > 0;

    if ($report_exists) {
        // 如果报告已存在，一切正常
        echo json_encode([
            'status' => 'success',
            'data' => [ 'previous_day_unclosed' => false, 'unclosed_date' => null ]
        ]);
        exit;
    }

    // 如果报告不存在，检查昨天是否有交易，以确定是否需要补结
    // 1. 计算 "昨天" 在 UTC 时间下的起止范围
    $yesterday_start_utc = (new DateTime($yesterday_date_str . ' 00:00:00', $tzMadrid))->setTimezone($utc)->format('Y-m-d H:i:s');
    $yesterday_end_utc   = (new DateTime($yesterday_date_str . ' 23:59:59', $tzMadrid))->setTimezone($utc)->format('Y-m-d H:i:s');

    // 2. 在该 UTC 范围内查找发票
    $stmt_invoice = $pdo->prepare(
        "SELECT 1 FROM pos_invoices WHERE store_id = :store_id AND issued_at BETWEEN :start_utc AND :end_utc LIMIT 1"
    );
    $stmt_invoice->execute([
        ':store_id' => $store_id,
        ':start_utc' => $yesterday_start_utc,
        ':end_utc' => $yesterday_end_utc
    ]);
    $invoice_exists = $stmt_invoice->fetchColumn() !== false;
    
    if ($invoice_exists) {
        // 报告不存在，但有交易 -> 需要补结
        echo json_encode([
            'status' => 'success',
            'data' => [ 'previous_day_unclosed' => true, 'unclosed_date' => $yesterday_date_str ]
        ]);
    } else {
        // 报告不存在，也没有交易 -> 无需操作
        echo json_encode([
            'status' => 'success',
            'data' => [ 'previous_day_unclosed' => false, 'unclosed_date' => null ]
        ]);
    }

} catch (Throwable $e) {
    error_log('[POS][check_eod_status fatal] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'检查日结状态失败: ' . $e->getMessage()]);
}