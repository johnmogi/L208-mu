<?php

namespace Lilac\SubscriptionTracker;

/**
 * Testing Interface
 * 
 * Provides admin interface for testing subscription functionality
 * 
 * @package Lilac\SubscriptionTracker
 * @since 1.0.0
 */
class TestingInterface {
    
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers for testing
        add_action('wp_ajax_st_test_grant_access', [$this, 'ajaxTestGrantAccess']);
        add_action('wp_ajax_st_test_check_access', [$this, 'ajaxTestCheckAccess']);
        add_action('wp_ajax_st_get_statistics', [$this, 'ajaxGetStatistics']);
        add_action('wp_ajax_st_search_users', [$this, 'ajaxSearchUsers']);
        add_action('wp_ajax_st_get_courses', [$this, 'ajaxGetCourses']);
        add_action('wp_ajax_st_test_revoke_access', [$this, 'ajaxTestRevokeAccess']);
        add_action('wp_ajax_st_test_import_subscriptions', [$this, 'ajaxTestImportSubscriptions']);
        add_action('wp_ajax_st_load_recent_subscriptions', [$this, 'ajaxLoadRecentSubscriptions']);
        add_action('wp_ajax_st_load_students', [$this, 'ajaxLoadStudents']);
    }
    
    /**
     * Render admin interface
     */
    public function renderAdminInterface(): void {
        $stats = DatabaseManager::getStatistics();
        ?>
        <div class="wrap">
            <h1>🎯 Subscription Tracker - Testing Interface</h1>
            
            <div class="subscription-tracker-admin">
                
                <!-- Statistics Dashboard -->
                <div class="stats-section">
                    <h2>📊 Statistics Overview</h2>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <h3><?php echo number_format($stats['total_subscriptions']); ?></h3>
                            <p>Total Subscriptions</p>
                        </div>
                        <div class="stat-box active">
                            <h3><?php echo number_format($stats['active_subscriptions']); ?></h3>
                            <p>Active Subscriptions</p>
                        </div>
                        <div class="stat-box expired">
                            <h3><?php echo number_format($stats['expired_subscriptions']); ?></h3>
                            <p>Expired Subscriptions</p>
                        </div>
                        <div class="stat-box warning">
                            <h3><?php echo number_format($stats['expiring_soon']); ?></h3>
                            <p>Expiring Soon (7 days)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Subscriptions -->
                <div class="recent-section">
                    <h2>🕒 Recent Subscriptions</h2>
                    <div class="recent-controls">
                        <button id="load-recent-btn" class="button button-secondary">Load Latest 5</button>
                        <button id="load-students-btn" class="button button-secondary">Show All Students</button>
                    </div>
                    <div id="recent-subscriptions" class="recent-container"></div>
                    <div id="students-list" class="students-container"></div>
                </div>
                
                <!-- Testing Tools -->
                <div class="testing-section">
                    <h2>🧪 Testing Tools</h2>
                    
                    <!-- Grant Access Test -->
                    <div class="test-tool">
                        <h3>Grant Course Access</h3>
                        <form id="grant-access-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="test-user-id">User</label></th>
                                    <td>
                                        <input type="text" id="test-user-id" name="user_id" placeholder="Search user..." class="user-search" />
                                        <div id="user-search-results" class="search-results"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="test-course-id">Course</label></th>
                                    <td>
                                        <select id="test-course-id" name="course_id" required>
                                            <option value="">Select Course...</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="test-duration">Duration (Days)</label></th>
                                    <td>
                                        <input type="number" id="test-duration" name="duration" value="30" min="1" max="365" required />
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary">Grant Access</button>
                            </p>
                        </form>
                    </div>
                    
                    <!-- Check Access Test -->
                    <div class="test-tool">
                        <h3>Check User Access</h3>
                        <form id="check-access-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="check-user-id">User ID</label></th>
                                    <td><input type="number" id="check-user-id" name="user_id" required /></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button">Check Access</button>
                            </p>
                        </form>
                        <div id="access-results" class="results-container"></div>
                    </div>
                    
                    <!-- Extend Access Tool -->
                    <div class="test-tool">
                        <h3>Extend Access</h3>
                        <form id="extend-access-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="extend-user-id">User ID</label></th>
                                    <td><input type="number" id="extend-user-id" name="user_id" required /></td>
                                </tr>
                                <tr>
                                    <th><label for="extend-course-id">Course ID</label></th>
                                    <td><input type="number" id="extend-course-id" name="course_id" required /></td>
                                </tr>
                                <tr>
                                    <th><label for="extend-days">Additional Days</label></th>
                                    <td><input type="number" id="extend-days" name="days" value="30" min="1" required /></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-secondary">Extend Access</button>
                            </p>
                        </form>
                    </div>
                    
                    <!-- Revoke Access Tool -->
                    <div class="test-tool revoke-tool">
                        <h3>🚫 Revoke Course Access</h3>
                        <form id="revoke-access-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="revoke-user-id">User ID</label></th>
                                    <td><input type="number" id="revoke-user-id" name="user_id" required /></td>
                                </tr>
                                <tr>
                                    <th><label for="revoke-course-id">Course ID</label></th>
                                    <td><input type="number" id="revoke-course-id" name="course_id" required /></td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-danger">Revoke Access</button>
                            </p>
                        </form>
                    </div>
                    
                    <!-- Import Existing Subscriptions -->
                    <div class="test-tool import-tool">
                        <h3>📥 Import Existing Subscriptions</h3>
                        <p>Import subscriptions from existing user meta data to populate the database.</p>
                        <p class="submit">
                            <button id="import-subscriptions-btn" class="button button-secondary">Import All Existing Subscriptions</button>
                        </p>
                    </div>
                </div>
                
                <!-- User Lookup -->
                <div class="lookup-section">
                    <h2>👤 User Subscription Lookup</h2>
                    <form id="user-lookup-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="lookup-user-id">User ID</label></th>
                                <td>
                                    <input type="number" id="lookup-user-id" name="user_id" required />
                                    <button type="submit" class="button">Lookup Subscriptions</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                    <div id="user-subscriptions" class="subscriptions-container"></div>
                </div>
                
                <!-- Debug Information -->
                <div class="debug-section">
                    <h2>🔧 Debug Information</h2>
                    <div class="debug-info">
                        <p><strong>Plugin Version:</strong> <?php echo Plugin::VERSION; ?></p>
                        <p><strong>Database Version:</strong> <?php echo get_option('subscription_tracker_db_version', 'Not installed'); ?></p>
                        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                        <p><strong>WooCommerce Active:</strong> <?php echo class_exists('WooCommerce') ? 'Yes' : 'No'; ?></p>
                        <p><strong>LearnDash Active:</strong> <?php echo defined('LEARNDASH_VERSION') ? 'Yes (' . LEARNDASH_VERSION . ')' : 'No'; ?></p>
                        <p><strong>Current Time:</strong> <?php echo current_time('Y-m-d H:i:s'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .subscription-tracker-admin {
            max-width: 1200px;
        }
        
        .stats-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
        }
        
        .stat-box.active {
            border-left-color: #00a32a;
        }
        
        .stat-box.expired {
            border-left-color: #d63638;
        }
        
        .stat-box.warning {
            border-left-color: #dba617;
        }
        
        .stat-box h3 {
            font-size: 2em;
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        
        .stat-box p {
            margin: 0;
            color: #646970;
        }
        
        .testing-section,
        .lookup-section,
        .debug-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .test-tool {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            border: 1px solid #e1e5e9;
        }
        
        .test-tool h3 {
            margin-top: 0;
            color: #1d2327;
        }
        
        .results-container,
        .subscriptions-container {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            display: none;
        }
        
        .results-container.show,
        .subscriptions-container.show {
            display: block;
        }
        
        .subscription-item {
            padding: 15px;
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
        }
        
        .subscription-item.expired {
            border-left-color: #d63638;
            background: #fef7f7;
        }
        
        .subscription-item.expiring {
            border-left-color: #dba617;
            background: #fffbf0;
        }
        
        .user-search {
            width: 300px;
        }
        
        .search-results {
            position: absolute;
            background: white;
            border: 1px solid #ccd0d4;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .search-result-item:hover {
            background: #f6f7f7;
        }
        
        .debug-info p {
            margin: 5px 0;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .success {
            color: #00a32a;
            font-weight: bold;
        }
        
        .error {
            color: #d63638;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Test grant access
     */
    public function ajaxTestGrantAccess(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        $duration = intval($_POST['duration'] ?? 30);
        
        if (!$user_id || !$course_id) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $user = get_user_by('id', $user_id);
        $course = get_post($course_id);
        
        if (!$user || !$course || $course->post_type !== 'sfwd-courses') {
            wp_send_json_error('Invalid user or course');
            return;
        }
        
        // Create a fake order for testing
        $fake_order_id = 999999;
        $fake_product_id = 999999;
        
        $subscription_manager = new SubscriptionManager();
        
        // Calculate expiry
        $expires_timestamp = time() + ($duration * DAY_IN_SECONDS);
        
        // Grant access directly
        update_user_meta($user_id, "course_{$course_id}_access_expires", $expires_timestamp);
        update_user_meta($user_id, "course_{$course_id}_order_id", $fake_order_id);
        update_user_meta($user_id, "course_{$course_id}_product_id", $fake_product_id);
        update_user_meta($user_id, "course_{$course_id}_granted_date", time());
        
        // Store in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscription_tracker';
        
        $wpdb->replace(
            $table_name,
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'order_id' => $fake_order_id,
                'product_id' => $fake_product_id,
                'granted_date' => current_time('mysql'),
                'expires_date' => date('Y-m-d H:i:s', $expires_timestamp),
                'status' => 'active',
                'access_duration_days' => $duration
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d']
        );
        
        // Enroll user in course
        ld_update_course_access($user_id, $course_id, false);
        
        DatabaseManager::logAction($user_id, $course_id, 'test_access_granted', [
            'duration_days' => $duration,
            'expires_timestamp' => $expires_timestamp
        ]);
        
        wp_send_json_success([
            'message' => sprintf(
                'Access granted to %s for course "%s" for %d days (expires: %s)',
                $user->display_name,
                $course->post_title,
                $duration,
                date('Y-m-d H:i:s', $expires_timestamp)
            )
        ]);
    }
    
    /**
     * AJAX: Test check access
     */
    public function ajaxTestCheckAccess(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('Missing user ID');
            return;
        }
        
        $subscription_manager = new SubscriptionManager();
        $subscriptions = $subscription_manager->getUserSubscriptions($user_id);
        
        $user = get_user_by('id', $user_id);
        $user_name = $user ? $user->display_name : 'Unknown User';
        
        wp_send_json_success([
            'user_name' => $user_name,
            'subscriptions' => $subscriptions,
            'total_count' => count($subscriptions)
        ]);
    }
    
    /**
     * AJAX: Get statistics
     */
    public function ajaxGetStatistics(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $stats = DatabaseManager::getStatistics();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Search users
     */
    public function ajaxSearchUsers(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        if (strlen($search) < 2) {
            wp_send_json_success([]);
            return;
        }
        
        $users = get_users([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 10
        ]);
        
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'login' => $user->user_login
            ];
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Get courses
     */
    public function ajaxGetCourses(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $results = [];
        foreach ($courses as $course) {
            $results[] = [
                'id' => $course->ID,
                'title' => $course->post_title
            ];
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Test revoke access
     */
    public function ajaxTestRevokeAccess(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$user_id || !$course_id) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $user = get_user_by('id', $user_id);
        $course = get_post($course_id);
        
        if (!$user || !$course || $course->post_type !== 'sfwd-courses') {
            wp_send_json_error('Invalid user or course');
            return;
        }
        
        $subscription_manager = new SubscriptionManager();
        $success = $subscription_manager->revokeAccess($user_id, $course_id);
        
        if ($success) {
            wp_send_json_success([
                'message' => sprintf(
                    'Access revoked for %s from course "%s"',
                    $user->display_name,
                    $course->post_title
                )
            ]);
        } else {
            wp_send_json_error('Failed to revoke access');
        }
    }
    
    /**
     * AJAX: Test import subscriptions
     */
    public function ajaxTestImportSubscriptions(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $subscription_manager = new SubscriptionManager();
        $stats = $subscription_manager->importAllExistingSubscriptions();
        
        wp_send_json_success([
            'message' => sprintf(
                'Import completed: %d imported, %d skipped, %d errors',
                $stats['imported'],
                $stats['skipped'],
                $stats['errors']
            ),
            'stats' => $stats
        ]);
    }
    
    /**
     * AJAX: Load recent subscriptions
     */
    public function ajaxLoadRecentSubscriptions(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $subscription_manager = new SubscriptionManager();
        $subscriptions = $subscription_manager->getRecentSubscriptions(5);
        
        wp_send_json_success($subscriptions);
    }
    
    /**
     * AJAX: Load students
     */
    public function ajaxLoadStudents(): void {
        check_ajax_referer('subscription_tracker_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $subscription_manager = new SubscriptionManager();
        $students = $subscription_manager->getAllStudents();
        
        wp_send_json_success($students);
    }
}
