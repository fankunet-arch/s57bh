<?php
/**
 * TopTea POS · Shift Management API
 * v1.5.1 (fix: no DDL inside transaction; stable time & tx guards)
 *
 * Actions: status | start | end
 */
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
require_once realpath(__DIR__ . '/../../../pos_backend/core/api_auth_core.php');

header('Content-Type: application/json; charset=utf-8');

/* ---------- helpers ---------- */
function send_json(string $status, string $message, $data = null, int $http = 200): void {
    http_response_code($http);
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}
function col_exists(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $col]);
    return (bool)$stmt->fetchColumn();
}

/**
 * 计算 expected_cash
 * 口径：starting_float + cash_sales + cash_in - cash_out - cash_refunds
 * 时间窗：本班次 start_time ~ end_time(UTC)
 */
function compute_expected_cash(PDO $pdo, int $store_id, string $start_iso, string $end_iso, float $starting_float): array {
    $cash_sales   = 0.0;
    $cash_in      = 0.0;
    $cash_out     = 0.0;
    $cash_refunds = 0.0;

    // 1) 优先用 pos_payments(method='CASH')
    if (table_exists($pdo, 'pos_payments')) {
        $ts_col  = col_exists($pdo,'pos_payments','paid_at')    ? 'paid_at'
                : (col_exists($pdo,'pos_payments','created_at') ? 'created_at' : null);
        $amt_col = col_exists($pdo,'pos_payments','amount_eur') ? 'amount_eur'
                : (col_exists($pdo,'pos_payments','amount')     ? 'amount'     : null);
        if ($ts_col && $amt_col && col_exists($pdo,'pos_payments','method')) {
            // 现金销售（正额，排除退款）
            $sql = "SELECT COALESCE(SUM($amt_col),0) FROM pos_payments
                    WHERE store_id=? AND method='CASH' AND $ts_col BETWEEN ? AND ?
                      AND (NOT (COALESCE(is_refund,0)=1))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$store_id, $start_iso, $end_iso]);
            $cash_sales = (float)$stmt->fetchColumn();

            // 现金退款
            if (col_exists($pdo, 'pos_payments', 'is_refund')) {
                $sql = "SELECT COALESCE(SUM($amt_col),0) FROM pos_payments
                        WHERE store_id=? AND method='CASH' AND COALESCE(is_refund,0)=1
                          AND $ts_col BETWEEN ? AND ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$store_id, $start_iso, $end_iso]);
                $cash_refunds = (float)$stmt->fetchColumn();
            } else {
                // 无 is_refund 字段时：负金额视作退款
                $sql = "SELECT COALESCE(SUM(CASE WHEN $amt_col<0 THEN -$amt_col ELSE 0 END),0)
                        FROM pos_payments
                        WHERE store_id=? AND method='CASH' AND $ts_col BETWEEN ? AND ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$store_id, $start_iso, $end_iso]);
                $cash_refunds = (float)$stmt->fetchColumn();
            }
        }
    }
    // 2) 回退：pos_orders（payment_method='CASH'）
    elseif (table_exists($pdo, 'pos_orders')) {
        $ts_col  = col_exists($pdo,'pos_orders','paid_at')     ? 'paid_at'
                : (col_exists($pdo,'pos_orders','created_at')  ? 'created_at' : null);
        $amt_col = col_exists($pdo,'pos_orders','grand_total_eur')   ? 'grand_total_eur'
                : (col_exists($pdo,'pos_orders','total_amount_eur') ? 'total_amount_eur'
                : (col_exists($pdo,'pos_orders','total')           ? 'total' : null));
        if ($ts_col && $amt_col && col_exists($pdo,'pos_orders','payment_method')) {
            $where_paid = col_exists($pdo,'pos_orders','status') ? "AND status IN ('PAID','COMPLETED','CLOSED')" : '';
            $sql = "SELECT COALESCE(SUM($amt_col),0) FROM pos_orders
                    WHERE store_id=? AND payment_method='CASH' $where_paid
                      AND $ts_col BETWEEN ? AND ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$store_id, $start_iso, $end_iso]);
            $cash_sales = (float)$stmt->fetchColumn();
        }
        // 退款回退口径：如需可补充
    }

    // 3) 现金出入：pos_cash_movements
    if (table_exists($pdo, 'pos_cash_movements')) {
        $ts_col  = col_exists($pdo,'pos_cash_movements','created_at') ? 'created_at'
                : (col_exists($pdo,'pos_cash_movements','occurred_at') ? 'occurred_at' : null);
        $amt_col = col_exists($pdo,'pos_cash_movements','amount_eur') ? 'amount_eur'
                : (col_exists($pdo,'pos_cash_movements','amount')     ? 'amount'     : null);
        $type_col= col_exists($pdo,'pos_cash_movements','movement_type') ? 'movement_type'
                : (col_exists($pdo,'pos_cash_movements','type')          ? 'type'          : null);
        if ($ts_col && $amt_col && $type_col) {
            // IN
            $sql = "SELECT COALESCE(SUM($amt_col),0) FROM pos_cash_movements
                    WHERE store_id=? AND $type_col IN ('CASH_IN','SAFE_IN','ADJUST_IN')
                      AND $ts_col BETWEEN ? AND ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$store_id, $start_iso, $end_iso]);
            $cash_in = (float)$stmt->fetchColumn();

            // OUT
            $sql = "SELECT COALESCE(SUM($amt_col),0) FROM pos_cash_movements
                    WHERE store_id=? AND $type_col IN ('CASH_OUT','PAYOUT','SAFE_OUT','ADJUST_OUT')
                      AND $ts_col BETWEEN ? AND ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$store_id, $start_iso, $end_iso]);
            $cash_out = (float)$stmt->fetchColumn();
        }
    }

    $expected_cash = (float)$starting_float + (float)$cash_sales + (float)$cash_in - (float)$cash_out - (float)$cash_refunds;

    return [
        'starting_float' => round((float)$starting_float, 2),
        'cash_sales'     => round((float)$cash_sales, 2),
        'cash_in'        => round((float)$cash_in, 2),
        'cash_out'       => round((float)$cash_out, 2),
        'cash_refunds'   => round((float)$cash_refunds, 2),
        'expected_cash'  => round((float)$expected_cash, 2),
    ];
}
/* ---------- helpers end ---------- */

$user_id  = (int)($_SESSION['pos_user_id']  ?? 0);
$store_id = (int)($_SESSION['pos_store_id'] ?? 0);
if ($user_id === 0 || $store_id === 0) {
    send_json('error', 'Unauthorized: invalid session.', null, 401);
}

$action = $_GET['action'] ?? null;
$body   = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== '') {
        $body = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            send_json('error', 'Bad JSON payload.', ['debug' => json_last_error_msg()], 400);
        }
        $action = $body['action'] ?? $action;
    }
}

/* 重要：确保 pos_eod_records 存在（放在事务外，避免隐式提交） */
if ($action === 'end' && !table_exists($pdo, 'pos_eod_records')) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pos_eod_records (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          shift_id BIGINT UNSIGNED NOT NULL,
          store_id BIGINT UNSIGNED NOT NULL,
          user_id  BIGINT UNSIGNED NOT NULL,
          started_at DATETIME NOT NULL,
          ended_at   DATETIME NOT NULL,
          starting_float DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          cash_sales     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          cash_in        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          cash_out       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          cash_refunds   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          expected_cash  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          counted_cash   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          cash_diff      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_store_time (store_id, started_at, ended_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

try {
    switch ($action) {
        case 'status': {
            $stmt = $pdo->prepare(
                "SELECT id, shift_uuid, store_id, user_id, start_time, end_time, status, starting_float, counted_cash
                 FROM pos_shifts
                 WHERE user_id=? AND store_id=? AND status='ACTIVE'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$user_id, $store_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $_SESSION['pos_shift_id'] = (int)$row['id'];
                send_json('success', 'Active shift found.', ['has_active_shift'=>true, 'shift'=>$row]);
            }
            unset($_SESSION['pos_shift_id']);
            send_json('success', 'No active shift.', ['has_active_shift'=>false]);
        }

        case 'start': {
            $starting_float = null;
            if (is_array($body) && array_key_exists('starting_float', $body)) {
                $starting_float = (float)$body['starting_float'];
            } elseif (isset($_POST['starting_float'])) {
                $starting_float = (float)$_POST['starting_float'];
            }
            if (!is_numeric($starting_float) || $starting_float < 0) {
                send_json('error', 'Invalid starting_float.', null, 422);
            }

            $tx_started = false;
            if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $tx_started = true; }

            // 幂等：已有ACTIVE则复用
            $chk = $pdo->prepare(
                "SELECT id, shift_uuid, store_id, user_id, start_time, end_time, status, starting_float, counted_cash
                 FROM pos_shifts
                 WHERE user_id=? AND store_id=? AND status='ACTIVE'
                 ORDER BY id DESC LIMIT 1
                 FOR UPDATE"
            );
            $chk->execute([$user_id, $store_id]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $_SESSION['pos_shift_id'] = (int)$existing['id'];
                if ($tx_started && $pdo->inTransaction()) $pdo->commit();
                send_json('success', 'Shift already active (reused).', ['shift'=>$existing]);
            }

            // 新建
            $uuid = bin2hex(random_bytes(16));
            $ins = $pdo->prepare(
                "INSERT INTO pos_shifts (shift_uuid, store_id, user_id, start_time, status, starting_float)
                 VALUES (?, ?, ?, UTC_TIMESTAMP(), 'ACTIVE', ?)"
            );
            $ins->execute([$uuid, $store_id, $user_id, $starting_float]);
            $shift_id = (int)$pdo->lastInsertId();

            if ($tx_started && $pdo->inTransaction()) $pdo->commit();
            $_SESSION['pos_shift_id'] = $shift_id;

            send_json('success','Shift started.',[
                'shift'=>[
                    'id'=>$shift_id,'shift_uuid'=>$uuid,'store_id'=>$store_id,'user_id'=>$user_id,
                    'start_time'=>gmdate('Y-m-d H:i:s'),'status'=>'ACTIVE',
                    'starting_float'=>(float)$starting_float,'counted_cash'=>null
                ]
            ]);
        }

        case 'end': {
            $counted_cash = null;
            $shift_id = (int)($_SESSION['pos_shift_id'] ?? 0);

            if (is_array($body)) {
                if (isset($body['counted_cash'])) $counted_cash = (float)$body['counted_cash'];
                if (isset($body['shift_id']))     $shift_id     = (int)$body['shift_id'];
            } elseif (isset($_POST['counted_cash'])) {
                $counted_cash = (float)$_POST['counted_cash'];
            }
            if ($shift_id <= 0) {
                send_json('error', 'No active shift in session.', null, 400);
            }
            if (!is_numeric($counted_cash) || $counted_cash < 0) {
                send_json('error', 'Invalid counted_cash.', null, 422);
            }

            $tx_started = false;
            if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $tx_started = true; }

            // 锁定该ACTIVE班次
            $lock = $pdo->prepare(
                "SELECT id, start_time, starting_float
                 FROM pos_shifts
                 WHERE id=? AND user_id=? AND store_id=? AND status='ACTIVE'
                 FOR UPDATE"
            );
            $lock->execute([$shift_id, $user_id, $store_id]);
            $shift = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$shift) {
                if ($tx_started && $pdo->inTransaction()) $pdo->rollBack();
                send_json('error', 'Active shift not found or already ended.', null, 404);
            }

            // 统一“现在”时间（PHP 生成一次 UTC）
            $now_utc = gmdate('Y-m-d H:i:s');

            // 计算理论现金
            $totals = compute_expected_cash($pdo, $store_id, $shift['start_time'], $now_utc, (float)$shift['starting_float']);
            $expected_cash = (float)$totals['expected_cash'];
            $cash_diff     = round((float)$counted_cash - $expected_cash, 2);

            // 更新班次
            $upd = $pdo->prepare("UPDATE pos_shifts SET end_time=?, status='ENDED', counted_cash=? WHERE id=?");
            $upd->execute([$now_utc, $counted_cash, $shift_id]);

            // 写交接班记录（DDL 已在事务外处理，不会触发隐式提交）
            $ins = $pdo->prepare("INSERT INTO pos_eod_records
              (shift_id, store_id, user_id, started_at, ended_at, starting_float,
               cash_sales, cash_in, cash_out, cash_refunds, expected_cash, counted_cash, cash_diff)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $shift_id, $store_id, $user_id, $shift['start_time'], $now_utc, (float)$totals['starting_float'],
                (float)$totals['cash_sales'], (float)$totals['cash_in'], (float)$totals['cash_out'],
                (float)$totals['cash_refunds'], $expected_cash, (float)$counted_cash, (float)$cash_diff
            ]);
            $eod_id = (int)$pdo->lastInsertId();

            if ($tx_started && $pdo->inTransaction()) $pdo->commit();
            unset($_SESSION['pos_shift_id']);

            send_json('success','Shift ended.',[
                'eod_id' => $eod_id,
                'eod' => [
                    'shift_id'        => $shift_id,
                    'started_at'      => $shift['start_time'],
                    'ended_at'        => $now_utc,
                    'starting_float'  => $totals['starting_float'],
                    'cash_sales'      => $totals['cash_sales'],
                    'cash_in'         => $totals['cash_in'],
                    'cash_out'        => $totals['cash_out'],
                    'cash_refunds'    => $totals['cash_refunds'],
                    'expected_cash'   => $totals['expected_cash'],
                    'counted_cash'    => (float)$counted_cash,
                    'cash_diff'       => $cash_diff
                ]
            ]);
        }

        default:
            send_json('error', 'Invalid action.', null, 400);
    }
} catch (Throwable $e) {
    // 只在确实有未提交事务时回滚，避免 "There is no active transaction"
    if (isset($pdo) && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $_) {} }
    error_log('Shift Handler Error: ' . $e->getMessage());
    send_json('error', 'Internal server error.', ['debug' => $e->getMessage()], 500);
}
