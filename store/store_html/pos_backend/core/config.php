<?php
/**
 * TopTea POS - Core Configuration
 * This file contains sensitive information and is stored outside the web root.
 * Engineer: Gemini | Date: 2025-10-29 | Revision: 2.1 (Enable File Logging)
 */

// --- Database Configuration (Same as all other systems) ---
$db_host = 'mhdlmskvtmwsnt5z.mysql.db';
$db_name = 'mhdlmskvtmwsnt5z';
$db_user = 'mhdlmskvtmwsnt5z';
$db_pass = 'p8PQF7M8ZKLVxtjvatMkrthFQQUB9';
$db_char = 'utf8mb4';

// --- Error Reporting ---
ini_set('display_errors', '0'); // Turn off displaying errors in production
ini_set('display_startup_errors', '0'); // Turn off displaying startup errors
ini_set('log_errors', '1'); // Enable error logging to file
ini_set('error_log', '/web_toptea/logs/php_errors_pos.log'); // Specify log file path (Adjust path if needed)
error_reporting(E_ALL); // Report all errors

// --- Database Connection (PDO) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_char";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Log the error instead of echoing
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// System timezone used by POS
if (!defined('APP_TZ')) {
    define('APP_TZ', 'Europe/Madrid');
}

// 班次策略：force_all | force_cash | optional
if (!defined('SHIFT_POLICY')) {
  define('SHIFT_POLICY', 'force_all'); // ← 强制所有交易必须在班次内
}
