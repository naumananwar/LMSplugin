<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Roles {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'maybe_add_capabilities'));
    }
    
    public static function create_roles() {
        // Remove existing roles first
        self::remove_roles();
        
        // Student role
        add_role('lms_student', __('LMS Student', 'lms-auth'), array(
            'read' => true,
            'lms_access_courses' => true,
            'lms_take_assessments' => true,
            'lms_view_own_results' => true,
            'lms_enroll_courses' => true
        ));
        
        // Instructor role
        add_role('lms_instructor', __('LMS Instructor', 'lms-auth'), array(
            'read' => true,
            'lms_access_courses' => true,
            'lms_take_assessments' => true,
            'lms_view_own_results' => true,
            'lms_enroll_courses' => true,
            'lms_create_courses' => true,
            'lms_edit_own_courses' => true,
            'lms_delete_own_courses' => true,
            'lms_create_assessments' => true,
            'lms_edit_own_assessments' => true,
            'lms_delete_own_assessments' => true,
            'lms_manage_own_students' => true,
            'lms_view_student_results' => true,
            'lms_create_lessons' => true,
            'lms_edit_own_lessons' => true,
            'lms_delete_own_lessons' => true
        ));
        
        // Institution role
        add_role('lms_institution', __('LMS Institution', 'lms-auth'), array(
            'read' => true,
            'lms_access_courses' => true,
            'lms_take_assessments' => true,
            'lms_view_own_results' => true,
            'lms_enroll_courses' => true,
            'lms_create_courses' => true,
            'lms_edit_courses' => true,
            'lms_delete_courses' => true,
            'lms_create_assessments' => true,
            'lms_edit_assessments' => true,
            'lms_delete_assessments' => true,
            'lms_manage_students' => true,
            'lms_manage_instructors' => true,
            'lms_view_all_results' => true,
            'lms_create_lessons' => true,
            'lms_edit_lessons' => true,
            'lms_delete_lessons' => true,
            'lms_create_packages' => true,
            'lms_edit_packages' => true,
            'lms_delete_packages' => true,
            'lms_view_analytics' => true,
            'lms_export_data' => true,
            'lms_manage_payments' => true
        ));
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'lms_access_courses',
                'lms_take_assessments',
                'lms_view_own_results',
                'lms_enroll_courses',
                'lms_create_courses',
                'lms_edit_courses',
                'lms_delete_courses',
                'lms_create_assessments',
                'lms_edit_assessments',
                'lms_delete_assessments',
                'lms_manage_students',
                'lms_manage_instructors',
                'lms_view_all_results',
                'lms_create_lessons',
                'lms_edit_lessons',
                'lms_delete_lessons',
                'lms_create_packages',
                'lms_edit_packages',
                'lms_delete_packages',
                'lms_view_analytics',
                'lms_export_data',
                'lms_manage_payments'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    public static function remove_roles() {
        remove_role('lms_student');
        remove_role('lms_instructor');
        remove_role('lms_institution');
        
        // Remove capabilities from administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'lms_access_courses',
                'lms_take_assessments',
                'lms_view_own_results',
                'lms_enroll_courses',
                'lms_create_courses',
                'lms_edit_courses',
                'lms_delete_courses',
                'lms_create_assessments',
                'lms_edit_assessments',
                'lms_delete_assessments',
                'lms_manage_students',
                'lms_manage_instructors',
                'lms_view_all_results',
                'lms_create_lessons',
                'lms_edit_lessons',
                'lms_delete_lessons',
                'lms_create_packages',
                'lms_edit_packages',
                'lms_delete_packages',
                'lms_view_analytics',
                'lms_export_data',
                'lms_manage_payments'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }
    
    public function maybe_add_capabilities() {
        // Check if capabilities need to be added (for plugin updates)
        $version = get_option('lms_auth_roles_version', '0');
        if (version_compare($version, LMS_AUTH_VERSION, '<')) {
            self::create_roles();
            update_option('lms_auth_roles_version', LMS_AUTH_VERSION);
        }
    }
    
    public static function get_user_role_type($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $roles = $user->roles;
        
        if (in_array('administrator', $roles) || in_array('lms_institution', $roles)) {
            return 'institution';
        } elseif (in_array('lms_instructor', $roles)) {
            return 'instructor';
        } elseif (in_array('lms_student', $roles)) {
            return 'student';
        }
        
        return false;
    }
    
    public static function user_can_access_dashboard($user_id = null, $dashboard_type = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user_role = self::get_user_role_type($user_id);
        
        if (!$dashboard_type) {
            return $user_role !== false;
        }
        
        return $user_role === $dashboard_type;
    }
    
    public static function get_dashboard_url($user_id = null) {
        $role = self::get_user_role_type($user_id);
        
        switch ($role) {
            case 'student':
                return get_permalink(get_page_by_path('student-dashboard'));
            case 'instructor':
                return get_permalink(get_page_by_path('instructor-dashboard'));
            case 'institution':
                return get_permalink(get_page_by_path('institution-dashboard'));
            default:
                return home_url();
        }
    }
    
    public static function redirect_after_login($user_id) {
        // Check if user has subscription
        $subscription = Database::get_user_subscription($user_id);
        
        if (!$subscription) {
            // Redirect to packages page if no subscription
            $packages_page = get_page_by_path('subscription-packages');
            if ($packages_page) {
                wp_redirect(get_permalink($packages_page->ID));
                exit;
            }
        }
        
        // Redirect to appropriate dashboard
        $dashboard_url = self::get_dashboard_url($user_id);
        if ($dashboard_url) {
            wp_redirect($dashboard_url);
            exit;
        }
        
        // Fallback to home page
        wp_redirect(home_url());
        exit;
    }
    
    public static function check_user_permissions($capability, $user_id = null, $object_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Check basic capability
        if (!user_can($user_id, $capability)) {
            return false;
        }
        
        // Additional checks for ownership-based capabilities
        if ($object_id) {
            switch ($capability) {
                case 'lms_edit_own_courses':
                case 'lms_delete_own_courses':
                    $course = get_post($object_id);
                    return $course && $course->post_author == $user_id;
                    
                case 'lms_edit_own_assessments':
                case 'lms_delete_own_assessments':
                    $assessment = get_post($object_id);
                    return $assessment && $assessment->post_author == $user_id;
                    
                case 'lms_edit_own_lessons':
                case 'lms_delete_own_lessons':
                    $lesson = get_post($object_id);
                    return $lesson && $lesson->post_author == $user_id;
            }
        }
        
        return true;
    }
    
    public static function get_users_by_role($role, $args = array()) {
        $defaults = array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return get_users($args);
    }
    
    public static function assign_role_to_user($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Remove existing LMS roles
        $user->remove_role('lms_student');
        $user->remove_role('lms_instructor');
        $user->remove_role('lms_institution');
        
        // Add new role
        $user->add_role($role);
        
        return true;
    }
}

