<?php
/**
 * Plugin Name: Subscription Tracker Loader
 * Description: Loads the modern PSR-4 Subscription Tracker plugin
 * Version: 1.0.0
 * Author: Lilac Development Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the plugin
$plugin_path = __DIR__ . '/plugins/subscription-tracker/vendor/autoload.php';

if (file_exists($plugin_path)) {
    require_once $plugin_path;
    
    // Initialize the plugin
    add_action('plugins_loaded', function() {
        \Lilac\SubscriptionTracker\Plugin::getInstance();
    });
} else {
    // Fallback: try to load without composer autoloader
    $src_path = __DIR__ . '/plugins/subscription-tracker/src/';
    
    if (is_dir($src_path)) {
        // Manual autoloader for development
        spl_autoload_register(function($class) use ($src_path) {
            if (strpos($class, 'Lilac\\SubscriptionTracker\\') === 0) {
                $class_file = str_replace('Lilac\\SubscriptionTracker\\', '', $class);
                $file_path = $src_path . $class_file . '.php';
                
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        });
        
        // Initialize the plugin
        add_action('plugins_loaded', function() {
            \Lilac\SubscriptionTracker\Plugin::getInstance();
        });
    } else {
        // Log error if plugin files are missing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subscription Tracker: Plugin files not found at ' . $src_path);
        }
        
        // Show admin notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Subscription Tracker:</strong> Plugin files not found. Please ensure the plugin is properly installed.</p></div>';
        });
    }
}
