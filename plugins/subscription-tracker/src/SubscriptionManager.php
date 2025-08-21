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
}
