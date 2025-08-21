<?php
/**
 * Debug Course Access - Temporary debugging tool
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Debug_Course_Access {
    
    public function __construct() {
        add_action('wp_ajax_debug_product_mapping', [$this, 'debug_product_mapping']);
        add_action('wp_ajax_debug_user_access', [$this, 'debug_user_access']);
        add_action('wp_ajax_debug_order_processing', [$this, 'debug_order_processing']);
        
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Debug Course Access',
            'Debug Course Access',
            'manage_options',
            'debug-course-access',
            [$this, 'admin_page']
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Debug Course Access</h1>
            
            <div class="card">
                <h2>Product Mapping Check</h2>
                <p>Check if product 10820 is mapped to course 4263:</p>
                <button type="button" class="button" onclick="debugProductMapping()">Check Product Mapping</button>
                <div id="product-mapping-result"></div>
            </div>
            
            <div class="card">
                <h2>User Access Check</h2>
                <p>Check user access for user ID:</p>
                <input type="number" id="user-id-input" placeholder="User ID" value="58">
                <button type="button" class="button" onclick="debugUserAccess()">Check User Access</button>
                <div id="user-access-result"></div>
            </div>
            
            <div class="card">
                <h2>Recent Orders Check</h2>
                <p>Check recent orders for debugging:</p>
                <button type="button" class="button" onclick="debugOrderProcessing()">Check Recent Orders</button>
                <div id="order-processing-result"></div>
            </div>
        </div>
        
        <script>
        function debugProductMapping() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=debug_product_mapping'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('product-mapping-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            });
        }
        
        function debugUserAccess() {
            const userId = document.getElementById('user-id-input').value;
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=debug_user_access&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('user-access-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            });
        }
        
        function debugOrderProcessing() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=debug_order_processing'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('order-processing-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            });
        }
        </script>
        
        <style>
        .card { margin: 20px 0; padding: 20px; background: white; border: 1px solid #ccd0d4; }
        pre { background: #f1f1f1; padding: 10px; overflow-x: auto; }
        </style>
        <?php
    }
    
    public function debug_product_mapping() {
        $product_id = 10820;
        $expected_course_id = 4263;
        
        $result = [
            'product_id' => $product_id,
            'expected_course_id' => $expected_course_id,
            'actual_course_id' => get_post_meta($product_id, '_lilac_course_id', true),
            'access_duration' => get_post_meta($product_id, '_lilac_access_duration', true),
            'product_exists' => get_post($product_id) !== null,
            'course_exists' => get_post($expected_course_id) !== null,
            'product_title' => get_the_title($product_id),
            'course_title' => get_the_title($expected_course_id),
            'all_product_meta' => get_post_meta($product_id)
        ];
        
        wp_send_json($result);
    }
    
    public function debug_user_access() {
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
            return;
        }
        
        $course_id = 4263;
        
        // Get access manager
        $access_manager = \Lilac\CourseAccess\Core\AccessManager::getInstance();
        
        $result = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'user_exists' => get_user_by('id', $user_id) !== false,
            'course_exists' => get_post($course_id) !== null,
            'has_learndash_access' => function_exists('sfwd_lms_has_access') ? sfwd_lms_has_access($course_id, $user_id) : 'LearnDash not available',
            'course_expiration' => $access_manager->getCourseExpiration($user_id, $course_id),
            'access_status' => $access_manager->getAccessStatus($user_id, $course_id),
            'has_course_access' => $access_manager->hasCourseAccess($user_id, $course_id),
            'user_courses' => $access_manager->getUserCourses($user_id),
            'recent_purchase_meta' => get_user_meta($user_id, 'lilac_recent_purchase_redirect', true),
            'all_course_meta' => $this->getUserCourseMeta($user_id),
            'paused_subscriptions' => $this->getPausedSubscriptionsMeta($user_id)
        ];
        
        wp_send_json($result);
    }
    
    public function debug_order_processing() {
        // Get recent orders
        $orders = wc_get_orders([
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['completed', 'processing', 'on-hold']
        ]);
        
        $result = [];
        
        foreach ($orders as $order) {
            $order_data = [
                'order_id' => $order->get_id(),
                'status' => $order->get_status(),
                'user_id' => $order->get_user_id(),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'items' => []
            ];
            
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $course_id = get_post_meta($product_id, '_lilac_course_id', true);
                
                $order_data['items'][] = [
                    'product_id' => $product_id,
                    'product_name' => $item->get_name(),
                    'mapped_course_id' => $course_id,
                    'access_duration' => get_post_meta($product_id, '_lilac_access_duration', true)
                ];
            }
            
            $result[] = $order_data;
        }
        
        wp_send_json($result);
    }
    
    private function getUserCourseMeta($user_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND (meta_key LIKE 'course_%_access_expires' OR meta_key LIKE 'paused_course_%')",
            $user_id
        ));
        
        $meta = [];
        foreach ($results as $result) {
            $meta[$result->meta_key] = $result->meta_value;
        }
        
        return $meta;
    }
    
    private function getPausedSubscriptionsMeta($user_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND meta_key LIKE 'paused_course_%'",
            $user_id
        ));
        
        $paused = [];
        foreach ($results as $result) {
            $paused[$result->meta_key] = maybe_unserialize($result->meta_value);
        }
        
        return $paused;
    }
}

// Initialize
new Debug_Course_Access();
