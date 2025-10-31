<?php
// shift_guard.php — 强制存在活动班次（后端兜底）
require_once __DIR__ . '/config.php';

function ensure_active_shift_or_fail(PDO $pdo) {
    $policy = defined('SHIFT_POLICY') ? SHIFT_POLICY : 'force_all';
    if ($policy === 'optional') return; // 不强制

    $user_id  = (int)($_SESSION['pos_user_id']  ?? 0);
    $store_id = (int)($_SESSION['pos_store_id'] ?? 0);
    $shift_id = (int)($_SESSION['pos_shift_id'] ?? 0);

    if ($shift_id > 0) return;

    // 二次确认（并补齐 session）
    $stmt = $pdo->prepare("SELECT id FROM pos_shifts WHERE user_id=? AND store_id=? AND status='ACTIVE' LIMIT 1");
    $stmt->execute([$user_id, $store_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) { $_SESSION['pos_shift_id'] = (int)$row['id']; return; }

    http_response_code(409);
    echo json_encode([
        'status'  => 'error',
        'message' => 'SHIFT_REQUIRED',
        'data'    => ['policy' => $policy]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
