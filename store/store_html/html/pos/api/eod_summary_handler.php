<?php
/**
 * POS · EOD（日结/补结算）处理器（V6 - 最终日期逻辑修复版）
 * - [最终修复] 默认目标日期从 'yesterday' 修正为 'today'，与前端“今日日结”意图匹配。
 * - 统一 get_preview 和 submit_report 的数据计算逻辑，确保预览数据与最终数据完全一致。
 * - 加固了支付汇总的JSON解析逻辑，确保正确累加所有历史格式的支付数据。
 */
header('Content-Type: application/json; charset=utf-8');

/**
 * 核心函数：计算指定日期范围内的所有发票汇总信息。
 * 返回一个包含交易总览和支付方式分类汇总的数组。
 */
function getInvoiceSummaryForPeriod(PDO $pdo, int $store_id, string $start_utc, string $end_utc): array {
    $invoices_table = 'pos_invoices';

    // 1. 计算交易总览（笔数、销售额、折扣等）
    $sqlInv = "SELECT 
                   COUNT(*) AS transactions_count,
                   COALESCE(SUM(taxable_base + vat_amount), 0) AS system_gross_sales,
                   COALESCE(SUM(discount_amount), 0) AS system_discounts,
                   COALESCE(SUM(final_total), 0) AS system_net_sales,
                   COALESCE(SUM(vat_amount), 0) AS system_tax
               FROM `{$invoices_table}`
               WHERE store_id=:sid AND issued_at BETWEEN :s AND :e AND status = 'ISSUED'";
    
    $st = $pdo->prepare($sqlInv);
    $st->execute([':sid' => $store_id, ':s' => $start_utc, ':e' => $end_utc]);
    $summary = $st->fetch(PDO::FETCH_ASSOC);

    // 2. 计算支付方式分类汇总
    $sqlPay = "SELECT payment_summary FROM `{$invoices_table}` WHERE store_id=:sid AND issued_at BETWEEN :s AND :e AND status = 'ISSUED'";
    $stmtPay = $pdo->prepare($sqlPay);
    $stmtPay->execute([':sid' => $store_id, ':s' => $start_utc, ':e' => $end_utc]);
    
    $breakdown = ['Cash' => 0.0, 'Card' => 0.0, 'Platform' => 0.0];
    
    while ($row = $stmtPay->fetch(PDO::FETCH_ASSOC)) {
        if (empty($row['payment_summary'])) continue;
        $payment_data = json_decode($row['payment_summary'], true);
        if (!is_array($payment_data)) continue;
        
        // --- START OF FINAL FIX: Handle all known formats ---
        if (isset($payment_data['summary']) && is_array($payment_data['summary'])) {
            $summary_part = $payment_data['summary'];
            // NEW FORMAT: summary is an array of objects, e.g., [{method:'Cash', amount:X}]
            if (isset($summary_part[0]) || empty($summary_part)) {
                foreach ($summary_part as $part) {
                    if (isset($part['method'], $part['amount']) && isset($breakdown[$part['method']])) {
                        $breakdown[$part['method']] += (float)$part['amount'];
                    }
                }
            } 
            // OLD FORMAT 1: summary is an object, e.g., {cash: X, card: Y}
            else {
                 if (isset($summary_part['cash'])) $breakdown['Cash'] += (float)$summary_part['cash'];
                 if (isset($summary_part['card'])) $breakdown['Card'] += (float)$summary_part['card'];
                 if (isset($summary_part['platform'])) $breakdown['Platform'] += (float)$summary_part['platform'];
            }
        } 
        // OLDEST FORMAT: no 'summary' key, top-level keys, e.g., {cash: X, card: Y}
        else {
            if (isset($payment_data['cash'])) $breakdown['Cash'] += (float)$payment_data['cash'];
            if (isset($payment_data['card'])) $breakdown['Card'] += (float)$payment_data['card'];
            if (isset($payment_data['platform'])) $breakdown['Platform'] += (float)$payment_data['platform'];
        }
        
        // Handle change amount for all formats
        if (isset($payment_data['change']) && (float)$payment_data['change'] > 0) {
            // Subtract change from cash, as change is given in cash
            $breakdown['Cash'] -= (float)$payment_data['change'];
        }
        // --- END OF FINAL FIX ---
    }
    
    foreach($breakdown as &$value) {
        $value = max(0, round($value, 2)); // Final sanity check to avoid negative amounts
    }

    // 3. 组合最终结果
    return [
        'summary' => $summary,
        'payments' => $breakdown
    ];
}


try {
    require_once __DIR__ . '/../../../pos_backend/core/config.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('DB connection ($pdo) not initialized.');
    }
    
    $json_data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $_GET['action'] ?? $json_data['action'] ?? null;
    if (!$action) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Action not specified.']);
        exit;
    }

    $tzMadrid = new DateTimeZone('Europe/Madrid');
    $utc      = new DateTimeZone('UTC');
    $store_id = (int)($_SESSION['pos_store_id'] ?? $_REQUEST['store_id'] ?? $json_data['store_id'] ?? 1);

    $target_business_date = null;
    $date_input = $_GET['target_business_date'] ?? $json_data['target_business_date'] ?? null;
    if ($date_input) {
        $d = DateTime::createFromFormat('Y-m-d', $date_input, $tzMadrid);
        if ($d !== false) $target_business_date = $d->format('Y-m-d');
    }
    
    // --- START OF FINAL DATE LOGIC FIX ---
    if ($target_business_date === null) {
        // If no date is specified, default to TODAY for "Today's EOD Report".
        $target_business_date = (new DateTime('today', $tzMadrid))->format('Y-m-d');
    }
    // --- END OF FINAL DATE LOGIC FIX ---
    
    $bd_start_utc = (new DateTime($target_business_date . ' 00:00:00', $tzMadrid))->setTimezone($utc)->format('Y-m-d H:i:s');
    $bd_end_utc   = (new DateTime($target_business_date . ' 23:59:59', $tzMadrid))->setTimezone($utc)->format('Y-m-d H:i:s');

    $eod_table = 'pos_eod_reports';

    $existing_report = null;
    $sql_check = "SELECT * FROM `{$eod_table}` WHERE store_id=:sid AND report_date = :bd LIMIT 1";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':sid' => $store_id, ':bd' => $target_business_date]);
    $existing_report = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($action === 'get_preview') {
        if ($existing_report) {
            echo json_encode(['status' => 'success', 'data' => ['is_submitted' => true, 'existing_report' => $existing_report]]);
            exit;
        }

        // 使用统一的计算函数
        $full_summary = getInvoiceSummaryForPeriod($pdo, $store_id, $bd_start_utc, $bd_end_utc);
        
        // 构建返回给前端的预览数据
        $preview_data = [
            'transactions_count'   => $full_summary['summary']['transactions_count'],
            'system_gross_sales'   => $full_summary['summary']['system_gross_sales'],
            'system_discounts'     => $full_summary['summary']['system_discounts'],
            'system_net_sales'     => $full_summary['summary']['system_net_sales'],
            'system_tax'           => $full_summary['summary']['system_tax'],
            'payments'             => $full_summary['payments'],
            'report_date'          => $target_business_date,
            'is_submitted'         => false
        ];

        echo json_encode(['status' => 'success', 'data' => $preview_data]);
        exit;
    } 
    elseif ($action === 'submit_report') {
        if ($existing_report) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => '该业务日已完成日结，不可重复提交。']);
            exit;
        }

        $counted_cash = isset($json_data['counted_cash']) ? (float)$json_data['counted_cash'] : 0.0;
        $notes = isset($json_data['notes']) ? trim($json_data['notes']) : '';

        // 同样使用统一的计算函数，确保数据一致性
        $full_summary = getInvoiceSummaryForPeriod($pdo, $store_id, $bd_start_utc, $bd_end_utc);
        $summary = $full_summary['summary'];
        $payments_breakdown = $full_summary['payments'];
        
        $cash_discrepancy = $counted_cash - $payments_breakdown['Cash'];

        $pdo->beginTransaction();

        $sql_insert = "INSERT INTO `{$eod_table}` (
                           report_date, store_id, user_id, executed_at,
                           transactions_count, system_gross_sales, system_discounts, system_net_sales, system_tax,
                           system_cash, system_card, system_platform,
                           counted_cash, cash_discrepancy, notes
                       ) VALUES (
                           :report_date, :store_id, :user_id, NOW(),
                           :transactions_count, :system_gross_sales, :system_discounts, :system_net_sales, :system_tax,
                           :system_cash, :system_card, :system_platform,
                           :counted_cash, :cash_discrepancy, :notes
                       )";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':report_date' => $target_business_date,
            ':store_id' => $store_id,
            ':user_id' => (int)($_SESSION['pos_user_id'] ?? $json_data['user_id'] ?? 1),
            ':transactions_count' => $summary['transactions_count'],
            ':system_gross_sales' => $summary['system_gross_sales'],
            ':system_discounts' => $summary['system_discounts'],
            ':system_net_sales' => $summary['system_net_sales'],
            ':system_tax' => $summary['system_tax'],
            ':system_cash' => $payments_breakdown['Cash'],
            ':system_card' => $payments_breakdown['Card'],
            ':system_platform' => $payments_breakdown['Platform'],
            ':counted_cash' => $counted_cash,
            ':cash_discrepancy' => $cash_discrepancy,
            ':notes' => $notes
        ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => '日结报告已成功提交。']);
        exit;
    }
    else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
        exit;
    }

} catch (Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[POS][eod_summary_handler] ' . $e->getMessage() . ' at line ' . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'EOD处理时发生服务器内部错误。'], JSON_UNESCAPED_UNICODE);
}