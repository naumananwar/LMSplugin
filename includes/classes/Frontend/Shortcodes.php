<?php

namespace LMS_Auth\Frontend;

use LMS_Auth\Core\Authentication;
use LMS_Auth\Core\Roles;
use LMS_Auth\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Register shortcodes
        add_shortcode('lms_login_form', array($this, 'login_form_shortcode'));
        add_shortcode('lms_register_form', array($this, 'register_form_shortcode'));
        add_shortcode('lms_packages', array($this, 'packages_shortcode'));
        add_shortcode('lms_student_dashboard', array($this, 'student_dashboard_shortcode'));
        add_shortcode('lms_instructor_dashboard', array($this, 'instructor_dashboard_shortcode'));
        add_shortcode('lms_institution_dashboard', array($this, 'institution_dashboard_shortcode'));
        add_shortcode('lms_course_list', array($this, 'course_list_shortcode'));
        add_shortcode('lms_assessment_list', array($this, 'assessment_list_shortcode'));
        add_shortcode('lms_take_assessment', array($this, 'take_assessment_shortcode'));
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('lms-frontend', LMS_AUTH_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), LMS_AUTH_VERSION, true);
        wp_enqueue_style('lms-frontend', LMS_AUTH_PLUGIN_URL . 'assets/css/frontend.css', array(), LMS_AUTH_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('lms-frontend', 'lms_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lms_auth_nonce')
        ));
    }
    
    public function login_form_shortcode($atts) {
        // Redirect if already logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $dashboard_url = Roles::get_dashboard_url($user_id);
            wp_redirect($dashboard_url);
            exit;
        }
        
        return Authentication::generate_login_form();
    }
    
    public function register_form_shortcode($atts) {
        // Redirect if already logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $dashboard_url = Roles::get_dashboard_url($user_id);
            wp_redirect($dashboard_url);
            exit;
        }
        
        return Authentication::generate_register_form();
    }
    
    public function packages_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => -1,
            'category' => ''
        ), $atts);
        
        $args = array(
            'post_type' => 'lms_package',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit'])
        );
        
        if (!empty($atts['category'])) {
            $args['meta_query'] = array(
                array(
                    'key' => '_lms_package_category',
                    'value' => sanitize_text_field($atts['category']),
                    'compare' => '='
                )
            );
        }
        
        $packages = get_posts($args);
        
        ob_start();
        ?>
        <div class="lms-packages-container">
            <h2><?php _e('Choose Your Subscription Package', 'lms-auth'); ?></h2>
            
            <div class="packages-grid">
                <?php foreach ($packages as $package): 
                    $price = get_post_meta($package->ID, '_lms_package_price', true);
                    $duration = get_post_meta($package->ID, '_lms_package_duration', true);
                    $duration_type = get_post_meta($package->ID, '_lms_package_duration_type', true);
                    $included_assessments = get_post_meta($package->ID, '_lms_package_assessments', true);
                    $included_courses = get_post_meta($package->ID, '_lms_package_courses', true);
                    
                    if (!is_array($included_assessments)) $included_assessments = array();
                    if (!is_array($included_courses)) $included_courses = array();
                ?>
                <div class="package-card" data-package-id="<?php echo $package->ID; ?>">
                    <div class="package-header">
                        <h3><?php echo esc_html($package->post_title); ?></h3>
                        <div class="package-price">
                            <span class="currency">$</span>
                            <span class="amount"><?php echo number_format($price, 2); ?></span>
                            <span class="period">/ <?php echo $duration . ' ' . $duration_type; ?></span>
                        </div>
                    </div>
                    
                    <div class="package-content">
                        <?php echo wpautop($package->post_content); ?>
                        
                        <div class="package-features">
                            <h4><?php _e('Included:', 'lms-auth'); ?></h4>
                            <ul>
                                <?php if (!empty($included_assessments)): ?>
                                    <li><?php printf(__('%d Assessments', 'lms-auth'), count($included_assessments)); ?></li>
                                <?php endif; ?>
                                <?php if (!empty($included_courses)): ?>
                                    <li><?php printf(__('%d Courses', 'lms-auth'), count($included_courses)); ?></li>
                                <?php endif; ?>
                                <li><?php printf(__('%d %s access', 'lms-auth'), $duration, $duration_type); ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="package-footer">
                        <button class="btn btn-primary subscribe-btn" data-package-id="<?php echo $package->ID; ?>">
                            <?php _e('Subscribe Now', 'lms-auth'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.subscribe-btn').on('click', function() {
                var packageId = $(this).data('package-id');
                
                // Redirect to payment or handle subscription
                var subscribeUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=lms_subscribe_package&package_id=' + packageId;
                window.location.href = subscribeUrl;
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function student_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your dashboard.', 'lms-auth') . '</p>';
        }
        
        if (!Roles::user_can_access_dashboard(null, 'student')) {
            return '<p>' . __('Access denied.', 'lms-auth') . '</p>';
        }
        
        $user_id = get_current_user_id();
        
        // Get enrolled courses
        $enrolled_courses = $this->get_user_enrolled_courses($user_id);
        
        // Get assessment results
        $assessment_results = Database::get_assessment_results($user_id, null, 10);
        
        // Get active subscription
        $subscription = Database::get_user_subscription($user_id);
        
        ob_start();
        ?>
        <div class="lms-student-dashboard">
            <div class="dashboard-header">
                <h1><?php _e('Student Dashboard', 'lms-auth'); ?></h1>
                <p><?php printf(__('Welcome back, %s!', 'lms-auth'), wp_get_current_user()->display_name); ?></p>
            </div>
            
            <div class="dashboard-content">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo count($enrolled_courses); ?></h3>
                        <p><?php _e('Enrolled Courses', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo count($assessment_results); ?></h3>
                        <p><?php _e('Assessments Taken', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $this->calculate_average_score($assessment_results); ?>%</h3>
                        <p><?php _e('Average Score', 'lms-auth'); ?></p>
                    </div>
                </div>
                
                <div class="dashboard-sections">
                    <div class="section courses-section">
                        <h2><?php _e('My Courses', 'lms-auth'); ?></h2>
                        <?php if (!empty($enrolled_courses)): ?>
                            <div class="courses-list">
                                <?php foreach ($enrolled_courses as $course): 
                                    $progress = $this->calculate_course_progress($user_id, $course->ID);
                                ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <h4><?php echo esc_html($course->post_title); ?></h4>
                                        <p><?php echo esc_html($course->post_excerpt); ?></p>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?php echo $progress; ?>% Complete</span>
                                    </div>
                                    <div class="course-actions">
                                        <a href="<?php echo get_permalink($course->ID); ?>" class="btn btn-primary">
                                            <?php _e('Continue Learning', 'lms-auth'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php _e('You are not enrolled in any courses yet.', 'lms-auth'); ?></p>
                            <a href="<?php echo get_post_type_archive_link('lms_course'); ?>" class="btn btn-primary">
                                <?php _e('Browse Courses', 'lms-auth'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section assessments-section">
                        <h2><?php _e('Recent Assessment Results', 'lms-auth'); ?></h2>
                        <?php if (!empty($assessment_results)): ?>
                            <div class="assessments-list">
                                <?php foreach ($assessment_results as $result): 
                                    $assessment = get_post($result->assessment_id);
                                    $status_class = $result->status === 'completed' ? 'completed' : 'in-progress';
                                ?>
                                <div class="assessment-item <?php echo $status_class; ?>">
                                    <div class="assessment-info">
                                        <h4><?php echo $assessment ? esc_html($assessment->post_title) : __('Unknown Assessment', 'lms-auth'); ?></h4>
                                        <p><?php printf(__('Score: %s/%s (%s%%)', 'lms-auth'), $result->correct_answers, $result->total_questions, number_format($result->score, 1)); ?></p>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $result->status)); ?>
                                        </span>
                                    </div>
                                    <div class="assessment-date">
                                        <small><?php echo date('M j, Y', strtotime($result->created_at)); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php _e('No assessment results yet.', 'lms-auth'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section subscription-section">
                        <h2><?php _e('Subscription Status', 'lms-auth'); ?></h2>
                        <?php if ($subscription): ?>
                            <div class="subscription-info">
                                <p><strong><?php _e('Status:', 'lms-auth'); ?></strong> <?php echo ucfirst($subscription->status); ?></p>
                                <p><strong><?php _e('Started:', 'lms-auth'); ?></strong> <?php echo date('M j, Y', strtotime($subscription->start_date)); ?></p>
                                <?php if ($subscription->end_date): ?>
                                    <p><strong><?php _e('Expires:', 'lms-auth'); ?></strong> <?php echo date('M j, Y', strtotime($subscription->end_date)); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p><?php _e('No active subscription.', 'lms-auth'); ?></p>
                            <a href="<?php echo get_permalink(get_page_by_path('subscription-packages')); ?>" class="btn btn-primary">
                                <?php _e('View Packages', 'lms-auth'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function instructor_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your dashboard.', 'lms-auth') . '</p>';
        }
        
        if (!Roles::user_can_access_dashboard(null, 'instructor')) {
            return '<p>' . __('Access denied.', 'lms-auth') . '</p>';
        }
        
        $user_id = get_current_user_id();
        
        // Get instructor's courses
        $my_courses = get_posts(array(
            'post_type' => 'lms_course',
            'author' => $user_id,
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1
        ));
        
        // Get instructor's assessments
        $my_assessments = get_posts(array(
            'post_type' => 'lms_assessment',
            'author' => $user_id,
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1
        ));
        
        ob_start();
        ?>
        <div class="lms-instructor-dashboard">
            <div class="dashboard-header">
                <h1><?php _e('Instructor Dashboard', 'lms-auth'); ?></h1>
                <p><?php printf(__('Welcome back, %s!', 'lms-auth'), wp_get_current_user()->display_name); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=lms_course'); ?>" class="btn btn-primary">
                    <?php _e('Create New Course', 'lms-auth'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=lms_assessment'); ?>" class="btn btn-primary">
                    <?php _e('Create New Assessment', 'lms-auth'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=lms_lesson'); ?>" class="btn btn-primary">
                    <?php _e('Create New Lesson', 'lms-auth'); ?>
                </a>
            </div>
            
            <div class="dashboard-content">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo count($my_courses); ?></h3>
                        <p><?php _e('My Courses', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo count($my_assessments); ?></h3>
                        <p><?php _e('My Assessments', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $this->count_enrolled_students($user_id); ?></h3>
                        <p><?php _e('Total Students', 'lms-auth'); ?></p>
                    </div>
                </div>
                
                <div class="dashboard-sections">
                    <div class="section courses-section">
                        <h2><?php _e('My Courses', 'lms-auth'); ?></h2>
                        <?php if (!empty($my_courses)): ?>
                            <div class="courses-list">
                                <?php foreach ($my_courses as $course): 
                                    $enrolled_count = $this->count_course_enrollments($course->ID);
                                ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <h4><?php echo esc_html($course->post_title); ?></h4>
                                        <p><?php echo esc_html($course->post_excerpt); ?></p>
                                        <span class="enrollment-count"><?php printf(__('%d students enrolled', 'lms-auth'), $enrolled_count); ?></span>
                                        <span class="course-status <?php echo $course->post_status; ?>"><?php echo ucfirst($course->post_status); ?></span>
                                    </div>
                                    <div class="course-actions">
                                        <a href="<?php echo get_edit_post_link($course->ID); ?>" class="btn btn-secondary">
                                            <?php _e('Edit', 'lms-auth'); ?>
                                        </a>
                                        <a href="<?php echo get_permalink($course->ID); ?>" class="btn btn-primary">
                                            <?php _e('View', 'lms-auth'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php _e('You haven\'t created any courses yet.', 'lms-auth'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section assessments-section">
                        <h2><?php _e('My Assessments', 'lms-auth'); ?></h2>
                        <?php if (!empty($my_assessments)): ?>
                            <div class="assessments-list">
                                <?php foreach ($my_assessments as $assessment): 
                                    $attempt_count = $this->count_assessment_attempts($assessment->ID);
                                ?>
                                <div class="assessment-item">
                                    <div class="assessment-info">
                                        <h4><?php echo esc_html($assessment->post_title); ?></h4>
                                        <p><?php echo esc_html($assessment->post_excerpt); ?></p>
                                        <span class="attempt-count"><?php printf(__('%d attempts', 'lms-auth'), $attempt_count); ?></span>
                                        <span class="assessment-status <?php echo $assessment->post_status; ?>"><?php echo ucfirst($assessment->post_status); ?></span>
                                    </div>
                                    <div class="assessment-actions">
                                        <a href="<?php echo get_edit_post_link($assessment->ID); ?>" class="btn btn-secondary">
                                            <?php _e('Edit', 'lms-auth'); ?>
                                        </a>
                                        <a href="#" class="btn btn-primary view-results" data-assessment-id="<?php echo $assessment->ID; ?>">
                                            <?php _e('View Results', 'lms-auth'); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php _e('You haven\'t created any assessments yet.', 'lms-auth'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function institution_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your dashboard.', 'lms-auth') . '</p>';
        }
        
        if (!Roles::user_can_access_dashboard(null, 'institution')) {
            return '<p>' . __('Access denied.', 'lms-auth') . '</p>';
        }
        
        // Get analytics data
        $total_students = count(Roles::get_users_by_role('lms_student'));
        $total_instructors = count(Roles::get_users_by_role('lms_instructor'));
        $total_courses = wp_count_posts('lms_course')->publish;
        $total_assessments = wp_count_posts('lms_assessment')->publish;
        
        // Get recent activity
        $recent_analytics = Database::get_analytics_data(array(), 50);
        
        ob_start();
        ?>
        <div class="lms-institution-dashboard">
            <div class="dashboard-header">
                <h1><?php _e('Institution Dashboard', 'lms-auth'); ?></h1>
                <p><?php printf(__('Welcome back, %s!', 'lms-auth'), wp_get_current_user()->display_name); ?></p>
            </div>
            
            <div class="dashboard-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=lms_package'); ?>" class="btn btn-primary">
                    <?php _e('Create Package', 'lms-auth'); ?>
                </a>
                <a href="<?php echo admin_url('users.php'); ?>" class="btn btn-primary">
                    <?php _e('Manage Users', 'lms-auth'); ?>
                </a>
                <a href="#analytics" class="btn btn-primary show-analytics">
                    <?php _e('View Analytics', 'lms-auth'); ?>
                </a>
            </div>
            
            <div class="dashboard-content">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo $total_students; ?></h3>
                        <p><?php _e('Total Students', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $total_instructors; ?></h3>
                        <p><?php _e('Total Instructors', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $total_courses; ?></h3>
                        <p><?php _e('Total Courses', 'lms-auth'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $total_assessments; ?></h3>
                        <p><?php _e('Total Assessments', 'lms-auth'); ?></p>
                    </div>
                </div>
                
                <div class="analytics-section" id="analytics" style="display: none;">
                    <h2><?php _e('Analytics & Reports', 'lms-auth'); ?></h2>
                    
                    <div class="charts-container">
                        <div class="chart-item">
                            <h3><?php _e('Assessment Attempts per Day', 'lms-auth'); ?></h3>
                            <canvas id="attempts-chart"></canvas>
                        </div>
                        
                        <div class="chart-item">
                            <h3><?php _e('Assessment Results Distribution', 'lms-auth'); ?></h3>
                            <canvas id="results-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="export-section">
                        <h3><?php _e('Export Data', 'lms-auth'); ?></h3>
                        <button class="btn btn-secondary export-btn" data-type="students">
                            <?php _e('Export Students', 'lms-auth'); ?>
                        </button>
                        <button class="btn btn-secondary export-btn" data-type="results">
                            <?php _e('Export Results', 'lms-auth'); ?>
                        </button>
                        <button class="btn btn-secondary export-btn" data-type="analytics">
                            <?php _e('Export Analytics', 'lms-auth'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        jQuery(document).ready(function($) {
            $('.show-analytics').on('click', function(e) {
                e.preventDefault();
                $('#analytics').toggle();
                
                if ($('#analytics').is(':visible')) {
                    loadAnalyticsCharts();
                }
            });
            
            $('.export-btn').on('click', function() {
                var type = $(this).data('type');
                window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=lms_export_data&type=' + type + '&nonce=<?php echo wp_create_nonce('lms_export_nonce'); ?>';
            });
            
            function loadAnalyticsCharts() {
                // Load attempts per day chart
                $.post(ajaxurl, {
                    action: 'lms_get_analytics_data',
                    type: 'attempts_per_day',
                    nonce: lms_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        createAttemptsChart(response.data);
                    }
                });
                
                // Load results distribution chart
                $.post(ajaxurl, {
                    action: 'lms_get_analytics_data',
                    type: 'results_distribution',
                    nonce: lms_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        createResultsChart(response.data);
                    }
                });
            }
            
            function createAttemptsChart(data) {
                var ctx = document.getElementById('attempts-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Attempts',
                            data: data.values,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function createResultsChart(data) {
                var ctx = document.getElementById('results-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Passed', 'Failed', 'In Progress'],
                        datasets: [{
                            data: [data.passed, data.failed, data.in_progress],
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.2)',
                                'rgba(255, 99, 132, 0.2)',
                                'rgba(255, 205, 86, 0.2)'
                            ],
                            borderColor: [
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 205, 86, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    // Helper methods
    private function get_user_enrolled_courses($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_course_enrollments';
        $course_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM $table WHERE user_id = %d AND status = 'enrolled'",
            $user_id
        ));
        
        if (empty($course_ids)) {
            return array();
        }
        
        return get_posts(array(
            'post_type' => 'lms_course',
            'include' => $course_ids,
            'post_status' => 'publish'
        ));
    }
    
    private function calculate_average_score($results) {
        if (empty($results)) {
            return 0;
        }
        
        $total_score = 0;
        $count = 0;
        
        foreach ($results as $result) {
            if ($result->status === 'completed') {
                $total_score += $result->score;
                $count++;
            }
        }
        
        return $count > 0 ? round($total_score / $count, 1) : 0;
    }
    
    private function calculate_course_progress($user_id, $course_id) {
        global $wpdb;
        
        // Get all lessons for the course
        $lessons = get_posts(array(
            'post_type' => 'lms_lesson',
            'meta_query' => array(
                array(
                    'key' => '_lms_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        if (empty($lessons)) {
            return 0;
        }
        
        $total_lessons = count($lessons);
        $completed_lessons = 0;
        
        $table = $wpdb->prefix . 'lms_lesson_progress';
        foreach ($lessons as $lesson) {
            $progress = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM $table WHERE user_id = %d AND lesson_id = %d",
                $user_id, $lesson->ID
            ));
            
            if ($progress && $progress->status === 'completed') {
                $completed_lessons++;
            }
        }
        
        return round(($completed_lessons / $total_lessons) * 100);
    }
    
    private function count_enrolled_students($instructor_id) {
        global $wpdb;
        
        // Get instructor's courses
        $courses = get_posts(array(
            'post_type' => 'lms_course',
            'author' => $instructor_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        if (empty($courses)) {
            return 0;
        }
        
        $table = $wpdb->prefix . 'lms_course_enrollments';
        $placeholders = implode(',', array_fill(0, count($courses), '%d'));
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table WHERE course_id IN ($placeholders) AND status = 'enrolled'",
            $courses
        ));
    }
    
    private function count_course_enrollments($course_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_course_enrollments';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE course_id = %d AND status = 'enrolled'",
            $course_id
        ));
    }
    
    private function count_assessment_attempts($assessment_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_assessment_results';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE assessment_id = %d",
            $assessment_id
        ));
    }
}

