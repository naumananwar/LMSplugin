<?php
/**
 * Instructor Dashboard Template
 */

// Ensure user is logged in and is an instructor
if (!is_user_logged_in() || !current_user_can('instructor')) {
    wp_redirect(wp_login_url());
    exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;

// Get instructor data
global $wpdb;
$institution_id = get_user_meta($user_id, 'institution_id', true);
$institution = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}lms_institutions WHERE id = %d", 
    $institution_id
));

// Get instructor's courses (placeholder - would need courses table)
$instructor_courses = array(
    (object) array(
        'id' => 1,
        'title' => 'Introduction to Web Development',
        'students_count' => 45,
        'assignments_pending' => 12,
        'status' => 'active'
    ),
    (object) array(
        'id' => 2,
        'title' => 'Advanced PHP Programming',
        'students_count' => 28,
        'assignments_pending' => 8,
        'status' => 'active'
    ),
    (object) array(
        'id' => 3,
        'title' => 'Database Design Fundamentals',
        'students_count' => 32,
        'assignments_pending' => 5,
        'status' => 'active'
    )
);

// Get recent activities (placeholder)
$recent_activities = array(
    (object) array(
        'type' => 'assignment_submitted',
        'student' => 'John Smith',
        'course' => 'Web Development',
        'description' => 'Submitted Assignment 3',
        'date' => '2024-01-15'
    ),
    (object) array(
        'type' => 'new_enrollment',
        'student' => 'Sarah Johnson',
        'course' => 'PHP Programming',
        'description' => 'New student enrolled',
        'date' => '2024-01-14'
    ),
    (object) array(
        'type' => 'question_posted',
        'student' => 'Mike Davis',
        'course' => 'Database Design',
        'description' => 'Posted question in forum',
        'date' => '2024-01-13'
    )
);

// Get pending tasks
$pending_tasks = array(
    (object) array(
        'type' => 'grading',
        'count' => 25,
        'description' => 'Assignments awaiting grades'
    ),
    (object) array(
        'type' => 'questions',
        'count' => 8,
        'description' => 'Unanswered student questions'
    ),
    (object) array(
        'type' => 'reviews',
        'count' => 3,
        'description' => 'Course content reviews needed'
    )
);
?>

<div class="lms-instructor-dashboard">
    <div class="dashboard-header">
        <h1><?php printf(__('Welcome back, %s', 'lms-authentication-system'), $user->display_name); ?></h1>
        <?php if ($institution): ?>
            <p class="institution-info"><?php echo esc_html($institution->name); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3><?php echo count($instructor_courses); ?></h3>
            <p><?php _e('Active Courses', 'lms-authentication-system'); ?></p>
        </div>
        <div class="stat-card">
            <h3><?php echo array_sum(array_column($instructor_courses, 'students_count')); ?></h3>
            <p><?php _e('Total Students', 'lms-authentication-system'); ?></p>
        </div>
        <div class="stat-card">
            <h3><?php echo array_sum(array_column($instructor_courses, 'assignments_pending')); ?></h3>
            <p><?php _e('Pending Assignments', 'lms-authentication-system'); ?></p>
        </div>
        <div class="stat-card">
            <h3>92%</h3>
            <p><?php _e('Student Satisfaction', 'lms-authentication-system'); ?></p>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="main-content">
            <section class="courses-overview">
                <div class="section-header">
                    <h2><?php _e('My Courses', 'lms-authentication-system'); ?></h2>
                    <a href="#" class="btn btn-primary"><?php _e('Create New Course', 'lms-authentication-system'); ?></a>
                </div>
                <div class="courses-grid">
                    <?php foreach ($instructor_courses as $course): ?>
                        <div class="course-card">
                            <h3><?php echo esc_html($course->title); ?></h3>
                            <div class="course-stats">
                                <div class="stat">
                                    <span class="count"><?php echo $course->students_count; ?></span>
                                    <span class="label"><?php _e('Students', 'lms-authentication-system'); ?></span>
                                </div>
                                <div class="stat">
                                    <span class="count"><?php echo $course->assignments_pending; ?></span>
                                    <span class="label"><?php _e('Pending', 'lms-authentication-system'); ?></span>
                                </div>
                            </div>
                            <div class="course-actions">
                                <a href="#" class="btn btn-primary"><?php _e('Manage', 'lms-authentication-system'); ?></a>
                                <a href="#" class="btn btn-secondary"><?php _e('View', 'lms-authentication-system'); ?></a>
                                <a href="#" class="btn btn-outline"><?php _e('Analytics', 'lms-authentication-system'); ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <section class="pending-tasks">
                <h2><?php _e('Pending Tasks', 'lms-authentication-system'); ?></h2>
                <div class="tasks-grid">
                    <?php foreach ($pending_tasks as $task): ?>
                        <div class="task-card">
                            <div class="task-icon">
                                <i class="icon-<?php echo $task->type; ?>"></i>
                            </div>
                            <div class="task-info">
                                <h4><?php echo $task->count; ?></h4>
                                <p><?php echo esc_html($task->description); ?></p>
                            </div>
                            <a href="#" class="btn btn-outline"><?php _e('View All', 'lms-authentication-system'); ?></a>
                        </div>
                    <?php endforeach; ?>
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
                                <p><strong><?php echo esc_html($activity->student); ?></strong></p>
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
                        <i class="icon-assignment"></i>
                        <?php _e('Create Assignment', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-quiz"></i>
                        <?php _e('Create Quiz', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-announcement"></i>
                        <?php _e('Post Announcement', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-gradebook"></i>
                        <?php _e('Grade Submissions', 'lms-authentication-system'); ?>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="icon-students"></i>
                        <?php _e('Manage Students', 'lms-authentication-system'); ?>
                    </a>
                </div>
            </section>
            
            <section class="upcoming-events">
                <h3><?php _e('Upcoming Events', 'lms-authentication-system'); ?></h3>
                <div class="events-list">
                    <div class="event-item">
                        <span class="event-date">Jan 20</span>
                        <div class="event-info">
                            <h4>Assignment Due</h4>
                            <p>Web Development - Project 4</p>
                        </div>
                    </div>
                    <div class="event-item">
                        <span class="event-date">Jan 22</span>
                        <div class="event-info">
                            <h4>Live Session</h4>
                            <p>PHP Programming - Q&A</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

