<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
require_once realpath(__DIR__ . '/../../../pos_backend/core/api_auth_core.php');
require_once realpath(__DIR__ . '/../../../pos_backend/services/PromotionEngine.php');
require_once realpath(__DIR__ . '/../../../pos_backend/core/shift_guard.php');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ★ 未开班直接拦截（409）
ensure_active_shift_or_fail($pdo);

function send_json_response($status, $message, $data = null, int $http = 200) {
  http_response_code($http);
  echo json_encode(['status'=>$status,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}
function diag($msg, $e = null) {
  $base = 'DIAG_V6.0 :: File: submit_order.php';
  if ($e instanceof Throwable) return $base . ' :: Line: ' . $e->getLine() . ' :: ' . $e->getMessage();
  return $base . ' :: ' . $msg;
}

/* ---------- 数值解析：支持 €4.50 / 4,50 / 1.234,56 ---------- */
function to_float($v): float {
  if (is_int($v) || is_float($v)) return (float)$v;
  if (!is_string($v)) return 0.0;
  $s = trim($v);
  $s = preg_replace('/[^\d\.,\-]/u', '', $s);
  if (strpos($s, ',') !== false && strpos($s, '.') === false) {
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  } else {
    $s = str_replace(',', '', $s);
  }
  if ($s === '' || $s === '-' || $s === '.') return 0.0;
  return is_numeric($s) ? (float)$s : 0.0;
}

/* ---------- 支付解析：兼容 summary 为“数组”或“对象” ---------- */
function extract_payment_totals($payment): array {
  $cash = 0.0; $card = 0.0; $platform = 0.0;

  if (!is_array($payment)) {
    $tmp = to_float($payment);
    if ($tmp > 0) $cash += $tmp;
    $payment = [];
  }

  // 1) summary = 对象 {cash, card, platform...}
  if (isset($payment['summary']) && is_array($payment['summary']) && array_values($payment['summary']) !== $payment['summary']) {
    $s = $payment['summary'];
    $cash     += to_float($s['cash'] ?? 0);
    $card     += to_float($s['card'] ?? 0);
    $platform += to_float($s['platform'] ?? 0)
               + to_float($s['bizum'] ?? 0) + to_float($s['qr'] ?? 0)
               + to_float($s['wechat'] ?? 0) + to_float($s['alipay'] ?? 0)
               + to_float($s['online'] ?? 0) + to_float($s['stripe'] ?? 0)
               + to_float($s['paypal'] ?? 0);
  }

  // 2) summary = 数组 [{method:'Cash', amount:4.5}, ...]
  if (isset($payment['summary']) && is_array($payment['summary']) && array_values($payment['summary']) === $payment['summary']) {
    foreach ($payment['summary'] as $line) {
      if (!is_array($line)) continue;
      $amount = to_float($line['amount'] ?? $line['value'] ?? 0);
      $m = strtolower((string)($line['method'] ?? $line['type'] ?? $line['channel'] ?? $line['name'] ?? ''));
      if (in_array($m, ['cash','efectivo'])) $cash += $amount;
      elseif (in_array($m, ['card','tarjeta'])) $card += $amount;
      else $platform += $amount; // 其他走平台
    }
  }

  // 3) 顶层字段
  $cash     += to_float($payment['cash'] ?? 0);
  $card     += to_float($payment['card'] ?? 0);
  $platform += to_float($payment['platform'] ?? 0)
             + to_float($payment['bizum'] ?? 0) + to_float($payment['qr'] ?? 0)
             + to_float($payment['wechat'] ?? 0) + to_float($payment['alipay'] ?? 0)
             + to_float($payment['online'] ?? 0) + to_float($payment['stripe'] ?? 0)
             + to_float($payment['paypal'] ?? 0);

  // 4) methods/tenders/lines
  foreach (['methods','tenders','lines'] as $k) {
    if (!empty($payment[$k]) && is_array($payment[$k])) {
      foreach ($payment[$k] as $m) {
        if (!is_array($m)) continue;
        $amount = to_float($m['amount'] ?? $m['value'] ?? 0);
        $t = strtolower((string)($m['type'] ?? $m['method'] ?? $m['channel'] ?? $m['name'] ?? ''));
        if (in_array($t, ['cash','efectivo'])) $cash += $amount;
        elseif (in_array($t, ['card','tarjeta'])) $card += $amount;
        else $platform += $amount;
      }
    }
  }

  // 5) paid / breakdown
  foreach (['paid','breakdown'] as $obj) {
    if (!empty($payment[$obj]) && is_array($payment[$obj])) {
      $o = $payment[$obj];
      $cash     += to_float($o['cash'] ?? 0);
      $card     += to_float($o['card'] ?? 0);
      $platform += to_float($o['platform'] ?? 0)
                 + to_float($o['bizum'] ?? 0) + to_float($o['qr'] ?? 0)
                 + to_float($o['wechat'] ?? 0) + to_float($o['alipay'] ?? 0)
                 + to_float($o['online'] ?? 0) + to_float($o['stripe'] ?? 0)
                 + to_float($o['paypal'] ?? 0);
    }
  }

  // 6) 兜底：仅有 total/paid/change 时，按唯一支付（默认现金）
  if ($cash == 0.0 && $card == 0.0 && $platform == 0.0) {
    $total  = to_float($payment['total']  ?? 0);
    $paid   = to_float($payment['paid']   ?? 0);
    $change = to_float($payment['change'] ?? 0);
    $candidate = 0.0;
    if ($paid > 0 || $change > 0) $candidate = max(0.0, $paid - $change);
    elseif ($total > 0) $candidate = $total;
    if ($candidate > 0) $cash = round($candidate, 2);
  }

  $cash = round($cash, 2); $card = round($card, 2); $platform = round($platform, 2);
  $sumPaid = round($cash + $card + $platform, 2);

  if (!isset($payment['summary']) || !is_array($payment['summary']) || array_values($payment['summary']) === $payment['summary']) {
    // 以对象形式写回 summary，方便入库与排错
    $payment['summary'] = ['cash'=>$cash,'card'=>$card,'platform'=>$platform,'total'=>$sumPaid];
  } else {
    $payment['summary']['cash'] = $cash;
    $payment['summary']['card'] = $card;
    $payment['summary']['platform'] = $platform;
    $payment['summary']['total'] = $sumPaid;
  }
  return [$cash, $card, $platform, $sumPaid, $payment];
}

/* ---------- 发票号：优先 counters 表；不存在则回退 MAX+1 ---------- */
function allocate_invoice_number(PDO $pdo, array $store_config, ?string $compliance_system): array {
  $system = $compliance_system ?: 'NONE';
  $series = 'A' . date('Y');
  $issuer = (string)($store_config['tax_id'] ?? '');
  $offset = (int)($store_config['invoice_number_offset'] ?? 10000);

  try {
    // 初始化
    $sqlInit = "INSERT INTO pos_invoice_counters (compliance_system, series, issuer_nif, current_number)
                VALUES (:s,:series,:nif,:offset)
                ON DUPLICATE KEY UPDATE current_number = current_number";
    $pdo->prepare($sqlInit)->execute([':s'=>$system, ':series'=>$series, ':nif'=>$issuer, ':offset'=>$offset]);

    // 原子自增
    $sqlBump = "UPDATE pos_invoice_counters
                SET current_number = LAST_INSERT_ID(current_number + 1)
                WHERE compliance_system=:s AND series=:series AND issuer_nif=:nif";
    $pdo->prepare($sqlBump)->execute([':s'=>$system, ':series'=>$series, ':nif'=>$issuer]);
    $next = (int)$pdo->lastInsertId();
    if ($next <= 0) throw new RuntimeException('counter bump failed');
    return [$series, $next];

  } catch (Throwable $e) {
    // 表不存在或其他问题 → 回退到 MAX+1
    $stmt_max = $pdo->prepare("SELECT MAX(`number`) FROM pos_invoices WHERE series=:series AND issuer_nif=:nif");
    $stmt_max->execute([':series'=>$series, ':nif'=>$issuer]);
    $max = (int)$stmt_max->fetchColumn();
    $next = ($max === 0 || $max < $offset) ? $offset + 1 : $max + 1;
    return [$series, $next];
  }
}

/* -------------------- 主流程 -------------------- */
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_json_response('error','Invalid request method.',null,405);

  $raw = file_get_contents('php://input') ?: '';
  $json_data = json_decode($raw, true);
  if (!is_array($json_data)) $json_data = $_POST;

  if (!$json_data || empty($json_data['cart']) || !is_array($json_data['cart'])) {
    send_json_response('error','Cart data is missing or empty.',null,400);
  }

  $shift_id = (int)($_SESSION['pos_shift_id'] ?? 0);
  if ($shift_id === 0) send_json_response('error','No active shift found for the current user. Cannot process order.',null,403);

  $store_id = (int)($_SESSION['pos_store_id'] ?? 0);
  $user_id  = (int)($_SESSION['pos_user_id']  ?? 0);
  if ($store_id === 0 || $user_id === 0) send_json_response('error','Invalid session.',null,401);

  $member_id  = isset($json_data['member_id']) ? (int)$json_data['member_id'] : null;
  $points_redeemed_from_payload = (int)($json_data['points_redeemed'] ?? 0);

  $couponCode = null;
  foreach (['coupon_code','coupon','code','promo_code','discount_code'] as $k) {
    if (!empty($json_data[$k])) { $couponCode = trim((string)$json_data[$k]); break; }
  }

  $payment_payload_raw = $json_data['payment'] ?? $json_data['payments'] ?? [];
  [, , , $sumPaid, $payment_summary] = extract_payment_totals($payment_payload_raw);

  // 门店配置
  $stmt_store = $pdo->prepare("SELECT * FROM kds_stores WHERE id = :store_id LIMIT 1");
  $stmt_store->execute([':store_id'=>$store_id]);
  $store_config = $stmt_store->fetch(PDO::FETCH_ASSOC) ?: [];
  if (!$store_config) throw new Exception("Store configuration for store_id #{$store_id} not found.");
  $vat_rate = isset($store_config['default_vat_rate']) ? (float)$store_config['default_vat_rate'] : 21.0;

  // 积分规则
  $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM pos_settings WHERE setting_key = 'points_euros_per_point'");
  $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
  $euros_per_point = isset($settings['points_euros_per_point']) ? (float)$settings['points_euros_per_point'] : 1.0;
  if ($euros_per_point <= 0) $euros_per_point = 1.0;

  // 促销重算
  $engine = new PromotionEngine($pdo);
  $promoResult = $engine->applyPromotions($json_data['cart'], $couponCode);
  $cart = $promoResult['cart'];
  $discount_from_promo = (float)($promoResult['discount_amount'] ?? 0.0);
  $final_total_after_promo = (float)($promoResult['final_total'] ?? 0.0);

  $pdo->beginTransaction();

  // 积分抵扣
  $points_discount_final = 0.0;
  $points_to_deduct = 0;
  if ($member_id && $points_redeemed_from_payload > 0) {
    $stmt_member = $pdo->prepare("SELECT points_balance FROM pos_members WHERE id = ? AND is_active = 1 FOR UPDATE");
    $stmt_member->execute([$member_id]);
    if ($m = $stmt_member->fetch(PDO::FETCH_ASSOC)) {
      $current_points = (int)$m['points_balance'];
      $max_possible_discount = $final_total_after_promo;
      $max_points_for_discount = (int)floor($max_possible_discount * 100);
      $points_to_deduct = min($points_redeemed_from_payload, $current_points, $max_points_for_discount);
      if ($points_to_deduct > 0) $points_discount_final = $points_to_deduct / 100.0;
      else $points_to_deduct = 0;
    }
  }

  $final_total = round($final_total_after_promo - $points_discount_final, 2);
  $discount_amount = round($discount_from_promo + $points_discount_final, 2);

  // --- 核心修复：调整支付核对逻辑 ---
  // 检查实收金额是否小于应收总额（允许0.01欧元的误差）
  if ($sumPaid < $final_total - 0.01) {
    $pdo->rollBack();
    send_json_response('error', 'Payment breakdown does not match final total.', [
      'final_total'=>$final_total,
      'sum_paid'=>$sumPaid,
    ], 422);
  }
  
  // 积分扣减与累计 (移至开票逻辑前，确保无论是否开票都执行)
  if ($member_id && $points_to_deduct > 0 && $points_discount_final > 0) {
    $pdo->prepare("UPDATE pos_members SET points_balance = points_balance - ? WHERE id = ?")
        ->execute([$points_to_deduct, $member_id]);
    $pdo->prepare("INSERT INTO pos_member_points_log (member_id, invoice_id, points_change, reason_code, notes, user_id)
                   VALUES (?,?,?,?,?,?)")
        ->execute([$member_id, null, -$points_to_deduct, 'REDEEM_DISCOUNT', "兑换抵扣 {$points_discount_final} EUR", $user_id]);
  }
  if ($member_id && $final_total > 0) {
    $points_to_add = (int)floor($final_total / $euros_per_point);
    if ($points_to_add > 0) {
      $pdo->prepare("UPDATE pos_members SET points_balance = points_balance + ? WHERE id = ?")
          ->execute([$points_to_add, $member_id]);
      $pdo->prepare("INSERT INTO pos_member_points_log (member_id, invoice_id, points_change, reason_code, user_id)
                     VALUES (?,?,?,?,?)")
          ->execute([$member_id, null, $points_to_add, 'PURCHASE', $user_id]);
    }
  }

  // --- 核心逻辑：检查是否需要开票 ---
  if ($store_config['billing_system'] === 'NONE') {
      // 不开票，直接提交事务并返回
      $pdo->commit();
      send_json_response('success', 'Order processed without invoice.', [
          'invoice_id' => null,
          'invoice_number' => 'NO_INVOICE',
          'qr_content' => null
      ]);
      // 此处 exit，后续代码不执行
  }

  // --- 开票流程（仅在 billing_system 不是 'NONE' 时执行）---
  $compliance_system = $store_config['billing_system'];
  [$series, $invoice_number] = allocate_invoice_number($pdo, $store_config, $compliance_system);

  $compliance_data = null;
  $qr_payload = null;
  if ($compliance_system) {
    $handler_path = realpath(__DIR__ . "/../../../pos_backend/compliance/{$compliance_system}Handler.php");
    if ($handler_path && file_exists($handler_path)) {
      require_once $handler_path;
      $class = $compliance_system . 'Handler';
      if (class_exists($class)) {
        $issuer_nif = (string)$store_config['tax_id'];
        $stmt_prev = $pdo->prepare("SELECT compliance_data FROM pos_invoices WHERE compliance_system=:system AND series=:series AND issuer_nif=:nif ORDER BY `number` DESC LIMIT 1");
        $stmt_prev->execute([':system'=>$compliance_system, ':series'=>$series, ':nif'=>$issuer_nif]);
        $prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $previous_hash = $prev ? (json_decode($prev['compliance_data'] ?? '[]', true)['hash'] ?? null) : null;
        $issued_at_micro = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s.u');
        $invoiceData = ['series'=>$series,'number'=>$invoice_number,'issued_at'=>$issued_at_micro,'final_total'=>$final_total];
        $handler = new $class();
        $compliance_data = $handler->generateComplianceData($pdo, $invoiceData, $previous_hash);
        if (is_array($compliance_data)) $qr_payload = $compliance_data['qr_content'] ?? null;
      }
    }
  }

  $issued_at = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s');
  $taxable_base = round($final_total / (1 + ($vat_rate / 100)), 2);
  $vat_amount   = round($final_total - $taxable_base, 2);

  $stmt_invoice = $pdo->prepare("
    INSERT INTO pos_invoices (invoice_uuid, store_id, user_id, shift_id, issuer_nif, series, `number`, issued_at, invoice_type, taxable_base, vat_amount, discount_amount, final_total, status, compliance_system, compliance_data, payment_summary) 
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");
  $stmt_invoice->execute([
    bin2hex(random_bytes(16)), $store_id, $user_id, $shift_id, (string)$store_config['tax_id'],
    $series, $invoice_number, $issued_at, 'F2', $taxable_base, $vat_amount, $discount_amount, $final_total,
    'ISSUED', $compliance_system, json_encode($compliance_data, JSON_UNESCAPED_UNICODE),
    json_encode($payment_summary, JSON_UNESCAPED_UNICODE)
  ]);
  $invoice_id = (int)$pdo->lastInsertId();

  // 更新积分流水的 invoice_id
  if ($member_id) {
      $pdo->prepare("UPDATE pos_member_points_log SET invoice_id = ? WHERE user_id = ? AND invoice_id IS NULL ORDER BY id DESC LIMIT 2")
          ->execute([$invoice_id, $user_id]);
  }

  $sql_item = "INSERT INTO pos_invoice_items (invoice_id, item_name, variant_name, quantity, unit_price, unit_taxable_base, vat_rate, vat_amount, customizations) VALUES (?,?,?,?,?,?,?,?,?)";
  $stmt_item = $pdo->prepare($sql_item);

  // (Plan II-4) 准备杯贴打印数据包
  $print_jobs = [];
  $full_invoice_number = $series . '-' . $invoice_number; // (Plan II-4) {cup_order_number}

  foreach ($cart as $item) {
    $qty = max(1, (int)($item['qty'] ?? 1));
    $unit_price = (float)($item['final_price'] ?? $item['unit_price_eur'] ?? 0);
    $item_total = round($unit_price * $qty, 2);
    $item_tax_base_total = round($item_total / (1 + ($vat_rate / 100)), 2);
    $item_vat_amount = round($item_total - $item_tax_base_total, 2);
    $unit_tax_base = ($qty > 0) ? round($item_tax_base_total / $qty, 4) : 0;
    $custom = ['ice' => $item['ice'] ?? null, 'sugar' => $item['sugar'] ?? null, 'addons' => $item['addons'] ?? [], 'remark' => $item['remark'] ?? ''];
    $stmt_item->execute([
      $invoice_id, (string)($item['title'] ?? ($item['name'] ?? '')), (string)($item['variant_name'] ?? ''),
      $qty, $unit_price, $unit_tax_base, $vat_rate, $item_vat_amount, json_encode($custom, JSON_UNESCAPED_UNICODE)
    ]);

    // (Plan II-4) 格式化定制详情
    $customizations_parts = [];
    if (!empty($custom['ice'])) $customizations_parts[] = 'Ice:' . $custom['ice'] . '%';
    if (!empty($custom['sugar'])) $customizations_parts[] = 'Sugar:' . $custom['sugar'] . '%';
    if (!empty($custom['addons'])) $customizations_parts[] = '+' . implode(',+', $custom['addons']);
    
    // (Plan II-4) 为此购物车的每一“杯”创建打印作业
    for ($i = 0; $i < $qty; $i++) {
        $print_jobs[] = [
            'type' => 'CUP_STICKER',
            'data' => [
                'cup_order_number' => $full_invoice_number,
                'item_name' => (string)($item['title'] ?? ($item['name'] ?? '')),
                'variant_name' => (string)($item['variant_name'] ?? ''),
                'customization_detail' => implode(' / ', $customizations_parts),
                'remark' => (string)($custom['remark'] ?? ''),
                'store_name' => $store_config['store_name'] ?? ''
            ]
        ];
    }
  }
  
  $pdo->commit();

  send_json_response('success','Order created.',[
    'invoice_id'=>$invoice_id,
    'invoice_number'=>$full_invoice_number,
    'qr_content'=>$qr_payload,
    'print_jobs' => $print_jobs // (Plan II-4) 返回杯贴打印作业
  ]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  error_log('submit_order error: '.$e->getMessage());
  send_json_response('error','Failed to create order.',['debug'=>diag('', $e)],500);
}
