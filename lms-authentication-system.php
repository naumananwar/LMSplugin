<?php
/**
 * Plugin Name: LMS Authentication System
 * Plugin URI: https://your-domain.com/lms-authentication-system
 * Description: Complete Learning Management System with Authentication, Assessment, and Course Management
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: lms-auth
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LMS_AUTH_PLUGIN_FILE', __FILE__);
define('LMS_AUTH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LMS_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LMS_AUTH_VERSION', '1.0.0');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'LMS_Auth\\';
    $base_dir = LMS_AUTH_PLUGIN_PATH . 'includes/classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
class LMS_Authentication_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('lms-auth', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize core classes
        $this->init_core_classes();
        
        // Initialize admin
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            $this->init_frontend();
        }
    }
    
    private function init_core_classes() {
        // Initialize core functionality
        LMS_Auth\Core\Database::get_instance();
        LMS_Auth\Core\Roles::get_instance();
        LMS_Auth\Core\Post_Types::get_instance();
        LMS_Auth\Core\Authentication::get_instance();
        LMS_Auth\Core\Assessment_System::get_instance();
        LMS_Auth\Core\Course_System::get_instance();
        LMS_Auth\Core\Payment_System::get_instance();
        LMS_Auth\Core\OpenAI_Integration::get_instance();
    }
    
    private function init_admin() {
        LMS_Auth\Admin\Admin_Menu::get_instance();
        LMS_Auth\Admin\Settings::get_instance();
    }
    
    private function init_frontend() {
        LMS_Auth\Frontend\Dashboard_Router::get_instance();
        LMS_Auth\Frontend\Shortcodes::get_instance();
        LMS_Auth\Frontend\AJAX_Handler::get_instance();
    }
    
    public function activate() {
        // Create database tables
        LMS_Auth\Core\Database::create_tables();
        
        // Create custom roles
        LMS_Auth\Core\Roles::create_roles();
        
        // Create pages
        $this->create_pages();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_pages() {
        $pages = array(
            'login' => array(
                'title' => 'Login',
                'content' => '[lms_login_form]',
                'slug' => 'lms-login'
            ),
            'register' => array(
                'title' => 'Register',
                'content' => '[lms_register_form]',
                'slug' => 'lms-register'
            ),
            'packages' => array(
                'title' => 'Subscription Packages',
                'content' => '[lms_packages]',
                'slug' => 'subscription-packages'
            ),
            'student_dashboard' => array(
                'title' => 'Student Dashboard',
                'content' => '[lms_student_dashboard]',
                'slug' => 'student-dashboard'
            ),
            'instructor_dashboard' => array(
                'title' => 'Instructor Dashboard',
                'content' => '[lms_instructor_dashboard]',
                'slug' => 'instructor-dashboard'
            ),
            'institution_dashboard' => array(
                'title' => 'Institution Dashboard',
                'content' => '[lms_institution_dashboard]',
                'slug' => 'institution-dashboard'
            )
        );
        
        foreach ($pages as $key => $page) {
            $existing_page = get_page_by_path($page['slug']);
            if (!$existing_page) {
                wp_insert_post(array(
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_name' => $page['slug'],
                    'post_status' => 'publish',
                    'post_type' => 'page'
                ));
            }
        }
    }
    
    private function set_default_options() {
        $defaults = array(
            'lms_auth_social_login_google' => '',
            'lms_auth_social_login_facebook' => '',
            'lms_auth_social_login_apple' => '',
            'lms_auth_openai_api_key' => '',
            'lms_auth_stripe_public_key' => '',
            'lms_auth_stripe_secret_key' => '',
            'lms_auth_paypal_client_id' => '',
            'lms_auth_paypal_client_secret' => ''
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}

// Initialize the plugin
LMS_Authentication_System::get_instance();

