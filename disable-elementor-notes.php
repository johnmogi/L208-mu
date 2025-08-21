<?php
/**
 * Disable Elementor Pro Notes Module Completely
 */

if (!defined('ABSPATH')) {
    exit;
}

// Disable Elementor Pro notes module at the earliest possible hook
add_action('plugins_loaded', function() {
    if (class_exists('ElementorPro\Plugin')) {
        // Remove all notes-related hooks
        remove_all_actions('elementor_pro/modules/notes');
        remove_all_filters('elementor_pro/modules/notes');
        
        // Prevent notes module from loading
        add_filter('elementor_pro/modules/notes/enabled', '__return_false', 999);
    }
}, 1);

// Override problematic Elementor Pro classes
if (!class_exists('ElementorPro\Modules\Notes\User\Capabilities')) {
    class ElementorPro_Notes_User_Capabilities_Override {
        public static function all() { return []; }
        public static function basic() { return []; }
    }
}

error_log("Elementor Pro Notes module disabled");
