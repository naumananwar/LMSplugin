<?php

namespace LMS_Auth\Admin;

use LMS_Auth\Core\Database;
use LMS_Auth\Core\Roles;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Menu {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_lms_get_analytics_data', array($this, 'handle_analytics_ajax'));
        add_action('wp_ajax_lms_export_data', array($this, 'handle_export_data'));
        add_action('wp_ajax_lms_generate_ai_questions', array($this, 'handle_ai_questions'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('LMS System', 'lms-auth'),
            __('LMS System', 'lms-auth'),
            'manage_options',
            'lms-system',
            array($this, 'dashboard_page'),
            'dashicons-graduation-cap',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'lms-system',
            __('Dashboard', 'lms-auth'),
            __('Dashboard', 'lms-auth'),
            'manage_options',
            'lms-system',
            array($this, 'dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'lms-system',
            __('Settings', 'lms-auth'),
            __('Settings', 'lms-auth'),
            'manage_options',
            'lms-settings',
            array($this, 'settings_page')
        );
        
        // Analytics submenu
        add_submenu_page(
            'lms-system',
            __('Analytics', 'lms-auth'),
            __('Analytics', 'lms-auth'),
            'lms_view_analytics',
            'lms-analytics',
            array($this, 'analytics_page')
        );
        
        // Users submenu
        add_submenu_page(
            'lms-system',
            __('LMS Users', 'lms-auth'),
            __('LMS Users', 'lms-auth'),
            'lms_manage_students',
            'lms-users',
            array($this, 'users_page')
        );
        
        // Reports submenu
        add_submenu_page(
            'lms-system',
            __('Reports', 'lms-auth'),
            __('Reports', 'lms-auth'),
            'lms_view_analytics',
            'lms-reports',
            array($this, 'reports_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'lms-') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script('lms-admin', LMS_AUTH_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'chart-js'), LMS_AUTH_VERSION, true);
        wp_enqueue_style('lms-admin', LMS_AUTH_PLUGIN_URL . 'assets/css/admin.css', array(), LMS_AUTH_VERSION);
        
        wp_localize_script('lms-admin', 'lms_admin_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lms_admin_nonce')
        ));
    }
    
    public function dashboard_page() {
        // Get statistics
        $total_students = count(Roles::get_users_by_role('lms_student'));
        $total_instructors = count(Roles::get_users_by_role('lms_instructor'));
        $total_institutions = count(Roles::get_users_by_role('lms_institution'));
        
        $total_courses = wp_count_posts('lms_course')->publish;
        $total_assessments = wp_count_posts('lms_assessment')->publish;
        $total_packages = wp_count_posts('lms_package')->publish;
        
        // Get recent activities
        $recent_users = get_users(array(
            'meta_key' => 'lms_role',
            'number' => 10,
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        $recent_assessments = Database::get_assessment_results(null, null, 10);
        
        ?>
        <div class="wrap">
            <h1><?php _e('LMS System Dashboard', 'lms-auth'); ?></h1>
            
            <div class="lms-dashboard-widgets">
                <div class="lms-stats-grid">
                    <div class="lms-stat-widget">
                        <div class="stat-icon"><span class="dashicons dashicons-groups"></span></div>
                        <div class="stat-content">
                            <h3><?php echo $total_students; ?></h3>
                            <p><?php _e('Total Students', 'lms-auth'); ?></p>
                        </div>
                    </div>
                    
                    <div class="lms-stat-widget">
                        <div class="stat-icon"><span class="dashicons dashicons-businessman"></span></div>
                        <div class="stat-content">
                            <h3><?php echo $total_instructors; ?></h3>
                            <p><?php _e('Total Instructors', 'lms-auth'); ?></p>
                        </div>
                    </div>
                    
                    <div class="lms-stat-widget">
                        <div class="stat-icon"><span class="dashicons dashicons-building"></span></div>
                        <div class="stat-content">
                            <h3><?php echo $total_institutions; ?></h3>
                            <p><?php _e('Total Institutions', 'lms-auth'); ?></p>
                        </div>
                    </div>
                    
                    <div class="lms-stat-widget">
                        <div class="stat-icon"><span class="dashicons dashicons-book-alt"></span></div>
                        <div class="stat-content">
                            <h3><?php echo $total_courses; ?></h3>
                            <p><?php _e('Total Courses', 'lms-auth'); ?></p>
                        </div>
                    </div>
                    
                    <div class="lms-stat-widget">
                        <div class="stat-icon"><span class="dashicons dashicons-clipboard"></span></div>
                        <div class="stat-content">
                            <h3><?php echo $total_assessments; ?></h3>
                            <p><?php _e('Total Assessments', 'lms-auth'); ?></p>
                        </div>
                    </div>
                    
                    <div class="lms-stat-widget">
                        <div class="stat-icon"><span class="dashicons dashicons-cart"></span></div>
                        <div class="stat-content">
                            <h3><?php echo $total_packages; ?></h3>
                            <p><?php _e('Total Packages', 'lms-auth'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="lms-dashboard-content">
                    <div class="lms-recent-activity">
                        <h2><?php _e('Recent Activities', 'lms-auth'); ?></h2>
                        
                        <div class="activity-section">
                            <h3><?php _e('Recent Registrations', 'lms-auth'); ?></h3>
                            <ul class="activity-list">
                                <?php if (!empty($recent_users)): ?>
                                    <?php foreach ($recent_users as $user): ?>
                                        <li>
                                            <strong><?php echo esc_html($user->display_name); ?></strong>
                                            <span class="user-role"><?php echo ucfirst(str_replace('lms_', '', $user->roles[0] ?? 'user')); ?></span>
                                            <span class="activity-time"><?php echo human_time_diff(strtotime($user->user_registered), current_time('timestamp')); ?> ago</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><?php _e('No recent registrations', 'lms-auth'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="activity-section">
                            <h3><?php _e('Recent Assessment Results', 'lms-auth'); ?></h3>
                            <ul class="activity-list">
                                <?php if (!empty($recent_assessments)): ?>
                                    <?php foreach ($recent_assessments as $result): 
                                        $user = get_userdata($result->user_id);
                                        $assessment = get_post($result->assessment_id);
                                    ?>
                                        <li>
                                            <strong><?php echo $user ? esc_html($user->display_name) : 'Unknown User'; ?></strong>
                                            completed
                                            <em><?php echo $assessment ? esc_html($assessment->post_title) : 'Unknown Assessment'; ?></em>
                                            with score <?php echo number_format($result->score, 1); ?>%
                                            <span class="activity-time"><?php echo human_time_diff(strtotime($result->created_at), current_time('timestamp')); ?> ago</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><?php _e('No recent assessment results', 'lms-auth'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="lms-quick-actions">
                        <h2><?php _e('Quick Actions', 'lms-auth'); ?></h2>
                        <div class="quick-actions-grid">
                            <a href="<?php echo admin_url('post-new.php?post_type=lms_course'); ?>" class="quick-action-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Create Course', 'lms-auth'); ?>
                            </a>
                            <a href="<?php echo admin_url('post-new.php?post_type=lms_assessment'); ?>" class="quick-action-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Create Assessment', 'lms-auth'); ?>
                            </a>
                            <a href="<?php echo admin_url('post-new.php?post_type=lms_package'); ?>" class="quick-action-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Create Package', 'lms-auth'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=lms-analytics'); ?>" class="quick-action-btn">
                                <span class="dashicons dashicons-chart-area"></span>
                                <?php _e('View Analytics', 'lms-auth'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=lms-users'); ?>" class="quick-action-btn">
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php _e('Manage Users', 'lms-auth'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=lms-settings'); ?>" class="quick-action-btn">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php _e('Settings', 'lms-auth'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('lms_settings_nonce');
            
            $settings = array(
                'lms_auth_social_login_google' => sanitize_text_field($_POST['google_client_id'] ?? ''),
                'lms_auth_social_login_facebook' => sanitize_text_field($_POST['facebook_app_id'] ?? ''),
                'lms_auth_social_login_facebook_secret' => sanitize_text_field($_POST['facebook_app_secret'] ?? ''),
                'lms_auth_social_login_apple' => sanitize_text_field($_POST['apple_service_id'] ?? ''),
                'lms_auth_openai_api_key' => sanitize_text_field($_POST['openai_api_key'] ?? ''),
                'lms_auth_stripe_public_key' => sanitize_text_field($_POST['stripe_public_key'] ?? ''),
                'lms_auth_stripe_secret_key' => sanitize_text_field($_POST['stripe_secret_key'] ?? ''),
                'lms_auth_paypal_client_id' => sanitize_text_field($_POST['paypal_client_id'] ?? ''),
                'lms_auth_paypal_client_secret' => sanitize_text_field($_POST['paypal_client_secret'] ?? '')
            );
            
            foreach ($settings as $key => $value) {
                update_option($key, $value);
            }
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'lms-auth') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('LMS Settings', 'lms-auth'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('lms_settings_nonce'); ?>
                
                <div class="lms-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#social-login" class="nav-tab nav-tab-active"><?php _e('Social Login', 'lms-auth'); ?></a>
                        <a href="#payment-gateways" class="nav-tab"><?php _e('Payment Gateways', 'lms-auth'); ?></a>
                        <a href="#openai" class="nav-tab"><?php _e('OpenAI Integration', 'lms-auth'); ?></a>
                        <a href="#general" class="nav-tab"><?php _e('General', 'lms-auth'); ?></a>
                    </nav>
                    
                    <div id="social-login" class="tab-content active">
                        <h2><?php _e('Social Login Configuration', 'lms-auth'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Google Client ID', 'lms-auth'); ?></th>
                                <td>
                                    <input type="text" name="google_client_id" value="<?php echo esc_attr(get_option('lms_auth_social_login_google')); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Get this from Google Cloud Console', 'lms-auth'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Facebook App ID', 'lms-auth'); ?></th>
                                <td>
                                    <input type="text" name="facebook_app_id" value="<?php echo esc_attr(get_option('lms_auth_social_login_facebook')); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Get this from Facebook Developers', 'lms-auth'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Facebook App Secret', 'lms-auth'); ?></th>
                                <td>
                                    <input type="password" name="facebook_app_secret" value="<?php echo esc_attr(get_option('lms_auth_social_login_facebook_secret')); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Facebook App Secret Key', 'lms-auth'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Apple Service ID', 'lms-auth'); ?></th>
                                <td>
                                    <input type="text" name="apple_service_id" value="<?php echo esc_attr(get_option('lms_auth_social_login_apple')); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Get this from Apple Developer Console', 'lms-auth'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="payment-gateways" class="tab-content">
                        <h2><?php _e('Payment Gateway Configuration', 'lms-auth'); ?></h2>
                        
                        <h3><?php _e('Stripe Settings', 'lms-auth'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Stripe Public Key', 'lms-auth'); ?></th>
                                <td>
                                    <input type="text" name="stripe_public_key" value="<?php echo esc_attr(get_option('lms_auth_stripe_public_key')); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Stripe Secret Key', 'lms-auth'); ?></th>
                                <td>
                                    <input type="password" name="stripe_secret_key" value="<?php echo esc_attr(get_option('lms_auth_stripe_secret_key')); ?>" class="regular-text" />
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('PayPal Settings', 'lms-auth'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('PayPal Client ID', 'lms-auth'); ?></th>
                                <td>
                                    <input type="text" name="paypal_client_id" value="<?php echo esc_attr(get_option('lms_auth_paypal_client_id')); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('PayPal Client Secret', 'lms-auth'); ?></th>
                                <td>
                                    <input type="password" name="paypal_client_secret" value="<?php echo esc_attr(get_option('lms_auth_paypal_client_secret')); ?>" class="regular-text" />
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="openai" class="tab-content">
                        <h2><?php _e('OpenAI Configuration', 'lms-auth'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('OpenAI API Key', 'lms-auth'); ?></th>
                                <td>
                                    <input type="password" name="openai_api_key" value="<?php echo esc_attr(get_option('lms_auth_openai_api_key')); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Used for AI-powered question generation', 'lms-auth'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="test-openai-section">
                            <h3><?php _e('Test OpenAI Connection', 'lms-auth'); ?></h3>
                            <button type="button" id="test-openai" class="button"><?php _e('Test Connection', 'lms-auth'); ?></button>
                            <div id="openai-test-result"></div>
                        </div>
                    </div>
                    
                    <div id="general" class="tab-content">
                        <h2><?php _e('General Settings', 'lms-auth'); ?></h2>
                        <p><?php _e('Additional general settings will be added here.', 'lms-auth'); ?></p>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                
                $(this).addClass('nav-tab-active');
                $($(this).attr('href')).addClass('active');
            });
            
            // Test OpenAI connection
            $('#test-openai').click(function() {
                var $button = $(this);
                var $result = $('#openai-test-result');
                
                $button.prop('disabled', true).text('Testing...');
                
                $.post(ajaxurl, {
                    action: 'lms_test_openai',
                    api_key: $('input[name="openai_api_key"]').val(),
                    nonce: lms_admin_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>Connection successful!</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>Connection failed: ' + response.data + '</p></div>');
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('Test Connection');
                });
            });
        });
        </script>
        <?php
    }
    
    public function analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('LMS Analytics', 'lms-auth'); ?></h1>
            
            <div class="lms-analytics-dashboard">
                <div class="analytics-filters">
                    <label for="date-range"><?php _e('Date Range:', 'lms-auth'); ?></label>
                    <select id="date-range">
                        <option value="7"><?php _e('Last 7 days', 'lms-auth'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'lms-auth'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'lms-auth'); ?></option>
                    </select>
                    <button id="refresh-analytics" class="button"><?php _e('Refresh', 'lms-auth'); ?></button>
                </div>
                
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h3><?php _e('Assessment Attempts per Day', 'lms-auth'); ?></h3>
                        <canvas id="attempts-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Results Distribution', 'lms-auth'); ?></h3>
                        <canvas id="results-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('User Registrations', 'lms-auth'); ?></h3>
                        <canvas id="registrations-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Course Enrollments', 'lms-auth'); ?></h3>
                        <canvas id="enrollments-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            loadAnalytics();
            
            $('#refresh-analytics').click(function() {
                loadAnalytics();
            });
            
            function loadAnalytics() {
                var dateRange = $('#date-range').val();
                
                // Load attempts chart
                $.post(ajaxurl, {
                    action: 'lms_get_analytics_data',
                    type: 'attempts_per_day',
                    date_range: dateRange,
                    nonce: lms_admin_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        createAttemptsChart(response.data);
                    }
                });
                
                // Load other charts...
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
        });
        </script>
        <?php
    }
    
    public function users_page() {
        $current_tab = $_GET['tab'] ?? 'students';
        
        ?>
        <div class="wrap">
            <h1><?php _e('LMS Users Management', 'lms-auth'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=lms-users&tab=students" class="nav-tab <?php echo $current_tab === 'students' ? 'nav-tab-active' : ''; ?>"><?php _e('Students', 'lms-auth'); ?></a>
                <a href="?page=lms-users&tab=instructors" class="nav-tab <?php echo $current_tab === 'instructors' ? 'nav-tab-active' : ''; ?>"><?php _e('Instructors', 'lms-auth'); ?></a>
                <a href="?page=lms-users&tab=institutions" class="nav-tab <?php echo $current_tab === 'institutions' ? 'nav-tab-active' : ''; ?>"><?php _e('Institutions', 'lms-auth'); ?></a>
            </nav>
            
            <div class="lms-users-content">
                <?php
                switch ($current_tab) {
                    case 'students':
                        $this->render_users_table('lms_student');
                        break;
                    case 'instructors':
                        $this->render_users_table('lms_instructor');
                        break;
                    case 'institutions':
                        $this->render_users_table('lms_institution');
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_users_table($role) {
        $users = Roles::get_users_by_role($role);
        
        ?>
        <div class="users-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'lms-auth'); ?></th>
                        <th><?php _e('Email', 'lms-auth'); ?></th>
                        <th><?php _e('Registered', 'lms-auth'); ?></th>
                        <th><?php _e('Last Login', 'lms-auth'); ?></th>
                        <th><?php _e('Status', 'lms-auth'); ?></th>
                        <th><?php _e('Actions', 'lms-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small><?php echo esc_html($user->user_login); ?></small>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                                <td>
                                    <?php 
                                    $last_login = get_user_meta($user->ID, 'last_login', true);
                                    echo $last_login ? human_time_diff($last_login, current_time('timestamp')) . ' ago' : 'Never';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $subscription = Database::get_user_subscription($user->ID);
                                    if ($subscription) {
                                        echo '<span class="status-active">Active</span>';
                                    } else {
                                        echo '<span class="status-inactive">No Subscription</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button button-small"><?php _e('Edit', 'lms-auth'); ?></a>
                                    <button class="button button-small view-user-details" data-user-id="<?php echo $user->ID; ?>"><?php _e('Details', 'lms-auth'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php _e('No users found.', 'lms-auth'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function reports_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('LMS Reports', 'lms-auth'); ?></h1>
            
            <div class="lms-reports-container">
                <div class="report-actions">
                    <h2><?php _e('Generate Reports', 'lms-auth'); ?></h2>
                    
                    <div class="report-buttons">
                        <button class="button button-primary export-btn" data-type="students">
                            <?php _e('Export Students Report', 'lms-auth'); ?>
                        </button>
                        <button class="button button-primary export-btn" data-type="instructors">
                            <?php _e('Export Instructors Report', 'lms-auth'); ?>
                        </button>
                        <button class="button button-primary export-btn" data-type="assessments">
                            <?php _e('Export Assessment Results', 'lms-auth'); ?>
                        </button>
                        <button class="button button-primary export-btn" data-type="courses">
                            <?php _e('Export Course Enrollments', 'lms-auth'); ?>
                        </button>
                        <button class="button button-primary export-btn" data-type="analytics">
                            <?php _e('Export Analytics Data', 'lms-auth'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="recent-exports">
                    <h2><?php _e('Export History', 'lms-auth'); ?></h2>
                    <p><?php _e('Recent exports will be listed here.', 'lms-auth'); ?></p>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.export-btn').click(function() {
                var type = $(this).data('type');
                var $btn = $(this);
                
                $btn.prop('disabled', true).text('Generating...');
                
                window.location.href = ajaxurl + '?action=lms_export_data&type=' + type + '&nonce=' + lms_admin_ajax.nonce;
                
                setTimeout(function() {
                    $btn.prop('disabled', false).text($btn.text().replace('Generating...', ''));
                }, 2000);
            });
        });
        </script>
        <?php
    }
    
    public function handle_analytics_ajax() {
        check_ajax_referer('lms_admin_nonce', 'nonce');
        
        $type = sanitize_text_field($_POST['type']);
        $date_range = intval($_POST['date_range'] ?? 30);
        
        switch ($type) {
            case 'attempts_per_day':
                $data = $this->get_attempts_per_day_data($date_range);
                break;
            case 'results_distribution':
                $data = $this->get_results_distribution_data();
                break;
            default:
                wp_send_json_error('Invalid analytics type');
        }
        
        wp_send_json_success($data);
    }
    
    private function get_attempts_per_day_data($days) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_assessment_results';
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$table} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            $date_from
        ));
        
        $labels = array();
        $values = array();
        
        foreach ($results as $result) {
            $labels[] = date('M j', strtotime($result->date));
            $values[] = intval($result->count);
        }
        
        return array(
            'labels' => $labels,
            'values' => $values
        );
    }
    
    private function get_results_distribution_data() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_assessment_results';
        
        $results = $wpdb->get_row(
            "SELECT 
                SUM(CASE WHEN status = 'completed' AND score >= 70 THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN status = 'completed' AND score < 70 THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
             FROM {$table}"
        );
        
        return array(
            'passed' => intval($results->passed),
            'failed' => intval($results->failed),
            'in_progress' => intval($results->in_progress)
        );
    }
    
    public function handle_export_data() {
        check_ajax_referer('lms_admin_nonce', 'nonce');
        
        if (!current_user_can('lms_export_data')) {
            wp_die('Insufficient permissions');
        }
        
        $type = sanitize_text_field($_GET['type']);
        
        switch ($type) {
            case 'students':
                $this->export_students_data();
                break;
            case 'instructors':
                $this->export_instructors_data();
                break;
            case 'assessments':
                $this->export_assessments_data();
                break;
            case 'courses':
                $this->export_courses_data();
                break;
            case 'analytics':
                $this->export_analytics_data();
                break;
            default:
                wp_die('Invalid export type');
        }
    }
    
    private function export_students_data() {
        $students = Roles::get_users_by_role('lms_student');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, array(
            'ID', 'Username', 'Display Name', 'Email', 'Registered Date', 
            'Last Login', 'Courses Enrolled', 'Assessments Completed', 'Average Score'
        ));
        
        foreach ($students as $student) {
            $last_login = get_user_meta($student->ID, 'last_login', true);
            $enrolled_courses = count($this->get_user_enrolled_courses($student->ID));
            $assessment_results = Database::get_assessment_results($student->ID);
            $completed_assessments = count(array_filter($assessment_results, function($result) {
                return $result->status === 'completed';
            }));
            $average_score = $this->calculate_average_score($assessment_results);
            
            fputcsv($output, array(
                $student->ID,
                $student->user_login,
                $student->display_name,
                $student->user_email,
                $student->user_registered,
                $last_login ? date('Y-m-d H:i:s', $last_login) : 'Never',
                $enrolled_courses,
                $completed_assessments,
                $average_score . '%'
            ));
        }
        
        fclose($output);
        exit;
    }
    
    private function export_assessments_data() {
        $results = Database::get_assessment_results();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="assessment_results_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, array(
            'Result ID', 'User ID', 'Username', 'Assessment ID', 'Assessment Title',
            'Score', 'Total Questions', 'Correct Answers', 'Status', 'Time Started',
            'Time Completed', 'Time Spent (minutes)', 'Attempts'
        ));
        
        foreach ($results as $result) {
            $user = get_userdata($result->user_id);
            $assessment = get_post($result->assessment_id);
            
            fputcsv($output, array(
                $result->id,
                $result->user_id,
                $user ? $user->user_login : 'Unknown',
                $result->assessment_id,
                $assessment ? $assessment->post_title : 'Unknown',
                $result->score,
                $result->total_questions,
                $result->correct_answers,
                $result->status,
                $result->time_started,
                $result->time_completed,
                round($result->time_spent / 60, 2),
                $result->attempts
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // Additional helper methods
    private function get_user_enrolled_courses($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_course_enrollments';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT course_id FROM {$table} WHERE user_id = %d AND status = 'enrolled'",
            $user_id
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
    
    // Add more export methods as needed...
    private function export_instructors_data() {
        // Similar to students export
    }
    
    private function export_courses_data() {
        // Export course enrollment data
    }
    
    private function export_analytics_data() {
        // Export analytics data
    }
}

