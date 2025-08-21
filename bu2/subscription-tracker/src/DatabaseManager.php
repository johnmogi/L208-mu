<?php

namespace Lilac\SubscriptionTracker;

/**
 * Database Manager
 * 
 * Handles database table creation and management
 * 
 * @package Lilac\SubscriptionTracker
 * @since 1.0.0
 */
class DatabaseManager {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Create database tables
     */
    public static function createTables(): void {
        global $wpdb;
        
        $installed_version = get_option('subscription_tracker_db_version');
        
        if ($installed_version !== self::DB_VERSION) {
            self::createSubscriptionTable();
            self::createLogTable();
            
            update_option('subscription_tracker_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Create subscription tracking table
     */
    private static function createSubscriptionTable(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            granted_date datetime NOT NULL,
            expires_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            access_duration_days int(11) NOT NULL DEFAULT 30,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_course (user_id, course_id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY expires_date (expires_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create log table
     */
    private static function createLogTable(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_tracker_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            action varchar(50) NOT NULL,
            data longtext,
            ip_address varchar(45),
            user_agent text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log action to database
     * 
     * @param int $user_id
     * @param int $course_id
     * @param string $action
     * @param array $data
     */
    public static function logAction(int $user_id, int $course_id, string $action, array $data = []): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_tracker_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'action' => $action,
                'data' => json_encode($data),
                'ip_address' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            [
                '%d', '%d', '%s', '%s', '%s', '%s'
            ]
        );
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private static function getClientIp(): string {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get subscription statistics
     * 
     * @return array
     */
    public static function getStatistics(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $stats = [];
        
        // Total subscriptions
        $stats['total_subscriptions'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Active subscriptions
        $stats['active_subscriptions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s AND expires_date > %s",
            'active',
            current_time('mysql')
        ));
        
        // Expired subscriptions
        $stats['expired_subscriptions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE expires_date <= %s",
            current_time('mysql')
        ));
        
        // Expiring soon (next 7 days)
        $stats['expiring_soon'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %s AND expires_date BETWEEN %s AND %s",
            'active',
            current_time('mysql'),
            date('Y-m-d H:i:s', strtotime('+7 days'))
        ));
        
        // Most popular courses
        $stats['popular_courses'] = $wpdb->get_results(
            "SELECT course_id, COUNT(*) as subscription_count 
             FROM $table_name 
             GROUP BY course_id 
             ORDER BY subscription_count DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        // Recent activity (last 30 days)
        $stats['recent_subscriptions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE granted_date >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        return $stats;
    }
    
    /**
     * Clean up old logs
     * 
     * @param int $days_to_keep
     */
    public static function cleanupLogs(int $days_to_keep = 90): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscription_tracker_logs';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"))
        ));
    }
    
    /**
     * Get user subscription history
     * 
     * @param int $user_id
     * @return array
     */
    public static function getUserHistory(int $user_id): array {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'subscription_tracker_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function dropTables(): void {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'subscription_tracker',
            $wpdb->prefix . 'subscription_tracker_logs'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('subscription_tracker_db_version');
    }
}
