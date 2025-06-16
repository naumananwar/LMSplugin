<?php
/**
 * Student Profile Template
 */

// Ensure user is logged in and is a student
if (!is_user_logged_in() || !current_user_can('student')) {
    wp_redirect(wp_login_url());
    exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;

$error_message = '';
$success_message = '';

// Handle form submission
if (isset($_POST['update_profile'])) {
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $bio = sanitize_textarea_field($_POST['bio']);
    $institution_id = sanitize_text_field($_POST['institution_id']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = __('First name, last name, and email are required.', 'lms-authentication-system');
    } elseif (!is_email($email)) {
        $error_message = __('Please enter a valid email address.', 'lms-authentication-system');
    } else {
        // Check if email exists for another user
        $email_exists = email_exists($email);
        if ($email_exists && $email_exists != $user_id) {
            $error_message = __('This email is already used by another user.', 'lms-authentication-system');
        } else {
            // Update user data
            $user_data = array(
                'ID' => $user_id,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name
            );
            
            $result = wp_update_user($user_data);
            
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                // Update meta fields
                update_user_meta($user_id, 'phone', $phone);
                update_user_meta($user_id, 'description', $bio);
                update_user_meta($user_id, 'institution_id', $institution_id);
                
                $success_message = __('Profile updated successfully!', 'lms-authentication-system');
                
                // Refresh user object
                $user = wp_get_current_user();
            }
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = __('All password fields are required.', 'lms-authentication-system');
    } elseif ($new_password !== $confirm_password) {
        $error_message = __('New passwords do not match.', 'lms-authentication-system');
    } elseif (!wp_check_password($current_password, $user->user_pass, $user_id)) {
        $error_message = __('Current password is incorrect.', 'lms-authentication-system');
    } else {
        wp_set_password($new_password, $user_id);
        $success_message = __('Password changed successfully!', 'lms-authentication-system');
    }
}

// Get user meta
$phone = get_user_meta($user_id, 'phone', true);
$bio = get_user_meta($user_id, 'description', true);
$institution_id = get_user_meta($user_id, 'institution_id', true);

// Get institutions for dropdown
global $wpdb;
$institutions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lms_institutions WHERE status = 'active' ORDER BY name");
?>

<div class="lms-student-profile">
    <div class="profile-header">
        <h1><?php _e('My Profile', 'lms-authentication-system'); ?></h1>
    </div>
    
    <?php if ($error_message): ?>
        <div class="lms-error"><?php echo esc_html($error_message); ?></div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="lms-success"><?php echo esc_html($success_message); ?></div>
    <?php endif; ?>
    
    <div class="profile-tabs">
        <div class="tab-nav">
            <button class="tab-btn active" data-tab="personal"><?php _e('Personal Information', 'lms-authentication-system'); ?></button>
            <button class="tab-btn" data-tab="password"><?php _e('Change Password', 'lms-authentication-system'); ?></button>
            <button class="tab-btn" data-tab="preferences"><?php _e('Preferences', 'lms-authentication-system'); ?></button>
        </div>
        
        <div class="tab-content">
            <!-- Personal Information Tab -->
            <div id="personal-tab" class="tab-panel active">
                <form method="post" action="" class="profile-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name"><?php _e('First Name', 'lms-authentication-system'); ?></label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name"><?php _e('Last Name', 'lms-authentication-system'); ?></label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><?php _e('Email Address', 'lms-authentication-system'); ?></label>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone"><?php _e('Phone Number', 'lms-authentication-system'); ?></label>
                            <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($phone); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="institution_id"><?php _e('Institution', 'lms-authentication-system'); ?></label>
                            <select id="institution_id" name="institution_id">
                                <option value=""><?php _e('Select Institution', 'lms-authentication-system'); ?></option>
                                <?php foreach ($institutions as $institution): ?>
                                    <option value="<?php echo esc_attr($institution->id); ?>" <?php selected($institution_id, $institution->id); ?>>
                                        <?php echo esc_html($institution->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="bio"><?php _e('Bio', 'lms-authentication-system'); ?></label>
                            <textarea id="bio" name="bio" rows="4" placeholder="<?php _e('Tell us about yourself...', 'lms-authentication-system'); ?>"><?php echo esc_textarea($bio); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <input type="submit" name="update_profile" value="<?php _e('Update Profile', 'lms-authentication-system'); ?>" class="btn btn-primary">
                    </div>
                </form>
            </div>
            
            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-panel">
                <form method="post" action="" class="password-form">
                    <div class="form-group">
                        <label for="current_password"><?php _e('Current Password', 'lms-authentication-system'); ?></label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password"><?php _e('New Password', 'lms-authentication-system'); ?></label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><?php _e('Confirm New Password', 'lms-authentication-system'); ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <input type="submit" name="change_password" value="<?php _e('Change Password', 'lms-authentication-system'); ?>" class="btn btn-primary">
                    </div>
                </form>
            </div>
            
            <!-- Preferences Tab -->
            <div id="preferences-tab" class="tab-panel">
                <form method="post" action="" class="preferences-form">
                    <h3><?php _e('Notification Preferences', 'lms-authentication-system'); ?></h3>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="email_notifications" value="1" checked>
                            <?php _e('Email notifications for new assignments', 'lms-authentication-system'); ?>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="deadline_reminders" value="1" checked>
                            <?php _e('Deadline reminders', 'lms-authentication-system'); ?>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="course_updates" value="1" checked>
                            <?php _e('Course updates and announcements', 'lms-authentication-system'); ?>
                        </label>
                    </div>
                    
                    <h3><?php _e('Display Preferences', 'lms-authentication-system'); ?></h3>
                    
                    <div class="form-group">
                        <label for="timezone"><?php _e('Timezone', 'lms-authentication-system'); ?></label>
                        <select id="timezone" name="timezone">
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">Eastern Time</option>
                            <option value="America/Chicago">Central Time</option>
                            <option value="America/Denver">Mountain Time</option>
                            <option value="America/Los_Angeles">Pacific Time</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <input type="submit" name="update_preferences" value="<?php _e('Save Preferences', 'lms-authentication-system'); ?>" class="btn btn-primary">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and panels
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding panel
            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });
});
</script>

