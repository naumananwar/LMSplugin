<?php
/**
 * Forgot Password Template
 */

if (is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_POST['submit'])) {
    $email = sanitize_email($_POST['email']);
    
    if (empty($email)) {
        $error_message = __('Email is required.', 'lms-authentication-system');
    } elseif (!is_email($email)) {
        $error_message = __('Please enter a valid email address.', 'lms-authentication-system');
    } else {
        $user = get_user_by('email', $email);
        
        if (!$user) {
            $error_message = __('No user found with this email address.', 'lms-authentication-system');
        } else {
            // Generate password reset key
            $reset_key = get_password_reset_key($user);
            
            if (is_wp_error($reset_key)) {
                $error_message = $reset_key->get_error_message();
            } else {
                // Send password reset email
                $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
                
                $subject = __('Password Reset Request', 'lms-authentication-system');
                $message = sprintf(
                    __('Hi %s,\n\nYou have requested to reset your password. Please click the link below to reset your password:\n\n%s\n\nIf you did not request this, please ignore this email.\n\nThanks!', 'lms-authentication-system'),
                    $user->display_name,
                    $reset_url
                );
                
                $sent = wp_mail($email, $subject, $message);
                
                if ($sent) {
                    $success_message = __('Password reset email sent! Please check your email.', 'lms-authentication-system');
                } else {
                    $error_message = __('Failed to send password reset email. Please try again.', 'lms-authentication-system');
                }
            }
        }
    }
}
?>

<div class="lms-auth-container">
    <div class="lms-auth-form">
        <h2><?php _e('Forgot Password', 'lms-authentication-system'); ?></h2>
        
        <?php if ($error_message): ?>
            <div class="lms-error"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="lms-success"><?php echo esc_html($success_message); ?></div>
        <?php else: ?>
            <form method="post" action="">
                <div class="form-group">
                    <label for="email"><?php _e('Email Address', 'lms-authentication-system'); ?></label>
                    <input type="email" id="email" name="email" required>
                    <small><?php _e('Enter your email address to receive password reset instructions.', 'lms-authentication-system'); ?></small>
                </div>
                
                <div class="form-group">
                    <input type="submit" name="submit" value="<?php _e('Send Reset Email', 'lms-authentication-system'); ?>" class="lms-btn lms-btn-primary">
                </div>
            </form>
        <?php endif; ?>
        
        <div class="lms-auth-links">
            <a href="<?php echo wp_login_url(); ?>"><?php _e('Back to Login', 'lms-authentication-system'); ?></a>
            <a href="<?php echo wp_registration_url(); ?>"><?php _e('Register', 'lms-authentication-system'); ?></a>
        </div>
    </div>
</div>

