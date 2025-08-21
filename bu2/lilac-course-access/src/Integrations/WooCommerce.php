<?php

namespace Lilac\CourseAccess\Integrations;

use Lilac\CourseAccess\Core\AccessManager;

/**
 * WooCommerce Integration
 */
class WooCommerce {
    
    private static $instance = null;
    private $accessManager;
    
    // Access duration options (Hebrew labels)
    private $access_options = [
        'paused_2weeks' => 'מנוי מושהה (גישה ל-2 שבועות לאחר הפעלה)',
        'trial_2weeks' => 'ניסיון 2 שבועות',
        'access_1month' => 'גישה לחודש',
        'access_60days' => 'גישה ל-60 יום',
        'access_1year' => 'גישה לשנה'
    ];
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->accessManager = AccessManager::getInstance();
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Product fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'addCustomFields']);
        add_action('woocommerce_process_product_meta', [$this, 'saveCustomFields']);
        
        // Order processing - ENABLED for proper course access management
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompletion'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'handlePaymentComplete'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'handleOrderCompletion'], 10, 1);
        
        // Product columns
        add_filter('manage_edit-product_columns', [$this, 'addProductColumns']);
        add_action('manage_product_posts_custom_column', [$this, 'showProductColumns'], 10, 2);
    }
    
    /**
     * Handle order completion
     */
    public function handleOrderCompletion($orderId) {
        $this->processOrderCourses($orderId, 'order_completed');
    }
    
    /**
     * Handle payment completion
     */
    public function handlePaymentComplete($orderId) {
        $this->processOrderCourses($orderId, 'payment_complete');
    }
    
    /**
     * Process courses for an order
     */
    private function processOrderCourses($orderId, $trigger) {
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return;
        }
        
        $userId = $order->get_user_id();
        
        if (!$userId) {
            // Create user from order if needed
            $userId = $this->createUserFromOrder($order);
        }
        
        if (!$userId) {
            return;
        }
        
        // Set grace period for recent purchase
        update_user_meta($userId, 'lilac_recent_purchase_redirect', time());
        
        // Process each item in the order
        foreach ($order->get_items() as $item) {
            $productId = $item->get_product_id();
            $courseId = get_post_meta($productId, '_lilac_course_id', true);
            $durationKey = get_post_meta($productId, '_lilac_access_duration', true);
            
            if (!$courseId) {
                continue;
            }
            
            // Handle paused subscriptions
            if ($durationKey === 'paused_2weeks') {
                // Store paused subscription info
                update_user_meta($userId, "paused_course_{$courseId}", [
                    'order_id' => $orderId,
                    'duration' => 14,
                    'purchased_date' => time(),
                    'status' => 'paused'
                ]);
                
                $this->logDebug("Created paused subscription for user {$userId}, course {$courseId}");
                continue;
            }
            
            // Regular access
            $duration = $this->getCourseAccessDuration($productId);
            $expires = $duration > 0 ? (time() + ($duration * DAY_IN_SECONDS)) : 0;
            
            $this->accessManager->setCourseAccess($userId, $courseId, $expires);
            
            $this->logDebug("Granted access to user {$userId}, course {$courseId}, expires: " . ($expires ? date('Y-m-d H:i:s', $expires) : 'never'));
        }
        
        $this->logDebug("Processed order {$orderId} for user {$userId} via {$trigger}");
    }
    
    /**
     * Create user from order
     */
    private function createUserFromOrder($order) {
        $email = $order->get_billing_email();
        $firstName = $order->get_billing_first_name();
        $lastName = $order->get_billing_last_name();
        
        if (!$email) {
            return false;
        }
        
        // Check if user already exists
        $existingUser = get_user_by('email', $email);
        if ($existingUser) {
            return $existingUser->ID;
        }
        
        // Create new user
        $username = sanitize_user($email);
        $password = wp_generate_password();
        
        $userId = wp_create_user($username, $password, $email);
        
        if (is_wp_error($userId)) {
            return false;
        }
        
        // Update user meta
        wp_update_user([
            'ID' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => trim($firstName . ' ' . $lastName)
        ]);
        
        $this->logDebug("Created user {$userId} from order");
        
        return $userId;
    }
    
    /**
     * Activate paused subscription
     */
    public function activatePausedSubscription($userId, $courseId) {
        $pausedData = get_user_meta($userId, "paused_course_{$courseId}", true);
        
        if (!$pausedData || $pausedData['status'] !== 'paused') {
            return false;
        }
        
        // Calculate expiration from now
        $duration = $pausedData['duration'];
        $expires = time() + ($duration * DAY_IN_SECONDS);
        
        // Grant access
        $this->accessManager->setCourseAccess($userId, $courseId, $expires);
        
        // Update paused subscription status
        $pausedData['status'] = 'active';
        $pausedData['activated_date'] = time();
        $pausedData['expires'] = $expires;
        update_user_meta($userId, "paused_course_{$courseId}", $pausedData);
        
        $this->logDebug("Activated paused subscription for user {$userId}, course {$courseId}, expires: " . date('Y-m-d H:i:s', $expires));
        
        return true;
    }
    
    /**
     * Get paused subscriptions for user
     */
    public function getPausedSubscriptions($userId) {
        global $wpdb;
        
        $pausedSubs = [];
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND meta_key LIKE 'paused_course_%'",
            $userId
        ));
        
        foreach ($results as $result) {
            preg_match('/paused_course_(\d+)/', $result->meta_key, $matches);
            if (!empty($matches[1])) {
                $courseId = intval($matches[1]);
                $data = maybe_unserialize($result->meta_value);
                
                if ($data && is_array($data)) {
                    $data['course_id'] = $courseId;
                    $data['course_title'] = get_the_title($courseId);
                    $pausedSubs[] = $data;
                }
            }
        }
        
        return $pausedSubs;
    }
    
    /**
     * Add custom fields to product edit page
     */
    public function addCustomFields() {
        global $post;
        
        echo '<div class="options_group">';
        
        // Course selection
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        
        $course_options = [''];
        foreach ($courses as $course) {
            $course_options[$course->ID] = $course->post_title;
        }
        
        woocommerce_wp_select([
            'id' => '_lilac_course_id',
            'label' => __('Associated Course', 'lilac-course-access'),
            'options' => $course_options,
            'desc_tip' => true,
            'description' => __('Select the course this product provides access to', 'lilac-course-access')
        ]);
        
        // Access duration
        woocommerce_wp_select([
            'id' => '_lilac_access_duration',
            'label' => __('Access Duration', 'lilac-course-access'),
            'options' => $this->access_options,
            'desc_tip' => true,
            'description' => __('How long the customer will have access to the course', 'lilac-course-access')
        ]);
        
        echo '</div>';
    }
    
    /**
     * Save custom fields
     */
    public function saveCustomFields($post_id) {
        if (isset($_POST['_lilac_course_id'])) {
            update_post_meta($post_id, '_lilac_course_id', sanitize_text_field($_POST['_lilac_course_id']));
        }
        
        if (isset($_POST['_lilac_access_duration'])) {
            update_post_meta($post_id, '_lilac_access_duration', sanitize_text_field($_POST['_lilac_access_duration']));
        }
    }
    
    /**
     * Add product columns
     */
    public function addProductColumns($columns) {
        $columns['lilac_course'] = __('Course', 'lilac-course-access');
        $columns['lilac_duration'] = __('Duration', 'lilac-course-access');
        return $columns;
    }
    
    /**
     * Show product columns
     */
    public function showProductColumns($column, $post_id) {
        switch ($column) {
            case 'lilac_course':
                $course_id = get_post_meta($post_id, '_lilac_course_id', true);
                if ($course_id) {
                    echo get_the_title($course_id);
                } else {
                    echo '—';
                }
                break;
                
            case 'lilac_duration':
                $duration = get_post_meta($post_id, '_lilac_access_duration', true);
                if ($duration && isset($this->access_options[$duration])) {
                    echo $this->access_options[$duration];
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * Get course access duration in days from product
     */
    private function getCourseAccessDuration($productId) {
        $duration_key = get_post_meta($productId, '_lilac_access_duration', true);
        
        switch ($duration_key) {
            case 'paused_2weeks':
                return 14; // Will be activated later
            case 'trial_2weeks':
                return 14;
            case 'access_1month':
                return 30;
            case 'access_60days':
                return 60;
            case 'access_1year':
                return 365;
            default:
                return 30; // Default 1 month
        }
    }
    
    /**
     * Debug logging
     */
    private function logDebug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Lilac Course Access - WooCommerce] ' . $message);
        }
    }
}
