<?php
/**
 * Plugin Name: Checkout Customizer (MU) - DISABLED
 * Description: DISABLED - Conflicts with functions.php checkout customization
 * Version: 1.0.0
 * Author: Lilac
 */

// DISABLED - This MU plugin conflicts with the functions.php checkout customization
// The functions.php version is now handling all checkout field customizations
// to prevent the Hebrew checkout error

/*
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants - renamed to force cache bypass
define('CHECKOUT_CUSTOMIZER_V2_PLUGIN_PATH', WPMU_PLUGIN_DIR . '/plugins/checkout-customizer-v2/');

// Check if the main plugin file exists
$main_plugin_file = CHECKOUT_CUSTOMIZER_V2_PLUGIN_PATH . 'checkout-customizer-v2.php';
if (file_exists($main_plugin_file)) {
    require_once $main_plugin_file;
} else {
    // Log error if the plugin file is missing
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Checkout Customizer V2 Error: Main plugin file not found at ' . $main_plugin_file);
    }
}
*/
