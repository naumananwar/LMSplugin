<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor logic if needed
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Assessment results table
        $table_results = $wpdb->prefix . 'lms_assessment_results';
        $sql_results = "CREATE TABLE $table_results (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            assessment_id bigint(20) unsigned NOT NULL,
            score decimal(5,2) NOT NULL DEFAULT 0,
            total_questions int(11) NOT NULL DEFAULT 0,
            correct_answers int(11) NOT NULL DEFAULT 0,
            status enum('in_progress','completed','failed') NOT NULL DEFAULT 'in_progress',
            time_started datetime NOT NULL,
            time_completed datetime NULL,
            time_spent int(11) NOT NULL DEFAULT 0,
            attempts int(11) NOT NULL DEFAULT 1,
            answers longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY assessment_id (assessment_id),
            KEY status (status)
        ) $charset_collate;";
        
        // User subscriptions table
        $table_subscriptions = $wpdb->prefix . 'lms_user_subscriptions';
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            package_id bigint(20) unsigned NOT NULL,
            status enum('active','inactive','expired','cancelled') NOT NULL DEFAULT 'active',
            start_date datetime NOT NULL,
            end_date datetime NULL,
            payment_method varchar(50),
            transaction_id varchar(255),
            amount decimal(10,2),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY package_id (package_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Course enrollments table
        $table_enrollments = $wpdb->prefix . 'lms_course_enrollments';
        $sql_enrollments = "CREATE TABLE $table_enrollments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status enum('enrolled','completed','dropped') NOT NULL DEFAULT 'enrolled',
            progress decimal(5,2) NOT NULL DEFAULT 0,
            enrollment_date datetime NOT NULL,
            completion_date datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status),
            UNIQUE KEY unique_enrollment (user_id, course_id)
        ) $charset_collate;";
        
        // Lesson progress table
        $table_lesson_progress = $wpdb->prefix . 'lms_lesson_progress';
        $sql_lesson_progress = "CREATE TABLE $table_lesson_progress (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
            time_spent int(11) NOT NULL DEFAULT 0,
            last_accessed datetime NULL,
            completed_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY lesson_id (lesson_id),
            KEY course_id (course_id),
            UNIQUE KEY unique_progress (user_id, lesson_id)
        ) $charset_collate;";
        
        // Analytics table
        $table_analytics = $wpdb->prefix . 'lms_analytics';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned,
            object_id bigint(20) unsigned,
            object_type varchar(50),
            meta_data longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_results);
        dbDelta($sql_subscriptions);
        dbDelta($sql_enrollments);
        dbDelta($sql_lesson_progress);
        dbDelta($sql_analytics);
    }
    
    public static function get_assessment_results($user_id = null, $assessment_id = null, $limit = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_assessment_results';
        $where = array('1=1');
        
        if ($user_id) {
            $where[] = $wpdb->prepare('user_id = %d', $user_id);
        }
        
        if ($assessment_id) {
            $where[] = $wpdb->prepare('assessment_id = %d', $assessment_id);
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }
        
        return $wpdb->get_results($sql);
    }
    
    public static function save_assessment_result($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_assessment_results';
        
        $defaults = array(
            'user_id' => 0,
            'assessment_id' => 0,
            'score' => 0,
            'total_questions' => 0,
            'correct_answers' => 0,
            'status' => 'in_progress',
            'time_started' => current_time('mysql'),
            'time_spent' => 0,
            'attempts' => 1,
            'answers' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (isset($data['id']) && $data['id']) {
            $result = $wpdb->update($table, $data, array('id' => $data['id']));
            return $data['id'];
        } else {
            $result = $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }
    
    public static function get_user_subscription($user_id, $package_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_user_subscriptions';
        $where = array($wpdb->prepare('user_id = %d', $user_id));
        
        if ($package_id) {
            $where[] = $wpdb->prepare('package_id = %d', $package_id);
        }
        
        $where[] = "status = 'active'";
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 1";
        
        return $wpdb->get_row($sql);
    }
    
    public static function create_subscription($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_user_subscriptions';
        
        $defaults = array(
            'user_id' => 0,
            'package_id' => 0,
            'status' => 'active',
            'start_date' => current_time('mysql'),
            'payment_method' => '',
            'transaction_id' => '',
            'amount' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }
    
    public static function get_course_enrollment($user_id, $course_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_course_enrollments';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND course_id = %d",
            $user_id, $course_id
        );
        
        return $wpdb->get_row($sql);
    }
    
    public static function enroll_user_in_course($user_id, $course_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_course_enrollments';
        
        $existing = self::get_course_enrollment($user_id, $course_id);
        
        if (!$existing) {
            $data = array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'status' => 'enrolled',
                'enrollment_date' => current_time('mysql')
            );
            
            $result = $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
        
        return $existing->id;
    }
    
    public static function update_lesson_progress($user_id, $lesson_id, $course_id, $status = 'in_progress', $time_spent = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_lesson_progress';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND lesson_id = %d",
            $user_id, $lesson_id
        ));
        
        $data = array(
            'user_id' => $user_id,
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
            'status' => $status,
            'time_spent' => $time_spent,
            'last_accessed' => current_time('mysql')
        );
        
        if ($status === 'completed') {
            $data['completed_at'] = current_time('mysql');
        }
        
        if ($existing) {
            $data['time_spent'] += $existing->time_spent;
            $wpdb->update($table, $data, array('id' => $existing->id));
            return $existing->id;
        } else {
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }
    
    public static function log_analytics($event_type, $user_id = null, $object_id = null, $object_type = null, $meta_data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_analytics';
        
        $data = array(
            'event_type' => $event_type,
            'user_id' => $user_id,
            'object_id' => $object_id,
            'object_type' => $object_type,
            'meta_data' => json_encode($meta_data),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        return $wpdb->insert($table, $data);
    }
    
    public static function get_analytics_data($filters = array(), $limit = 100) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'lms_analytics';
        $where = array('1=1');
        
        if (!empty($filters['event_type'])) {
            $where[] = $wpdb->prepare('event_type = %s', $filters['event_type']);
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = $wpdb->prepare('user_id = %d', $filters['user_id']);
        }
        
        if (!empty($filters['object_type'])) {
            $where[] = $wpdb->prepare('object_type = %s', $filters['object_type']);
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = $wpdb->prepare('created_at >= %s', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = $wpdb->prepare('created_at <= %s', $filters['date_to']);
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . 
               " ORDER BY created_at DESC LIMIT " . intval($limit);
        
        return $wpdb->get_results($sql);
    }
}

