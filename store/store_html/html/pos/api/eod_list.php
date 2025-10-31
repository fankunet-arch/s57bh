<?php
// TopTea POS - EOD list API
// GET ?limit=50  -> 最近 50 条交接班记录（按门店过滤）

require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
require_once realpath(__DIR__ . '/../../../pos_backend/core/api_auth_core.php');

header('Content-Type: application/json; charset=utf-8');

function out($status, $message, $data=null, $http=200){
  http_response_code($http);
  echo json_encode(['status'=>$status,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $user_id  = (int)($_SESSION['pos_user_id']  ?? 0);
  $store_id = (int)($_SESSION['pos_store_id'] ?? 0);
  if ($user_id===0 || $store_id===0) out('error','Unauthorized',null,401);

  $limit = isset($_GET['limit']) ? max(1,min(200,(int)$_GET['limit'])) : 50;

  $sql = "SELECT id, shift_id, store_id, user_id,
                 started_at, ended_at,
                 starting_float, cash_sales, cash_in, cash_out, cash_refunds,
                 expected_cash, counted_cash, cash_diff, created_at
          FROM pos_eod_records
          WHERE store_id = ?
          ORDER BY id DESC
          LIMIT ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$store_id, $limit]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  out('success','ok', ['items'=>$rows, 'count'=>count($rows)]);
} catch (Throwable $e){
  error_log('eod_list error: '.$e->getMessage());
  out('error','Internal server error', ['debug'=>$e->getMessage()], 500);
}
