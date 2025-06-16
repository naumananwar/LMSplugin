<?php
namespace LMS_Auth\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Assessment_System {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX hooks for assessment actions
        add_action('wp_ajax_lms_start_assessment', array($this, 'handle_start_assessment'));
        add_action('wp_ajax_lms_submit_assessment', array($this, 'handle_submit_assessment'));
        add_action('wp_ajax_lms_next_question', array($this, 'handle_next_question')); // For per-question time
        add_action('wp_ajax_lms_save_answer', array($this, 'handle_save_answer')); // For total time, saving individual answers

        // Shortcode to display an assessment
        add_shortcode('lms_assessment', array($this, 'render_assessment_shortcode'));
        add_shortcode('lms_assessment_results', array($this, 'render_assessment_results_shortcode'));
    }

    public function handle_start_assessment() {
        check_ajax_referer('lms_auth_nonce', 'nonce');

        $assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
        $user_id = get_current_user_id();

        if (!$assessment_id || !$user_id) {
            wp_send_json_error(__('Invalid request.', 'lms-auth'));
        }

        if (!Roles::check_user_permissions('lms_take_assessments', $user_id)) {
            wp_send_json_error(__('You do not have permission to take assessments.', 'lms-auth'));
        }

        // Check attempts
        $max_attempts = get_post_meta($assessment_id, '_lms_assessment_max_attempts', true) ?: 3;
        $user_attempts = Database::get_assessment_results($user_id, $assessment_id);
        $num_attempts = is_array($user_attempts) ? count($user_attempts) : 0;

        $active_attempt = null;
        $attempt_id = null;
        $existing_answers = array();

        // Check for an existing 'in_progress' attempt
        $in_progress_attempts = Database::get_assessment_results($user_id, $assessment_id, 1); // Get the latest
        if ($in_progress_attempts && isset($in_progress_attempts[0]) && $in_progress_attempts[0]->status === 'in_progress') {
            $active_attempt = $in_progress_attempts[0];
            $attempt_id = $active_attempt->id;
            $existing_answers = json_decode($active_attempt->answers, true) ?: array();
            // If resuming, we don't count this as a new attempt against max_attempts yet.
        } elseif ($num_attempts >= $max_attempts) {
            wp_send_json_error(__('You have reached the maximum number of attempts for this assessment.', 'lms-auth'));
        } else {
            // Start new attempt
            $attempt_data = array(
                'user_id' => $user_id,
                'assessment_id' => $assessment_id,
                'status' => 'in_progress',
                'time_started' => current_time('mysql'),
                'attempts' => $num_attempts + 1,
                'answers' => json_encode(array())
            );
            $attempt_id = Database::save_assessment_result($attempt_data);
            if (!$attempt_id) {
                wp_send_json_error(__('Could not start the assessment. Please try again.', 'lms-auth'));
            }
        }

        $assessment_data = $this->get_assessment_data_for_display($assessment_id);
        if (!$assessment_data) {
            wp_send_json_error(__('Assessment not found or has no questions.', 'lms-auth'));
        }

        wp_send_json_success(array(
            'message' => __('Assessment started.', 'lms-auth'),
            'assessment' => $assessment_data,
            'attempt_id' => $attempt_id,
            'existing_answers' => $existing_answers // Send existing answers if resuming
        ));
    }

    public function handle_submit_assessment() {
        check_ajax_referer('lms_auth_nonce', 'nonce');

        $assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
        $attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
        $user_answers = isset($_POST['answers']) ? json_decode(stripslashes_deep($_POST['answers']), true) : array();
        $time_spent = isset($_POST['time_spent']) ? intval($_POST['time_spent']) : 0;
        $user_id = get_current_user_id();

        if (!$assessment_id || !$user_id || !$attempt_id || !is_array($user_answers)) {
            wp_send_json_error(__('Invalid submission data.', 'lms-auth'));
        }

        $assessment_post = get_post($assessment_id);
        if (!$assessment_post || $assessment_post->post_type !== 'lms_assessment') {
            wp_send_json_error(__('Invalid assessment.', 'lms-auth'));
        }

        $questions_meta = get_post_meta($assessment_id, '_lms_assessment_questions', true);
        if (empty($questions_meta) || !is_array($questions_meta)) {
             wp_send_json_error(__('Assessment has no questions configured.', 'lms-auth'));
        }

        // Calculate score
        $score = 0;
        $correct_answers_count = 0;
        $total_questions = count($questions_meta);

        foreach ($questions_meta as $index => $q_meta) {
            $submitted_answer_data = $user_answers[$index] ?? null; // e.g., ['answer' => 'user_choice']
            $user_answer_value = isset($submitted_answer_data['answer']) ? $submitted_answer_data['answer'] : null;

            if ($user_answer_value !== null) {
                $is_correct = false;
                $correct_answer_from_meta = $q_meta['correct_answer'] ?? '';
                $question_type = $q_meta['type'] ?? 'short_answer';

                switch ($question_type) {
                    case 'multiple_choice':
                    case 'short_answer':
                        // For MCQs, correct_answer is the text of the correct option.
                        // For Short Answer, it's the expected text.
                        if (is_string($correct_answer_from_meta) && is_string($user_answer_value) &&
                            strtolower(trim($correct_answer_from_meta)) === strtolower(trim($user_answer_value))) {
                            $is_correct = true;
                        }
                        break;
                    case 'true_false':
                        // Normalize boolean-like strings for comparison
                        $normalized_user_answer = filter_var(strtolower(trim($user_answer_value)), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $normalized_correct_answer = filter_var(strtolower(trim($correct_answer_from_meta)), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($normalized_user_answer !== null && $normalized_user_answer === $normalized_correct_answer) {
                            $is_correct = true;
                        }
                        break;
                }
                if ($is_correct) {
                    $score += intval($q_meta['points'] ?? 1); // Use actual points from question meta
                    $correct_answers_count++;
                }
            }
        }

        $pass_percentage = get_post_meta($assessment_id, '_lms_assessment_pass_percentage', true) ?: 70;
        $total_possible_points = array_sum(array_column($questions_meta, 'points')) ?: $total_questions;
        $achieved_percentage = ($total_possible_points > 0) ? ($score / $total_possible_points) * 100 : 0;

        $status = ($achieved_percentage >= $pass_percentage) ? 'completed' : 'failed'; // 'completed' means passed

        $result_data = array(
            'id' => $attempt_id, // Make sure this updates the existing record
            'user_id' => $user_id,
            'assessment_id' => $assessment_id,
            'score' => $achieved_percentage, // Store percentage score
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers_count,
            'status' => $status,
            'time_completed' => current_time('mysql'),
            'time_spent' => $time_spent,
            'answers' => json_encode($user_answers) // Store user's submitted answers
        );

        Database::save_assessment_result($result_data);
        Database::log_analytics('assessment_submitted', $user_id, $assessment_id, 'assessment', ['score' => $achieved_percentage, 'status' => $status]);

        wp_send_json_success(array(
            'message' => __('Assessment submitted successfully.', 'lms-auth'),
            'score' => $achieved_percentage,
            'status' => $status,
            'correct_answers' => $correct_answers_count,
            'total_questions' => $total_questions,
            'pass_percentage' => $pass_percentage
        ));
    }

    // For per-question timing, to save answer and acknowledge, then frontend moves to next.
    public function handle_next_question() {
        check_ajax_referer('lms_auth_nonce', 'nonce');

        $attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
        $question_index = isset($_POST['question_index']) ? intval($_POST['question_index']) : -1;
        // Assuming answer is sent as an object/array like {'answer': 'value'}
        $answer_data = isset($_POST['answer_data']) ? json_decode(stripslashes_deep($_POST['answer_data']), true) : null;

        if (!$attempt_id || $question_index < 0 || $answer_data === null) {
            wp_send_json_error(__('Invalid data for saving answer.', 'lms-auth'));
        }

        $this->_save_individual_answer($attempt_id, $question_index, $answer_data);
        wp_send_json_success(__('Answer saved.', 'lms-auth'));
    }

    // For total time assessments, to save answers as user progresses
    public function handle_save_answer() {
        check_ajax_referer('lms_auth_nonce', 'nonce');

        $attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
        $question_index = isset($_POST['question_index']) ? intval($_POST['question_index']) : -1;
        $answer_data = isset($_POST['answer_data']) ? json_decode(stripslashes_deep($_POST['answer_data']), true) : null;

        if (!$attempt_id || $question_index < 0 || $answer_data === null) {
            wp_send_json_error(__('Invalid data for saving answer.', 'lms-auth'));
        }

        $this->_save_individual_answer($attempt_id, $question_index, $answer_data);
        wp_send_json_success(__('Answer saved.', 'lms-auth'));
    }

    /**
     * Helper function to save an individual answer to an ongoing assessment attempt.
     */
    private function _save_individual_answer($attempt_id, $question_index, $answer_data) {
        global $wpdb;
        $table_results = $wpdb->prefix . 'lms_assessment_results';

        // Fetch the current attempt
        $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_results WHERE id = %d AND status = 'in_progress'", $attempt_id));

        if (!$attempt) {
            // Optionally send an error, but for robustness, might just log and ignore if attempt not found or not in progress
            return false;
        }

        $current_answers = json_decode($attempt->answers, true);
        if (!is_array($current_answers)) {
            $current_answers = array();
        }

        // Store the answer data against the question index.
        // Assumes $answer_data is structured like {'answer': 'user_input_value'}
        $current_answers[$question_index] = $answer_data;

        return Database::save_assessment_result(array('id' => $attempt_id, 'answers' => json_encode($current_answers)));
    }


    public function render_assessment_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'lms_assessment');

        $assessment_id = intval($atts['id']);
        if (!$assessment_id || !get_post($assessment_id) || get_post_type($assessment_id) !== 'lms_assessment') {
            return '<p>' . __('Invalid assessment ID.', 'lms-auth') . '</p>';
        }

        if (!is_user_logged_in()) {
            return '<p>' . sprintf(__('Please <a href="%s">login</a> to take this assessment.', 'lms-auth'), wp_login_url(get_permalink())) . '</p>';
        }

        if (!Roles::check_user_permissions('lms_take_assessments', get_current_user_id())) {
            return '<p>' . __('You do not have permission to take assessments.', 'lms-auth') . '</p>';
        }

        // Frontend will handle the actual rendering and AJAX calls
        // This shortcode just sets up the container and necessary data
        ob_start();
        ?>
        <div id="lms-assessment-<?php echo esc_attr($assessment_id); ?>" class="lms-assessment-container"
             data-assessment-id="<?php echo esc_attr($assessment_id); ?>"
             data-nonce="<?php echo wp_create_nonce('lms_auth_nonce'); ?>"
             data-ajax-url="<?php echo admin_url('admin-ajax.php'); ?>">
            <p><?php _e('Loading assessment...', 'lms-auth'); ?></p>
            <!-- Assessment content will be loaded here by JavaScript -->
        </div>
        <?php
        // It's generally better to enqueue scripts via 'wp_enqueue_scripts' action hook.
        // However, if this shortcode is the only place it's needed, you can do it here,
        // but ensure it's only enqueued once.
        // if (!wp_script_is('lms-assessment-js', 'enqueued')) {
        //     wp_enqueue_script('lms-assessment-js', LMS_AUTH_PLUGIN_URL . 'assets/js/assessment.js', array('jquery'), LMS_AUTH_VERSION, true);
        // }
        // Localize script with data if needed, e.g., for initial load or specific settings not covered by data attributes.
        // wp_localize_script('lms-assessment-js', 'lmsAssessmentData', array( ... initial data ... ));
        return ob_get_clean();
    }

    private function get_assessment_data_for_display($assessment_id) {
        $assessment_post = get_post($assessment_id);
        if (!$assessment_post || $assessment_post->post_type !== 'lms_assessment') {
            return null;
        }

        $questions_meta = get_post_meta($assessment_id, '_lms_assessment_questions', true);
        if (empty($questions_meta) || !is_array($questions_meta)) {
            return null; // No questions
        }

        $questions_for_display = array();
        foreach ($questions_meta as $q_meta) {
            $question_data = array(
                'type' => $q_meta['type'],
                'question_text' => $q_meta['question'],
                // Ensure options are always an array, even if empty
                'options' => ($q_meta['type'] === 'multiple_choice' && !empty($q_meta['options']) && is_array($q_meta['options'])) ? array_values($q_meta['options']) : array(),
                // DO NOT send correct_answer or explanation to the client before submission
            );
            $questions_for_display[] = $question_data;
        }

        if (get_post_meta($assessment_id, '_lms_assessment_randomize_questions', true)) {
            shuffle($questions_for_display);
        }

        return array(
            'id' => $assessment_id,
            'title' => $assessment_post->post_title,
            'time_limit_type' => get_post_meta($assessment_id, '_lms_assessment_time_limit_type', true),
            'total_time' => get_post_meta($assessment_id, '_lms_assessment_total_time', true), // minutes
            'per_question_time' => get_post_meta($assessment_id, '_lms_assessment_per_question_time', true), // seconds
            'questions' => $questions_for_display
        );
    }

    public function render_assessment_results_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'assessment_id' => 0, // 0 for all assessments by the user
        ), $atts, 'lms_assessment_results');

        $user_id = intval($atts['user_id']);
        $assessment_id_filter = intval($atts['assessment_id']);

        if (!$user_id) {
            return '<p>' . __('User not specified or not logged in.', 'lms-auth') . '</p>';
        }

        // Add permission check: only admin/institution or the user themselves can see results.
        if ($user_id !== get_current_user_id() && !current_user_can('lms_view_all_results') && !current_user_can('lms_view_student_results')) {
             return '<p>' . __('You do not have permission to view these results.', 'lms-auth') . '</p>';
        }

        $results = Database::get_assessment_results($user_id, $assessment_id_filter ?: null);

        if (empty($results)) {
            return '<p>' . __('No assessment results found.', 'lms-auth') . '</p>';
        }

        ob_start();
        echo '<div class="lms-assessment-results-list">';
        echo '<h3>' . __('Assessment Results', 'lms-auth') . '</h3>';
        echo '<ul>';
        foreach ($results as $result) {
            $assessment_title = get_the_title($result->assessment_id);
            echo '<li>';
            echo '<strong>' . esc_html($assessment_title) . '</strong><br>';
            echo sprintf(__('Attempt %d on %s', 'lms-auth'), $result->attempts, mysql2date(get_option('date_format'), $result->time_started)) . '<br>';
            echo sprintf(__('Score: %.2f%%', 'lms-auth'), $result->score) . ' - ';
            echo sprintf(__('Status: %s', 'lms-auth'), ucfirst($result->status)) . '<br>';
            // Optionally link to a detailed view if you build one
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
        return ob_get_clean();
    }

}