# Auto-Login System Documentation

## Overview
The Auto-Login system automatically authenticates users after a successful WooCommerce checkout. It creates new user accounts using the customer's phone number as the username and their ID number as the password, or logs them in if they already exist.

## Key Features

1. **Automatic User Creation**
   - Creates new users during checkout if they don't exist
   - Uses phone number as username (with 'user_' prefix)
   - Uses ID number as password
   - Sets default role as 'customer'

2. **Robust Field Mapping**
   - Supports multiple field name variations for ID number:
     - `billing_id_number`
     - `id_number`
     - `_billing_id_number`
     - `_id_number`
   - Extracts from multiple sources:
     - Order meta
     - Posted data
     - POST superglobal
     - Direct post meta

3. **Secure Login Process**
   - Uses WordPress core authentication functions
   - Implements proper session handling
   - Includes nonce verification for AJAX requests
   - Clears existing auth cookies before login

4. **Comprehensive Debugging**
   - Detailed logging at each step
   - Error handling with specific messages
   - Server status monitoring
   - Nonce and session debugging

## Workflow

### 1. Checkout Completion
- Hooks into `woocommerce_checkout_order_processed`
- Extracts phone and ID number from order data
- Sanitizes phone number for username
- Creates new user if needed
- Logs the user in immediately
- Stores user ID in order meta for reference

### 2. AJAX Auto-Login (Fallback)
- Handles cases where immediate login fails
- Verifies nonce for security
- Retrieves user from order meta or email
- Performs secure login
- Returns success/failure response

## Error Handling

### Common Issues
1. **Missing ID Number**
   - Logs available data keys for debugging
   - Tries multiple field name variations
   - Falls back to different data sources

2. **User Creation Failures**
   - Validates required fields
   - Logs detailed error messages
   - Returns appropriate error responses

3. **Login Issues**
   - Verifies user exists before login
   - Clears existing auth cookies
   - Validates SSL and cookie settings

## Technical Details

### Hooks Used
- `woocommerce_checkout_order_processed` - Main checkout handler
- `woocommerce_checkout_create_order` - Early field persistence
- `wp_enqueue_scripts` - Frontend script loading
- `wp_ajax_*` - AJAX request handling

### Security Measures
- Nonce verification for all AJAX requests
- Input sanitization
- Secure cookie handling
- Error message sanitization

## Debugging

### Log Locations
- WordPress debug.log
- Server error logs
- AJAX response data

### Common Log Entries
- `AUTO LOGIN: Checkout completed` - Process started
- `Persisted ID number during order creation` - Field mapping success
- `User created successfully` - New user account created
- `User logged in successfully` - Authentication complete
- `AUTO LOGIN ERROR:` - Error conditions

## Dependencies
- WooCommerce
- WordPress core functions

## Known Limitations
- Requires phone number and ID number at checkout
- Assumes ID number is suitable for password use
- May conflict with other authentication plugins

## Future Improvements
1. Add password strength requirements
2. Implement account recovery options
3. Add admin interface for configuration
4. Support additional user meta fields
5. Add user notification emails
