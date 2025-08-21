<?php
/**
 * Debug script to check user course access and expiration
 */

// Hook into WordPress after it's fully loaded
add_action('wp_loaded', 'debug_user_course_access');

function debug_user_course_access() {
    // Only run if specifically requested via URL parameter
    if (!isset($_GET['debug_user_access'])) {
        return;
    }
    
    // Get user_3 details
    $user = get_user_by('login', 'user_3');
    if (!$user) {
        echo "User 'user_3' not found\n";
        exit;
    }

    echo "=== USER DEBUG ===\n";
    echo "User ID: " . $user->ID . "\n";
    echo "Username: " . $user->user_login . "\n";
    echo "Display Name: " . $user->display_name . "\n";
    echo "Email: " . $user->user_email . "\n";

    // Get all user meta related to courses
    $all_meta = get_user_meta($user->ID);
    echo "\n=== COURSE ACCESS META ===\n";
    foreach ($all_meta as $key => $value) {
        if (strpos($key, 'course_') === 0) {
            $val = is_array($value) ? $value[0] : $value;
            if (strpos($key, '_expires') !== false && is_numeric($val)) {
                $date_str = $val > 0 ? date('Y-m-d H:i:s', $val) : 'No expiration';
                echo "$key: $val ($date_str)\n";
            } else {
                echo "$key: $val\n";
            }
        }
    }

    // Check LearnDash enrollment
    echo "\n=== LEARNDASH ENROLLMENT ===\n";
    if (function_exists('learndash_user_get_enrolled_courses')) {
        $enrolled_courses = learndash_user_get_enrolled_courses($user->ID);
        echo "Enrolled courses: " . print_r($enrolled_courses, true) . "\n";
    } else {
        echo "LearnDash not available\n";
    }

    // Check recent orders
    echo "\n=== RECENT ORDERS ===\n";
    $orders = wc_get_orders(array(
        'customer_id' => $user->ID,
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    foreach ($orders as $order) {
        echo "Order #" . $order->get_id() . " - " . $order->get_status() . " - " . $order->get_date_created()->format('Y-m-d H:i:s') . "\n";
        
        // Check order items for courses
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                echo "  Product: " . $product->get_name() . " (ID: " . $product->get_id() . ")\n";
                
                // Check if product has associated courses
                $courses = get_post_meta($product->get_id(), '_related_course', true);
                if ($courses) {
                    echo "    Associated courses: " . print_r($courses, true) . "\n";
                }
            }
        }
    }

    echo "\n=== CURRENT TIME ===\n";
    echo "Current timestamp: " . current_time('timestamp') . " (" . current_time('Y-m-d H:i:s') . ")\n";
}
