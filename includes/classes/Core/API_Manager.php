<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class API_Manager {
    
    private static $instance = null;
    private $namespace = 'lms-auth/v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_authentication_errors', array($this, 'authenticate_request'));
    }
    
    public function register_routes() {
        // Authentication endpoints
        register_rest_route($this->namespace, '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_login'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array('required' => true, 'type' => 'string'),
                'password' => array('required' => true, 'type' => 'string')
            )
        ));
        
        register_rest_route($this->namespace, '/auth/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_registration'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array('required' => true, 'type' => 'string'),
                'email' => array('required' => true, 'type' => 'string'),
                'password' => array('required' => true, 'type' => 'string'),
                'role' => array('required' => false, 'type' => 'string', 'default' => 'lms_student')
            )
        ));
        
        // Course endpoints
        register_rest_route($this->namespace, '/courses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_courses'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/courses/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_course'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        // Assessment endpoints
        register_rest_route($this->namespace, '/assessments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_assessments'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/assessments/(?P<id>\d+)/start', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_assessment'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        register_rest_route($this->namespace, '/assessments/(?P<id>\d+)/submit', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_assessment'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer'),
                'answers' => array('required' => true, 'type' => 'object')
            )
        ));
        
        // User progress endpoints
        register_rest_route($this->namespace, '/progress/courses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_course_progress'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route($this->namespace, '/progress/assessments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_assessment_progress'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Analytics endpoints (admin only)
        register_rest_route($this->namespace, '/analytics/overview', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_analytics_overview'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
    }
    
    /**
     * Handle API login
     */
    public function handle_login($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        
        // Rate limiting
        $security = Security_Manager::get_instance();
        if (!$security->check_rate_limit('api', $username)) {
            return new \WP_Error('rate_limit', 'Too many requests', array('status' => 429));
        }
        
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new \WP_Error('invalid_credentials', 'Invalid username or password', array('status' => 401));
        }
        
        // Generate API token
        $token = $this->generate_api_token($user->ID);
        
        return array(
            'success' => true,
            'data' => array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'role' => $user->roles[0] ?? '',
                'token' => $token,
                'expires' => time() + (24 * 60 * 60) // 24 hours
            )
        );
    }
    
    /**
     * Handle API registration
     */
    public function handle_registration($request) {
        $username = $request->get_param('username');
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $role = $request->get_param('role');
        
        // Validation
        if (username_exists($username)) {
            return new \WP_Error('username_exists', 'Username already exists', array('status' => 400));
        }
        
        if (email_exists($email)) {
            return new \WP_Error('email_exists', 'Email already exists', array('status' => 400));
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Assign role
        $user = new \WP_User($user_id);
        $user->set_role($role);
        
        // Generate API token
        $token = $this->generate_api_token($user_id);
        
        return array(
            'success' => true,
            'data' => array(
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'token' => $token
            )
        );
    }
    
    /**
     * Get courses
     */
    public function get_courses($request) {
        $user_id = $this->get_current_user_id();
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        
        $args = array(
            'post_type' => 'lms_course',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page
        );
        
        $courses = get_posts($args);
        $formatted_courses = array();
        
        foreach ($courses as $course) {
            $formatted_courses[] = array(
                'id' => $course->ID,
                'title' => $course->post_title,
                'description' => $course->post_excerpt,
                'content' => $course->post_content,
                'instructor' => get_the_author_meta('display_name', $course->post_author),
                'created_date' => $course->post_date,
                'meta' => array(
                    'price' => get_post_meta($course->ID, '_lms_course_price', true),
                    'duration' => get_post_meta($course->ID, '_lms_course_duration', true),
                    'difficulty' => get_post_meta($course->ID, '_lms_course_difficulty', true)
                )
            );
        }
        
        return array(
            'success' => true,
            'data' => $formatted_courses,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => wp_count_posts('lms_course')->publish
            )
        );
    }
    
    /**
     * Get single course
     */
    public function get_course($request) {
        $course_id = $request->get_param('id');
        $course = get_post($course_id);
        
        if (!$course || $course->post_type !== 'lms_course') {
            return new \WP_Error('course_not_found', 'Course not found', array('status' => 404));
        }
        
        // Get course lessons
        $lessons = get_posts(array(
            'post_type' => 'lms_lesson',
            'meta_query' => array(
                array(
                    'key' => '_lms_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'posts_per_page' => -1
        ));
        
        $formatted_lessons = array();
        foreach ($lessons as $lesson) {
            $formatted_lessons[] = array(
                'id' => $lesson->ID,
                'title' => $lesson->post_title,
                'content' => $lesson->post_content,
                'type' => get_post_meta($lesson->ID, '_lms_lesson_type', true),
                'duration' => get_post_meta($lesson->ID, '_lms_lesson_duration', true),
                'video_url' => get_post_meta($lesson->ID, '_lms_lesson_video_url', true),
                'is_preview' => get_post_meta($lesson->ID, '_lms_lesson_is_preview', true)
            );
        }
        
        return array(
            'success' => true,
            'data' => array(
                'id' => $course->ID,
                'title' => $course->post_title,
                'description' => $course->post_excerpt,
                'content' => $course->post_content,
                'instructor' => get_the_author_meta('display_name', $course->post_author),
                'lessons' => $formatted_lessons,
                'meta' => array(
                    'price' => get_post_meta($course->ID, '_lms_course_price', true),
                    'duration' => get_post_meta($course->ID, '_lms_course_duration', true),
                    'difficulty' => get_post_meta($course->ID, '_lms_course_difficulty', true)
                )
            )
        );
    }
    
    /**
     * Start assessment
     */
    public function start_assessment($request) {
        $assessment_id = $request->get_param('id');
        $user_id = $this->get_current_user_id();
        
        // Validate assessment
        $assessment = get_post($assessment_id);
        if (!$assessment || $assessment->post_type !== 'lms_assessment') {
            return new \WP_Error('assessment_not_found', 'Assessment not found', array('status' => 404));
        }
        
        // Check permissions and attempts
        $max_attempts = get_post_meta($assessment_id, '_lms_assessment_max_attempts', true) ?: 3;
        $user_attempts = Database::get_assessment_results($user_id, $assessment_id);
        
        if (count($user_attempts) >= $max_attempts) {
            return new \WP_Error('max_attempts_reached', 'Maximum attempts reached', array('status' => 403));
        }
        
        // Create assessment attempt
        $attempt_data = array(
            'user_id' => $user_id,
            'assessment_id' => $assessment_id,
            'status' => 'in_progress',
            'time_started' => current_time('mysql'),
            'attempts' => count($user_attempts) + 1
        );
        
        $attempt_id = Database::save_assessment_result($attempt_data);
        
        // Get questions (without correct answers)
        $questions = get_post_meta($assessment_id, '_lms_assessment_questions', true) ?: array();
        $formatted_questions = array();
        
        foreach ($questions as $index => $question) {
            $formatted_questions[] = array(
                'index' => $index,
                'type' => $question['type'],
                'question' => $question['question'],
                'options' => $question['options'] ?? array(),
                'points' => $question['points'] ?? 1
            );
        }
        
        return array(
            'success' => true,
            'data' => array(
                'attempt_id' => $attempt_id,
                'assessment' => array(
                    'id' => $assessment_id,
                    'title' => $assessment->post_title,
                    'time_limit' => get_post_meta($assessment_id, '_lms_assessment_total_time', true),
                    'questions' => $formatted_questions
                )
            )
        );
    }
    
    /**
     * Check permissions
     */
    public function check_permissions($request) {
        $user_id = $this->get_current_user_id();
        return $user_id > 0;
    }
    
    /**
     * Check admin permissions
     */
    public function check_admin_permissions($request) {
        $user_id = $this->get_current_user_id();
        return $user_id > 0 && user_can($user_id, 'manage_options');
    }
    
    /**
     * Authenticate API request
     */
    public function authenticate_request($result) {
        if (!empty($result)) {
            return $result;
        }
        
        $token = $this->get_auth_token();
        if (!$token) {
            return $result;
        }
        
        $user_id = $this->validate_token($token);
        if (!$user_id) {
            return new \WP_Error('invalid_token', 'Invalid or expired token', array('status' => 401));
        }
        
        wp_set_current_user($user_id);
        return true;
    }
    
    /**
     * Generate API token
     */
    private function generate_api_token($user_id) {
        $security = Security_Manager::get_instance();
        $token_data = array(
            'user_id' => $user_id,
            'issued_at' => time(),
            'expires_at' => time() + (24 * 60 * 60) // 24 hours
        );
        
        $token = base64_encode(json_encode($token_data));
        
        // Store token hash for validation
        $token_hash = hash('sha256', $token);
        update_user_meta($user_id, '_lms_api_token_hash', $token_hash);
        update_user_meta($user_id, '_lms_api_token_expires', $token_data['expires_at']);
        
        return $token;
    }
    
    /**
     * Validate API token
     */
    private function validate_token($token) {
        $token_data = json_decode(base64_decode($token), true);
        
        if (!$token_data || !isset($token_data['user_id'], $token_data['expires_at'])) {
            return false;
        }
        
        // Check expiration
        if ($token_data['expires_at'] < time()) {
            return false;
        }
        
        // Validate token hash
        $user_id = $token_data['user_id'];
        $stored_hash = get_user_meta($user_id, '_lms_api_token_hash', true);
        $token_hash = hash('sha256', $token);
        
        if (!hash_equals($stored_hash, $token_hash)) {
            return false;
        }
        
        return $user_id;
    }
    
    /**
     * Get auth token from request
     */
    private function get_auth_token() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
            if (strpos($auth_header, 'Bearer ') === 0) {
                return substr($auth_header, 7);
            }
        }
        
        return $_GET['token'] ?? $_POST['token'] ?? null;
    }
    
    /**
     * Get current user ID from token
     */
    private function get_current_user_id() {
        $token = $this->get_auth_token();
        if (!$token) {
            return 0;
        }
        
        return $this->validate_token($token) ?: 0;
    }
}