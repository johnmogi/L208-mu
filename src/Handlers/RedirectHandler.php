<?php

namespace LilacCourseSystem\Handlers;

/**
 * Handles post-purchase redirects and auto-login
 */
class RedirectHandler {
    
    public function __construct() {
        $this->register_hooks();
    }
    
    private function register_hooks() {
        // Post-purchase redirect
        add_action('woocommerce_thankyou', [$this, 'handle_post_purchase_redirect'], 5, 1);
        
        // Login redirect
        add_filter('login_redirect', [$this, 'handle_login_redirect'], 10, 3);
        
        // Auto-login after checkout
        add_action('woocommerce_checkout_order_processed', [$this, 'auto_login_after_checkout'], 10, 3);
    }
    
    /**
     * Handle post-purchase redirect to course
     */
    public function handle_post_purchase_redirect($order_id) {
        if (!$order_id) return;
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        // Get the course to redirect to
        $redirect_course = get_user_meta($user_id, 'lilac_redirect_course', true);
        if (!$redirect_course) return;
        
        $course_url = get_permalink($redirect_course);
        if (!$course_url) return;
        
        // Set a flag for JavaScript redirect
        echo '<script>
            setTimeout(function() {
                window.location.href = "' . esc_url($course_url) . '";
            }, 3000);
        </script>';
        
        // Clean up redirect meta
        delete_user_meta($user_id, 'lilac_redirect_course');
        
        error_log("Lilac Course System: Redirecting user {$user_id} to course {$redirect_course}");
    }
    
    /**
     * Handle login redirect for recent purchasers
     */
    public function handle_login_redirect($redirect_to, $request, $user) {
        if (!$user || is_wp_error($user)) {
            return $redirect_to;
        }
        
        $user_id = $user->ID;
        $purchase_time = get_user_meta($user_id, 'lilac_purchase_time', true);
        
        // Check if user made a recent purchase (within 10 minutes)
        if ($purchase_time && (current_time('timestamp') - $purchase_time) < 600) {
            $redirect_course = get_user_meta($user_id, 'lilac_redirect_course', true);
            
            if ($redirect_course) {
                $course_url = get_permalink($redirect_course);
                if ($course_url) {
                    delete_user_meta($user_id, 'lilac_redirect_course');
                    delete_user_meta($user_id, 'lilac_purchase_time');
                    
                    error_log("Lilac Course System: Login redirect to course {$redirect_course} for user {$user_id}");
                    return $course_url;
                }
            }
        }
        
        return $redirect_to;
    }
    
    /**
     * Auto-login user after successful checkout
     */
    public function auto_login_after_checkout($order_id, $posted_data, $order) {
        // Only for guest checkouts
        if (is_user_logged_in()) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        // Auto-login the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        error_log("Lilac Course System: Auto-logged in user {$user_id} after checkout");
    }
}
