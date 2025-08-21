<?php
/**
 * Simple debug script to check course access system
 */

// Direct database query to check user_3
global $wpdb;

echo "=== DIRECT DATABASE QUERIES ===\n";

// Get user_3 ID
$user_id = $wpdb->get_var("SELECT ID FROM {$wpdb->users} WHERE user_login = 'user_3'");
echo "User ID for 'user_3': " . ($user_id ?: 'NOT FOUND') . "\n\n";

if ($user_id) {
    // Get all course-related meta
    $course_meta = $wpdb->get_results($wpdb->prepare("
        SELECT meta_key, meta_value 
        FROM {$wpdb->usermeta} 
        WHERE user_id = %d 
        AND (meta_key LIKE 'course_%' OR meta_key LIKE 'paused_%' OR meta_key LIKE 'ld_%' OR meta_key LIKE '%course%')
        ORDER BY meta_key
    ", $user_id));
    
    echo "=== COURSE ACCESS META ===\n";
    if ($course_meta) {
        foreach ($course_meta as $meta) {
            $value = $meta->meta_value;
            if (strpos($meta->meta_key, '_expires') !== false && is_numeric($value)) {
                $date_str = $value > 0 ? date('Y-m-d H:i:s', $value) : 'No expiration';
                echo "{$meta->meta_key}: {$value} ({$date_str})\n";
            } else {
                echo "{$meta->meta_key}: {$value}\n";
            }
        }
    } else {
        echo "No course-related meta found\n";
    }
    
    // Get recent orders
    echo "\n=== RECENT ORDERS ===\n";
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_status, p.post_date, pm.meta_value as customer_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
        WHERE p.post_type = 'shop_order' 
        AND pm.meta_value = %d
        ORDER BY p.post_date DESC
        LIMIT 5
    ", $user_id));
    
    if ($orders) {
        foreach ($orders as $order) {
            echo "Order #{$order->ID} - {$order->post_status} - {$order->post_date}\n";
            
            // Get order items
            $items = $wpdb->get_results($wpdb->prepare("
                SELECT oi.order_item_name, oim.meta_value as product_id
                FROM {$wpdb->prefix}woocommerce_order_items oi
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
                WHERE oi.order_id = %d AND oi.order_item_type = 'line_item'
            ", $order->ID));
            
            foreach ($items as $item) {
                echo "  Product: {$item->order_item_name} (ID: {$item->product_id})\n";
                
                // Check product course association
                $course_id = $wpdb->get_var($wpdb->prepare("
                    SELECT meta_value FROM {$wpdb->postmeta} 
                    WHERE post_id = %d AND meta_key = '_lilac_course_id'
                ", $item->product_id));
                
                if ($course_id) {
                    echo "    Associated Course ID: {$course_id}\n";
                }
                
                $duration = $wpdb->get_var($wpdb->prepare("
                    SELECT meta_value FROM {$wpdb->postmeta} 
                    WHERE post_id = %d AND meta_key = '_lilac_access_duration'
                ", $item->product_id));
                
                if ($duration) {
                    echo "    Access Duration: {$duration}\n";
                }
            }
        }
    } else {
        echo "No orders found for user\n";
    }
}

echo "\n=== PLUGIN STATUS ===\n";
echo "Lilac Course Access Plugin Active: " . (class_exists('Lilac\CourseAccess\Plugin') ? 'YES' : 'NO') . "\n";
echo "WC LearnDash Access Manager Active: " . (class_exists('WC_LearnDash_Access_Manager') ? 'YES' : 'NO') . "\n";
echo "Auto Login Plugin Active: " . (class_exists('Auto_Login_After_Checkout') ? 'YES' : 'NO') . "\n";

echo "\n=== CURRENT TIME ===\n";
echo "Current timestamp: " . current_time('timestamp') . " (" . current_time('Y-m-d H:i:s') . ")\n";
