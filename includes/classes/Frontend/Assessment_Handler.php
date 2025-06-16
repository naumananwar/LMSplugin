<?php

namespace LMS_Auth\Frontend;

use LMS_Auth\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Assessment_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_start_assessment', array($this, 'start_assessment'));
        add_action('wp_ajax_save_assessment_answer', array($this, 'save_assessment_answer'));
        add_action('wp_ajax_submit_assessment', array($this, 'submit_assessment'));
        add_action('wp_ajax_get_assessment_results', array($this, 'get_assessment_results'));
        add_shortcode('lms_assessment_quiz', array($this, 'render_quiz_shortcode'));
    }
    
    public function enqueue_scripts() {
        if (is_singular('assessment') || $this->is_assessment_page()) {
            wp_enqueue_script(
                'lms-assessment-js',
                LMS_AUTH_PLUGIN_URL . 'assets/js/assessment.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            wp_enqueue_style(
                'lms-assessment-css',
                LMS_AUTH_PLUGIN_URL . 'assets/css/assessment.css',
                array(),
                '1.0.0'
            );
            
            wp_localize_script('lms-assessment-js', 'lms_assessment_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lms_assessment_nonce'),
                'strings' => array(
                    'confirm_submit' => __('Are you sure you want to submit your assessment? You cannot change answers after submission.', 'lms-auth'),
                    'time_up' => __('Time is up! Your assessment will be submitted automatically.', 'lms-auth'),
                    'connection_error' => __('Connection error. Please check your internet connection and try again.', 'lms-auth'),
                    'save_error' => __('Error saving your answer. Please try again.', 'lms-auth'),
                    'loading' => __('Loading...', 'lms-auth')
                )
            ));
        }
    }
    
    private function is_assessment_page() {
        global $wp_query;
        return isset($wp_query->query_vars['assessment_id']) || 
               (isset($_GET['assessment_id']) && is_numeric($_GET['assessment_id']));
    }
    
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_results' => false
        ), $atts);
        
        if (!$atts['id']) {
            return '<p>' . __('Assessment ID is required.', 'lms-auth') . '</p>';
        }
        
        if ($atts['show_results']) {
            return $this->render_assessment_results($atts['id']);
        }
        
        return $this->render_assessment_quiz($atts['id']);
    }
    
    public function render_assessment_quiz($assessment_id) {
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to take this assessment.', 'lms-auth') . '</p>';
        }
        
        $assessment = get_post($assessment_id);
        if (!$assessment || $assessment->post_type !== 'assessment') {
            return '<p>' . __('Assessment not found.', 'lms-auth') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $existing_result = $this->get_user_assessment_result($user_id, $assessment_id);
        
        // Check if user has already completed this assessment
        if ($existing_result && $existing_result->status === 'completed') {
            $retake_allowed = get_post_meta($assessment_id, '_allow_retake', true);
            if (!$retake_allowed) {
                return $this->render_completed_assessment($existing_result);
            }
        }
        
        // Get assessment settings
        $settings = $this->get_assessment_settings($assessment_id);
        $questions = $this->get_assessment_questions($assessment_id);
        
        if (empty($questions)) {
            return '<p>' . __('This assessment has no questions.', 'lms-auth') . '</p>';
        }
        
        ob_start();
        include LMS_AUTH_PLUGIN_PATH . 'templates/assessment/quiz-interface.php';
        return ob_get_clean();
    }
    
    public function get_assessment_settings($assessment_id) {
        return array(
            'time_limit' => get_post_meta($assessment_id, '_time_limit', true) ?: 0,
            'passing_score' => get_post_meta($assessment_id, '_passing_score', true) ?: 70,
            'max_attempts' => get_post_meta($assessment_id, '_max_attempts', true) ?: 1,
            'show_results' => get_post_meta($assessment_id, '_show_results', true) ?: 'after_submission',
            'allow_retake' => get_post_meta($assessment_id, '_allow_retake', true) ?: false,
            'randomize_questions' => get_post_meta($assessment_id, '_randomize_questions', true) ?: false,
            'question_per_page' => get_post_meta($assessment_id, '_question_per_page', true) ?: 1
        );
    }
    
    public function get_assessment_questions($assessment_id) {
        $questions = get_posts(array(
            'post_type' => 'question',
            'post_parent' => $assessment_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $formatted_questions = array();
        foreach ($questions as $question) {
            $question_data = array(
                'id' => $question->ID,
                'title' => $question->post_title,
                'content' => $question->post_content,
                'type' => get_post_meta($question->ID, '_question_type', true),
                'points' => get_post_meta($question->ID, '_question_points', true) ?: 1,
                'options' => get_post_meta($question->ID, '_question_options', true) ?: array(),
                'correct_answer' => get_post_meta($question->ID, '_correct_answer', true),
                'explanation' => get_post_meta($question->ID, '_explanation', true)
            );
            $formatted_questions[] = $question_data;
        }
        
        return $formatted_questions;
    }
    
    public function start_assessment() {
        check_ajax_referer('lms_assessment_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $assessment_id = intval($_POST['assessment_id']);
        $user_id = get_current_user_id();
        
        // Check if user can take this assessment
        $can_take = $this->can_user_take_assessment($user_id, $assessment_id);
        if (!$can_take['allowed']) {
            wp_send_json_error($can_take['message']);
        }
        
        // Create or update assessment result record
        $existing_result = $this->get_user_assessment_result($user_id, $assessment_id, 'in_progress');
        
        if ($existing_result) {
            $result_id = $existing_result->id;
        } else {
            $attempts = $this->get_user_attempts($user_id, $assessment_id);
            $result_id = Database::save_assessment_result(array(
                'user_id' => $user_id,
                'assessment_id' => $assessment_id,
                'status' => 'in_progress',
                'time_started' => current_time('mysql'),
                'attempts' => $attempts + 1
            ));
        }
        
        wp_send_json_success(array(
            'result_id' => $result_id,
            'start_time' => current_time('timestamp')
        ));
    }
    
    public function save_assessment_answer() {
        check_ajax_referer('lms_assessment_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $result_id = intval($_POST['result_id']);
        $question_id = intval($_POST['question_id']);
        $answer = sanitize_text_field($_POST['answer']);
        
        // Get current result
        $result = $this->get_assessment_result_by_id($result_id);
        if (!$result || $result->user_id != get_current_user_id()) {
            wp_send_json_error('Invalid assessment result');
        }
        
        // Parse existing answers
        $answers = json_decode($result->answers, true) ?: array();
        $answers[$question_id] = $answer;
        
        // Update result with new answer
        Database::save_assessment_result(array(
            'id' => $result_id,
            'answers' => json_encode($answers)
        ));
        
        wp_send_json_success('Answer saved');
    }
    
    public function submit_assessment() {
        check_ajax_referer('lms_assessment_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $result_id = intval($_POST['result_id']);
        $time_spent = intval($_POST['time_spent']);
        
        // Get current result
        $result = $this->get_assessment_result_by_id($result_id);
        if (!$result || $result->user_id != get_current_user_id()) {
            wp_send_json_error('Invalid assessment result');
        }
        
        // Calculate score
        $score_data = $this->calculate_assessment_score($result->assessment_id, $result->answers);
        
        // Update result with final score
        $update_data = array(
            'id' => $result_id,
            'score' => $score_data['percentage'],
            'total_questions' => $score_data['total_questions'],
            'correct_answers' => $score_data['correct_answers'],
            'status' => 'completed',
            'time_completed' => current_time('mysql'),
            'time_spent' => $time_spent
        );
        
        Database::save_assessment_result($update_data);
        
        // Log analytics
        Database::log_analytics('assessment_completed', get_current_user_id(), $result->assessment_id, 'assessment', array(
            'score' => $score_data['percentage'],
            'time_spent' => $time_spent,
            'attempts' => $result->attempts
        ));
        
        wp_send_json_success(array(
            'score' => $score_data['percentage'],
            'passing_score' => $this->get_assessment_settings($result->assessment_id)['passing_score'],
            'correct_answers' => $score_data['correct_answers'],
            'total_questions' => $score_data['total_questions'],
            'passed' => $score_data['percentage'] >= $this->get_assessment_settings($result->assessment_id)['passing_score']
        ));
    }
    
    private function calculate_assessment_score($assessment_id, $answers_json) {
        $answers = json_decode($answers_json, true) ?: array();
        $questions = $this->get_assessment_questions($assessment_id);
        
        $total_points = 0;
        $earned_points = 0;
        $correct_answers = 0;
        
        foreach ($questions as $question) {
            $total_points += $question['points'];
            
            if (isset($answers[$question['id']])) {
                $user_answer = $answers[$question['id']];
                $correct_answer = $question['correct_answer'];
                
                if ($this->is_answer_correct($user_answer, $correct_answer, $question['type'])) {
                    $earned_points += $question['points'];
                    $correct_answers++;
                }
            }
        }
        
        $percentage = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;
        
        return array(
            'percentage' => $percentage,
            'earned_points' => $earned_points,
            'total_points' => $total_points,
            'correct_answers' => $correct_answers,
            'total_questions' => count($questions)
        );
    }
    
    private function is_answer_correct($user_answer, $correct_answer, $question_type) {
        switch ($question_type) {
            case 'multiple_choice':
            case 'true_false':
                return $user_answer === $correct_answer;
            
            case 'short_answer':
                // Simple text comparison (could be enhanced with fuzzy matching)
                return strtolower(trim($user_answer)) === strtolower(trim($correct_answer));
            
            case 'multiple_select':
                $user_answers = is_array($user_answer) ? $user_answer : explode(',', $user_answer);
                $correct_answers = is_array($correct_answer) ? $correct_answer : explode(',', $correct_answer);
                sort($user_answers);
                sort($correct_answers);
                return $user_answers === $correct_answers;
            
            default:
                return false;
        }
    }
    
    private function can_user_take_assessment($user_id, $assessment_id) {
        $settings = $this->get_assessment_settings($assessment_id);
        $attempts = $this->get_user_attempts($user_id, $assessment_id);
        
        if ($settings['max_attempts'] > 0 && $attempts >= $settings['max_attempts']) {
            return array(
                'allowed' => false,
                'message' => __('You have reached the maximum number of attempts for this assessment.', 'lms-auth')
            );
        }
        
        // Check if user is enrolled in the course (if assessment belongs to a course)
        $course_id = get_post_meta($assessment_id, '_course_id', true);
        if ($course_id) {
            $enrollment = Database::get_course_enrollment($user_id, $course_id);
            if (!$enrollment || $enrollment->status !== 'enrolled') {
                return array(
                    'allowed' => false,
                    'message' => __('You must be enrolled in the course to take this assessment.', 'lms-auth')
                );
            }
        }
        
        return array('allowed' => true);
    }
    
    private function get_user_attempts($user_id, $assessment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lms_assessment_results';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND assessment_id = %d AND status = 'completed'",
            $user_id, $assessment_id
        ));
    }
    
    private function get_user_assessment_result($user_id, $assessment_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'lms_assessment_results';
        
        $where = $wpdb->prepare('user_id = %d AND assessment_id = %d', $user_id, $assessment_id);
        if ($status) {
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }
        
        return $wpdb->get_row("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT 1");
    }
    
    private function get_assessment_result_by_id($result_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lms_assessment_results';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $result_id
        ));
    }
    
    public function render_assessment_results($assessment_id) {
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to view results.', 'lms-auth') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $results = Database::get_assessment_results($user_id, $assessment_id);
        
        if (empty($results)) {
            return '<p>' . __('No results found.', 'lms-auth') . '</p>';
        }
        
        ob_start();
        include LMS_AUTH_PLUGIN_PATH . 'templates/assessment/results.php';
        return ob_get_clean();
    }
    
    private function render_completed_assessment($result) {
        $assessment = get_post($result->assessment_id);
        $settings = $this->get_assessment_settings($result->assessment_id);
        $passed = $result->score >= $settings['passing_score'];
        
        ob_start();
        ?>
        <div class="lms-assessment-completed">
            <h3><?php echo esc_html($assessment->post_title); ?></h3>
            <div class="assessment-result <?php echo $passed ? 'passed' : 'failed'; ?>">
                <div class="score">
                    <span class="score-value"><?php echo esc_html($result->score); ?>%</span>
                    <span class="score-label"><?php _e('Final Score', 'lms-auth'); ?></span>
                </div>
                <div class="result-details">
                    <p class="status">
                        <?php echo $passed ? __('Congratulations! You passed!', 'lms-auth') : __('You did not pass this assessment.', 'lms-auth'); ?>
                    </p>
                    <ul class="result-stats">
                        <li><?php printf(__('Score: %d%%', 'lms-auth'), $result->score); ?></li>
                        <li><?php printf(__('Passing Score: %d%%', 'lms-auth'), $settings['passing_score']); ?></li>
                        <li><?php printf(__('Correct Answers: %d/%d', 'lms-auth'), $result->correct_answers, $result->total_questions); ?></li>
                        <li><?php printf(__('Time Spent: %s', 'lms-auth'), $this->format_time($result->time_spent)); ?></li>
                        <li><?php printf(__('Completed: %s', 'lms-auth'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($result->time_completed))); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function format_time($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }
    
    public function get_assessment_results() {
        check_ajax_referer('lms_assessment_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $assessment_id = intval($_POST['assessment_id']);
        $user_id = get_current_user_id();
        
        $results = Database::get_assessment_results($user_id, $assessment_id);
        wp_send_json_success($results);
    }
}

