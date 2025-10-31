<?php
@session_start();
if (isset($_SESSION['kds_logged_in']) && $_SESSION['kds_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
require_once realpath(__DIR__ . '/../../kds/app/views/kds/login_view.php');