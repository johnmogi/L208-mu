<?php

namespace LilacCourseSystem\Core;

use LilacCourseSystem\Handlers\PurchaseHandler;
use LilacCourseSystem\Handlers\AccessHandler;
use LilacCourseSystem\Handlers\RedirectHandler;
use LilacCourseSystem\Dashboard\UserDashboard;

/**
 * Main system manager for Lilac Course System
 */
class SystemManager {
    
    private $purchase_handler;
    private $access_handler;
    private $redirect_handler;
    private $user_dashboard;
    
    public function __construct() {
        $this->init_handlers();
        $this->register_hooks();
        
        error_log('Lilac Course System: SystemManager initialized');
    }
    
    private function init_handlers() {
        $this->purchase_handler = new PurchaseHandler();
        $this->access_handler = new AccessHandler();
        $this->redirect_handler = new RedirectHandler();
        $this->user_dashboard = new UserDashboard();
    }
    
    private function register_hooks() {
        // Core system hooks
        add_action('init', [$this, 'init_system']);
        add_action('wp_loaded', [$this, 'system_ready']);
        
        // Emergency cache clear
        add_action('wp_loaded', [$this, 'emergency_cache_clear'], 1);
    }
    
    public function init_system() {
        // Initialize database tables if needed
        $this->maybe_create_tables();
        
        // Clear any problematic transients
        $this->clear_learndash_transients();
    }
    
    public function system_ready() {
        error_log('Lilac Course System: All components loaded and ready');
    }
    
    public function emergency_cache_clear() {
        if (isset($_GET['clear_lilac_cache']) && current_user_can('manage_options')) {
            $this->clear_learndash_transients();
            wp_redirect(remove_query_arg('clear_lilac_cache'));
            exit;
        }
    }
    
    private function clear_learndash_transients() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_learndash_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_learndash_%'");
        
        wp_cache_flush();
        error_log('Lilac Course System: LearnDash transients cleared');
    }
    
    private function maybe_create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lilac_user_subscriptions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                course_id bigint(20) NOT NULL,
                order_id bigint(20) NOT NULL,
                access_from datetime NOT NULL,
                access_expires datetime DEFAULT NULL,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY course_id (course_id),
                KEY order_id (order_id),
                KEY status (status),
                KEY access_expires (access_expires)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            error_log('Lilac Course System: Created subscriptions table');
        }
    }
}
