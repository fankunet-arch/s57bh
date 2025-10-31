<?php
/**
 * Toptea Store - KDS
 * Expiry Tracking Page Entry Point (Secured)
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 8.4 (Shell App Cache Fix)
 */

// --- START: DEFINITIVE CACHE FIX for Shell Apps ---
// These headers command any browser or WebView to never cache this page.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
// --- END: DEFINITIVE CACHE FIX ---

require_once realpath(__DIR__ . '/../../kds/core/kds_auth_core.php');
header('Content-Type: text/html; charset=utf-8');
require_once realpath(__DIR__ . '/../../kds/core/config.php');

$page_title = '效期追踪 - KDS';
$content_view = KDS_APP_PATH . '/views/kds/expiry_view.php';
$page_js = 'kds_expiry.js';

if (!file_exists($content_view)) {
    die("Critical Error: View file not found at path: " . htmlspecialchars($content_view));
}

include KDS_APP_PATH . '/views/kds/layouts/main.php';