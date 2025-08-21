<?php

namespace Lilac\SubscriptionTracker;

/**
 * Subscription Manager
 * 
 * Handles subscription tracking and course access management
 * 
 * @package Lilac\SubscriptionTracker
 * @since 1.0.0
 */
class SubscriptionManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // WooCommerce hooks
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted']);
        add_action('woocommerce_subscription_status_updated', [$this, 'handleSubscriptionStatusChange'], 10, 3);
        
        // LearnDash hooks
        add_filter('learndash_user_has_course_access', [$this, 'checkCourseAccess'], 10, 3);
        
        // AJAX handlers
        add_action('wp_ajax_st_get_user_subscriptions', [$this, 'ajaxGetUserSubscriptions']);
        add_action('wp_ajax_st_update_subscription', [$this, 'ajaxUpdateSubscription']);
        add_action('wp_ajax_st_extend_access', [$this, 'ajaxExtendAccess']);
        add_action('wp_ajax_st_revoke_access', [$this, 'ajaxRevokeAccess']);
        add_action('wp_ajax_st_import_existing_subscriptions', [$this, 'ajaxImportExistingSubscriptions']);
        add_action('wp_ajax_st_get_recent_subscriptions', [$this, 'ajaxGetRecentSubscriptions']);
        add_action('wp_ajax_st_get_all_students', [$this, 'ajaxGetAllStudents']);
        
        // Cron for cleanup
        add_action('subscription_tracker_daily_cleanup', [$this, 'dailyCleanup']);
        if (!wp_next_scheduled('subscription_tracker_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'subscription_tracker_daily_cleanup');
        }
    }
    
    /**
     * Handle completed WooCommerce order
     * 
     * @param int $order_id
     */
    public function handleOrderCompleted(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            // Get associated courses
            $courses = get_post_meta($product_id, '_learndash_courses', true);
            if (empty($courses) || !is_array($courses)) {
                continue;
            }
            
            foreach ($courses as $course_id) {
                $this->grantCourseAccess($user_id, $course_id, $order_id, $product_id);
            }
        }
    }
    
    /**
     * Grant course access to user
     * 
     * @param int $user_id
     * @param int $course_id
     * @param int $order_id
     * @param int $product_id
     */
    public function grantCourseAccess(int $user_id, int $course_id, int $order_id, int $product_id): void {
        // Calculate expiry date
        $access_duration = get_post_meta($product_id, '_subscription_duration_days', true);
        $access_duration = $access_duration ? intval($access_duration) : 30; // Default 30 days
        
        $expires_timestamp = time() + ($access_duration * DAY_IN_SECONDS);
        
        // Store in user meta
        update_user_meta($user_id, "course_{$course_id}_access_expires", $expires_timestamp);
        update_user_meta($user_id, "course_{$course_id}_order_id", $order_id);
        update_user_meta($user_id, "course_{$course_id}_product_id", $product_id);
        update_user_meta($user_id, "course_{$course_id}_granted_date", time());
        
        // Store in custom table for better querying
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $wpdb->replace(
            $table_name,
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'order_id' => $order_id,
                'product_id' => $product_id,
                'granted_date' => current_time('mysql'),
                'expires_date' => date('Y-m-d H:i:s', $expires_timestamp),
                'status' => 'active',
                'access_duration_days' => $access_duration
            ],
            [
                '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d'
            ]
        );
        
        // Enroll user in course
        ld_update_course_access($user_id, $course_id, false);
        
        // Log the action
        $this->logAction($user_id, $course_id, 'access_granted', [
            'order_id' => $order_id,
            'product_id' => $product_id,
            'expires_timestamp' => $expires_timestamp,
            'duration_days' => $access_duration
        ]);
    }
    
    /**
     * Check if user has course access
     * 
     * @param bool $has_access
     * @param int $user_id
     * @param int $course_id
     * @return bool
     */
    public function checkCourseAccess(bool $has_access, int $user_id, int $course_id): bool {
        // If already has access, don't interfere
        if ($has_access) {
            return $has_access;
        }
        
        // Check our custom access
        $expires_timestamp = get_user_meta($user_id, "course_{$course_id}_access_expires", true);
        
        if ($expires_timestamp && is_numeric($expires_timestamp)) {
            $current_time = time();
            
            if ($expires_timestamp > $current_time) {
                return true; // Access is still valid
            } else {
                // Access expired, update status
                $this->updateSubscriptionStatus($user_id, $course_id, 'expired');
                return false;
            }
        }
        
        return $has_access;
    }
    
    /**
     * Get user subscriptions
     * 
     * @param int $user_id
     * @return array
     */
    public function getUserSubscriptions(int $user_id): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY granted_date DESC",
            $user_id
        ), ARRAY_A);
        
        // If no subscriptions in our table, try to import from user meta
        if (empty($subscriptions)) {
            $subscriptions = $this->importUserMetaSubscriptions($user_id);
        }
        
        // Enhance with course and product info
        foreach ($subscriptions as &$subscription) {
            $course = get_post($subscription['course_id']);
            $product = wc_get_product($subscription['product_id']);
            
            $subscription['course_title'] = $course ? $course->post_title : 'Unknown Course';
            $subscription['product_name'] = $product ? $product->get_name() : 'Unknown Product';
            $subscription['is_expired'] = strtotime($subscription['expires_date']) < time();
            $subscription['days_remaining'] = max(0, ceil((strtotime($subscription['expires_date']) - time()) / DAY_IN_SECONDS));
        }
        
        return $subscriptions;
    }
    
    /**
     * Update subscription status
     * 
     * @param int $user_id
     * @param int $course_id
     * @param string $status
     */
    public function updateSubscriptionStatus(int $user_id, int $course_id, string $status): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $wpdb->update(
            $table_name,
            ['status' => $status],
            ['user_id' => $user_id, 'course_id' => $course_id],
            ['%s'],
            ['%d', '%d']
        );
        
        $this->logAction($user_id, $course_id, 'status_updated', ['new_status' => $status]);
    }
    
    /**
     * Extend access for a subscription
     * 
     * @param int $user_id
     * @param int $course_id
     * @param int $additional_days
     * @return bool
     */
    public function extendAccess(int $user_id, int $course_id, int $additional_days): bool {
        $current_expires = get_user_meta($user_id, "course_{$course_id}_access_expires", true);
        
        if (!$current_expires) {
            return false;
        }
        
        // Extend from current expiry or now, whichever is later
        $base_time = max(time(), intval($current_expires));
        $new_expires = $base_time + ($additional_days * DAY_IN_SECONDS);
        
        // Update user meta
        update_user_meta($user_id, "course_{$course_id}_access_expires", $new_expires);
        
        // Update database
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $wpdb->update(
            $table_name,
            [
                'expires_date' => date('Y-m-d H:i:s', $new_expires),
                'status' => 'active'
            ],
            ['user_id' => $user_id, 'course_id' => $course_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
        
        $this->logAction($user_id, $course_id, 'access_extended', [
            'additional_days' => $additional_days,
            'new_expires' => $new_expires
        ]);
        
        return true;
    }
    
    /**
     * Log action
     * 
     * @param int $user_id
     * @param int $course_id
     * @param string $action
     * @param array $data
     */
    private function logAction(int $user_id, int $course_id, string $action, array $data = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Subscription Tracker: %s for user %d, course %d. Data: %s',
                $action,
                $user_id,
                $course_id,
                json_encode($data)
            ));
        }
    }
    
    /**
     * AJAX: Get user subscriptions
     */
    public function ajaxGetUserSubscriptions(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
            return;
        }
        
        $subscriptions = $this->getUserSubscriptions($user_id);
        wp_send_json_success($subscriptions);
    }
    
    /**
     * AJAX: Update subscription
     */
    public function ajaxUpdateSubscription(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        if (!$user_id || !$course_id || !$status) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $this->updateSubscriptionStatus($user_id, $course_id, $status);
        wp_send_json_success('Subscription updated');
    }
    
    /**
     * AJAX: Extend access
     */
    public function ajaxExtendAccess(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        $days = intval($_POST['days'] ?? 0);
        
        if (!$user_id || !$course_id || !$days) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $success = $this->extendAccess($user_id, $course_id, $days);
        
        if ($success) {
            wp_send_json_success('Access extended successfully');
        } else {
            wp_send_json_error('Failed to extend access');
        }
    }
    
    /**
     * Daily cleanup task
     */
    public function dailyCleanup(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        // Update expired subscriptions
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET status = 'expired' WHERE expires_date < %s AND status = 'active'",
            current_time('mysql')
        ));
        
        $this->logAction(0, 0, 'daily_cleanup_completed');
    }
    
    /**
     * Revoke course access
     * 
     * @param int $user_id
     * @param int $course_id
     * @return bool
     */
    public function revokeAccess(int $user_id, int $course_id): bool {
        // Remove from user meta
        delete_user_meta($user_id, "course_{$course_id}_access_expires");
        delete_user_meta($user_id, "course_{$course_id}_order_id");
        delete_user_meta($user_id, "course_{$course_id}_product_id");
        delete_user_meta($user_id, "course_{$course_id}_granted_date");
        
        // Update database status
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $wpdb->update(
            $table_name,
            ['status' => 'revoked'],
            ['user_id' => $user_id, 'course_id' => $course_id],
            ['%s'],
            ['%d', '%d']
        );
        
        // Remove from LearnDash
        ld_update_course_access($user_id, $course_id, true); // true = remove access
        
        $this->logAction($user_id, $course_id, 'access_revoked');
        
        return true;
    }
    
    /**
     * Import subscriptions from user meta (for existing data)
     * 
     * @param int $user_id
     * @return array
     */
    public function importUserMetaSubscriptions(int $user_id): array {
        $subscriptions = [];
        
        // Get all user meta keys that match our pattern
        $user_meta = get_user_meta($user_id);
        
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                $course_id = intval($matches[1]);
                $expires_timestamp = intval($value[0]);
                
                if ($expires_timestamp > 0) {
                    $order_id = get_user_meta($user_id, "course_{$course_id}_order_id", true) ?: 0;
                    $product_id = get_user_meta($user_id, "course_{$course_id}_product_id", true) ?: 0;
                    $granted_timestamp = get_user_meta($user_id, "course_{$course_id}_granted_date", true) ?: time();
                    
                    $subscription = [
                        'id' => 0, // No DB record yet
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'order_id' => intval($order_id),
                        'product_id' => intval($product_id),
                        'granted_date' => date('Y-m-d H:i:s', $granted_timestamp),
                        'expires_date' => date('Y-m-d H:i:s', $expires_timestamp),
                        'status' => $expires_timestamp > time() ? 'active' : 'expired',
                        'access_duration_days' => ceil(($expires_timestamp - $granted_timestamp) / DAY_IN_SECONDS),
                        'created_at' => date('Y-m-d H:i:s', $granted_timestamp),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $subscriptions[] = $subscription;
                }
            }
        }
        
        return $subscriptions;
    }
    
    /**
     * Import all existing subscriptions from user meta to database
     * 
     * @return array Statistics about import
     */
    public function importAllExistingSubscriptions(): array {
        global $wpdb;
        
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        
        // Get all users with course access meta
        $results = $wpdb->get_results(
            "SELECT user_id, meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'course_%_access_expires'",
            ARRAY_A
        );
        
        foreach ($results as $row) {
            if (preg_match('/^course_(\d+)_access_expires$/', $row['meta_key'], $matches)) {
                $user_id = intval($row['user_id']);
                $course_id = intval($matches[1]);
                $expires_timestamp = intval($row['meta_value']);
                
                if ($expires_timestamp > 0) {
                    // Check if already exists in our table
                    $table_name = $wpdb->prefix . 'subscription_tracker';
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$table_name} WHERE user_id = %d AND course_id = %d",
                        $user_id, $course_id
                    ));
                    
                    if (!$exists) {
                        $order_id = get_user_meta($user_id, "course_{$course_id}_order_id", true) ?: 0;
                        $product_id = get_user_meta($user_id, "course_{$course_id}_product_id", true) ?: 0;
                        $granted_timestamp = get_user_meta($user_id, "course_{$course_id}_granted_date", true) ?: time();
                        
                        $result = $wpdb->insert(
                            $table_name,
                            [
                                'user_id' => $user_id,
                                'course_id' => $course_id,
                                'order_id' => intval($order_id),
                                'product_id' => intval($product_id),
                                'granted_date' => date('Y-m-d H:i:s', $granted_timestamp),
                                'expires_date' => date('Y-m-d H:i:s', $expires_timestamp),
                                'status' => $expires_timestamp > time() ? 'active' : 'expired',
                                'access_duration_days' => ceil(($expires_timestamp - $granted_timestamp) / DAY_IN_SECONDS)
                            ],
                            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d']
                        );
                        
                        if ($result) {
                            $stats['imported']++;
                        } else {
                            $stats['errors']++;
                        }
                    } else {
                        $stats['skipped']++;
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * AJAX: Revoke access
     */
    public function ajaxRevokeAccess(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$user_id || !$course_id) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $success = $this->revokeAccess($user_id, $course_id);
        
        if ($success) {
            wp_send_json_success('Access revoked successfully');
        } else {
            wp_send_json_error('Failed to revoke access');
        }
    }
    
    /**
     * AJAX: Import existing subscriptions
     */
    public function ajaxImportExistingSubscriptions(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $stats = $this->importAllExistingSubscriptions();
        
        wp_send_json_success([
            'message' => sprintf(
                'Import completed: %d imported, %d skipped, %d errors',
                $stats['imported'],
                $stats['skipped'],
                $stats['errors']
            ),
            'stats' => $stats
        ]);
    }
    
    /**
     * Get recent subscriptions
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentSubscriptions(int $limit = 5): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY granted_date DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        
        // If no subscriptions in table, try to get from user meta
        if (empty($subscriptions)) {
            $subscriptions = $this->getRecentSubscriptionsFromMeta($limit);
        }
        
        // Enhance with user, course and product info
        foreach ($subscriptions as &$subscription) {
            $user = get_user_by('id', $subscription['user_id']);
            $course = get_post($subscription['course_id']);
            $product = wc_get_product($subscription['product_id']);
            
            $subscription['user_name'] = $user ? $user->display_name : 'Unknown User';
            $subscription['user_email'] = $user ? $user->user_email : '';
            $subscription['course_title'] = $course ? $course->post_title : 'Unknown Course';
            $subscription['product_name'] = $product ? $product->get_name() : 'Unknown Product';
            $subscription['is_expired'] = strtotime($subscription['expires_date']) < time();
            $subscription['days_remaining'] = max(0, ceil((strtotime($subscription['expires_date']) - time()) / DAY_IN_SECONDS));
        }
        
        return $subscriptions;
    }
    
    /**
     * Get recent subscriptions from user meta (fallback)
     * 
     * @param int $limit
     * @return array
     */
    private function getRecentSubscriptionsFromMeta(int $limit = 5): array {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT user_id, meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'course_%_access_expires'
             ORDER BY user_id DESC
             LIMIT 20",
            ARRAY_A
        );
        
        $subscriptions = [];
        foreach ($results as $row) {
            if (preg_match('/^course_(\\d+)_access_expires$/', $row['meta_key'], $matches)) {
                $user_id = intval($row['user_id']);
                $course_id = intval($matches[1]);
                $expires_timestamp = intval($row['meta_value']);
                
                if ($expires_timestamp > 0) {
                    $order_id = get_user_meta($user_id, "course_{$course_id}_order_id", true) ?: 0;
                    $product_id = get_user_meta($user_id, "course_{$course_id}_product_id", true) ?: 0;
                    $granted_timestamp = get_user_meta($user_id, "course_{$course_id}_granted_date", true) ?: time();
                    
                    $subscriptions[] = [
                        'id' => 0,
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'order_id' => intval($order_id),
                        'product_id' => intval($product_id),
                        'granted_date' => date('Y-m-d H:i:s', $granted_timestamp),
                        'expires_date' => date('Y-m-d H:i:s', $expires_timestamp),
                        'status' => $expires_timestamp > time() ? 'active' : 'expired',
                        'access_duration_days' => ceil(($expires_timestamp - $granted_timestamp) / DAY_IN_SECONDS)
                    ];
                    
                    if (count($subscriptions) >= $limit) {
                        break;
                    }
                }
            }
        }
        
        return $subscriptions;
    }
    
    /**
     * Get all students with subscriptions
     * 
     * @return array
     */
    public function getAllStudents(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        // Get users from subscription table
        $user_ids = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$table_name} ORDER BY user_id DESC"
        );
        
        // If no users in table, get from user meta
        if (empty($user_ids)) {
            $user_ids = $wpdb->get_col(
                "SELECT DISTINCT user_id FROM {$wpdb->usermeta} 
                 WHERE meta_key LIKE 'course_%_access_expires'
                 ORDER BY user_id DESC
                 LIMIT 50"
            );
        }
        
        $students = [];
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $subscription_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
                    $user_id
                ));
                
                // If no count from table, check user meta
                if (!$subscription_count) {
                    $meta_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                         WHERE user_id = %d AND meta_key LIKE 'course_%%_access_expires'",
                        $user_id
                    ));
                    $subscription_count = $meta_count;
                }
                
                $students[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'login' => $user->user_login,
                    'subscription_count' => intval($subscription_count),
                    'registered' => $user->user_registered
                ];
            }
        }
        
        return $students;
    }
    
    /**
     * AJAX: Get recent subscriptions
     */
    public function ajaxGetRecentSubscriptions(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $limit = intval($_POST['limit'] ?? 5);
        $limit = min(max($limit, 1), 20); // Between 1 and 20
        
        $subscriptions = $this->getRecentSubscriptions($limit);
        wp_send_json_success($subscriptions);
    }
    
    /**
     * AJAX: Get all students
     */
    public function ajaxGetAllStudents(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $students = $this->getAllStudents();
        wp_send_json_success($students);
    }
}
