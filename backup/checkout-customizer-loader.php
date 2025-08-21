<?php
/**
 * Plugin Name: Checkout Customizer (MU) - DISABLED
 * Description: DISABLED - Conflicts with functions.php checkout customization
 * 
 * DISABLED FOR CHECKOUT CLEANUP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// DISABLED - return early to prevent any functionality
return;

// Load the checkout customizer
require_once __DIR__ . '/checkout-customizer/checkout-customizer.php';
