<?php
/**
 * Toptea HQ - POS Settings API Handler
 * Handles saving and loading POS global settings like points rules.
 * Engineer: Gemini | Date: 2025-10-28 | Revision: 1.5 (Production Ready)
 */

// 使用绝对路径确保文件包含
require_once realpath(__DIR__ . '/../../../core/config.php'); 
if (defined('APP_PATH')) {
    require_once APP_PATH . '/helpers/auth_helper.php'; 
} else {
     require_once realpath(__DIR__ . '/../../app/helpers/auth_helper.php');
}

header('Content-Type: application/json; charset=utf-8');
function send_json_response($status, $message, $data = null) { echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]); exit; }

@session_start();

// 明确声明使用全局 $pdo 连接
global $pdo; 

// Security Check: Only Super Admins can manage settings
if (!defined('ROLE_SUPER_ADMIN')) {
     http_response_code(500);
     send_json_response('error', '内部配置错误: 角色常量未定义。');
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== ROLE_SUPER_ADMIN) {
    http_response_code(403);
    send_json_response('error', '权限不足。');
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

// Ensure PDO connection is available AFTER declaring global
if (!isset($pdo) || !$pdo instanceof PDO) {
     http_response_code(500);
     send_json_response('error', '数据库连接不可用。');
}


try {
    switch ($action) {
        case 'load':
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM pos_settings WHERE setting_key LIKE 'points_%'");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            // Ensure the key exists even if the table was empty or query failed slightly
            if (!isset($settings['points_euros_per_point'])) {
                $settings['points_euros_per_point'] = '1.00'; // Provide default if missing
            }
            send_json_response('success', 'Settings loaded.', $settings);
            break;

        case 'save':
            $settings_data = (is_array($json_data) && isset($json_data['settings'])) ? $json_data['settings'] : [];
            if (empty($settings_data)) {
                send_json_response('error', 'No settings data provided.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO pos_settings (setting_key, setting_value)
                VALUES (:key, :value)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            foreach ($settings_data as $key => $value) {
                if ($key === 'points_euros_per_point') {
                    $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);
                    if ($floatVal === false || $floatVal <= 0) {
                        $pdo->rollBack();
                        send_json_response('error', '“每积分所需欧元”必须是一个大于0的数字。');
                    }
                     $value = number_format($floatVal, 2, '.', ''); 
                }
                
                 if (strpos($key, 'points_') === 0) {
                    $stmt->execute([':key' => $key, ':value' => $value]);
                 }
            }

            $pdo->commit();
            send_json_response('success', '设置已成功保存！');
            break;

        default:
            http_response_code(400);
            send_json_response('error', '无效的操作请求。');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    // Log error internally instead of exposing full trace
    error_log("Database Error in pos_settings_handler: " . $e->getMessage()); 
    send_json_response('error', '数据库操作失败，请检查日志。', ['code' => $e->getCode()]);
} catch (Throwable $e) { 
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
     // Log error internally
    error_log("Server Error in pos_settings_handler: " . $e->getMessage());
    send_json_response('error', '服务器内部发生错误，请检查日志。');
}

