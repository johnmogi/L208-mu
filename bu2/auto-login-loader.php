<?php
/**
 * Auto Login Loader - Load the auto-login system from bu2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load the auto-login system
$auto_login_file = __DIR__ . '/bu2/auto-login-after-checkout.php';
if (file_exists($auto_login_file)) {
    require_once $auto_login_file;
    error_log('Auto Login Loader: Loaded auto-login-after-checkout.php from bu2');
} else {
    error_log('Auto Login Loader: auto-login-after-checkout.php not found in bu2');
}
