<?php
/**
 * Plugin Name: Lilac Course System Loader
 * Description: PSR-4 structured course access system for WooCommerce + LearnDash
 * Version: 1.0.0
 * Author: Lilac Learning
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('LILAC_COURSE_SYSTEM_PATH', __DIR__ . '/src/');
define('LILAC_COURSE_SYSTEM_URL', plugin_dir_url(__FILE__) . 'src/');

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'LilacCourseSystem\\') === 0) {
        $class_file = str_replace('\\', '/', substr($class, 18)) . '.php';
        $file_path = LILAC_COURSE_SYSTEM_PATH . $class_file;
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

// Initialize the system
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce') && function_exists('learndash_get_course_list')) {
        new \LilacCourseSystem\Core\SystemManager();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Lilac Course System requires WooCommerce and LearnDash to be active.</p></div>';
        });
    }
});
