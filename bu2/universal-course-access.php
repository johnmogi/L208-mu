<?php
/**
 * Universal Course Access System for ALL Users
 * Automatically grants course access when any user completes a purchase
 */

// Helper functions for the new integrated system
function get_product_courses($product_id) {
    // Priority order: LearnDash WooCommerce (_related_course) first, then Lilac system
    $courses = get_post_meta($product_id, '_related_course', true);
    if (!$courses) {
        $courses = get_post_meta($product_id, '_lilac_course_id', true);
    }
    if (!$courses) {
        $courses = get_post_meta($product_id, '_learndash_courses', true);
    }
    
    if (!is_array($courses)) {
        $courses = $courses ? [$courses] : [];
    }
    
    return array_filter($courses);
}

function get_product_duration_settings($product_id) {
    // First check for Lilac system duration
    $duration = get_post_meta($product_id, '_lilac_access_duration', true);
    
    // Check for custom expiry date (from LearnDash WooCommerce settings)
    $custom_date = get_post_meta($product_id, '_wc_learndash_custom_expiry_date', true);
    
    if ($custom_date) {
        return [
            'duration' => $duration ?: 'custom',
            'custom_date' => $custom_date,
            'source' => 'LearnDash WooCommerce custom date'
        ];
    }
    
    // Fallback to duration only
    if ($duration) {
        return [
            'duration' => $duration,
            'source' => 'Lilac duration setting'
        ];
    }
    
    // Default fallback
    return [
        'duration' => 'access_60days',
        'source' => 'Default (60 days)'
    ];
}

function calculate_expiration_from_settings($duration_settings) {
    $current_time = time();
    
    // Handle array format (with custom date)
    if (is_array($duration_settings)) {
        if (!empty($duration_settings['custom_date'])) {
            return strtotime($duration_settings['custom_date']);
        }
        $duration = $duration_settings['duration'];
    } else {
        $duration = $duration_settings;
    }
    
    switch ($duration) {
        case 'trial_2weeks':
            return strtotime('+2 weeks', $current_time);
        case 'access_1month':
            return strtotime('+1 month', $current_time);
        case 'access_60days':
            return strtotime('+60 days', $current_time);
        case 'access_1year':
            return strtotime('+1 year', $current_time);
        case 'paused_2weeks':
            return strtotime('+2 weeks', $current_time);
        default:
            return 0; // No expiration
    }
}

// Hook into WooCommerce order completion - works for ALL users
add_action('woocommerce_order_status_completed', 'grant_course_access_on_purchase');
add_action('woocommerce_order_status_processing', 'grant_course_access_on_purchase');

function grant_course_access_on_purchase($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    
    error_log("=== GRANTING COURSE ACCESS FOR USER $user_id ===");
    error_log("Order #" . $order->get_id());
    
    // Process each item in the order
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $product = $item->get_product();
        
        if (!$product) continue;
        
        error_log("Processing product: " . $product->get_name() . " (ID: $product_id)");
        
        // Use the integrated system to get course and duration settings
        $courses = get_product_courses($product_id);
        $duration_settings = get_product_duration_settings($product_id);
        
        if (empty($courses)) {
            error_log("  No course association found - setting default course ID 4236");
            $course_id = 4236; // Default course
            $courses = [$course_id];
            
            // Set the association for future use
            update_post_meta($product_id, '_related_course', [$course_id]);
            update_post_meta($product_id, '_lilac_access_duration', 'access_60days');
        } else {
            $course_id = $courses[0]; // Use first course
        }
        
        error_log("  Course settings: " . print_r($duration_settings, true));
        
        // Calculate expiration using the integrated system
        $expires = calculate_expiration_from_settings($duration_settings);
        
        // Handle paused subscriptions
        if (is_array($duration_settings) && $duration_settings['duration'] === 'paused_2weeks') {
            error_log("  Creating paused subscription for course $course_id");
            update_user_meta($user_id, "paused_course_{$course_id}", array(
                'order_id' => $order->get_id(),
                'duration' => 14,
                'purchased_date' => time(),
                'status' => 'paused'
            ));
            continue;
        }
        
        // Set course access using both systems for compatibility
        $access_key = "course_{$course_id}_access_from";
        $expire_key = "course_{$course_id}_access_expires";
        $order_key = "course_{$course_id}_order_id";
        
        update_user_meta($user_id, $access_key, time());
        update_user_meta($user_id, $expire_key, $expires);
        update_user_meta($user_id, $order_key, $order->get_id());
        
        // Enroll in LearnDash if available
        if (function_exists('ld_update_course_access')) {
            ld_update_course_access($user_id, $course_id, false);
        }
        
        error_log("  ✅ Granted access to course $course_id for user $user_id, expires: " . date('Y-m-d H:i:s', $expires));
    }
}

// Manual fix function for existing users (optional - can be triggered via URL)
add_action('wp_loaded', 'manual_fix_user_course_access');

function manual_fix_user_course_access() {
    // Only run if specifically requested via URL parameter
    if (!isset($_GET['fix_user_access']) || !isset($_GET['user_login'])) {
        return;
    }
    
    $user_login = sanitize_text_field($_GET['user_login']);
    $user = get_user_by('login', $user_login);
    
    if (!$user) {
        echo "User '$user_login' not found\n";
        exit;
    }

    echo "=== FIXING COURSE ACCESS FOR USER: $user_login ===\n";
    echo "User ID: " . $user->ID . "\n";

    // Get the most recent order for this user
    $orders = wc_get_orders(array(
        'customer_id' => $user->ID,
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array('completed', 'processing')
    ));

    if (empty($orders)) {
        echo "No completed orders found for user\n";
        exit;
    }

    $order = $orders[0];
    grant_course_access_on_purchase($order->get_id());
    
    echo "\n=== COMPLETE ===\n";
}
