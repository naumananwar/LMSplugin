<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Security_Manager {
    
    private static $instance = null;
    private $rate_limits = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_security'));
        add_action('wp_login_failed', array($this, 'handle_failed_login'));
        add_filter('authenticate', array($this, 'check_login_attempts'), 30, 3);
    }
    
    public function init_security() {
        // Initialize rate limiting
        $this->rate_limits = array(
            'login' => array('limit' => 5, 'window' => 900), // 5 attempts per 15 minutes
            'assessment' => array('limit' => 10, 'window' => 3600), // 10 attempts per hour
            'api' => array('limit' => 100, 'window' => 3600) // 100 API calls per hour
        );
    }
    
    /**
     * Check rate limits
     */
    public function check_rate_limit($action, $identifier = null) {
        if (!isset($this->rate_limits[$action])) {
            return true;
        }
        
        $identifier = $identifier ?: $this->get_client_ip();
        $cache_key = "rate_limit_{$action}_{$identifier}";
        
        $attempts = get_transient($cache_key) ?: 0;
        $limit = $this->rate_limits[$action]['limit'];
        
        if ($attempts >= $limit) {
            return false;
        }
        
        // Increment counter
        set_transient($cache_key, $attempts + 1, $this->rate_limits[$action]['window']);
        return true;
    }
    
    /**
     * Handle failed login attempts
     */
    public function handle_failed_login($username) {
        $ip = $this->get_client_ip();
        $cache_key = "failed_login_{$ip}";
        
        $attempts = get_transient($cache_key) ?: 0;
        set_transient($cache_key, $attempts + 1, 900); // 15 minutes
        
        // Log security event
        $this->log_security_event('failed_login', array(
            'username' => $username,
            'ip' => $ip,
            'attempts' => $attempts + 1
        ));
        
        // Block IP after 5 failed attempts
        if ($attempts >= 4) {
            $this->block_ip($ip, 3600); // Block for 1 hour
        }
    }
    
    /**
     * Check login attempts before authentication
     */
    public function check_login_attempts($user, $username, $password) {
        $ip = $this->get_client_ip();
        
        // Check if IP is blocked
        if ($this->is_ip_blocked($ip)) {
            return new \WP_Error('ip_blocked', __('Too many failed login attempts. Please try again later.', 'lms-auth'));
        }
        
        // Check rate limit
        if (!$this->check_rate_limit('login', $ip)) {
            return new \WP_Error('rate_limit_exceeded', __('Too many login attempts. Please try again later.', 'lms-auth'));
        }
        
        return $user;
    }
    
    /**
     * Validate assessment submission security
     */
    public function validate_assessment_submission($user_id, $assessment_id, $data) {
        // Check if user is enrolled
        $enrollment = Database::get_course_enrollment($user_id, $assessment_id);
        if (!$enrollment) {
            return new \WP_Error('not_enrolled', __('You are not enrolled in this assessment.', 'lms-auth'));
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit('assessment', $user_id)) {
            return new \WP_Error('rate_limit', __('Too many assessment attempts. Please wait before trying again.', 'lms-auth'));
        }
        
        // Validate data integrity
        if (!$this->validate_data_integrity($data)) {
            return new \WP_Error('invalid_data', __('Invalid submission data detected.', 'lms-auth'));
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate user input
     */
    public function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'html':
                return wp_kses_post($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'int':
                return intval($data);
            case 'float':
                return floatval($data);
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Generate secure token
     */
    public function generate_secure_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data($data, $key = null) {
        if (!$key) {
            $key = $this->get_encryption_key();
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt_data($encrypted_data, $key = null) {
        if (!$key) {
            $key = $this->get_encryption_key();
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Block IP address
     */
    private function block_ip($ip, $duration = 3600) {
        set_transient("blocked_ip_{$ip}", true, $duration);
        
        $this->log_security_event('ip_blocked', array(
            'ip' => $ip,
            'duration' => $duration
        ));
    }
    
    /**
     * Check if IP is blocked
     */
    private function is_ip_blocked($ip) {
        return get_transient("blocked_ip_{$ip}") !== false;
    }
    
    /**
     * Validate data integrity
     */
    private function validate_data_integrity($data) {
        // Check for common attack patterns
        $dangerous_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is'
        );
        
        $data_string = is_array($data) ? serialize($data) : $data;
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $data_string)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get encryption key
     */
    private function get_encryption_key() {
        $key = get_option('lms_auth_encryption_key');
        
        if (!$key) {
            $key = $this->generate_secure_token(64);
            update_option('lms_auth_encryption_key', $key);
        }
        
        return $key;
    }
    
    /**
     * Log security events
     */
    private function log_security_event($event_type, $data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_security_logs';
        
        // Create table if it doesn't exist
        $this->maybe_create_security_table();
        
        $wpdb->insert($table, array(
            'event_type' => $event_type,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => json_encode($data),
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Create security logs table
     */
    private function maybe_create_security_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_security_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text,
                data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY ip_address (ip_address),
                KEY created_at (created_at)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}