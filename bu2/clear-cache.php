<?php
/**
 * Clear WordPress Cache - Emergency Cache Clearing
 */

// Clear object cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Clear transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");

// Clear Elementor cache
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

// Clear WP Rocket cache if exists
if (function_exists('rocket_clean_domain')) {
    rocket_clean_domain();
}

// Clear W3 Total Cache if exists
if (function_exists('w3tc_flush_all')) {
    w3tc_flush_all();
}

error_log("Emergency cache clear executed");
