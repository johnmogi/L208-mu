# Subscription Tracker Plugin

A modern, secure PSR-4 MU plugin for tracking user subscriptions and course access in LearnDash/WooCommerce environments.

## Features

- **🎯 Subscription Tracking**: Complete user subscription lifecycle management
- **🔒 Security First**: Proper nonce verification, capability checks, and input validation
- **🧪 Testing Interface**: Comprehensive admin interface for testing and management
- **📊 Statistics Dashboard**: Real-time subscription statistics and insights
- **🏗️ Modern Architecture**: PSR-4 autoloading with modular design
- **📝 Comprehensive Logging**: Detailed action logging with IP tracking
- **⚡ Performance Optimized**: Efficient database queries with proper indexing

## Installation

1. Place the plugin in `wp-content/mu-plugins/plugins/subscription-tracker/`
2. Create the loader file in the mu-plugins directory
3. Run composer install to generate autoloader
4. Database tables will be created automatically on first load

## Architecture

```
src/
├── Plugin.php              # Main plugin class
├── SubscriptionManager.php  # Core subscription logic
├── DatabaseManager.php     # Database operations
└── TestingInterface.php    # Admin testing interface

assets/
├── css/
│   ├── admin.css           # Admin interface styles
│   └── frontend.css        # Frontend widget styles
└── js/
    └── admin.js            # Admin interface JavaScript
```

## Database Schema

### subscription_tracker
- Tracks all user course subscriptions
- Includes expiry dates, order info, and status
- Optimized indexes for performance

### subscription_tracker_logs
- Comprehensive action logging
- IP address and user agent tracking
- Audit trail for all subscription changes

## Usage

### Admin Interface
Access via **Tools > Subscription Tracker**

- **Statistics Dashboard**: View subscription metrics
- **Grant Access Tool**: Manually grant course access for testing
- **User Lookup**: Search and view user subscriptions
- **Extend Access**: Add additional days to existing subscriptions

### Programmatic Usage

```php
$manager = new \Lilac\SubscriptionTracker\SubscriptionManager();

// Grant access
$manager->grantCourseAccess($user_id, $course_id, $order_id, $product_id);

// Check access
$has_access = $manager->checkCourseAccess(false, $user_id, $course_id);

// Get user subscriptions
$subscriptions = $manager->getUserSubscriptions($user_id);

// Extend access
$manager->extendAccess($user_id, $course_id, 30); // 30 additional days
```

## Hooks & Filters

### Actions
- `woocommerce_order_status_completed` - Auto-grant access on order completion
- `woocommerce_subscription_status_updated` - Handle subscription status changes
- `subscription_tracker_daily_cleanup` - Daily maintenance cron

### Filters
- `learndash_user_has_course_access` - Override LearnDash access checks

## AJAX Endpoints

All endpoints require `manage_options` capability and proper nonce verification:

- `st_get_user_subscriptions` - Get user's subscriptions
- `st_update_subscription` - Update subscription status
- `st_extend_access` - Extend subscription access
- `st_test_grant_access` - Grant access for testing
- `st_test_check_access` - Check user access
- `st_get_statistics` - Get subscription statistics
- `st_search_users` - Search users
- `st_get_courses` - Get available courses

## Security Features

- ✅ Nonce verification on all AJAX requests
- ✅ Capability checks (`manage_options` required)
- ✅ Input sanitization and validation
- ✅ SQL injection prevention with prepared statements
- ✅ XSS protection with proper escaping
- ✅ CSRF protection
- ✅ Direct access prevention

## Performance Optimizations

- Database indexes on frequently queried columns
- Efficient SQL queries with proper JOINs
- Transient caching for statistics
- Minimal frontend resource loading
- Optimized autoloader configuration

## Logging & Debugging

All actions are logged with:
- User ID and course ID
- Action type and timestamp
- IP address and user agent
- Additional context data

Enable WordPress debug logging to see detailed operation logs.

## Maintenance

### Daily Cleanup
Automatic cron job updates expired subscriptions and cleans old logs.

### Manual Cleanup
```php
// Clean logs older than 90 days
\Lilac\SubscriptionTracker\DatabaseManager::cleanupLogs(90);
```

## Compatibility

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **LearnDash**: 3.0+
- **WooCommerce**: 4.0+

## License

GPL-2.0-or-later

## Support

For issues or questions, check the debug information in the admin interface and WordPress debug logs.
