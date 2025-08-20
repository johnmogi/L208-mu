# Auto-Login Flow Documentation

This document outlines the complete flow of the auto-login system from checkout to course access.

## 1. Checkout Initialization
- User submits WooCommerce checkout form
- `persist_custom_checkout_meta()` is triggered by `woocommerce_checkout_create_order` hook
  - Captures ID number from multiple possible field names
  - Saves to order meta as `_billing_id_number` and `_id_number`
  - Logs successful field persistence

## 2. Order Processing
- WooCommerce processes payment and creates order
- `handle_checkout_completion()` is triggered by `woocommerce_checkout_order_processed` hook
  - Logs order details and server status
  - Retrieves phone and ID number with fallback methods:
    1. Order meta (`_billing_id_number`)
    2. Posted data array
    3. POST superglobal
    4. Direct post meta lookup

## 3. User Management
### New User Creation
1. Sanitizes phone number for username (prefixes with 'user_')
2. Validates required fields
3. Creates user with:
   - Username: sanitized phone
   - Password: ID number
   - Email: From order
4. Sets user role to 'customer'
5. Logs creation details

### Existing User Detection
1. Looks up by username (sanitized phone)
2. If found, retrieves existing user
3. Logs user details

## 4. Login Process
1. Clears existing auth cookies
2. Sets current user with `wp_set_current_user()`
3. Sets auth cookie with secure parameters:
   - Secure flag if on HTTPS
   - 2-day expiration
   - Proper cookie path/domain
4. Triggers `wp_login` action
5. Verifies successful login
6. Stores user ID in order meta as `_auto_login_user_id`

## 5. AJAX Fallback (if needed)
1. Frontend detects if user not logged in
2. Makes AJAX request to `wp_ajax_auto_login_after_checkout`
3. Server verifies nonce
4. Retrieves user from order meta or email
5. Performs secure login
6. Returns success/failure response

## 6. Debug Log Example
```
[timestamp] Auto Login After Checkout plugin initialized
[timestamp] === AUTO LOGIN: Checkout completed ===
[timestamp] Order ID: 1234
[timestamp] Order Status: processing
[timestamp] Phone: 0521234567
[timestamp] ID Number: 123456789
[timestamp] Creating new user...
[timestamp] User created successfully. ID: 42
[timestamp] User logged in successfully - ID: 42, Username: user_0521234567
[timestamp] === AUTO LOGIN: Checkout handling complete ===
```

## 7. Error Handling
### Missing ID Number
1. Logs available data keys
2. Tries all field variations
3. Logs detailed error if not found

### User Creation Failure
1. Logs WP_Error message
2. Returns early without login

### Login Failure
1. Verifies user exists
2. Validates credentials
3. Logs detailed error if login fails

## 8. Common Issues and Solutions

### Issue: User not logged in after checkout
- **Check**: Verify ID number is being captured in order meta
- **Solution**: Check debug logs for field mapping issues

### Issue: Login fails on order received page
- **Check**: AJAX nonce validation
- **Solution**: Ensure proper script enqueuing on thank you page

### Issue: Session not persisting
- **Check**: Cookie settings and SSL configuration
- **Solution**: Verify COOKIE_DOMAIN and HTTPS settings

## 9. Testing Checklist
- [ ] New user checkout
- [ ] Existing user checkout
- [ ] Missing ID number
- [ ] Invalid phone format
- [ ] AJAX fallback login
- [ ] Session persistence
- [ ] Course access verification
