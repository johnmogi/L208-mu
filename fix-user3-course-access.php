<?php
/**
 * Fix user_3 course access and expiration
 */

// Hook into WordPress after it's fully loaded
add_action('wp_loaded', 'fix_user3_course_access');

function fix_user3_course_access() {
    // Only run if specifically requested via URL parameter
    if (!isset($_GET['fix_user3_access'])) {
        return;
    }
    
    // Get user_3
    $user = get_user_by('login', 'user_3');
if (!$user) {
    echo "User 'user_3' not found\n";
    exit;
}

echo "=== FIXING USER_3 COURSE ACCESS ===\n";
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
echo "Found order #" . $order->get_id() . "\n";

// Process each item in the order
foreach ($order->get_items() as $item) {
    $product_id = $item->get_product_id();
    $product = $item->get_product();
    
    if (!$product) continue;
    
    echo "Processing product: " . $product->get_name() . " (ID: $product_id)\n";
    
    // Check if product has course association
    $course_id = get_post_meta($product_id, '_lilac_course_id', true);
    $duration_key = get_post_meta($product_id, '_lilac_access_duration', true);
    
    // If no Lilac association, try main system
    if (!$course_id) {
        $course_id = get_post_meta($product_id, '_related_course', true);
        if (is_array($course_id)) {
            $course_id = $course_id[0];
        }
    }
    
    if (!$course_id) {
        echo "  No course association found - setting default course ID 4236\n";
        $course_id = 4236; // Default course from URL
        
        // Set the association for future use
        update_post_meta($product_id, '_lilac_course_id', $course_id);
        update_post_meta($product_id, '_lilac_access_duration', 'access_1month');
    }
    
    // Calculate expiration (default 30 days)
    $duration_days = 30;
    if ($duration_key) {
        switch ($duration_key) {
            case 'trial_2weeks':
                $duration_days = 14;
                break;
            case 'access_1month':
                $duration_days = 30;
                break;
            case 'access_60days':
                $duration_days = 60;
                break;
            case 'access_1year':
                $duration_days = 365;
                break;
            case 'paused_2weeks':
                // Handle paused subscription
                echo "  Creating paused subscription for course $course_id\n";
                update_user_meta($user->ID, "paused_course_{$course_id}", array(
                    'order_id' => $order->get_id(),
                    'duration' => 14,
                    'purchased_date' => time(),
                    'status' => 'paused'
                ));
                continue 2;
        }
    }
    
    $expires = time() + ($duration_days * DAY_IN_SECONDS);
    
    // Set course access using both systems for compatibility
    $access_key = "course_{$course_id}_access_from";
    $expire_key = "course_{$course_id}_access_expires";
    $order_key = "course_{$course_id}_order_id";
    
    update_user_meta($user->ID, $access_key, time());
    update_user_meta($user->ID, $expire_key, $expires);
    update_user_meta($user->ID, $order_key, $order->get_id());
    
    // Enroll in LearnDash if available
    if (function_exists('ld_update_course_access')) {
        ld_update_course_access($user->ID, $course_id, false);
    }
    
    echo "  ✅ Granted access to course $course_id, expires: " . date('Y-m-d H:i:s', $expires) . "\n";
}

echo "\n=== VERIFICATION ===\n";
// Verify access was set
$user_meta = get_user_meta($user->ID);
foreach ($user_meta as $key => $value) {
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

echo "\n=== COMPLETE ===\n";
