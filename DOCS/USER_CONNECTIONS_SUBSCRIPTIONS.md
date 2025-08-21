# USER CONNECTIONS FOR SUBSCRIPTIONS

## Overview
This document outlines the database structure and processes for managing user connections to course subscriptions in the LILAC learning management system.

## Database Tables

### Core Tables
- `edc_learndash_user_activity` - Tracks user course enrollment and progress
- `edc_usermeta` - Stores user-specific course access metadata
- `edc_posts` - Contains course information (post_type: 'sfwd-courses')
- `edc_postmeta` - Course configuration and pricing

### Key Meta Keys
- `course_{course_id}_access_from` - Unix timestamp when access starts
- `course_expire_{course_id}` - Unix timestamp when access expires
- `ld_course_{course_id}` - LearnDash course association
- `_ld_course_access_list` - Course access restrictions
- `_ld_price_type` - Course pricing model (open/closed/paynow)

## User Subscription Process

### 1. Course Purchase (WooCommerce)
```sql
-- Order completion triggers enrollment
INSERT INTO edc_learndash_user_activity 
(user_id, post_id, course_id, activity_type, activity_status, activity_started, activity_updated)
VALUES (user_id, course_id, course_id, 'access', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
```

### 2. Access Metadata Setup
```sql
-- Set access timestamps
INSERT INTO edc_usermeta (user_id, meta_key, meta_value) VALUES 
(user_id, 'course_{course_id}_access_from', UNIX_TIMESTAMP()),
(user_id, 'course_expire_{course_id}', UNIX_TIMESTAMP() + duration),
(user_id, 'ld_course_{course_id}', course_id);
```

### 3. Course Configuration
```sql
-- Ensure course is accessible
UPDATE edc_postmeta SET meta_value = 'open' 
WHERE post_id = course_id AND meta_key = '_ld_price_type';

-- Clear access restrictions if needed
UPDATE edc_postmeta SET meta_value = '' 
WHERE post_id = course_id AND meta_key = '_ld_course_access_list';
```

## Connection Status Verification

### Check User Access
```sql
SELECT 
    p.ID as course_id,
    p.post_title,
    um.meta_key,
    CASE 
        WHEN um.meta_key LIKE '%expire%' OR um.meta_key LIKE '%access%' 
        THEN FROM_UNIXTIME(um.meta_value)
        ELSE um.meta_value 
    END as value
FROM edc_posts p
LEFT JOIN edc_usermeta um ON um.user_id = ? 
    AND um.meta_key IN ('course_{course_id}_access_from', 'course_expire_{course_id}', 'ld_course_{course_id}')
WHERE p.ID = ?;
```

### Validate Course Enrollment
```sql
SELECT user_id, course_id, activity_status, activity_updated
FROM edc_learndash_user_activity 
WHERE user_id = ? AND course_id = ? AND activity_type = 'access';
```

## Troubleshooting

### Common Issues
1. **Duplicate Meta Entries** - Remove duplicates keeping latest umeta_id
2. **Missing Activity Record** - Insert enrollment activity
3. **Expired Access** - Update expiration timestamp
4. **Course Access Restrictions** - Clear _ld_course_access_list

### Cleanup Procedures
```sql
-- Remove duplicate user meta
DELETE FROM edc_usermeta 
WHERE user_id = ? AND meta_key IN (course_meta_keys)
AND umeta_id NOT IN (SELECT MAX(umeta_id) FROM edc_usermeta WHERE user_id = ? GROUP BY meta_key);

-- Clear LearnDash transients
DELETE FROM edc_options 
WHERE option_name LIKE '%_transient_learndash_%' 
   OR option_name LIKE '%_transient_timeout_learndash_%';
```

## Integration Points

### WooCommerce Hooks
- `woocommerce_order_status_completed` - Trigger enrollment
- `woocommerce_payment_complete` - Process access grant

### LearnDash Hooks  
- `learndash_update_course_access` - Sync access changes
- `learndash_course_completed` - Track completion

## Last Updated
2025-08-21 09:35:00 UTC

## Related Files
- `wc-learndash-access-manager.php` - Main access management
- `course-expiration-manager.php` - Handles expiration logic
- `STATUS_TASK_COURSE_ACCESS.md` - Frontend workflow documentation
