<?php
/**
 * Plugin Name: Auto Login After Checkout
 * Description: Automatically logs in users after WooCommerce checkout completion
 * Version: 1.0.0
 * Author: Lilac
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Auto_Login_After_Checkout {
    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_checkout_completion'), 10, 3);
        add_action('woocommerce_checkout_create_order', array($this, 'persist_custom_checkout_meta'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Debug logging
        add_action('init', function() {
            error_log('Auto Login After Checkout plugin initialized');
        });
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        if (is_order_received_page()) {
            wp_enqueue_script(
                'auto-login-after-checkout',
                plugins_url('js/auto-login.js', __FILE__),
                array('jquery'),
                '1.0.0',
                true
            );
            
            // Enhanced nonce and session debugging
            $nonce = wp_create_nonce('auto_login_nonce');
            error_log('=== NONCE DEBUG ===');
            error_log('Generated nonce: ' . $nonce);
            error_log('Nonce verification test: ' . (wp_verify_nonce($nonce, 'auto_login_nonce') ? 'VALID' : 'INVALID'));
            error_log('Session ID: ' . (session_id() ?: 'NO SESSION'));
            error_log('Cookie domain: ' . COOKIE_DOMAIN);
            error_log('=== END NONCE DEBUG ===');
            
            wp_localize_script('auto-login-after-checkout', 'autoLoginVars', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
                'debug' => WP_DEBUG
            ));
        }
    }

    /**
     * Handle checkout completion
     */
    public function handle_checkout_completion($order_id, $posted_data, $order) {
        try {
            error_log('=== AUTO LOGIN: Checkout completed ===');
            error_log('Order ID: ' . $order_id);
            error_log('Order Status: ' . $order->get_status());
            
            // Enhanced server status debugging
            error_log('=== SERVER STATUS DEBUG ===');
            error_log('PHP Memory Limit: ' . ini_get('memory_limit'));
            error_log('PHP Memory Usage: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB');
            error_log('PHP Max Execution Time: ' . ini_get('max_execution_time'));
            error_log('Session Status: ' . session_status());
            error_log('Headers Sent: ' . (headers_sent() ? 'YES' : 'NO'));
            error_log('Current User ID: ' . get_current_user_id());
            error_log('Is User Logged In: ' . (is_user_logged_in() ? 'YES' : 'NO'));
            error_log('WordPress Debug: ' . (WP_DEBUG ? 'ON' : 'OFF'));
            error_log('=== END SERVER STATUS ===');
            
            // Get the billing phone and ID
            $phone = $order->get_billing_phone();
            $id_number = $order->get_meta('_billing_id_number');
            
            // If not found, try without underscore prefix
            if (empty($id_number)) {
                $id_number = $order->get_meta('billing_id_number');
            }
            
            // If still not found, try as post meta
            if (empty($id_number)) {
                $id_number = get_post_meta($order->get_id(), '_billing_id_number', true);
            }

            // If still empty, try extracting from posted data
            $pd_array = array();
            if (is_array($posted_data)) {
                $pd_array = $posted_data;
            } elseif (is_string($posted_data) && strpos($posted_data, '=') !== false) {
                parse_str($posted_data, $pd_array);
            }
            
            // Try different field name variations from posted data
            if (empty($id_number) && !empty($pd_array['billing_id_number'])) {
                $id_number = sanitize_text_field($pd_array['billing_id_number']);
            }
            if (empty($id_number) && !empty($pd_array['id_number'])) {
                $id_number = sanitize_text_field($pd_array['id_number']);
            }
            
            // If still empty, try from superglobal
            if (empty($id_number) && !empty($_POST['billing_id_number'])) {
                $id_number = sanitize_text_field(wp_unslash($_POST['billing_id_number']));
            }
            if (empty($id_number) && !empty($_POST['id_number'])) {
                $id_number = sanitize_text_field(wp_unslash($_POST['id_number']));
            }
            
            // Also try non-underscored post meta as a fallback
            if (empty($id_number)) {
                $id_number = get_post_meta($order->get_id(), 'billing_id_number', true);
            }
            if (empty($id_number)) {
                $id_number = get_post_meta($order->get_id(), 'id_number', true);
            }
            
            error_log('Phone: ' . $phone);
            error_log('ID Number: ' . $id_number);
            
            if (empty($phone) || empty($id_number)) {
                error_log('AUTO LOGIN: Missing phone or ID number');
                // Log posted data keys to help mapping
                if (!empty($pd_array)) {
                    error_log('Posted data keys: ' . implode(', ', array_keys($pd_array)));
                } else {
                    error_log('Posted data not available or not parsable');
                }
                // Log all available order meta for debugging
                $all_meta = $order->get_meta_data();
                error_log('Available order meta: ' . print_r($all_meta, true));
                return;
            }
            
            // Sanitize phone number for username
            $username = $this->sanitize_phone_for_username($phone);
            
            // Check if user exists, if not create one
            $user = get_user_by('login', $username);
            
            if (!$user) {
                $user_id = $this->create_user($username, $order->get_billing_email(), $id_number);
                if (is_wp_error($user_id)) {
                    error_log('AUTO LOGIN: Error creating user - ' . $user_id->get_error_message());
                    return;
                }
                $user = get_user_by('id', $user_id);
                error_log("AUTO LOGIN: Created new user ID: $user_id");
            } else {
                error_log("AUTO LOGIN: Found existing user ID: " . $user->ID);
            }
            
            // Actually log the user in
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
            
            // Store user ID in order meta for reference
            $order->update_meta_data('_auto_login_user_id', $user->ID);
            $order->save();
            
            error_log('AUTO LOGIN: User logged in successfully - ID: ' . $user->ID . ', Username: ' . $user->user_login);
            error_log('=== AUTO LOGIN: Checkout handling complete ===');
            
        } catch (Exception $e) {
            error_log('AUTO LOGIN ERROR: ' . $e->getMessage());
        }
    }

    /**
     * Persist custom checkout meta early in order creation
     */
    public function persist_custom_checkout_meta($order, $data) {
        try {
            $id_number = '';
            
            // Try multiple field name variations
            if (is_array($data) && !empty($data['billing_id_number'])) {
                $id_number = sanitize_text_field($data['billing_id_number']);
            } elseif (is_array($data) && !empty($data['id_number'])) {
                $id_number = sanitize_text_field($data['id_number']);
            } elseif (!empty($_POST['billing_id_number'])) {
                $id_number = sanitize_text_field(wp_unslash($_POST['billing_id_number']));
            } elseif (!empty($_POST['id_number'])) {
                $id_number = sanitize_text_field(wp_unslash($_POST['id_number']));
            }
            
            if (!empty($id_number)) {
                $order->update_meta_data('_billing_id_number', $id_number);
                $order->update_meta_data('_id_number', $id_number);
                // WooCommerce saves the order after this hook
                error_log('Persisted ID number during order creation: ' . $id_number);
            } else {
                error_log('AUTO LOGIN: No ID number found in checkout data');
                if (is_array($data)) {
                    error_log('Available data keys: ' . implode(', ', array_keys($data)));
                }
            }
        } catch (Exception $e) {
            error_log('AUTO LOGIN: persist_custom_checkout_meta error: ' . $e->getMessage());
        }
    }
    
    /**
     * Sanitize phone number for username
     */
    private function sanitize_phone_for_username($phone) {
        // Remove all non-numeric characters
        $username = preg_replace('/[^0-9]/', '', $phone);
        // Ensure it's not empty and add a prefix to ensure it's valid
        return 'user_' . $username;
    }
    
    /**
     * Create a new user
     */
    private function create_user($username, $email, $password) {
        error_log('Creating new user:');
        error_log('- Username: ' . $username);
        error_log('- Email: ' . $email);
        error_log('- Password starts with: ' . (!empty($password) ? $password[0] . '...' : 'empty'));
        
        $user_id = wp_create_user(
            $username,
            $password, // Using ID as password
            $email
        );
        
        if (is_wp_error($user_id)) {
            error_log('Error creating user: ' . $user_id->get_error_message());
            return $user_id;
        }
        
        // Set user role (adjust as needed)
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        error_log('User created successfully. ID: ' . $user_id);
        
        return $user_id;
    }
    
    /**
     * AJAX handler for auto-login
     */
    public function ajax_auto_login() {
        error_log('=== AUTO LOGIN: AJAX Request Received ===');
        error_log('POST Data: ' . print_r($_POST, true));
        error_log('Current User ID before login: ' . get_current_user_id());
        
        // Enhanced session and nonce debugging
        error_log('=== AJAX SESSION DEBUG ===');
        error_log('Session Status: ' . session_status());
        error_log('Session ID: ' . (session_id() ?: 'NO SESSION'));
        error_log('Headers Sent: ' . (headers_sent() ? 'YES' : 'NO'));
        error_log('Received Nonce: ' . ($_POST['nonce'] ?? 'MISSING'));
        error_log('Nonce Verification: ' . (wp_verify_nonce($_POST['nonce'] ?? '', 'auto_login_nonce') ? 'VALID' : 'INVALID'));
        error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Content Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
        error_log('User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'NOT SET'));
        error_log('=== END AJAX SESSION DEBUG ===');
        
        // Nonce verification with enhanced error handling
        $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'auto_login_nonce');
        if (!$nonce_check) {
            error_log('AUTO LOGIN ERROR: Nonce verification failed');
            error_log('Expected action: auto_login_nonce');
            error_log('Received nonce: ' . ($_POST['nonce'] ?? 'MISSING'));
            wp_send_json_error('Security check failed');
            return;
        }
        
        check_ajax_referer('auto_login_nonce', 'nonce');
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        error_log('Processing order ID: ' . $order_id);
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            $error = 'Invalid order';
            error_log('AUTO LOGIN ERROR: ' . $error);
            wp_send_json_error($error);
            return;
        }
        
        $user_id = $order->get_meta('_auto_login_user_id');
        error_log('Found user ID in order meta: ' . ($user_id ?: 'none'));
        
        if (!$user_id) {
            // Try to get user by email as fallback
            $user = get_user_by('email', $order->get_billing_email());
            if ($user) {
                $user_id = $user->ID;
                error_log('Found user by email, ID: ' . $user_id);
                $order->update_meta_data('_auto_login_user_id', $user_id);
                $order->save();
            } else {
                $error = 'No user to login and could not find by email';
                error_log('AUTO LOGIN ERROR: ' . $error);
                wp_send_json_error($error);
                return;
            }
        }
        
        // Log the user in with enhanced debugging
        error_log('Attempting to log in user ID: ' . $user_id);
        
        // Enhanced login process debugging
        error_log('=== LOGIN PROCESS DEBUG ===');
        error_log('SSL Status: ' . (is_ssl() ? 'YES' : 'NO'));
        error_log('Cookie Path: ' . COOKIEPATH);
        error_log('Cookie Domain: ' . COOKIE_DOMAIN);
        error_log('Site URL: ' . site_url());
        error_log('Home URL: ' . home_url());
        
        // Clear all auth cookies first
        wp_clear_auth_cookie();
        error_log('Cleared existing auth cookies');
        
        // Set the current user
        wp_set_current_user($user_id);
        error_log('Set current user to: ' . $user_id);
        
        // Set the auth cookie with enhanced logging
        $secure_cookie = is_ssl();
        $expiration = time() + apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $secure_cookie);
        
        error_log('Cookie expiration: ' . date('Y-m-d H:i:s', $expiration));
        error_log('Secure cookie: ' . ($secure_cookie ? 'YES' : 'NO'));
        
        wp_set_auth_cookie($user_id, true, $secure_cookie, $expiration);
        error_log('Auth cookie set for user: ' . $user_id);
        error_log('=== END LOGIN PROCESS DEBUG ===');
        
        // Double check if user is logged in
        $current_user = wp_get_current_user();
        
        if ($current_user->ID === $user_id) {
            error_log('User successfully logged in. User ID: ' . $current_user->ID);
            error_log('User login: ' . $current_user->user_login);
            error_log('User email: ' . $current_user->user_email);
        } else {
            error_log('ERROR: Failed to set current user');
            error_log('Expected user ID: ' . $user_id . ', Got: ' . $current_user->ID);
        }
        
        $redirect_url = $order->get_checkout_order_received_url();
        error_log('Redirecting to: ' . $redirect_url);
        
        wp_send_json_success(array(
            'redirect' => $redirect_url,
            'user_id' => $user_id,
            'is_logged_in' => is_user_logged_in() ? 'yes' : 'no'
        ));
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        new Auto_Login_After_Checkout();
    }
});

// Add AJAX handler
add_action('wp_ajax_auto_login_after_checkout', array('Auto_Login_After_Checkout', 'ajax_auto_login'));
add_action('wp_ajax_nopriv_auto_login_after_checkout', array('Auto_Login_After_Checkout', 'ajax_auto_login'));
