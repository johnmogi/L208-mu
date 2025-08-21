<?php

namespace LilacCourseSystem\Dashboard;

/**
 * User dashboard for course access and expiry management
 */
class UserDashboard {
    
    public function __construct() {
        $this->register_hooks();
    }
    
    private function register_hooks() {
        // Add dashboard shortcode
        add_shortcode('lilac_user_dashboard', [$this, 'render_dashboard']);
        
        // Add dashboard to user profile
        add_action('show_user_profile', [$this, 'show_user_courses']);
        add_action('edit_user_profile', [$this, 'show_user_courses']);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }
    
    /**
     * Render user dashboard shortcode
     */
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your courses.</p>';
        }
        
        $user_id = get_current_user_id();
        $courses = $this->get_user_courses($user_id);
        
        ob_start();
        ?>
        <div class="lilac-user-dashboard">
            <h3>My Courses</h3>
            
            <?php if (empty($courses)): ?>
                <p>You don't have any active courses yet.</p>
                <a href="<?php echo home_url('/shop'); ?>" class="button">Browse Courses</a>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card <?php echo $course['status']; ?>">
                            <h4><?php echo esc_html($course['title']); ?></h4>
                            
                            <div class="course-meta">
                                <p><strong>Status:</strong> 
                                    <span class="status-<?php echo $course['status']; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </p>
                                
                                <?php if ($course['expires']): ?>
                                    <p><strong>Expires:</strong> <?php echo $course['expires_formatted']; ?></p>
                                    
                                    <?php if ($course['days_remaining'] > 0): ?>
                                        <p><strong>Days Remaining:</strong> <?php echo $course['days_remaining']; ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p><strong>Access:</strong> Unlimited</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-actions">
                                <?php if ($course['status'] === 'active'): ?>
                                    <a href="<?php echo get_permalink($course['course_id']); ?>" class="button button-primary">
                                        Access Course
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo home_url('/shop'); ?>" class="button">
                                        Renew Access
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .lilac-user-dashboard {
            max-width: 800px;
            margin: 20px 0;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .course-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
        }
        .course-card.expired {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .course-card.active {
            border-color: #28a745;
            background: #f8fff9;
        }
        .course-card h4 {
            margin-top: 0;
            color: #333;
        }
        .course-meta p {
            margin: 8px 0;
            font-size: 14px;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-expired {
            color: #dc3545;
            font-weight: bold;
        }
        .course-actions {
            margin-top: 15px;
        }
        .button {
            display: inline-block;
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .button:hover {
            background: #005177;
        }
        .button-primary {
            background: #28a745;
        }
        .button-primary:hover {
            background: #1e7e34;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user courses with status and expiry info
     */
    private function get_user_courses($user_id) {
        global $wpdb;
        
        $courses = [];
        $current_time = current_time('timestamp');
        
        // Get courses from subscriptions table
        $table_name = $wpdb->prefix . 'lilac_user_subscriptions';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
        
        foreach ($results as $subscription) {
            $course_id = $subscription->course_id;
            $expires = $subscription->access_expires ? strtotime($subscription->access_expires) : 0;
            
            $status = 'active';
            $days_remaining = 0;
            
            if ($expires && $expires <= $current_time) {
                $status = 'expired';
            } elseif ($expires) {
                $days_remaining = ceil(($expires - $current_time) / DAY_IN_SECONDS);
            }
            
            $courses[] = [
                'course_id' => $course_id,
                'title' => get_the_title($course_id),
                'status' => $status,
                'expires' => $expires,
                'expires_formatted' => $expires ? date('M j, Y', $expires) : null,
                'days_remaining' => $days_remaining,
                'order_id' => $subscription->order_id
            ];
        }
        
        return $courses;
    }
    
    /**
     * Show user courses in profile
     */
    public function show_user_courses($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        $courses = $this->get_user_courses($user->ID);
        ?>
        <h3>Course Access</h3>
        <table class="form-table">
            <tr>
                <th><label>Active Courses</label></th>
                <td>
                    <?php if (empty($courses)): ?>
                        <p>No courses found.</p>
                    <?php else: ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo esc_html($course['title']); ?></td>
                                        <td>
                                            <span class="status-<?php echo $course['status']; ?>">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $course['expires_formatted'] ?: 'Never'; ?>
                                            <?php if ($course['days_remaining'] > 0): ?>
                                                (<?php echo $course['days_remaining']; ?> days)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="button toggle-access" 
                                                    data-user="<?php echo $user->ID; ?>"
                                                    data-course="<?php echo $course['course_id']; ?>"
                                                    data-action="<?php echo $course['status'] === 'active' ? 'disable' : 'enable'; ?>">
                                                <?php echo $course['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('.toggle-access').on('click', function() {
                var $btn = $(this);
                var userId = $btn.data('user');
                var courseId = $btn.data('course');
                var action = $btn.data('action');
                
                $.post(ajaxurl, {
                    action: 'toggle_course_access',
                    user_id: userId,
                    course_id: courseId,
                    toggle_action: action
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add dashboard widget for admins
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'lilac_course_stats',
                'Course Access Stats',
                [$this, 'render_stats_widget']
            );
        }
    }
    
    /**
     * Render stats widget
     */
    public function render_stats_widget() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lilac_user_subscriptions';
        $current_time = current_time('mysql');
        
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active' AND (access_expires IS NULL OR access_expires > %s)",
            $current_time
        ));
        
        $expired_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE access_expires IS NOT NULL AND access_expires <= %s",
            $current_time
        ));
        
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        echo '<div class="lilac-stats">';
        echo '<p><strong>Active Subscriptions:</strong> ' . $active_count . '</p>';
        echo '<p><strong>Expired Subscriptions:</strong> ' . $expired_count . '</p>';
        echo '<p><strong>Total Subscriptions:</strong> ' . $total_count . '</p>';
        echo '</div>';
    }
}
