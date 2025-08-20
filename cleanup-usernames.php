<?php
/**
 * One-time script to remove 'user_' prefix from usernames
 * Run this once to clean up existing usernames
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function cleanup_user_prefixes() {
    global $wpdb;
    
    // Get all users with 'user_' prefix
    $users_with_prefix = $wpdb->get_results(
        "SELECT ID, user_login FROM {$wpdb->users} WHERE user_login LIKE 'user_%'"
    );
    
    if (empty($users_with_prefix)) {
        error_log('No users with user_ prefix found');
        return;
    }
    
    error_log('Found ' . count($users_with_prefix) . ' users with user_ prefix to clean up');
    
    foreach ($users_with_prefix as $user) {
        $old_login = $user->user_login;
        $new_login = str_replace('user_', '', $old_login);
        
        // Skip if new login would be empty
        if (empty($new_login)) {
            error_log('Skipping user ID ' . $user->ID . ' - would result in empty login');
            continue;
        }
        
        // Check if new login already exists
        $existing_user = get_user_by('login', $new_login);
        if ($existing_user && $existing_user->ID != $user->ID) {
            error_log('Skipping user ID ' . $user->ID . ' - login "' . $new_login . '" already exists');
            continue;
        }
        
        // Update the username
        $result = $wpdb->update(
            $wpdb->users,
            ['user_login' => $new_login],
            ['ID' => $user->ID],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            error_log('Updated user ID ' . $user->ID . ': "' . $old_login . '" -> "' . $new_login . '"');
        } else {
            error_log('Failed to update user ID ' . $user->ID . ': "' . $old_login . '"');
        }
    }
    
    error_log('Username cleanup completed');
}

// Run the cleanup
add_action('init', function() {
    if (is_admin() && current_user_can('manage_options')) {
        cleanup_user_prefixes();
    }
});
