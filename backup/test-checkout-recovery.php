<?php
/**
 * Test Checkout Recovery
 * Simple test to verify checkout functionality after fixes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function test_checkout_recovery() {
    error_log('=== CHECKOUT RECOVERY TEST ===');
    
    // Test 1: Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        error_log('TEST FAILED: WooCommerce not active');
        return false;
    }
    error_log('✓ WooCommerce is active');
    
    // Test 2: Check if checkout customizer is loaded
    if (!class_exists('Lilac\CheckoutCustomizer\CheckoutCustomizer')) {
        error_log('TEST FAILED: CheckoutCustomizer class not found');
        return false;
    }
    error_log('✓ CheckoutCustomizer class is loaded');
    
    // Test 3: Check if auto-login plugin is loaded
    if (!class_exists('Auto_Login_After_Checkout')) {
        error_log('TEST FAILED: Auto_Login_After_Checkout class not found');
        return false;
    }
    error_log('✓ Auto_Login_After_Checkout class is loaded');
    
    // Test 4: Check if functions.php checkout fields are registered
    $has_checkout_filter = has_filter('woocommerce_checkout_fields', 'lilac_consolidated_checkout_fields');
    if (!$has_checkout_filter) {
        error_log('TEST FAILED: Consolidated checkout fields filter not registered');
        return false;
    }
    error_log('✓ Consolidated checkout fields filter is registered with priority: ' . $has_checkout_filter);
    
    // Test 5: Check if validation is registered
    $has_validation = has_action('woocommerce_after_checkout_validation', 'lilac_validate_checkout_confirmations');
    if (!$has_validation) {
        error_log('TEST FAILED: Checkout validation not registered');
        return false;
    }
    error_log('✓ Checkout validation is registered');
    
    error_log('=== ALL TESTS PASSED ===');
    return true;
}

// Run test on admin_init
add_action('admin_init', function() {
    if (current_user_can('administrator')) {
        test_checkout_recovery();
    }
});
