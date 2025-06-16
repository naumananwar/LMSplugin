<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Performance_Monitor {
    
    private static $instance = null;
    private $start_time;
    private $queries_start;
    private $memory_start;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_monitoring'));
        add_action('wp_footer', array($this, 'output_debug_info'));
        add_action('admin_footer', array($this, 'output_debug_info'));
    }
    
    public function init_monitoring() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->start_time = microtime(true);
            $this->queries_start = get_num_queries();
            $this->memory_start = memory_get_usage();
        }
    }
    
    /**
     * Monitor database query performance
     */
    public function monitor_query($query, $execution_time) {
        if ($execution_time > 0.1) { // Log slow queries (>100ms)
            $this->log_slow_query($query, $execution_time);
        }
    }
    
    /**
     * Monitor memory usage
     */
    public function check_memory_usage() {
        $current_memory = memory_get_usage();
        $peak_memory = memory_get_peak_usage();
        $memory_limit = $this->get_memory_limit();
        
        $usage_percentage = ($peak_memory / $memory_limit) * 100;
        
        if ($usage_percentage > 80) {
            $this->log_performance_issue('high_memory_usage', array(
                'current' => $this->format_bytes($current_memory),
                'peak' => $this->format_bytes($peak_memory),
                'limit' => $this->format_bytes($memory_limit),
                'percentage' => round($usage_percentage, 2)
            ));
        }
        
        return array(
            'current' => $current_memory,
            'peak' => $peak_memory,
            'limit' => $memory_limit,
            'percentage' => $usage_percentage
        );
    }
    
    /**
     * Monitor page load time
     */
    public function get_page_load_time() {
        if (!$this->start_time) {
            return 0;
        }
        
        $load_time = microtime(true) - $this->start_time;
        
        if ($load_time > 3) { // Log slow page loads (>3 seconds)
            $this->log_performance_issue('slow_page_load', array(
                'load_time' => $load_time,
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'queries' => get_num_queries() - $this->queries_start
            ));
        }
        
        return $load_time;
    }
    
    /**
     * Get database query statistics
     */
    public function get_query_stats() {
        global $wpdb;
        
        $total_queries = get_num_queries();
        $plugin_queries = $total_queries - $this->queries_start;
        
        return array(
            'total' => $total_queries,
            'plugin' => $plugin_queries,
            'time' => $wpdb->timer_stop()
        );
    }
    
    /**
     * Optimize database queries
     */
    public function optimize_queries() {
        global $wpdb;
        
        // Add indexes for common queries
        $tables_to_optimize = array(
            $wpdb->prefix . 'lms_assessment_results' => array(
                'idx_user_assessment' => 'user_id, assessment_id',
                'idx_status_created' => 'status, created_at'
            ),
            $wpdb->prefix . 'lms_course_enrollments' => array(
                'idx_user_course' => 'user_id, course_id',
                'idx_status' => 'status'
            ),
            $wpdb->prefix . 'lms_analytics' => array(
                'idx_event_date' => 'event_type, created_at',
                'idx_user_object' => 'user_id, object_id'
            )
        );
        
        foreach ($tables_to_optimize as $table => $indexes) {
            foreach ($indexes as $index_name => $columns) {
                $this->add_index_if_not_exists($table, $index_name, $columns);
            }
        }
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Clean up old analytics data (older than 1 year)
        $analytics_table = $wpdb->prefix . 'lms_analytics';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$analytics_table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-1 year'))
        ));
        
        // Clean up old security logs (older than 3 months)
        $security_table = $wpdb->prefix . 'lms_security_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$security_table}'") == $security_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$security_table} WHERE created_at < %s",
                date('Y-m-d H:i:s', strtotime('-3 months'))
            ));
        }
        
        // Clean up expired transients
        $this->cleanup_expired_transients();
    }
    
    /**
     * Output debug information
     */
    public function output_debug_info() {
        if (!defined('WP_DEBUG') || !WP_DEBUG || !current_user_can('manage_options')) {
            return;
        }
        
        $load_time = $this->get_page_load_time();
        $memory_stats = $this->check_memory_usage();
        $query_stats = $this->get_query_stats();
        
        echo "<!-- LMS Performance Debug Info\n";
        echo "Page Load Time: " . round($load_time, 4) . "s\n";
        echo "Memory Usage: " . $this->format_bytes($memory_stats['current']) . " / " . $this->format_bytes($memory_stats['limit']) . " (" . round($memory_stats['percentage'], 2) . "%)\n";
        echo "Peak Memory: " . $this->format_bytes($memory_stats['peak']) . "\n";
        echo "Database Queries: " . $query_stats['total'] . " (Plugin: " . $query_stats['plugin'] . ")\n";
        echo "-->";
    }
    
    /**
     * Log slow queries
     */
    private function log_slow_query($query, $execution_time) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("LMS Slow Query ({$execution_time}s): " . $query);
        }
    }
    
    /**
     * Log performance issues
     */
    private function log_performance_issue($type, $data) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("LMS Performance Issue ({$type}): " . json_encode($data));
        }
    }
    
    /**
     * Get memory limit in bytes
     */
    private function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            $value = $matches[1];
            $unit = strtoupper($matches[2]);
            
            switch ($unit) {
                case 'G':
                    return $value * 1024 * 1024 * 1024;
                case 'M':
                    return $value * 1024 * 1024;
                case 'K':
                    return $value * 1024;
                default:
                    return $value;
            }
        }
        
        return 128 * 1024 * 1024; // Default 128MB
    }
    
    /**
     * Format bytes for display
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Add database index if it doesn't exist
     */
    private function add_index_if_not_exists($table, $index_name, $columns) {
        global $wpdb;
        
        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM {$table} WHERE Key_name = %s",
            $index_name
        ));
        
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX {$index_name} ({$columns})");
        }
    }
    
    /**
     * Clean up expired transients
     */
    private function cleanup_expired_transients() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%' AND option_name NOT IN (SELECT REPLACE(option_name, '_timeout', '') FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%')");
    }
}