jQuery(document).ready(function($) {
    'use strict';
    
    // Load courses on page load
    loadCourses();
    
    // User search functionality
    let searchTimeout;
    $(document).on('input', '.user-search', function() {
        const $input = $(this);
        const $results = $('#user-search-results');
        const query = $input.val().trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            $results.hide().empty();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            searchUsers(query, $results, $input);
        }, 300);
    });
    
    // Select user from search results
    $(document).on('click', '.search-result-item', function() {
        const $item = $(this);
        const userId = $item.data('user-id');
        const userName = $item.text();
        
        $('#test-user-id').val(userId).attr('data-user-name', userName);
        $('#user-search-results').hide().empty();
    });
    
    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.user-search, #user-search-results').length) {
            $('#user-search-results').hide();
        }
    });
    
    // Grant access form
    $('#grant-access-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const originalText = $button.text();
        
        const formData = {
            action: 'st_test_grant_access',
            nonce: subscriptionTracker.nonce,
            user_id: $('#test-user-id').val(),
            course_id: $('#test-course-id').val(),
            duration: $('#test-duration').val()
        };
        
        if (!formData.user_id || !formData.course_id) {
            alert('Please select both user and course');
            return;
        }
        
        $button.text('Granting...').prop('disabled', true);
        
        $.post(subscriptionTracker.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    $form[0].reset();
                    $('#test-user-id').removeAttr('data-user-name');
                } else {
                    showMessage('error', response.data || 'Failed to grant access');
                }
            })
            .fail(function() {
                showMessage('error', 'AJAX request failed');
            })
            .always(function() {
                $button.text(originalText).prop('disabled', false);
            });
    });
    
    // Check access form
    $('#check-access-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $results = $('#access-results');
        const originalText = $button.text();
        
        const userId = $('#check-user-id').val();
        
        if (!userId) {
            alert('Please enter a user ID');
            return;
        }
        
        $button.text('Checking...').prop('disabled', true);
        $results.removeClass('show').html('');
        
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_test_check_access',
            nonce: subscriptionTracker.nonce,
            user_id: userId
        })
        .done(function(response) {
            if (response.success) {
                displayUserSubscriptions(response.data, $results);
                $results.addClass('show');
            } else {
                showMessage('error', response.data || 'Failed to check access');
            }
        })
        .fail(function() {
            showMessage('error', 'AJAX request failed');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Extend access form
    $('#extend-access-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const originalText = $button.text();
        
        const formData = {
            action: 'st_extend_access',
            nonce: subscriptionTracker.nonce,
            user_id: $('#extend-user-id').val(),
            course_id: $('#extend-course-id').val(),
            days: $('#extend-days').val()
        };
        
        if (!formData.user_id || !formData.course_id || !formData.days) {
            alert('Please fill in all fields');
            return;
        }
        
        $button.text('Extending...').prop('disabled', true);
        
        $.post(subscriptionTracker.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    showMessage('success', response.data);
                    $form[0].reset();
                } else {
                    showMessage('error', response.data || 'Failed to extend access');
                }
            })
            .fail(function() {
                showMessage('error', 'AJAX request failed');
            })
            .always(function() {
                $button.text(originalText).prop('disabled', false);
            });
    });
    
    // Revoke access form
    $('#revoke-access-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const originalText = $button.text();
        
        const userId = $('#revoke-user-id').val();
        const courseId = $('#revoke-course-id').val();
        
        if (!userId || !courseId) {
            alert('Please fill in both User ID and Course ID');
            return;
        }
        
        // Confirmation dialog
        if (!confirm('Are you sure you want to revoke access? This will immediately remove the user\'s access to the course.')) {
            return;
        }
        
        $button.text('Revoking...').prop('disabled', true);
        
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_test_revoke_access',
            nonce: subscriptionTracker.nonce,
            user_id: userId,
            course_id: courseId
        })
        .done(function(response) {
            if (response.success) {
                showMessage('success', response.data.message);
                $form[0].reset();
            } else {
                showMessage('error', response.data || 'Failed to revoke access');
            }
        })
        .fail(function() {
            showMessage('error', 'AJAX request failed');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Import subscriptions button
    $('#import-subscriptions-btn').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        
        if (!confirm('This will import all existing subscription data from user meta. Continue?')) {
            return;
        }
        
        $button.text('Importing...').prop('disabled', true);
        
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_test_import_subscriptions',
            nonce: subscriptionTracker.nonce
        })
        .done(function(response) {
            if (response.success) {
                showMessage('success', response.data.message);
                // Refresh statistics
                location.reload();
            } else {
                showMessage('error', response.data || 'Failed to import subscriptions');
            }
        })
        .fail(function() {
            showMessage('error', 'AJAX request failed');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Load recent subscriptions
    $('#load-recent-btn').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $container = $('#recent-subscriptions');
        const originalText = $button.text();
        
        $button.text('Loading...').prop('disabled', true);
        $('#students-list').removeClass('show').html('');
        
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_load_recent_subscriptions',
            nonce: subscriptionTracker.nonce
        })
        .done(function(response) {
            if (response.success) {
                displayRecentSubscriptions(response.data, $container);
                $container.addClass('show');
            } else {
                showMessage('error', response.data || 'Failed to load recent subscriptions');
            }
        })
        .fail(function() {
            showMessage('error', 'AJAX request failed');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Load students list
    $('#load-students-btn').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $container = $('#students-list');
        const originalText = $button.text();
        
        $button.text('Loading...').prop('disabled', true);
        $('#recent-subscriptions').removeClass('show').html('');
        
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_load_students',
            nonce: subscriptionTracker.nonce
        })
        .done(function(response) {
            if (response.success) {
                displayStudentsList(response.data, $container);
                $container.addClass('show');
            } else {
                showMessage('error', response.data || 'Failed to load students');
            }
        })
        .fail(function() {
            showMessage('error', 'AJAX request failed');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // User lookup form
    $('#user-lookup-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $results = $('#user-subscriptions');
        const originalText = $button.text();
        
        const userId = $('#lookup-user-id').val();
        
        if (!userId) {
            alert('Please enter a user ID');
            return;
        }
        
        $button.text('Looking up...').prop('disabled', true);
        $results.removeClass('show').html('');
        
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_get_user_subscriptions',
            nonce: subscriptionTracker.nonce,
            user_id: userId
        })
        .done(function(response) {
            if (response.success) {
                displayUserSubscriptions({
                    user_name: 'User ID: ' + userId,
                    subscriptions: response.data,
                    total_count: response.data.length
                }, $results);
                $results.addClass('show');
            } else {
                showMessage('error', response.data || 'Failed to lookup subscriptions');
            }
        })
        .fail(function() {
            showMessage('error', 'AJAX request failed');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Helper functions
    function searchUsers(query, $results, $input) {
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_search_users',
            nonce: subscriptionTracker.nonce,
            search: query
        })
        .done(function(response) {
            if (response.success && response.data.length > 0) {
                let html = '';
                response.data.forEach(function(user) {
                    html += `<div class="search-result-item" data-user-id="${user.id}">
                        ${user.name} (${user.email}) - ID: ${user.id}
                    </div>`;
                });
                $results.html(html).show();
            } else {
                $results.hide().empty();
            }
        })
        .fail(function() {
            $results.hide().empty();
        });
    }
    
    function loadCourses() {
        $.post(subscriptionTracker.ajaxUrl, {
            action: 'st_get_courses',
            nonce: subscriptionTracker.nonce
        })
        .done(function(response) {
            if (response.success) {
                const $select = $('#test-course-id');
                $select.empty().append('<option value="">Select Course...</option>');
                
                response.data.forEach(function(course) {
                    $select.append(`<option value="${course.id}">${course.title}</option>`);
                });
            }
        });
    }
    
    function displayUserSubscriptions(data, $container) {
        let html = `<h4>👤 ${data.user_name} - ${data.total_count} Subscription(s)</h4>`;
        
        if (data.subscriptions.length === 0) {
            html += '<p>No subscriptions found for this user.</p>';
        } else {
            data.subscriptions.forEach(function(sub) {
                const statusClass = sub.is_expired ? 'expired' : (sub.days_remaining <= 7 ? 'expiring' : '');
                const statusText = sub.is_expired ? 'EXPIRED' : `${sub.days_remaining} days remaining`;
                const statusIcon = sub.is_expired ? '❌' : (sub.days_remaining <= 7 ? '⚠️' : '✅');
                
                html += `
                    <div class="subscription-item ${statusClass}">
                        <h5>${statusIcon} ${sub.course_title}</h5>
                        <p><strong>Status:</strong> ${sub.status.toUpperCase()} - ${statusText}</p>
                        <p><strong>Product:</strong> ${sub.product_name}</p>
                        <p><strong>Granted:</strong> ${formatDate(sub.granted_date)}</p>
                        <p><strong>Expires:</strong> ${formatDate(sub.expires_date)}</p>
                        <p><strong>Duration:</strong> ${sub.access_duration_days} days</p>
                        <p><strong>Order ID:</strong> ${sub.order_id}</p>
                        <div class="subscription-actions">
                            <button class="button button-small revoke-subscription-btn" 
                                    data-user-id="${data.user_name.includes('ID:') ? data.user_name.split('ID: ')[1] : ''}" 
                                    data-course-id="${sub.course_id}" 
                                    data-course-title="${sub.course_title}">
                                🚫 Revoke Access
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        $container.html(html);
        
        // Add click handlers for revoke buttons
        $container.find('.revoke-subscription-btn').on('click', function() {
            const $btn = $(this);
            const userId = $btn.data('user-id');
            const courseId = $btn.data('course-id');
            const courseTitle = $btn.data('course-title');
            
            if (!userId || !courseId) {
                alert('Missing user or course information');
                return;
            }
            
            if (!confirm(`Are you sure you want to revoke access to "${courseTitle}"?`)) {
                return;
            }
            
            const originalText = $btn.text();
            $btn.text('Revoking...').prop('disabled', true);
            
            $.post(subscriptionTracker.ajaxUrl, {
                action: 'st_test_revoke_access',
                nonce: subscriptionTracker.nonce,
                user_id: userId,
                course_id: courseId
            })
            .done(function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    // Remove the subscription item from display
                    $btn.closest('.subscription-item').fadeOut();
                } else {
                    showMessage('error', response.data || 'Failed to revoke access');
                    $btn.text(originalText).prop('disabled', false);
                }
            })
            .fail(function() {
                showMessage('error', 'AJAX request failed');
                $btn.text(originalText).prop('disabled', false);
            });
        });
    }
    
    function displayRecentSubscriptions(subscriptions, $container) {
        let html = '<h4>🕒 Latest 5 Subscriptions</h4>';
        
        if (subscriptions.length === 0) {
            html += '<p>No recent subscriptions found.</p>';
        } else {
            subscriptions.forEach(function(sub) {
                const statusClass = sub.is_expired ? 'expired' : (sub.days_remaining <= 7 ? 'expiring' : '');
                const statusText = sub.is_expired ? 'EXPIRED' : `${sub.days_remaining} days remaining`;
                const statusIcon = sub.is_expired ? '❌' : (sub.days_remaining <= 7 ? '⚠️' : '✅');
                
                html += `
                    <div class="subscription-item ${statusClass}">
                        <div class="subscription-header">
                            <h5>${statusIcon} ${sub.user_name} - ${sub.course_title}</h5>
                            <span class="subscription-date">${formatDate(sub.granted_date)}</span>
                        </div>
                        <div class="subscription-details">
                            <p><strong>Status:</strong> ${sub.status.toUpperCase()} - ${statusText}</p>
                            <p><strong>Email:</strong> ${sub.user_email}</p>
                            <p><strong>Product:</strong> ${sub.product_name}</p>
                            <p><strong>Expires:</strong> ${formatDate(sub.expires_date)}</p>
                        </div>
                        <div class="subscription-actions">
                            <button class="button button-small revoke-subscription-btn" 
                                    data-user-id="${sub.user_id}" 
                                    data-course-id="${sub.course_id}" 
                                    data-course-title="${sub.course_title}">
                                🚫 Revoke Access
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        $container.html(html);
        
        // Add click handlers for revoke buttons
        $container.find('.revoke-subscription-btn').on('click', function() {
            const $btn = $(this);
            const userId = $btn.data('user-id');
            const courseId = $btn.data('course-id');
            const courseTitle = $btn.data('course-title');
            
            if (!confirm(`Are you sure you want to revoke access to "${courseTitle}"?`)) {
                return;
            }
            
            const originalText = $btn.text();
            $btn.text('Revoking...').prop('disabled', true);
            
            $.post(subscriptionTracker.ajaxUrl, {
                action: 'st_test_revoke_access',
                nonce: subscriptionTracker.nonce,
                user_id: userId,
                course_id: courseId
            })
            .done(function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    $btn.closest('.subscription-item').fadeOut();
                } else {
                    showMessage('error', response.data || 'Failed to revoke access');
                    $btn.text(originalText).prop('disabled', false);
                }
            })
            .fail(function() {
                showMessage('error', 'AJAX request failed');
                $btn.text(originalText).prop('disabled', false);
            });
        });
    }
    
    function displayStudentsList(students, $container) {
        let html = '<h4>👥 All Students with Subscriptions</h4>';
        
        if (students.length === 0) {
            html += '<p>No students found.</p>';
        } else {
            html += '<div class="students-search"><input type="text" id="students-filter" placeholder="Search students by name or email..." class="students-search-input"></div>';
            html += '<div class="students-grid">';
            
            students.forEach(function(student) {
                html += `
                    <div class="student-card" data-name="${student.name.toLowerCase()}" data-email="${student.email.toLowerCase()}">
                        <div class="student-info">
                            <h5>👤 ${student.name}</h5>
                            <p><strong>User ID:</strong> ${student.id}</p>
                            <p><strong>Email:</strong> ${student.email}</p>
                            <p><strong>Login:</strong> ${student.login}</p>
                            <p><strong>Subscriptions:</strong> ${student.subscription_count}</p>
                            <p><strong>Registered:</strong> ${formatDate(student.registered)}</p>
                        </div>
                        <div class="student-actions" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #e1e5e9; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                            <button class="button button-small view-student-subscriptions" data-user-id="${student.id}" data-user-name="${student.name}">
                                📋 View Subscriptions
                            </button>
                            <button class="button button-small button-danger remove-all-subscriptions" data-user-id="${student.id}" data-user-name="${student.name}">
                                🗑️ Remove All
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        $container.html(html);
        
        // Add search functionality
        $('#students-filter').on('input', function() {
            const query = $(this).val().toLowerCase();
            $('.student-card').each(function() {
                const $card = $(this);
                const name = $card.data('name');
                const email = $card.data('email');
                
                if (name.includes(query) || email.includes(query)) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        });
        
        // Add click handlers for view subscriptions
        $container.find('.view-student-subscriptions').on('click', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            
            // Populate the user lookup form and trigger it
            $('#lookup-user-id').val(userId);
            $('#user-lookup-form').trigger('submit');
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#user-subscriptions').offset().top - 100
            }, 500);
        });
        
        // Add click handlers for remove all subscriptions
        $container.find('.remove-all-subscriptions').on('click', function() {
            const $btn = $(this);
            const userId = $btn.data('user-id');
            const userName = $btn.data('user-name');
            
            if (!confirm(`Are you sure you want to remove ALL subscriptions for "${userName}" (ID: ${userId})?\n\nThis will revoke access to all their courses immediately.`)) {
                return;
            }
            
            const originalText = $btn.text();
            $btn.text('Removing...').prop('disabled', true);
            
            // First get user's subscriptions
            $.post(subscriptionTracker.ajaxUrl, {
                action: 'st_get_user_subscriptions',
                nonce: subscriptionTracker.nonce,
                user_id: userId
            })
            .done(function(response) {
                if (response.success && response.data.length > 0) {
                    let removedCount = 0;
                    let totalCount = response.data.length;
                    
                    // Remove each subscription
                    response.data.forEach(function(sub) {
                        $.post(subscriptionTracker.ajaxUrl, {
                            action: 'st_test_revoke_access',
                            nonce: subscriptionTracker.nonce,
                            user_id: userId,
                            course_id: sub.course_id
                        })
                        .done(function() {
                            removedCount++;
                            if (removedCount === totalCount) {
                                showMessage('success', `Successfully removed ${totalCount} subscription(s) for ${userName}`);
                                // Update the subscription count in the card
                                $btn.closest('.student-card').find('.student-info p:contains("Subscriptions:")').html('<strong>Subscriptions:</strong> 0');
                                $btn.text('All Removed').prop('disabled', true).removeClass('button-danger').addClass('button-secondary');
                            }
                        })
                        .fail(function() {
                            showMessage('error', `Failed to remove subscription for course ${sub.course_id}`);
                            $btn.text(originalText).prop('disabled', false);
                        });
                    });
                } else {
                    showMessage('error', 'No subscriptions found for this user');
                    $btn.text(originalText).prop('disabled', false);
                }
            })
            .fail(function() {
                showMessage('error', 'Failed to get user subscriptions');
                $btn.text(originalText).prop('disabled', false);
            });
        });
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    function showMessage(type, message) {
        const $message = $(`<div class="notice notice-${type === 'success' ? 'success' : 'error'} is-dismissible">
            <p class="${type}">${message}</p>
        </div>`);
        
        $('.wrap h1').after($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
});
