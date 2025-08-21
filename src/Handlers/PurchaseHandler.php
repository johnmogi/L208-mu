<?php

namespace LilacCourseSystem\Handlers;

/**
 * Handles WooCommerce purchase processing and course enrollment
 */
class PurchaseHandler {
    
    public function __construct() {
        $this->register_hooks();
    }
    
    private function register_hooks() {
        // WooCommerce order completion hooks
        add_action('woocommerce_order_status_completed', [$this, 'process_order_completion'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'process_payment_completion'], 10, 1);
        
        // Product meta fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_product_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_fields']);
    }
    
    /**
     * Process order completion - main enrollment trigger
     */
    public function process_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Lilac Course System: Order {$order_id} not found");
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            error_log("Lilac Course System: No user ID for order {$order_id}");
            return;
        }
        
        $enrolled_courses = [];
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $courses = $this->get_product_courses($product_id);
            $duration_settings = $this->get_product_duration_settings($product_id);
            
            if (empty($courses)) {
                error_log("Lilac Course System: No courses found for product {$product_id}");
                continue;
            }
            
            foreach ($courses as $course_id) {
                if (!in_array($course_id, $enrolled_courses)) {
                    $this->enroll_user_in_course($user_id, $course_id, $duration_settings, $order_id);
                    $enrolled_courses[] = $course_id;
                }
            }
        }
        
        if (!empty($enrolled_courses)) {
            // Set redirect course (first purchased course)
            update_user_meta($user_id, 'lilac_redirect_course', $enrolled_courses[0]);
            update_user_meta($user_id, 'lilac_purchase_time', current_time('timestamp'));
            
            $order->add_order_note('Lilac Course System: Enrolled in ' . count($enrolled_courses) . ' courses');
            error_log("Lilac Course System: Order {$order_id} processed - enrolled in courses: " . implode(', ', $enrolled_courses));
        }
    }
    
    /**
     * Process payment completion for immediate access
     */
    public function process_payment_completion($order_id) {
        // Set grace period for immediate access
        $order = wc_get_order($order_id);
        if ($order && $order->get_user_id()) {
            update_user_meta($order->get_user_id(), 'lilac_grace_period', current_time('timestamp'));
        }
    }
    
    /**
     * Enroll user in course with proper access control
     */
    private function enroll_user_in_course($user_id, $course_id, $duration_settings, $order_id) {
        // Calculate expiration
        $expires = $this->calculate_expiration($duration_settings);
        
        // LearnDash enrollment
        if (function_exists('ld_update_course_access')) {
            ld_update_course_access($user_id, $course_id, false);
        }
        
        // Set access metadata
        $access_key = "course_{$course_id}_access_from";
        $expire_key = "course_{$course_id}_access_expires";
        $order_key = "course_{$course_id}_order_id";
        
        update_user_meta($user_id, $access_key, current_time('timestamp'));
        update_user_meta($user_id, $expire_key, $expires);
        update_user_meta($user_id, $order_key, $order_id);
        
        // Update subscriptions table
        $this->update_subscription_record($user_id, $course_id, $expires, $order_id);
        
        $course_title = get_the_title($course_id);
        $expire_date = $expires ? date('Y-m-d H:i:s', $expires) : 'No expiration';
        $duration_info = is_array($duration_settings) ? $duration_settings['duration'] : $duration_settings;
        error_log("Lilac Course System: User {$user_id} enrolled in '{$course_title}' (ID: {$course_id}), duration: {$duration_info}, expires: {$expire_date}");
    }
    
    /**
     * Calculate expiration timestamp based on duration settings
     */
    private function calculate_expiration($duration_settings) {
        $current_time = current_time('timestamp');
        
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
                // Handle paused subscriptions differently
                return strtotime('+2 weeks', $current_time);
            default:
                return 0; // No expiration
        }
    }
    
    /**
     * Get courses associated with a product
     */
    private function get_product_courses($product_id) {
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
        
        // Log which meta field was used for debugging
        $meta_source = '';
        if (get_post_meta($product_id, '_related_course', true)) {
            $meta_source = '_related_course (LearnDash WooCommerce)';
        } elseif (get_post_meta($product_id, '_lilac_course_id', true)) {
            $meta_source = '_lilac_course_id (Lilac system)';
        } elseif (get_post_meta($product_id, '_learndash_courses', true)) {
            $meta_source = '_learndash_courses (Legacy)';
        }
        
        if (!empty($courses)) {
            error_log("Lilac Course System: Product {$product_id} courses found via {$meta_source}: " . implode(', ', $courses));
        }
        
        return array_filter($courses);
    }
    
    /**
     * Get product duration settings from LearnDash WooCommerce integration
     */
    private function get_product_duration_settings($product_id) {
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
    
    /**
     * Update subscription record in database
     */
    private function update_subscription_record($user_id, $course_id, $expires, $order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lilac_user_subscriptions';
        
        $wpdb->replace(
            $table_name,
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'order_id' => $order_id,
                'access_from' => current_time('mysql'),
                'access_expires' => $expires ? date('Y-m-d H:i:s', $expires) : null,
                'status' => 'active'
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Add product fields for course configuration
     */
    public function add_product_fields() {
        global $post;
        
        echo '<div class="options_group lilac-course-fields">';
        echo '<h4>🎓 Lilac Course Access Settings</h4>';
        
        // Access duration
        woocommerce_wp_select([
            'id' => '_lilac_access_duration',
            'label' => 'Access Duration',
            'options' => [
                '' => 'Select duration...',
                'trial_2weeks' => 'Trial 2 weeks',
                'access_1month' => 'Access 1 month',
                'access_60days' => 'Access 60 days',
                'access_1year' => 'Access 1 year',
                'paused_2weeks' => 'Paused subscription (2 weeks)'
            ]
        ]);
        
        // Course selection
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        if (!empty($courses)) {
            echo '<p class="form-field">';
            echo '<label for="_lilac_course_id">Associated Course</label>';
            echo '<select id="_lilac_course_id" name="_lilac_course_id">';
            echo '<option value="">Select course...</option>';
            
            $selected_course = get_post_meta($post->ID, '_lilac_course_id', true);
            
            foreach ($courses as $course) {
                $selected = selected($selected_course, $course->ID, false);
                echo '<option value="' . $course->ID . '" ' . $selected . '>' . esc_html($course->post_title) . '</option>';
            }
            echo '</select>';
            echo '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Save product fields
     */
    public function save_product_fields($post_id) {
        $duration = sanitize_text_field($_POST['_lilac_access_duration'] ?? '');
        $course_id = intval($_POST['_lilac_course_id'] ?? 0);
        
        update_post_meta($post_id, '_lilac_access_duration', $duration);
        update_post_meta($post_id, '_lilac_course_id', $course_id);
    }
}
