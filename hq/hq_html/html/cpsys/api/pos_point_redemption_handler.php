<?php
/**
 * Toptea HQ - POS Point Redemption Rules API Handler
 * Handles CRUD operations for point redemption rules.
 * Engineer: Gemini | Date: 2025-10-28
 */

require_once realpath(__DIR__ . '/../../../core/config.php');
require_once APP_PATH . '/helpers/auth_helper.php'; // For role check

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

@session_start();
// Security Check: Only Super Admins can manage these rules
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== ROLE_SUPER_ADMIN) {
    http_response_code(403);
    send_json_response('error', '权限不足。');
}

global $pdo;
if (!isset($pdo) || !$pdo instanceof PDO) {
     http_response_code(500);
     send_json_response('error', '数据库连接不可用。');
}

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    if (is_array($json_data) && isset($json_data['action'])) {
        $action = $json_data['action'];
    }
}

try {
    switch ($action) {
        case 'get': // Get a single rule for editing
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) send_json_response('error', '无效的ID。');
            $stmt = $pdo->prepare("SELECT * FROM pos_point_redemption_rules WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($rule) {
                send_json_response('success', 'Rule loaded.', $rule);
            } else {
                http_response_code(404);
                send_json_response('error', '未找到指定的规则。');
            }
            break;

        case 'save':
            $data = $json_data['data'] ?? [];
            $id = $data['id'] ? (int)$data['id'] : null;

            // Basic validation
            $name_zh = trim($data['rule_name_zh'] ?? '');
            $name_es = trim($data['rule_name_es'] ?? '');
            $points = filter_var($data['points_required'] ?? null, FILTER_VALIDATE_INT);
            $reward_type = $data['reward_type'] ?? '';
            $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
            $reward_value_decimal = null;
            $reward_promo_id = null;

            if (empty($name_zh) || empty($name_es) || $points === false || $points <= 0) {
                send_json_response('error', '规则名称和所需积分为必填项，且积分必须大于0。');
            }
            if (!in_array($reward_type, ['DISCOUNT_AMOUNT', 'SPECIFIC_PROMOTION'])) {
                send_json_response('error', '无效的奖励类型。');
            }

            if ($reward_type === 'DISCOUNT_AMOUNT') {
                $reward_value_decimal = filter_var($data['reward_value_decimal'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($reward_value_decimal === false || $reward_value_decimal <= 0) {
                    send_json_response('error', '选择减免金额时，必须提供一个大于0的有效金额。');
                }
                 $reward_value_decimal = number_format($reward_value_decimal, 2, '.', ''); // Format
            } elseif ($reward_type === 'SPECIFIC_PROMOTION') {
                $reward_promo_id = filter_var($data['reward_promo_id'] ?? null, FILTER_VALIDATE_INT);
                 if ($reward_promo_id === false || $reward_promo_id <= 0) {
                    send_json_response('error', '选择赠送活动时，必须选择一个有效的活动。');
                 }
            }

            $params = [
                ':rule_name_zh' => $name_zh,
                ':rule_name_es' => $name_es,
                ':points_required' => $points,
                ':reward_type' => $reward_type,
                ':reward_value_decimal' => $reward_value_decimal,
                ':reward_promo_id' => $reward_promo_id,
                ':is_active' => $is_active
            ];

            $pdo->beginTransaction();

            if ($id) { // Update
                $params[':id'] = $id;
                $sql = "UPDATE pos_point_redemption_rules SET
                            rule_name_zh = :rule_name_zh, rule_name_es = :rule_name_es,
                            points_required = :points_required, reward_type = :reward_type,
                            reward_value_decimal = :reward_value_decimal, reward_promo_id = :reward_promo_id,
                            is_active = :is_active
                        WHERE id = :id AND deleted_at IS NULL";
                $pdo->prepare($sql)->execute($params);
                $message = '兑换规则已成功更新！';
            } else { // Create
                 $sql = "INSERT INTO pos_point_redemption_rules (
                            rule_name_zh, rule_name_es, points_required, reward_type,
                            reward_value_decimal, reward_promo_id, is_active
                         ) VALUES (
                            :rule_name_zh, :rule_name_es, :points_required, :reward_type,
                            :reward_value_decimal, :reward_promo_id, :is_active
                         )";
                 $pdo->prepare($sql)->execute($params);
                 $message = '新兑换规则已成功创建！';
            }
            $pdo->commit();
            send_json_response('success', $message);
            break;

        case 'delete':
            $id = (int)($json_data['id'] ?? 0);
            if (!$id) send_json_response('error', '无效的ID。');
            // Use soft delete
            $stmt = $pdo->prepare("UPDATE pos_point_redemption_rules SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            send_json_response('success', '兑换规则已成功删除。');
            break;

        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    error_log("DB Error in point redemption handler: " . $e->getMessage());
    send_json_response('error', '数据库操作失败。');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    error_log("Server Error in point redemption handler: " . $e->getMessage());
    send_json_response('error', '服务器内部发生错误。');
}
