<?php
/**
 * Toptea Store - KDS
 * Main Entry Point (Secured)
 * Engineer: Gemini | Date: 2025-10-23
 */

// This MUST be the first include. It checks if the user is logged in for the KDS.
require_once realpath(__DIR__ . '/../../kds/core/kds_auth_core.php');

header('Content-Type: text/html; charset=utf-8');

// Load the core configuration for the KDS.
require_once realpath(__DIR__ . '/../../kds/core/config.php');

$page = $_GET['page'] ?? 'sop'; // Default page is the Standard Operating Procedure view.

switch ($page) {
    case 'sop':
        $page_title = '制茶助手 - SOP';
        $content_view = KDS_APP_PATH . '/views/kds/sop_view.php';
        $page_js = 'kds_sop.js';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 - Page Not Found</h1>";
        exit;
}

if (!file_exists($content_view)) {
    die("Critical Error: View file not found at path: " . htmlspecialchars($content_view));
}

// Load the main KDS layout file, which will in turn include the content view.
include KDS_APP_PATH . '/views/kds/layouts/main.php';