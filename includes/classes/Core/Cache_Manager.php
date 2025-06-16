<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Cache_Manager {
    
    private static $instance = null;
    private $cache_group = 'lms_auth';
    private $cache_expiry = 3600; // 1 hour default
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_cache'));
        add_action('lms_clear_cache', array($this, 'clear_all_cache'));
    }
    
    public function init_cache() {
        // Initialize cache groups
        wp_cache_add_global_groups(array($this->cache_group));
    }
    
    /**
     * Get cached data
     */
    public function get($key, $default = false) {
        return wp_cache_get($key, $this->cache_group) ?: $default;
    }
    
    /**
     * Set cache data
     */
    public function set($key, $data, $expiry = null) {
        $expiry = $expiry ?: $this->cache_expiry;
        return wp_cache_set($key, $data, $this->cache_group, $expiry);
    }
    
    /**
     * Delete cached data
     */
    public function delete($key) {
        return wp_cache_delete($key, $this->cache_group);
    }
    
    /**
     * Get user courses with caching
     */
    public function get_user_courses($user_id, $force_refresh = false) {
        $cache_key = "user_courses_{$user_id}";
        
        if (!$force_refresh) {
            $cached = $this->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'lms_course_enrollments';
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'enrolled'",
            $user_id
        ));
        
        $this->set($cache_key, $courses, 1800); // 30 minutes
        return $courses;
    }
    
    /**
     * Get assessment results with caching
     */
    public function get_assessment_results($user_id, $assessment_id = null, $force_refresh = false) {
        $cache_key = "assessment_results_{$user_id}";
        if ($assessment_id) {
            $cache_key .= "_{$assessment_id}";
        }
        
        if (!$force_refresh) {
            $cached = $this->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $results = Database::get_assessment_results($user_id, $assessment_id);
        $this->set($cache_key, $results, 900); // 15 minutes
        return $results;
    }
    
    /**
     * Clear user-specific cache
     */
    public function clear_user_cache($user_id) {
        $keys = array(
            "user_courses_{$user_id}",
            "assessment_results_{$user_id}",
            "user_progress_{$user_id}",
            "user_subscription_{$user_id}"
        );
        
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }
    
    /**
     * Clear course-specific cache
     */
    public function clear_course_cache($course_id) {
        $keys = array(
            "course_structure_{$course_id}",
            "course_lessons_{$course_id}",
            "course_enrollments_{$course_id}"
        );
        
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }
    
    /**
     * Clear all plugin cache
     */
    public function clear_all_cache() {
        wp_cache_flush_group($this->cache_group);
    }
}