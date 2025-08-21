<?php

namespace LilacCourseSystem\Handlers;

/**
 * Handles course access control and expiration
 */
class AccessHandler {
    
    public function __construct() {
        $this->register_hooks();
    }
    
    private function register_hooks() {
        // Course access control
        add_filter('learndash_user_has_course_access', [$this, 'check_course_access'], 10, 3);
        add_action('template_redirect', [$this, 'check_course_page_access'], 5);
        
        // Content filtering for expired access
        add_filter('the_content', [$this, 'filter_expired_content'], 10);
        
        // AJAX handlers for access management
        add_action('wp_ajax_toggle_course_access', [$this, 'ajax_toggle_access']);
        add_action('wp_ajax_extend_course_access', [$this, 'ajax_extend_access']);
    }
    
    /**
     * Check if user has access to course
     */
    public function check_course_access($has_access, $user_id, $course_id) {
        if (!$user_id || !$course_id) {
            return false;
        }
        
        // Admin always has access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check grace period for recent purchases
        $grace_period = get_user_meta($user_id, 'lilac_grace_period', true);
        if ($grace_period && (current_time('timestamp') - $grace_period) < 600) {
            return true;
        }
        
        // Check expiration
        $expire_key = "course_{$course_id}_access_expires";
        $expires = get_user_meta($user_id, $expire_key, true);
        
        if (!$expires) {
            return $has_access; // No custom expiration, use default LearnDash logic
        }
        
        $current_time = current_time('timestamp');
        $access_valid = $expires > $current_time;
        
        error_log("Lilac Course System: Access check - User: {$user_id}, Course: {$course_id}, Expires: " . date('Y-m-d H:i:s', $expires) . ", Valid: " . ($access_valid ? 'Yes' : 'No'));
        
        return $access_valid;
    }
    
    /**
     * Check access when visiting course page
     */
    public function check_course_page_access() {
        if (!is_singular('sfwd-courses')) {
            return;
        }
        
        $course_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Non-logged-in users see purchase incentive
        if (!$user_id) {
            add_action('wp_footer', [$this, 'inject_purchase_notice']);
            return;
        }
        
        // Check if access is expired
        if (!$this->check_course_access(true, $user_id, $course_id)) {
            add_action('wp_footer', [$this, 'inject_expired_notice']);
        }
    }
    
    /**
     * Filter content for expired access
     */
    public function filter_expired_content($content) {
        if (!is_singular('sfwd-courses')) {
            return $content;
        }
        
        global $post;
        $course_id = $post->ID;
        $user_id = get_current_user_id();
        
        // Skip for admins
        if ($user_id && current_user_can('manage_options')) {
            return $content;
        }
        
        // Check access
        if (!$user_id || !$this->check_course_access(true, $user_id, $course_id)) {
            return $this->get_access_denied_content($course_id, $user_id);
        }
        
        return $content;
    }
    
    /**
     * Get access denied content
     */
    private function get_access_denied_content($course_id, $user_id) {
        $course_title = get_the_title($course_id);
        $is_logged_in = $user_id > 0;
        
        $title = $is_logged_in ? 'Access Expired' : 'Purchase Required';
        $message = $is_logged_in 
            ? "Your access to <strong>{$course_title}</strong> has expired."
            : "Purchase required to access <strong>{$course_title}</strong>.";
        
        return '<div class="lilac-access-denied">
            <div class="notice-container">
                <h2>🔒 ' . $title . '</h2>
                <p>' . $message . '</p>
                <div class="purchase-options">
                    <a href="' . home_url('/product/מנוי-תרגול-לאתר/') . '" class="purchase-btn">
                        🛒 Site Practice Subscription
                    </a>
                    <a href="' . home_url('/product/מנוי-תרגול/') . '" class="purchase-btn">
                        🛒 Practice Subscription
                    </a>
                    <a href="' . home_url('/קורס-מקוון/') . '" class="purchase-btn">
                        🛒 Online Course
                    </a>
                </div>
                <p class="support-link">
                    Need help? <a href="' . home_url('/contact') . '">Contact Support</a>
                </p>
            </div>
        </div>
        
        <style>
        .lilac-access-denied {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
            text-align: center;
        }
        .lilac-access-denied h2 {
            color: #856404;
            margin-top: 0;
        }
        .lilac-access-denied p {
            color: #856404;
            font-size: 16px;
        }
        .purchase-options {
            margin: 20px 0;
        }
        .purchase-btn {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
            display: inline-block;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .purchase-btn:hover {
            background: #005177;
            transform: translateY(-2px);
        }
        .support-link {
            font-size: 14px;
            margin-top: 15px;
        }
        </style>';
    }
    
    /**
     * Inject purchase notice for non-logged-in users
     */
    public function inject_purchase_notice() {
        global $post;
        $course_title = get_the_title($post->ID);
        
        echo '<script>
        jQuery(document).ready(function($) {
            var noticeHtml = `<div class="lilac-purchase-notice">
                <h2>🎓 Purchase Required</h2>
                <p>To access <strong>' . esc_js($course_title) . '</strong>, please purchase one of our packages:</p>
                <div class="purchase-options">
                    <a href="' . home_url('/product/מנוי-תרגול-לאתר/') . '" class="purchase-btn">🛒 Site Practice Subscription</a>
                    <a href="' . home_url('/product/מנוי-תרגול/') . '" class="purchase-btn">🛒 Practice Subscription</a>
                    <a href="' . home_url('/קורס-מקוון/') . '" class="purchase-btn">🛒 Online Course</a>
                </div>
            </div>`;
            
            $(".learndash-wrapper").first().html(noticeHtml);
        });
        </script>';
    }
    
    /**
     * Inject expired notice for logged-in users
     */
    public function inject_expired_notice() {
        global $post;
        $course_title = get_the_title($post->ID);
        
        echo '<script>
        jQuery(document).ready(function($) {
            var noticeHtml = `<div class="lilac-expired-notice">
                <h2>⏰ Access Expired</h2>
                <p>Your access to <strong>' . esc_js($course_title) . '</strong> has expired.</p>
                <p>To renew your access, please purchase again:</p>
                <div class="purchase-options">
                    <a href="' . home_url('/product/מנוי-תרגול-לאתר/') . '" class="purchase-btn">🛒 Renew Access</a>
                </div>
            </div>`;
            
            $(".learndash-wrapper").first().html(noticeHtml);
        });
        </script>';
    }
    
    /**
     * AJAX handler to toggle course access
     */
    public function ajax_toggle_access() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $action = sanitize_text_field($_POST['toggle_action']);
        
        if ($action === 'disable') {
            // Set expiration to past date
            update_user_meta($user_id, "course_{$course_id}_access_expires", current_time('timestamp') - 1);
            wp_send_json_success(['message' => 'Access disabled']);
        } else {
            // Extend access by 30 days
            $new_expiry = current_time('timestamp') + (30 * DAY_IN_SECONDS);
            update_user_meta($user_id, "course_{$course_id}_access_expires", $new_expiry);
            wp_send_json_success(['message' => 'Access extended']);
        }
    }
    
    /**
     * AJAX handler to extend course access
     */
    public function ajax_extend_access() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $days = intval($_POST['days']);
        
        $new_expiry = current_time('timestamp') + ($days * DAY_IN_SECONDS);
        update_user_meta($user_id, "course_{$course_id}_access_expires", $new_expiry);
        
        wp_send_json_success([
            'message' => "Access extended by {$days} days",
            'new_expiry' => date('Y-m-d H:i:s', $new_expiry)
        ]);
    }
}
