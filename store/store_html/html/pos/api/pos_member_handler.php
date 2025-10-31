<?php
/**
 * TopTea POS - Member Management API
 * Handles finding and creating members from the POS interface.
 * Engineer: Gemini | Date: 2025-10-27 | Revision: 1.0
 */
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');

header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

$action = $_GET['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $json_data['action'] ?? $action;
}

// In a real system, these would come from an authenticated user session.
$store_id = 1; 
$user_id = 1;

try {
    switch ($action) {
        case 'find':
            $phone = trim($_GET['phone'] ?? '');
            if (empty($phone)) {
                http_response_code(400);
                send_json_response('error', 'Phone number is required.');
            }

            $stmt = $pdo->prepare("
                SELECT m.*, ml.level_name_zh, ml.level_name_es
                FROM pos_members m
                LEFT JOIN pos_member_levels ml ON m.member_level_id = ml.id
                WHERE m.phone_number = ? AND m.deleted_at IS NULL
            ");
            $stmt->execute([$phone]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($member) {
                send_json_response('success', 'Member found.', $member);
            } else {
                http_response_code(404);
                send_json_response('error', 'Member not found.');
            }
            break;

        case 'create':
            $data = $json_data['data'] ?? [];
            $phone = trim($data['phone_number'] ?? '');

            if (empty($phone)) {
                http_response_code(400);
                send_json_response('error', '手机号为必填项。 (Phone number is required.)');
            }

            // Check for duplicates
            $stmt_check = $pdo->prepare("SELECT id FROM pos_members WHERE phone_number = ? AND deleted_at IS NULL");
            $stmt_check->execute([$phone]);
            if ($stmt_check->fetch()) {
                http_response_code(409); // Conflict
                send_json_response('error', '此手机号已被注册。 (This phone number is already registered.)');
            }

            // Prepare data for insertion
            $first_name = !empty($data['first_name']) ? trim($data['first_name']) : null;
            $last_name = !empty($data['last_name']) ? trim($data['last_name']) : null;
            $email = !empty($data['email']) ? trim($data['email']) : null;
            $birthdate = !empty($data['birthdate']) ? trim($data['birthdate']) : null;
            
            // Validate birthdate format if provided
            if ($birthdate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
                 http_response_code(400);
                 send_json_response('error', '生日格式无效，请使用 YYYY-MM-DD 格式。');
            }

            $stmt_insert = $pdo->prepare("
                INSERT INTO pos_members (member_uuid, phone_number, first_name, last_name, email, birthdate)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $uuid = bin2hex(random_bytes(16));
            $stmt_insert->execute([$uuid, $phone, $first_name, $last_name, $email, $birthdate]);
            
            $new_member_id = $pdo->lastInsertId();
            
            // Fetch the newly created member to return to frontend
            $stmt_get = $pdo->prepare("SELECT * FROM pos_members WHERE id = ?");
            $stmt_get->execute([$new_member_id]);
            $new_member_data = $stmt_get->fetch(PDO::FETCH_ASSOC);

            http_response_code(201); // Created
            send_json_response('success', '新会员已成功创建！ (Member created successfully!)', $new_member_data);
            break;

        default:
            http_response_code(400);
            send_json_response('error', 'Invalid action requested.');
    }
} catch (Exception $e) {
    http_response_code(500);
    send_json_response('error', 'An error occurred.', ['debug' => $e->getMessage()]);
}