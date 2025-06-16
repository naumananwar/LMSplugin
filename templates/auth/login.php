<?php
/**
 * Login Template
 */

if (is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

$error_message = '';
if (isset($_POST['submit'])) {
    $username = sanitize_text_field($_POST['username']);
    $password = $_POST['password'];
    
    $creds = array(
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => isset($_POST['remember'])
    );
    
    $user = wp_signon($creds, false);
    
    if (is_wp_error($user)) {
        $error_message = $user->get_error_message();
    } else {
        $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : home_url();
        wp_redirect($redirect_to);
        exit;
    }
}
?>

<div class="lms-auth-container">
    <div class="lms-auth-form">
        <h2><?php _e('Login', 'lms-authentication-system'); ?></h2>
        
        <?php if ($error_message): ?>
            <div class="lms-error"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="username"><?php _e('Username or Email', 'lms-authentication-system'); ?></label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?php _e('Password', 'lms-authentication-system'); ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember" value="1">
                    <?php _e('Remember Me', 'lms-authentication-system'); ?>
                </label>
            </div>
            
            <div class="form-group">
                <input type="submit" name="submit" value="<?php _e('Login', 'lms-authentication-system'); ?>" class="lms-btn lms-btn-primary">
            </div>
            
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr(isset($_GET['redirect_to']) ? $_GET['redirect_to'] : ''); ?>">
        </form>
        
        <div class="lms-auth-links">
            <a href="<?php echo wp_registration_url(); ?>"><?php _e('Register', 'lms-authentication-system'); ?></a>
            <a href="<?php echo wp_lostpassword_url(); ?>"><?php _e('Forgot Password?', 'lms-authentication-system'); ?></a>
        </div>
    </div>
</div>

