<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Authentication {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_login', array($this, 'handle_login'), 10, 2);
        add_action('user_register', array($this, 'handle_registration'));
        add_action('wp_logout', array($this, 'handle_logout'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_social_scripts'));
        add_action('wp_ajax_lms_social_login', array($this, 'handle_social_login'));
        add_action('wp_ajax_nopriv_lms_social_login', array($this, 'handle_social_login'));
        add_action('wp_ajax_lms_register_user', array($this, 'handle_ajax_registration'));
        add_action('wp_ajax_nopriv_lms_register_user', array($this, 'handle_ajax_registration'));
        add_action('wp_ajax_lms_login_user', array($this, 'handle_ajax_login'));
        add_action('wp_ajax_nopriv_lms_login_user', array($this, 'handle_ajax_login'));
    }
    
    public function handle_login($user_login, $user) {
        // Log analytics
        Database::log_analytics('user_login', $user->ID, null, 'user');
        
        // Redirect based on role
        if (!wp_doing_ajax()) {
            Roles::redirect_after_login($user->ID);
        }
    }
    
    public function handle_registration($user_id) {
        // Assign default role if none assigned
        $user = get_userdata($user_id);
        if (!$user || empty($user->roles)) {
            Roles::assign_role_to_user($user_id, 'lms_student');
        }
        
        // Log analytics
        Database::log_analytics('user_registration', $user_id, null, 'user');
    }
    
    public function handle_logout() {
        $user_id = get_current_user_id();
        if ($user_id) {
            Database::log_analytics('user_logout', $user_id, null, 'user');
        }
    }
    
    public function enqueue_social_scripts() {
        if (is_page('lms-login') || is_page('lms-register')) {
            // Google Sign-In
            $google_client_id = get_option('lms_auth_social_login_google');
            if ($google_client_id) {
                wp_enqueue_script('google-signin', 'https://apis.google.com/js/platform.js', array(), null, true);
                wp_add_inline_script('google-signin', '
                    window.onload = function() {
                        gapi.load("auth2", function() {
                            gapi.auth2.init({
                                client_id: "' . esc_js($google_client_id) . '"
                            });
                        });
                    };
                ');
            }
            
            // Facebook SDK
            $facebook_app_id = get_option('lms_auth_social_login_facebook');
            if ($facebook_app_id) {
                wp_enqueue_script('facebook-sdk', 'https://connect.facebook.net/en_US/sdk.js', array(), null, true);
                wp_add_inline_script('facebook-sdk', '
                    window.fbAsyncInit = function() {
                        FB.init({
                            appId: "' . esc_js($facebook_app_id) . '",
                            cookie: true,
                            xfbml: true,
                            version: "v18.0"
                        });
                    };
                ');
            }
            
            // Apple Sign-In (requires server-side setup)
            $apple_service_id = get_option('lms_auth_social_login_apple');
            if ($apple_service_id) {
                wp_enqueue_script('apple-signin', 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js', array(), null, true);
            }
        }
    }
    
    public function handle_social_login() {
        check_ajax_referer('lms_auth_nonce', 'nonce');
        
        $provider = sanitize_text_field($_POST['provider']);
        $token = sanitize_text_field($_POST['token']);
        $user_data = array();
        
        switch ($provider) {
            case 'google':
                $user_data = $this->verify_google_token($token);
                break;
            case 'facebook':
                $user_data = $this->verify_facebook_token($token);
                break;
            case 'apple':
                $user_data = $this->verify_apple_token($token);
                break;
            default:
                wp_die('Invalid provider');
        }
        
        if (!$user_data || !isset($user_data['email'])) {
            wp_send_json_error('Invalid token or user data');
        }
        
        // Check if user exists
        $user = get_user_by('email', $user_data['email']);
        
        if (!$user) {
            // Create new user
            $username = $this->generate_username($user_data['email']);
            $password = wp_generate_password(12, false);
            
            $user_id = wp_create_user($username, $password, $user_data['email']);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error('Failed to create user');
            }
            
            // Update user meta
            if (isset($user_data['first_name'])) {
                update_user_meta($user_id, 'first_name', $user_data['first_name']);
            }
            if (isset($user_data['last_name'])) {
                update_user_meta($user_id, 'last_name', $user_data['last_name']);
            }
            
            // Assign default role
            Roles::assign_role_to_user($user_id, 'lms_student');
            
            $user = get_userdata($user_id);
        }
        
        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        // Get redirect URL
        $redirect_url = Roles::get_dashboard_url($user->ID);
        
        // Check subscription
        $subscription = Database::get_user_subscription($user->ID);
        if (!$subscription) {
            $packages_page = get_page_by_path('subscription-packages');
            if ($packages_page) {
                $redirect_url = get_permalink($packages_page->ID);
            }
        }
        
        wp_send_json_success(array(
            'redirect_url' => $redirect_url
        ));
    }
    
    private function verify_google_token($token) {
        $google_client_id = get_option('lms_auth_social_login_google');
        
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $token);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || $data['aud'] !== $google_client_id) {
            return false;
        }
        
        return array(
            'email' => $data['email'],
            'first_name' => $data['given_name'] ?? '',
            'last_name' => $data['family_name'] ?? ''
        );
    }
    
    private function verify_facebook_token($token) {
        $facebook_app_id = get_option('lms_auth_social_login_facebook');
        $facebook_app_secret = get_option('lms_auth_social_login_facebook_secret');
        
        // Verify token
        $verify_url = 'https://graph.facebook.com/debug_token?input_token=' . $token . '&access_token=' . $facebook_app_id . '|' . $facebook_app_secret;
        $verify_response = wp_remote_get($verify_url);
        
        if (is_wp_error($verify_response)) {
            return false;
        }
        
        $verify_data = json_decode(wp_remote_retrieve_body($verify_response), true);
        
        if (!$verify_data['data']['is_valid']) {
            return false;
        }
        
        // Get user data
        $user_url = 'https://graph.facebook.com/me?fields=email,first_name,last_name&access_token=' . $token;
        $user_response = wp_remote_get($user_url);
        
        if (is_wp_error($user_response)) {
            return false;
        }
        
        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);
        
        return array(
            'email' => $user_data['email'] ?? '',
            'first_name' => $user_data['first_name'] ?? '',
            'last_name' => $user_data['last_name'] ?? ''
        );
    }
    
    private function verify_apple_token($token) {
        // Apple Sign-In verification is more complex and requires JWT verification
        // This is a simplified version - in production, you'd need proper JWT library
        
        $apple_team_id = get_option('lms_auth_social_login_apple_team_id');
        $apple_key_id = get_option('lms_auth_social_login_apple_key_id');
        
        // For now, return false - this needs proper implementation with JWT verification
        return false;
    }
    
    private function generate_username($email) {
        $username = sanitize_user(current(explode('@', $email)), true);
        
        // Make sure username is unique
        $i = 1;
        $original_username = $username;
        while (username_exists($username)) {
            $username = $original_username . $i;
            $i++;
        }
        
        return $username;
    }
    
    public function handle_ajax_registration() {
        check_ajax_referer('lms_auth_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize_text_field($_POST['role'] ?? 'lms_student');
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        
        // Validate inputs
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error('All fields are required');
        }
        
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        if (username_exists($username)) {
            wp_send_json_error('Username already exists');
        }
        
        if (email_exists($email)) {
            wp_send_json_error('Email already exists');
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error('Password must be at least 6 characters');
        }
        
        // Validate role
        $allowed_roles = array('lms_student', 'lms_instructor', 'lms_institution');
        if (!in_array($role, $allowed_roles)) {
            $role = 'lms_student';
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error('Failed to create user: ' . $user_id->get_error_message());
        }
        
        // Set user meta
        if ($first_name) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        if ($last_name) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
        
        // Assign role
        Roles::assign_role_to_user($user_id, $role);
        
        // Log user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Get redirect URL
        $packages_page = get_page_by_path('subscription-packages');
        $redirect_url = $packages_page ? get_permalink($packages_page->ID) : home_url();
        
        wp_send_json_success(array(
            'message' => 'Registration successful',
            'redirect_url' => $redirect_url
        ));
    }
    
    public function handle_ajax_login() {
        check_ajax_referer('lms_auth_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Username and password are required');
        }
        
        // Attempt login
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            wp_send_json_error('Invalid username or password');
        }
        
        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        
        // Get redirect URL based on subscription and role
        $subscription = Database::get_user_subscription($user->ID);
        
        if (!$subscription) {
            $packages_page = get_page_by_path('subscription-packages');
            $redirect_url = $packages_page ? get_permalink($packages_page->ID) : home_url();
        } else {
            $redirect_url = Roles::get_dashboard_url($user->ID);
        }
        
        wp_send_json_success(array(
            'message' => 'Login successful',
            'redirect_url' => $redirect_url
        ));
    }
    
    public static function generate_login_form() {
        ob_start();
        ?>
        <div id="lms-login-form" class="lms-auth-form">
            <h2><?php _e('Login', 'lms-auth'); ?></h2>
            
            <form id="lms-login-form-element" method="post">
                <?php wp_nonce_field('lms_auth_nonce', 'nonce'); ?>
                
                <div class="form-group">
                    <label for="username"><?php _e('Username or Email', 'lms-auth'); ?></label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><?php _e('Password', 'lms-auth'); ?></label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="remember" value="true">
                        <?php _e('Remember Me', 'lms-auth'); ?>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary"><?php _e('Login', 'lms-auth'); ?></button>
            </form>
            
            <div class="social-login">
                <?php if (get_option('lms_auth_social_login_google')): ?>
                    <button id="google-login" class="btn btn-google"><?php _e('Login with Google', 'lms-auth'); ?></button>
                <?php endif; ?>
                
                <?php if (get_option('lms_auth_social_login_facebook')): ?>
                    <button id="facebook-login" class="btn btn-facebook"><?php _e('Login with Facebook', 'lms-auth'); ?></button>
                <?php endif; ?>
                
                <?php if (get_option('lms_auth_social_login_apple')): ?>
                    <button id="apple-login" class="btn btn-apple"><?php _e('Login with Apple', 'lms-auth'); ?></button>
                <?php endif; ?>
            </div>
            
            <p class="auth-links">
                <a href="<?php echo get_permalink(get_page_by_path('lms-register')); ?>"><?php _e('Don\'t have an account? Register', 'lms-auth'); ?></a>
            </p>
            
            <div id="lms-login-messages"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#lms-login-form-element').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=lms_login_user';
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        $('#lms-login-messages').html('<div class="alert alert-success">' + response.data.message + '</div>');
                        window.location.href = response.data.redirect_url;
                    } else {
                        $('#lms-login-messages').html('<div class="alert alert-error">' + response.data + '</div>');
                    }
                });
            });
            
            // Google Sign-In
            $('#google-login').on('click', function() {
                var auth2 = gapi.auth2.getAuthInstance();
                auth2.signIn().then(function(googleUser) {
                    var id_token = googleUser.getAuthResponse().id_token;
                    
                    $.post(ajaxurl, {
                        action: 'lms_social_login',
                        provider: 'google',
                        token: id_token,
                        nonce: $('[name="nonce"]').val()
                    }, function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            $('#lms-login-messages').html('<div class="alert alert-error">' + response.data + '</div>');
                        }
                    });
                });
            });
            
            // Facebook Login
            $('#facebook-login').on('click', function() {
                FB.login(function(response) {
                    if (response.status === 'connected') {
                        $.post(ajaxurl, {
                            action: 'lms_social_login',
                            provider: 'facebook',
                            token: response.authResponse.accessToken,
                            nonce: $('[name="nonce"]').val()
                        }, function(response) {
                            if (response.success) {
                                window.location.href = response.data.redirect_url;
                            } else {
                                $('#lms-login-messages').html('<div class="alert alert-error">' + response.data + '</div>');
                            }
                        });
                    }
                }, {scope: 'email'});
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public static function generate_register_form() {
        ob_start();
        ?>
        <div id="lms-register-form" class="lms-auth-form">
            <h2><?php _e('Register', 'lms-auth'); ?></h2>
            
            <form id="lms-register-form-element" method="post">
                <?php wp_nonce_field('lms_auth_nonce', 'nonce'); ?>
                
                <div class="form-group">
                    <label for="first_name"><?php _e('First Name', 'lms-auth'); ?></label>
                    <input type="text" id="first_name" name="first_name">
                </div>
                
                <div class="form-group">
                    <label for="last_name"><?php _e('Last Name', 'lms-auth'); ?></label>
                    <input type="text" id="last_name" name="last_name">
                </div>
                
                <div class="form-group">
                    <label for="username"><?php _e('Username', 'lms-auth'); ?></label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><?php _e('Email', 'lms-auth'); ?></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><?php _e('Password', 'lms-auth'); ?></label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="role"><?php _e('Account Type', 'lms-auth'); ?></label>
                    <select id="role" name="role">
                        <option value="lms_student"><?php _e('Student', 'lms-auth'); ?></option>
                        <option value="lms_instructor"><?php _e('Instructor', 'lms-auth'); ?></option>
                        <option value="lms_institution"><?php _e('Institution', 'lms-auth'); ?></option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary"><?php _e('Register', 'lms-auth'); ?></button>
            </form>
            
            <div class="social-login">
                <?php if (get_option('lms_auth_social_login_google')): ?>
                    <button id="google-register" class="btn btn-google"><?php _e('Register with Google', 'lms-auth'); ?></button>
                <?php endif; ?>
                
                <?php if (get_option('lms_auth_social_login_facebook')): ?>
                    <button id="facebook-register" class="btn btn-facebook"><?php _e('Register with Facebook', 'lms-auth'); ?></button>
                <?php endif; ?>
                
                <?php if (get_option('lms_auth_social_login_apple')): ?>
                    <button id="apple-register" class="btn btn-apple"><?php _e('Register with Apple', 'lms-auth'); ?></button>
                <?php endif; ?>
            </div>
            
            <p class="auth-links">
                <a href="<?php echo get_permalink(get_page_by_path('lms-login')); ?>"><?php _e('Already have an account? Login', 'lms-auth'); ?></a>
            </p>
            
            <div id="lms-register-messages"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#lms-register-form-element').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=lms_register_user';
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        $('#lms-register-messages').html('<div class="alert alert-success">' + response.data.message + '</div>');
                        window.location.href = response.data.redirect_url;
                    } else {
                        $('#lms-register-messages').html('<div class="alert alert-error">' + response.data + '</div>');
                    }
                });
            });
            
            // Social registration (similar to login)
            $('#google-register, #facebook-register').on('click', function() {
                // Same as login handlers
                var provider = $(this).attr('id').replace('-register', '').replace('-login', '');
                // Trigger same social login flow
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

