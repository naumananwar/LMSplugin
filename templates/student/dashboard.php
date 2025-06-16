<?php
/**
 * Student Dashboard Template
 */

// Ensure user is logged in and is a student
if (!is_user_logged_in() || !current_user_can('student')) {
    wp_redirect(wp_login_url());
    exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;

// Get student data
global $wpdb;
$institution_id = get_user_meta($user_id, 'institution_id', true);
$institution = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}lms_institutions WHERE id = %d", 
    $institution_id
));

// Get enrolled courses (placeholder - would need courses table)
$enrolled_courses = array(
    (object) array(
        'id' => 1,
        'title' => 'Introduction to Web Development',
        'instructor' => 'John Doe',
        'progress' => 75,
        'status' => 'active'
    ),
    (object) array(
        'id' => 2,
        'title' => 'Advanced PHP Programming',
        'instructor' => 'Jane Smith',
        'progress' => 30,
        'status' => 'active'
    )
);

// Get recent activities (placeholder)
$recent_activities = array(
    (object) array(
        'type' => 'assignment_submitted',
        'course' => 'Web Development',
        'description' => 'Submitted Assignment 3',
        'date' => '2024-01-15'
    ),
    (object) array(
        'type' => 'quiz_completed',
        'course' => 'PHP Programming',
        'description' => 'Completed Quiz 2',
        'date' => '2024-01-14'
    )
);
?>

<div class="lms-student-dashboard">
    <div class="dashboard-header">
        <h1><?php printf(__('Welcome back, %s', 'lms-authentication-system'), $user->display_name); ?></h1>
        <?php if ($institution): ?>
            <p class="institution-info"><?php echo esc_html($institution->name); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3><?php echo count($enrolled_courses); ?></h3>
            <p><?php _e('Enrolled Courses', 'lms-authentication-system'); ?></p>
        </div>
        <div class="stat-card">
            <h3>67%</h3>
            <p><?php _e('Average Progress', 'lms-authentication-system'); ?></p>
        </div>
        <div class="stat-card">
            <h3>8</h3>
            <p><?php _e('Completed Assignments', 'lms-authentication-system'); ?></p>
        </div>
        <div class="stat-card">
            <h3>5</h3>
            <p><?php _e('Upcoming Deadlines', 'lms-authentication-system'); ?></p>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="main-content">
            <section class="enrolled-courses">
                <h2><?php _e('My Courses', 'lms-authentication-system'); ?></h2>
                <div class="courses-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="course-card">
                            <h3><?php echo esc_html($course->title); ?></h3>
                            <p class="instructor"><?php printf(__('Instructor: %s', 'lms-authentication-system'), $course->instructor); ?></p>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $course->progress; ?>%"></div>
                            </div>
                            <p class="progress-text"><?php printf(__('%d%% Complete', 'lms-authentication-system'), $course->progress); ?></p>
                            <div class="course-actions">
                                <a href="#" class="btn btn-primary"><?php _e('Continue', 'lms-authentication-system'); ?></a>
                                <a href="#" class="btn btn-secondary"><?php _e('View Details', 'lms-authentication-system'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <section class="upcoming-deadlines">
                <h2><?php _e('Upcoming Deadlines', 'lms-authentication-system'); ?></h2>
                <div class="deadlines-list">
                    <div class="deadline-item">
                        <span class="deadline-date">Jan 20</span>
                        <div class="deadline-info">
                            <h4>Assignment 4 - Web Development</h4>
                            <p>Final project submission</p>
                        </div>
                        <span class="deadline-status urgent">Due in 2 days</span>
                    </div>
                    <div class="deadline-item">
                        <span class="deadline-date">Jan 25</span>
                        <div class="deadline-info">
                            <h4>Quiz 3 - PHP Programming</h4>
                            <p>Advanced concepts quiz</p>
                        </div>
                        <span class="deadline-status normal">Due in 7 days</span>
                    </div>
                </div>
            </section>
        </div>
        
        <div class="sidebar">
            <section class="recent-activities">
                <h3><?php _e('Recent Activities', 'lms-authentication-system'); ?></h3>
                <div class="activities-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="icon-<?php echo $activity->type; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <p><?php echo esc_html($activity->description); ?></p>
                                <span class="activity-course"><?php echo esc_html($activity->course); ?></span>
                                <span class="activity-date"><?php echo esc_html($activity->date); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <section class="quick-actions">
                <h3><?php _e('Quick Actions', 'lms-authentication-system'); ?></h3>
                <div class="actions-list">
                    <a href="#" class="action-btn">
                        <i class="icon-courses"></i>
                        <?php _e('Browse Courses', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-assignments"></i>
                        <?php _e('View Assignments', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-grades"></i>
                        <?php _e('Check Grades', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-profile"></i>
                        <?php _e('Edit Profile', 'lms-authentication-system'); ?>
                    </a>
                </div>
            </section>
        </div>
    </div>
</div>

