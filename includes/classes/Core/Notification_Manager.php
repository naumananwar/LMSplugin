<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Notification_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('lms_assessment_completed', array($this, 'handle_assessment_completion'), 10, 3);
        add_action('lms_course_enrolled', array($this, 'handle_course_enrollment'), 10, 2);
        add_action('lms_assignment_submitted', array($this, 'handle_assignment_submission'), 10, 3);
        add_action('wp_ajax_lms_mark_notification_read', array($this, 'mark_notification_read'));
    }
    
    /**
     * Send notification
     */
    public function send_notification($user_id, $type, $title, $message, $data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_notifications';
        $this->maybe_create_notifications_table();
        
        $notification_data = array(
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data),
            'is_read' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $notification_data);
        
        if ($result) {
            $notification_id = $wpdb->insert_id;
            
            // Send email if user has email notifications enabled
            if ($this->should_send_email($user_id, $type)) {
                $this->send_email_notification($user_id, $title, $message, $data);
            }
            
            // Send push notification if enabled
            if ($this->should_send_push($user_id, $type)) {
                $this->send_push_notification($user_id, $title, $message, $data);
            }
            
            return $notification_id;
        }
        
        return false;
    }
    
    /**
     * Get user notifications
     */
    public function get_user_notifications($user_id, $limit = 20, $unread_only = false) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_notifications';
        $where = array("user_id = %d");
        $values = array($user_id);
        
        if ($unread_only) {
            $where[] = "is_read = 0";
        }
        
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT %d";
        $values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get unread notification count
     */
    public function get_unread_count($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_notifications';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read() {
        check_ajax_referer('lms_notification_nonce', 'nonce');
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'lms_notifications';
        
        $result = $wpdb->update(
            $table,
            array('is_read' => 1),
            array('id' => $notification_id, 'user_id' => $user_id)
        );
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to mark notification as read');
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function mark_all_read($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_notifications';
        return $wpdb->update(
            $table,
            array('is_read' => 1),
            array('user_id' => $user_id)
        );
    }
    
    /**
     * Handle assessment completion
     */
    public function handle_assessment_completion($user_id, $assessment_id, $result) {
        $assessment = get_post($assessment_id);
        $passed = $result['status'] === 'completed';
        
        $title = $passed ? 
            __('Assessment Passed!', 'lms-auth') : 
            __('Assessment Completed', 'lms-auth');
            
        $message = sprintf(
            __('You have completed the assessment "%s" with a score of %.1f%%.', 'lms-auth'),
            $assessment->post_title,
            $result['score']
        );
        
        $this->send_notification($user_id, 'assessment_completed', $title, $message, array(
            'assessment_id' => $assessment_id,
            'score' => $result['score'],
            'passed' => $passed
        ));
        
        // Notify instructor
        $instructor_id = $assessment->post_author;
        if ($instructor_id && $instructor_id != $user_id) {
            $user = get_userdata($user_id);
            $instructor_message = sprintf(
                __('%s has completed the assessment "%s" with a score of %.1f%%.', 'lms-auth'),
                $user->display_name,
                $assessment->post_title,
                $result['score']
            );
            
            $this->send_notification($instructor_id, 'student_assessment_completed', 
                __('Student Assessment Completed', 'lms-auth'), $instructor_message, array(
                'student_id' => $user_id,
                'assessment_id' => $assessment_id,
                'score' => $result['score']
            ));
        }
    }
    
    /**
     * Handle course enrollment
     */
    public function handle_course_enrollment($user_id, $course_id) {
        $course = get_post($course_id);
        
        $title = __('Course Enrollment Confirmed', 'lms-auth');
        $message = sprintf(
            __('You have been successfully enrolled in the course "%s". You can now access all course materials.', 'lms-auth'),
            $course->post_title
        );
        
        $this->send_notification($user_id, 'course_enrolled', $title, $message, array(
            'course_id' => $course_id
        ));
        
        // Notify instructor
        $instructor_id = $course->post_author;
        if ($instructor_id && $instructor_id != $user_id) {
            $user = get_userdata($user_id);
            $instructor_message = sprintf(
                __('%s has enrolled in your course "%s".', 'lms-auth'),
                $user->display_name,
                $course->post_title
            );
            
            $this->send_notification($instructor_id, 'new_enrollment', 
                __('New Course Enrollment', 'lms-auth'), $instructor_message, array(
                'student_id' => $user_id,
                'course_id' => $course_id
            ));
        }
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($user_id, $title, $message, $data = array()) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $subject = get_bloginfo('name') . ' - ' . $title;
        
        $email_template = $this->get_email_template($title, $message, $data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($user->user_email, $subject, $email_template, $headers);
    }
    
    /**
     * Send push notification
     */
    private function send_push_notification($user_id, $title, $message, $data = array()) {
        // Implementation for push notifications (Firebase, OneSignal, etc.)
        // This would require additional setup and API keys
        
        $push_token = get_user_meta($user_id, '_lms_push_token', true);
        if (!$push_token) {
            return false;
        }
        
        // Example implementation for Firebase Cloud Messaging
        $fcm_api_key = get_option('lms_auth_fcm_api_key');
        if (!$fcm_api_key) {
            return false;
        }
        
        $notification_data = array(
            'to' => $push_token,
            'notification' => array(
                'title' => $title,
                'body' => $message,
                'icon' => get_site_icon_url(),
                'click_action' => home_url('/dashboard')
            ),
            'data' => $data
        );
        
        $headers = array(
            'Authorization: key=' . $fcm_api_key,
            'Content-Type: application/json'
        );
        
        $response = wp_remote_post('https://fcm.googleapis.com/fcm/send', array(
            'headers' => $headers,
            'body' => json_encode($notification_data),
            'timeout' => 30
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * Check if user should receive email notifications
     */
    private function should_send_email($user_id, $type) {
        $email_notifications = get_user_meta($user_id, '_lms_email_notifications', true);
        
        if ($email_notifications === '') {
            return true; // Default to enabled
        }
        
        $enabled_types = is_array($email_notifications) ? $email_notifications : array();
        return in_array($type, $enabled_types) || in_array('all', $enabled_types);
    }
    
    /**
     * Check if user should receive push notifications
     */
    private function should_send_push($user_id, $type) {
        $push_notifications = get_user_meta($user_id, '_lms_push_notifications', true);
        
        if ($push_notifications === '') {
            return false; // Default to disabled
        }
        
        $enabled_types = is_array($push_notifications) ? $push_notifications : array();
        return in_array($type, $enabled_types) || in_array('all', $enabled_types);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($title, $message, $data = array()) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4299e1; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px 20px; background: #f8f9fa; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 14px; }
                .button { display: inline-block; padding: 12px 24px; background: #4299e1; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($title); ?></h1>
                </div>
                <div class="content">
                    <p><?php echo nl2br(esc_html($message)); ?></p>
                    
                    <?php if (isset($data['course_id']) || isset($data['assessment_id'])): ?>
                        <p>
                            <a href="<?php echo home_url('/dashboard'); ?>" class="button">
                                View Dashboard
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="footer">
                    <p>This email was sent from <?php echo get_bloginfo('name'); ?></p>
                    <p>
                        <a href="<?php echo home_url('/dashboard/settings'); ?>">Manage notification preferences</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Create notifications table
     */
    private function maybe_create_notifications_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_notifications';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                message text NOT NULL,
                data longtext,
                is_read tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY type (type),
                KEY is_read (is_read),
                KEY created_at (created_at)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Clean up old notifications
     */
    public function cleanup_old_notifications($days = 90) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_notifications';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s AND is_read = 1",
            $cutoff_date
        ));
    }
}