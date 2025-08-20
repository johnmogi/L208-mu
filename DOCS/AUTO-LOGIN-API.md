# Auto-Login System API Documentation

## Class: Auto_Login_After_Checkout

### Constructor
```php
public function __construct()
```
- **Purpose**: Initializes the plugin and sets up all necessary hooks
- **Hooks Added**:
  - `woocommerce_checkout_order_processed` - Main checkout handler
  - `woocommerce_checkout_create_order` - Early field persistence
  - `wp_enqueue_scripts` - Frontend script loading
- **Actions**:
  - Initializes debug logging

---

### enqueue_scripts()
```php
public function enqueue_scripts()
```
- **Purpose**: Enqueues necessary JavaScript and localizes script variables
- **Runs On**: Order received page
- **Localized Variables**:
  - `ajaxurl`: Admin AJAX URL
  - `nonce`: Security nonce
  - `debug`: Debug mode status
- **Debug Info**:
  - Logs nonce generation and verification
  - Logs session and cookie information

---

### handle_checkout_completion($order_id, $posted_data, $order)
```php
public function handle_checkout_completion($order_id, $posted_data, $order)
```
- **Purpose**: Main handler for checkout completion
- **Parameters**:
  - `$order_id`: (int) WooCommerce order ID
  - `$posted_data`: (array) Posted form data
  - `$order`: (WC_Order) Order object
- **Process**:
  1. Logs order details and server status
  2. Retrieves phone and ID number with fallbacks
  3. Creates or finds user
  4. Performs login
  5. Stores user ID in order meta
- **Debug Logs**:
  - Order processing details
  - Field mapping results
  - User creation/login status

---

### persist_custom_checkout_meta($order, $data)
```php
public function persist_custom_checkout_meta($order, $data)
```
- **Purpose**: Saves custom checkout fields early in order creation
- **Parameters**:
  - `$order`: (WC_Order) Order object
  - `$data`: (array) Posted form data
- **Process**:
  1. Tries multiple field name variations for ID number
  2. Saves to order meta as both `_billing_id_number` and `_id_number`
- **Debug Logs**:
  - Field persistence status
  - Available data keys if field not found

---

### sanitize_phone_for_username($phone)
```php
private function sanitize_phone_for_username($phone)
```
- **Purpose**: Converts phone number to valid WordPress username
- **Parameters**:
  - `$phone`: (string) Raw phone number
- **Returns**: (string) Sanitized username with 'user_' prefix
- **Process**:
  1. Removes all non-numeric characters
  2. Prefixes with 'user_'
- **Example**:
  - Input: "052-123-4567"
  - Output: "user_0521234567"

---

### create_user($username, $email, $password)
```php
private function create_user($username, $email, $password)
```
- **Purpose**: Creates a new WordPress user
- **Parameters**:
  - `$username`: (string) Username (sanitized phone)
  - `$email`: (string) User email
  - `$password`: (string) Raw password (ID number)
- **Returns**: (int|WP_Error) User ID or error object
- **Process**:
  1. Creates user with provided credentials
  2. Sets role to 'customer'
  3. Logs creation details
- **Security**:
  - Uses WordPress core functions
  - Handles errors properly

---

### ajax_auto_login()
```php
public static function ajax_auto_login()
```
- **Purpose**: Handles AJAX login requests
- **Access**: Public (static)
- **Process**:
  1. Verifies nonce
  2. Gets order and user data
  3. Performs secure login
  4. Returns JSON response
- **Response**:
  - Success: Redirect URL and user ID
  - Error: Error message
- **Debug Logs**:
  - Session status
  - Nonce verification
  - Login process details

---

## Helper Functions

### Plugin Initialization
```php
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        new Auto_Login_After_Checkout();
    }
});
```
- **Purpose**: Initializes the plugin after WooCommerce is loaded
- **Dependencies**: Requires WooCommerce

### AJAX Hooks
```php
add_action('wp_ajax_auto_login_after_checkout', 
    array('Auto_Login_After_Checkout', 'ajax_auto_login'));
add_action('wp_ajax_nopriv_auto_login_after_checkout', 
    array('Auto_Login_After_Checkout', 'ajax_auto_login'));
```
- **Purpose**: Registers AJAX endpoints
- **Endpoints**:
  - `wp_ajax_auto_login_after_checkout` (logged-in users)
  - `wp_ajax_nopriv_auto_login_after_checkout` (logged-out users)

## Error Handling

### Common Errors
1. **Missing ID Number**
   - Check field mapping in `handle_checkout_completion()`
   - Verify form field names match expected values

2. **Login Failures**
   - Check cookie settings in `wp-config.php`
   - Verify SSL configuration if using HTTPS

3. **AJAX Errors**
   - Check browser console for JavaScript errors
   - Verify nonce generation in `enqueue_scripts()`

## Security Considerations
- All user input is sanitized
- Nonce verification for AJAX requests
- Secure cookie handling
- Error messages are logged, not shown to users
- No sensitive data in debug logs
