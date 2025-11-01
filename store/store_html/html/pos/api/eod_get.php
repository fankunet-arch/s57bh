<?php
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
require_once realpath(__DIR__ . '/../../../pos_backend/core/api_auth_core.php');
header('Content-Type: application/json; charset=utf-8');

function out($s,$m,$d=null,$h=200){ http_response_code($h); echo json_encode(['status'=>$s,'message'=>$m,'data'=>$d], JSON_UNESCAPED_UNICODE); exit; }

$store_id = (int)($_SESSION['pos_store_id'] ?? 0);
$eod_id   = isset($_GET['eod_id']) ? (int)$_GET['eod_id'] : 0;
if ($eod_id <= 0) out('error','Missing eod_id',null,400);

try {
  // 只允许读取本门店的数据
  $stmt = $pdo->prepare("SELECT * FROM pos_eod_records WHERE id=? AND store_id=? LIMIT 1");
  $stmt->execute([$eod_id, $store_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) out('error','Record not found',null,404);

  out('success','OK',['item'=>$row]);
} catch (Throwable $e) {
  error_log('eod_get error: '.$e->getMessage());
  out('error','Internal server error.',['debug'=>$e->getMessage()],500);
}
