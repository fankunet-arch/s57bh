<?php
/**
 * Toptea Store - KDS
 * Core Configuration File
 * Engineer: Gemini | Date: 2025-10-29 | Revision: 2.3 (Enable File Logging)
 */

// --- Database Configuration (Same as cpsys) ---
$db_host = 'mhdlmskvtmwsnt5z.mysql.db';
$db_name = 'mhdlmskvtmwsnt5z';
$db_user = 'mhdlmskvtmwsnt5z';
$db_pass = 'p8PQF7M8ZKLVxtjvatMkrthFQQUB9';
$db_char = 'utf8mb4';

// --- Application Settings ---
define('KDS_BASE_URL', 'http://store.toptea.es/kds/');

// --- Directory Paths (Relative to this new structure) ---
define('KDS_ROOT_PATH', dirname(__DIR__)); // Resolves to /web_toptea/store/store_html/kds
define('KDS_APP_PATH', KDS_ROOT_PATH . '/app');
define('KDS_HELPERS_PATH', KDS_ROOT_PATH . '/helpers'); // Correct path to the helpers directory

// --- Error Reporting ---
ini_set('display_errors', '0'); // Turn off displaying errors
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1'); // Enable logging errors
ini_set('error_log', '/web_toptea/logs/php_errors_kds.log'); // Specify log file path (Adjust path if needed)
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
    // Log the error
    error_log("KDS Database connection failed: " . $e->getMessage());
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
