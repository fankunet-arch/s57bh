<?php
/**
 * Toptea Store - KDS
 * Core Authentication & Session Check for KDS
 * Engineer: Gemini | Date: 2025-10-23
 */
@session_start();
if (!isset($_SESSION['kds_logged_in']) || $_SESSION['kds_logged_in'] !== true) {
    session_destroy();
    header('Location: login.php');
    exit;
}