<?php
/**
 * Registration Template
 */

if (is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_POST['submit'])) {
    $username = sanitize_text_field($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_text_field($_POST['role']);
    $institution_id = sanitize_text_field($_POST['institution_id']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = __('All fields are required.', 'lms-authentication-system');
    } elseif ($password !== $confirm_password) {
        $error_message = __('Passwords do not match.', 'lms-authentication-system');
    } elseif (username_exists($username)) {
        $error_message = __('Username already exists.', 'lms-authentication-system');
    } elseif (email_exists($email)) {
        $error_message = __('Email already exists.', 'lms-authentication-system');
    } else {
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $error_message = $user_id->get_error_message();
        } else {
            // Set user role and meta
            $user = new WP_User($user_id);
            $user->set_role($role);
            
            if ($institution_id) {
                update_user_meta($user_id, 'institution_id', $institution_id);
            }
            
            // Send welcome email
            wp_new_user_notification($user_id, null, 'both');
            
            $success_message = __('Registration successful! Please check your email for login details.', 'lms-authentication-system');
        }
    }
}

// Get institutions for dropdown
global $wpdb;
$institutions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lms_institutions WHERE status = 'active' ORDER BY name");
?>

<div class="lms-auth-container">
    <div class="lms-auth-form">
        <h2><?php _e('Register', 'lms-authentication-system'); ?></h2>
        
        <?php if ($error_message): ?>
            <div class="lms-error"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="lms-success"><?php echo esc_html($success_message); ?></div>
        <?php else: ?>
            <form method="post" action="">
                <div class="form-group">
                    <label for="username"><?php _e('Username', 'lms-authentication-system'); ?></label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><?php _e('Email', 'lms-authentication-system'); ?></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><?php _e('Password', 'lms-authentication-system'); ?></label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?php _e('Confirm Password', 'lms-authentication-system'); ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="role"><?php _e('Role', 'lms-authentication-system'); ?></label>
                    <select id="role" name="role" required>
                        <option value=""><?php _e('Select Role', 'lms-authentication-system'); ?></option>
                        <option value="student"><?php _e('Student', 'lms-authentication-system'); ?></option>
                        <option value="instructor"><?php _e('Instructor', 'lms-authentication-system'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="institution_id"><?php _e('Institution', 'lms-authentication-system'); ?></label>
                    <select id="institution_id" name="institution_id">
                        <option value=""><?php _e('Select Institution', 'lms-authentication-system'); ?></option>
                        <?php foreach ($institutions as $institution): ?>
                            <option value="<?php echo esc_attr($institution->id); ?>"><?php echo esc_html($institution->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <input type="submit" name="submit" value="<?php _e('Register', 'lms-authentication-system'); ?>" class="lms-btn lms-btn-primary">
                </div>
            </form>
        <?php endif; ?>
        
        <div class="lms-auth-links">
            <a href="<?php echo wp_login_url(); ?>"><?php _e('Already have an account? Login', 'lms-authentication-system'); ?></a>
        </div>
    </div>
</div>

