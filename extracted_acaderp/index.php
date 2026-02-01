<?php
/**
 * Main Index File
 * Redirects to login if not authenticated, otherwise to dashboard
 */

require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . 'auth/login.php');
}
exit;
?>
