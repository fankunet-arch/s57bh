<?php
/**
 * TopTea HQ - POS Promotion Management API
 * Revision: 1.3（允许 PRODUCT_MANAGER，统一返回 message，避免“undefined”）
 * Patched by GPT-5 · 2025-10-27
 */

require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/kds_helper.php';
require_once APP_PATH . '/helpers/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

@session_start();

// 允许 超管 + 产品经理
$role = $_SESSION['role_id'] ?? null;
$allowed = [ROLE_SUPER_ADMIN, ROLE_PRODUCT_MANAGER];
if (!in_array($role, $allowed, true)) {
    http_response_code(403);
    send_json_response('error', '权限不足。');
}

$action  = '';
$payload = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
    $action = $payload['action'] ?? '';
}

try {
    switch ($action) {
        case 'get': {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) send_json_response('error', '无效的ID。');

            $stmt = $pdo->prepare("SELECT * FROM pos_promotions WHERE id = ?");
            $stmt->execute([$id]);
            $promo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($promo) send_json_response('success', '活动已加载。', $promo);
            http_response_code(404);
            send_json_response('error', '未找到指定的活动。');
        }

        case 'save': {
            $data = $payload['data'] ?? [];
            $id   = !empty($data['id']) ? (int)$data['id'] : null;

            $promo_name         = trim((string)($data['promo_name'] ?? ''));
            $promo_priority     = (int)($data['promo_priority'] ?? 0);
            $promo_exclusive    = (int)($data['promo_exclusive'] ?? 0);
            $promo_is_active    = (int)($data['promo_is_active'] ?? 0);
            $promo_trigger_type = trim((string)($data['promo_trigger_type'] ?? 'AUTO_APPLY'));
            $promo_code         = trim((string)($data['promo_code'] ?? ''));
            $promo_start_date   = trim((string)($data['promo_start_date'] ?? ''));
            $promo_end_date     = trim((string)($data['promo_end_date'] ?? ''));
            $promo_conditions   = json_encode($data['promo_conditions'] ?? [], JSON_UNESCAPED_UNICODE);
            $promo_actions      = json_encode($data['promo_actions'] ?? [], JSON_UNESCAPED_UNICODE);

            if ($promo_name === '') {
                send_json_response('error', '活动名称不能为空。');
            }
            if ($promo_trigger_type === 'COUPON_CODE' && $promo_code === '') {
                send_json_response('error', '优惠码类型的活动，优惠码不能为空。');
            }

            // 优惠码唯一性（大小写不敏感）
            if ($promo_trigger_type === 'COUPON_CODE' && $promo_code !== '') {
                $sql = "SELECT id FROM pos_promotions WHERE LOWER(TRIM(promo_code)) = LOWER(TRIM(?))";
                $params = [$promo_code];
                if ($id) { $sql .= " AND id != ?"; $params[] = $id; }
                $dup = $pdo->prepare($sql);
                $dup->execute($params);
                if ($dup->fetch()) send_json_response('error', '此优惠码已被其他活动使用。');
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE pos_promotions
                       SET promo_name = ?, promo_priority = ?, promo_exclusive = ?, promo_is_active = ?,
                           promo_trigger_type = ?, promo_code = ?,
                           promo_conditions = ?, promo_actions = ?,
                           promo_start_date = ?, promo_end_date = ?
                     WHERE id = ?
                ");
                $stmt->execute([
                    $promo_name, $promo_priority, $promo_exclusive, $promo_is_active,
                    $promo_trigger_type, ($promo_trigger_type === 'COUPON_CODE' ? $promo_code : null),
                    $promo_conditions, $promo_actions,
                    ($promo_start_date !== '' ? str_replace('T',' ', $promo_start_date) : null),
                    ($promo_end_date   !== '' ? str_replace('T',' ', $promo_end_date)   : null),
                    $id
                ]);
                send_json_response('success', '活动已成功更新！');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO pos_promotions
                        (promo_name, promo_priority, promo_exclusive, promo_is_active,
                         promo_trigger_type, promo_code,
                         promo_conditions, promo_actions, promo_start_date, promo_end_date)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $promo_name, $promo_priority, $promo_exclusive, $promo_is_active,
                    $promo_trigger_type, ($promo_trigger_type === 'COUPON_CODE' ? $promo_code : null),
                    $promo_conditions, $promo_actions,
                    ($promo_start_date !== '' ? str_replace('T',' ', $promo_start_date) : null),
                    ($promo_end_date   !== '' ? str_replace('T',' ', $promo_end_date)   : null)
                ]);
                send_json_response('success', '新活动已成功创建！');
            }
        }

        case 'delete': {
            $id = (int)($payload['id'] ?? 0);
            if ($id <= 0) send_json_response('error', '无效的ID。');

            $stmt = $pdo->prepare("DELETE FROM pos_promotions WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '活动已成功删除。');
        }

        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    if (!empty($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        http_response_code(409);
        send_json_response('error', '保存失败：优惠码必须是唯一的。');
    }
    http_response_code(500);
    send_json_response('error', '数据库操作失败。', ['debug' => $e->getMessage()]);
}
