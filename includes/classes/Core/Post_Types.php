<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Post_Types {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post_meta'));
    }
    
    public function register_post_types() {
        // Course post type
        register_post_type('lms_course', array(
            'labels' => array(
                'name' => __('Courses', 'lms-auth'),
                'singular_name' => __('Course', 'lms-auth'),
                'add_new' => __('Add New Course', 'lms-auth'),
                'add_new_item' => __('Add New Course', 'lms-auth'),
                'edit_item' => __('Edit Course', 'lms-auth'),
                'new_item' => __('New Course', 'lms-auth'),
                'view_item' => __('View Course', 'lms-auth'),
                'search_items' => __('Search Courses', 'lms-auth'),
                'not_found' => __('No courses found', 'lms-auth'),
                'not_found_in_trash' => __('No courses found in trash', 'lms-auth')
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-book-alt',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'author'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => array('slug' => 'courses'),
            'show_in_rest' => true
        ));
        
        // Lesson post type
        register_post_type('lms_lesson', array(
            'labels' => array(
                'name' => __('Lessons', 'lms-auth'),
                'singular_name' => __('Lesson', 'lms-auth'),
                'add_new' => __('Add New Lesson', 'lms-auth'),
                'add_new_item' => __('Add New Lesson', 'lms-auth'),
                'edit_item' => __('Edit Lesson', 'lms-auth'),
                'new_item' => __('New Lesson', 'lms-auth'),
                'view_item' => __('View Lesson', 'lms-auth'),
                'search_items' => __('Search Lessons', 'lms-auth'),
                'not_found' => __('No lessons found', 'lms-auth'),
                'not_found_in_trash' => __('No lessons found in trash', 'lms-auth')
            ),
            'public' => true,
            'has_archive' => false,
            'menu_icon' => 'dashicons-media-text',
            'supports' => array('title', 'editor', 'excerpt', 'author', 'page-attributes'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => array('slug' => 'lessons'),
            'show_in_rest' => true
        ));
        
        // Assessment post type
        register_post_type('lms_assessment', array(
            'labels' => array(
                'name' => __('Assessments', 'lms-auth'),
                'singular_name' => __('Assessment', 'lms-auth'),
                'add_new' => __('Add New Assessment', 'lms-auth'),
                'add_new_item' => __('Add New Assessment', 'lms-auth'),
                'edit_item' => __('Edit Assessment', 'lms-auth'),
                'new_item' => __('New Assessment', 'lms-auth'),
                'view_item' => __('View Assessment', 'lms-auth'),
                'search_items' => __('Search Assessments', 'lms-auth'),
                'not_found' => __('No assessments found', 'lms-auth'),
                'not_found_in_trash' => __('No assessments found in trash', 'lms-auth')
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => array('title', 'editor', 'excerpt', 'author'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => array('slug' => 'assessments'),
            'show_in_rest' => true
        ));
        
        // Question post type
        register_post_type('lms_question', array(
            'labels' => array(
                'name' => __('Questions', 'lms-auth'),
                'singular_name' => __('Question', 'lms-auth'),
                'add_new' => __('Add New Question', 'lms-auth'),
                'add_new_item' => __('Add New Question', 'lms-auth'),
                'edit_item' => __('Edit Question', 'lms-auth'),
                'new_item' => __('New Question', 'lms-auth'),
                'view_item' => __('View Question', 'lms-auth'),
                'search_items' => __('Search Questions', 'lms-auth'),
                'not_found' => __('No questions found', 'lms-auth'),
                'not_found_in_trash' => __('No questions found in trash', 'lms-auth')
            ),
            'public' => false,
            'show_ui' => true,
            'has_archive' => false,
            'menu_icon' => 'dashicons-editor-help',
            'supports' => array('title', 'editor', 'author'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'show_in_rest' => true
        ));
        
        // Package post type
        register_post_type('lms_package', array(
            'labels' => array(
                'name' => __('Packages', 'lms-auth'),
                'singular_name' => __('Package', 'lms-auth'),
                'add_new' => __('Add New Package', 'lms-auth'),
                'add_new_item' => __('Add New Package', 'lms-auth'),
                'edit_item' => __('Edit Package', 'lms-auth'),
                'new_item' => __('New Package', 'lms-auth'),
                'view_item' => __('View Package', 'lms-auth'),
                'search_items' => __('Search Packages', 'lms-auth'),
                'not_found' => __('No packages found', 'lms-auth'),
                'not_found_in_trash' => __('No packages found in trash', 'lms-auth')
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-cart',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'author'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => array('slug' => 'packages'),
            'show_in_rest' => true
        ));
    }
    
    public function register_taxonomies() {
        // Course categories
        register_taxonomy('lms_course_category', 'lms_course', array(
            'labels' => array(
                'name' => __('Course Categories', 'lms-auth'),
                'singular_name' => __('Course Category', 'lms-auth'),
                'search_items' => __('Search Categories', 'lms-auth'),
                'all_items' => __('All Categories', 'lms-auth'),
                'parent_item' => __('Parent Category', 'lms-auth'),
                'parent_item_colon' => __('Parent Category:', 'lms-auth'),
                'edit_item' => __('Edit Category', 'lms-auth'),
                'update_item' => __('Update Category', 'lms-auth'),
                'add_new_item' => __('Add New Category', 'lms-auth'),
                'new_item_name' => __('New Category Name', 'lms-auth')
            ),
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'course-category'),
            'show_in_rest' => true
        ));
        
        // Course tags
        register_taxonomy('lms_course_tag', 'lms_course', array(
            'labels' => array(
                'name' => __('Course Tags', 'lms-auth'),
                'singular_name' => __('Course Tag', 'lms-auth'),
                'search_items' => __('Search Tags', 'lms-auth'),
                'popular_items' => __('Popular Tags', 'lms-auth'),
                'all_items' => __('All Tags', 'lms-auth'),
                'edit_item' => __('Edit Tag', 'lms-auth'),
                'update_item' => __('Update Tag', 'lms-auth'),
                'add_new_item' => __('Add New Tag', 'lms-auth'),
                'new_item_name' => __('New Tag Name', 'lms-auth')
            ),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'course-tag'),
            'show_in_rest' => true
        ));
    }
    
    public function add_meta_boxes() {
        // Course meta boxes
        add_meta_box(
            'lms_course_settings',
            __('Course Settings', 'lms-auth'),
            array($this, 'course_settings_meta_box'),
            'lms_course',
            'normal',
            'high'
        );
        
        // Lesson meta boxes
        add_meta_box(
            'lms_lesson_settings',
            __('Lesson Settings', 'lms-auth'),
            array($this, 'lesson_settings_meta_box'),
            'lms_lesson',
            'normal',
            'high'
        );
        
        // Assessment meta boxes
        add_meta_box(
            'lms_assessment_settings',
            __('Assessment Settings', 'lms-auth'),
            array($this, 'assessment_settings_meta_box'),
            'lms_assessment',
            'normal',
            'high'
        );
        
        add_meta_box(
            'lms_assessment_questions',
            __('Questions', 'lms-auth'),
            array($this, 'assessment_questions_meta_box'),
            'lms_assessment',
            'normal',
            'high'
        );
        
        // Question meta boxes
        add_meta_box(
            'lms_question_settings',
            __('Question Settings', 'lms-auth'),
            array($this, 'question_settings_meta_box'),
            'lms_question',
            'normal',
            'high'
        );
        
        // Package meta boxes
        add_meta_box(
            'lms_package_settings',
            __('Package Settings', 'lms-auth'),
            array($this, 'package_settings_meta_box'),
            'lms_package',
            'normal',
            'high'
        );
    }
    
    public function course_settings_meta_box($post) {
        wp_nonce_field('lms_course_meta', 'lms_course_meta_nonce');
        
        $price = get_post_meta($post->ID, '_lms_course_price', true);
        $is_free = get_post_meta($post->ID, '_lms_course_is_free', true);
        $duration = get_post_meta($post->ID, '_lms_course_duration', true);
        $difficulty = get_post_meta($post->ID, '_lms_course_difficulty', true);
        $objectives = get_post_meta($post->ID, '_lms_course_objectives', true);
        $requirements = get_post_meta($post->ID, '_lms_course_requirements', true);
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="lms_course_is_free">' . __('Course Type', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<label><input type="radio" name="lms_course_is_free" value="1" ' . checked($is_free, '1', false) . '> ' . __('Free', 'lms-auth') . '</label><br>';
        echo '<label><input type="radio" name="lms_course_is_free" value="0" ' . checked($is_free, '0', false) . '> ' . __('Paid', 'lms-auth') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr id="price-row" style="display: ' . ($is_free == '1' ? 'none' : 'table-row') . '">';
        echo '<th><label for="lms_course_price">' . __('Price ($)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" step="0.01" name="lms_course_price" value="' . esc_attr($price) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_course_duration">' . __('Duration (hours)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" name="lms_course_duration" value="' . esc_attr($duration) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_course_difficulty">' . __('Difficulty Level', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<select name="lms_course_difficulty">';
        echo '<option value="beginner" ' . selected($difficulty, 'beginner', false) . '>' . __('Beginner', 'lms-auth') . '</option>';
        echo '<option value="intermediate" ' . selected($difficulty, 'intermediate', false) . '>' . __('Intermediate', 'lms-auth') . '</option>';
        echo '<option value="advanced" ' . selected($difficulty, 'advanced', false) . '>' . __('Advanced', 'lms-auth') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_course_objectives">' . __('Learning Objectives', 'lms-auth') . '</label></th>';
        echo '<td><textarea name="lms_course_objectives" rows="5" class="large-text">' . esc_textarea($objectives) . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_course_requirements">' . __('Requirements', 'lms-auth') . '</label></th>';
        echo '<td><textarea name="lms_course_requirements" rows="3" class="large-text">' . esc_textarea($requirements) . '</textarea></td>';
        echo '</tr>';
        
        echo '</table>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('input[name="lms_course_is_free"]').change(function() {
                if ($(this).val() == '1') {
                    $('#price-row').hide();
                } else {
                    $('#price-row').show();
                }
            });
        });
        </script>
        <?php
    }
    
    public function lesson_settings_meta_box($post) {
        wp_nonce_field('lms_lesson_meta', 'lms_lesson_meta_nonce');
        
        $course_id = get_post_meta($post->ID, '_lms_lesson_course', true);
        $lesson_type = get_post_meta($post->ID, '_lms_lesson_type', true);
        $video_url = get_post_meta($post->ID, '_lms_lesson_video_url', true);
        $duration = get_post_meta($post->ID, '_lms_lesson_duration', true);
        $is_preview = get_post_meta($post->ID, '_lms_lesson_is_preview', true);
        
        $courses = get_posts(array(
            'post_type' => 'lms_course',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="lms_lesson_course">' . __('Course', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<select name="lms_lesson_course" required>';
        echo '<option value="">' . __('Select Course', 'lms-auth') . '</option>';
        foreach ($courses as $course) {
            echo '<option value="' . $course->ID . '" ' . selected($course_id, $course->ID, false) . '>' . esc_html($course->post_title) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_lesson_type">' . __('Lesson Type', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<select name="lms_lesson_type">';
        echo '<option value="text" ' . selected($lesson_type, 'text', false) . '>' . __('Text', 'lms-auth') . '</option>';
        echo '<option value="video" ' . selected($lesson_type, 'video', false) . '>' . __('Video', 'lms-auth') . '</option>';
        echo '<option value="document" ' . selected($lesson_type, 'document', false) . '>' . __('Document', 'lms-auth') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr id="video-url-row" style="display: ' . ($lesson_type == 'video' ? 'table-row' : 'none') . '">';
        echo '<th><label for="lms_lesson_video_url">' . __('Video URL', 'lms-auth') . '</label></th>';
        echo '<td><input type="url" name="lms_lesson_video_url" value="' . esc_attr($video_url) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_lesson_duration">' . __('Duration (minutes)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" name="lms_lesson_duration" value="' . esc_attr($duration) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_lesson_is_preview">' . __('Free Preview', 'lms-auth') . '</label></th>';
        echo '<td><label><input type="checkbox" name="lms_lesson_is_preview" value="1" ' . checked($is_preview, '1', false) . '> ' . __('Allow as free preview', 'lms-auth') . '</label></td>';
        echo '</tr>';
        
        echo '</table>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('select[name="lms_lesson_type"]').change(function() {
                if ($(this).val() == 'video') {
                    $('#video-url-row').show();
                } else {
                    $('#video-url-row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    public function assessment_settings_meta_box($post) {
        wp_nonce_field('lms_assessment_meta', 'lms_assessment_meta_nonce');
        
        $time_limit_type = get_post_meta($post->ID, '_lms_assessment_time_limit_type', true);
        $total_time = get_post_meta($post->ID, '_lms_assessment_total_time', true);
        $per_question_time = get_post_meta($post->ID, '_lms_assessment_per_question_time', true);
        $pass_percentage = get_post_meta($post->ID, '_lms_assessment_pass_percentage', true);
        $max_attempts = get_post_meta($post->ID, '_lms_assessment_max_attempts', true);
        $randomize_questions = get_post_meta($post->ID, '_lms_assessment_randomize_questions', true);
        $show_correct_answers = get_post_meta($post->ID, '_lms_assessment_show_correct_answers', true);
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="lms_assessment_time_limit_type">' . __('Time Limit Type', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<select name="lms_assessment_time_limit_type">';
        echo '<option value="total" ' . selected($time_limit_type, 'total', false) . '>' . __('Total Time', 'lms-auth') . '</option>';
        echo '<option value="per_question" ' . selected($time_limit_type, 'per_question', false) . '>' . __('Per Question', 'lms-auth') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr id="total-time-row" style="display: ' . ($time_limit_type != 'per_question' ? 'table-row' : 'none') . '">';
        echo '<th><label for="lms_assessment_total_time">' . __('Total Time (minutes)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" name="lms_assessment_total_time" value="' . esc_attr($total_time) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr id="per-question-time-row" style="display: ' . ($time_limit_type == 'per_question' ? 'table-row' : 'none') . '">';
        echo '<th><label for="lms_assessment_per_question_time">' . __('Time Per Question (seconds)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" name="lms_assessment_per_question_time" value="' . esc_attr($per_question_time) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_assessment_pass_percentage">' . __('Pass Percentage (%)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" min="0" max="100" name="lms_assessment_pass_percentage" value="' . esc_attr($pass_percentage ?: 70) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_assessment_max_attempts">' . __('Maximum Attempts', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" min="1" name="lms_assessment_max_attempts" value="' . esc_attr($max_attempts ?: 3) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_assessment_randomize_questions">' . __('Randomize Questions', 'lms-auth') . '</label></th>';
        echo '<td><label><input type="checkbox" name="lms_assessment_randomize_questions" value="1" ' . checked($randomize_questions, '1', false) . '> ' . __('Randomize question order', 'lms-auth') . '</label></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_assessment_show_correct_answers">' . __('Show Correct Answers', 'lms-auth') . '</label></th>';
        echo '<td><label><input type="checkbox" name="lms_assessment_show_correct_answers" value="1" ' . checked($show_correct_answers, '1', false) . '> ' . __('Show correct answers after completion', 'lms-auth') . '</label></td>';
        echo '</tr>';
        
        echo '</table>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('select[name="lms_assessment_time_limit_type"]').change(function() {
                if ($(this).val() == 'per_question') {
                    $('#total-time-row').hide();
                    $('#per-question-time-row').show();
                } else {
                    $('#total-time-row').show();
                    $('#per-question-time-row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    public function assessment_questions_meta_box($post) {
        $questions = get_post_meta($post->ID, '_lms_assessment_questions', true);
        if (!is_array($questions)) {
            $questions = array();
        }
        
        echo '<div id="lms-questions-container">';
        
        if (!empty($questions)) {
            foreach ($questions as $index => $question) {
                $this->render_question_row($index, $question);
            }
        }
        
        echo '</div>';
        
        echo '<p>';
        echo '<button type="button" id="add-question" class="button">' . __('Add Question', 'lms-auth') . '</button> ';
        echo '<button type="button" id="generate-ai-questions" class="button">' . __('Generate with AI', 'lms-auth') . '</button>';
        echo '</p>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            var questionIndex = <?php echo count($questions); ?>;
            
            $('#add-question').click(function() {
                var template = '<div class="question-row" data-index="' + questionIndex + '">' +
                    '<h4>Question ' + (questionIndex + 1) + '</h4>' +
                    '<table class="form-table">' +
                    '<tr>' +
                    '<th>Question Type</th>' +
                    '<td>' +
                    '<select name="lms_assessment_questions[' + questionIndex + '][type]">' +
                    '<option value="multiple_choice">Multiple Choice</option>' +
                    '<option value="true_false">True/False</option>' +
                    '<option value="short_answer">Short Answer</option>' +
                    '</select>' +
                    '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<th>Question Text</th>' +
                    '<td><textarea name="lms_assessment_questions[' + questionIndex + '][question]" rows="3" class="large-text"></textarea></td>' +
                    '</tr>' +
                    '<tr class="options-row">' +
                    '<th>Options</th>' +
                    '<td>' +
                    '<div class="options-container">' +
                    '<input type="text" name="lms_assessment_questions[' + questionIndex + '][options][]" placeholder="Option 1" class="regular-text"><br>' +
                    '<input type="text" name="lms_assessment_questions[' + questionIndex + '][options][]" placeholder="Option 2" class="regular-text"><br>' +
                    '<input type="text" name="lms_assessment_questions[' + questionIndex + '][options][]" placeholder="Option 3" class="regular-text"><br>' +
                    '<input type="text" name="lms_assessment_questions[' + questionIndex + '][options][]" placeholder="Option 4" class="regular-text"><br>' +
                    '</div>' +
                    '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<th>Correct Answer</th>' +
                    '<td><input type="text" name="lms_assessment_questions[' + questionIndex + '][correct_answer]" class="regular-text"></td>' +
                    '</tr>' +
                    '<tr>' +
                    '<th>Explanation</th>' +
                    '<td><textarea name="lms_assessment_questions[' + questionIndex + '][explanation]" rows="2" class="large-text"></textarea></td>' +
                    '</tr>' +
                    '<tr>' +
                    '<th>Points</th>' +
                    '<td><input type="number" name="lms_assessment_questions[' + questionIndex + '][points]" value="1" min="1" class="small-text"></td>' +
                    '</tr>' +
                    '</table>' +
                    '<button type="button" class="remove-question button">Remove Question</button>' +
                    '<hr>' +
                    '</div>';
                
                $('#lms-questions-container').append(template);
                questionIndex++;
            });
            
            $(document).on('click', '.remove-question', function() {
                $(this).closest('.question-row').remove();
            });
            
            $(document).on('change', 'select[name*="[type]"]', function() {
                var $row = $(this).closest('.question-row');
                if ($(this).val() === 'multiple_choice') {
                    $row.find('.options-row').show();
                } else {
                    $row.find('.options-row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    private function render_question_row($index, $question) {
        echo '<div class="question-row" data-index="' . $index . '">';
        echo '<h4>Question ' . ($index + 1) . '</h4>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th>Question Type</th>';
        echo '<td>';
        echo '<select name="lms_assessment_questions[' . $index . '][type]">';
        echo '<option value="multiple_choice" ' . selected($question['type'] ?? '', 'multiple_choice', false) . '>Multiple Choice</option>';
        echo '<option value="true_false" ' . selected($question['type'] ?? '', 'true_false', false) . '>True/False</option>';
        echo '<option value="short_answer" ' . selected($question['type'] ?? '', 'short_answer', false) . '>Short Answer</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Question Text</th>';
        echo '<td><textarea name="lms_assessment_questions[' . $index . '][question]" rows="3" class="large-text">' . esc_textarea($question['question'] ?? '') . '</textarea></td>';
        echo '</tr>';
        
        $show_options = ($question['type'] ?? '') === 'multiple_choice';
        echo '<tr class="options-row" style="display: ' . ($show_options ? 'table-row' : 'none') . '">';
        echo '<th>Options</th>';
        echo '<td>';
        echo '<div class="options-container">';
        $options = $question['options'] ?? array('', '', '', '');
        for ($i = 0; $i < 4; $i++) {
            echo '<input type="text" name="lms_assessment_questions[' . $index . '][options][]" placeholder="Option ' . ($i + 1) . '" value="' . esc_attr($options[$i] ?? '') . '" class="regular-text"><br>';
        }
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Correct Answer</th>';
        echo '<td><input type="text" name="lms_assessment_questions[' . $index . '][correct_answer]" value="' . esc_attr($question['correct_answer'] ?? '') . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Explanation</th>';
        echo '<td><textarea name="lms_assessment_questions[' . $index . '][explanation]" rows="2" class="large-text">' . esc_textarea($question['explanation'] ?? '') . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>Points</th>';
        echo '<td><input type="number" name="lms_assessment_questions[' . $index . '][points]" value="' . esc_attr($question['points'] ?? 1) . '" min="1" class="small-text"></td>';
        echo '</tr>';
        
        echo '</table>';
        echo '<button type="button" class="remove-question button">Remove Question</button>';
        echo '<hr>';
        echo '</div>';
    }
    
    public function question_settings_meta_box($post) {
        wp_nonce_field('lms_question_meta', 'lms_question_meta_nonce');
        
        $question_type = get_post_meta($post->ID, '_lms_question_type', true);
        $options = get_post_meta($post->ID, '_lms_question_options', true);
        $correct_answer = get_post_meta($post->ID, '_lms_question_correct_answer', true);
        $explanation = get_post_meta($post->ID, '_lms_question_explanation', true);
        $points = get_post_meta($post->ID, '_lms_question_points', true);
        
        if (!is_array($options)) {
            $options = array('', '', '', '');
        }
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="lms_question_type">' . __('Question Type', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<select name="lms_question_type">';
        echo '<option value="multiple_choice" ' . selected($question_type, 'multiple_choice', false) . '>' . __('Multiple Choice', 'lms-auth') . '</option>';
        echo '<option value="true_false" ' . selected($question_type, 'true_false', false) . '>' . __('True/False', 'lms-auth') . '</option>';
        echo '<option value="short_answer" ' . selected($question_type, 'short_answer', false) . '>' . __('Short Answer', 'lms-auth') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr id="options-row" style="display: ' . ($question_type == 'multiple_choice' ? 'table-row' : 'none') . '">';
        echo '<th><label>' . __('Options', 'lms-auth') . '</label></th>';
        echo '<td>';
        for ($i = 0; $i < 4; $i++) {
            echo '<input type="text" name="lms_question_options[]" placeholder="' . sprintf(__('Option %d', 'lms-auth'), $i + 1) . '" value="' . esc_attr($options[$i]) . '" class="regular-text"><br>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_question_correct_answer">' . __('Correct Answer', 'lms-auth') . '</label></th>';
        echo '<td><input type="text" name="lms_question_correct_answer" value="' . esc_attr($correct_answer) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_question_explanation">' . __('Explanation', 'lms-auth') . '</label></th>';
        echo '<td><textarea name="lms_question_explanation" rows="3" class="large-text">' . esc_textarea($explanation) . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_question_points">' . __('Points', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" name="lms_question_points" value="' . esc_attr($points ?: 1) . '" min="1" class="small-text"></td>';
        echo '</tr>';
        
        echo '</table>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('select[name="lms_question_type"]').change(function() {
                if ($(this).val() == 'multiple_choice') {
                    $('#options-row').show();
                } else {
                    $('#options-row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    public function package_settings_meta_box($post) {
        wp_nonce_field('lms_package_meta', 'lms_package_meta_nonce');
        
        $price = get_post_meta($post->ID, '_lms_package_price', true);
        $duration = get_post_meta($post->ID, '_lms_package_duration', true);
        $duration_type = get_post_meta($post->ID, '_lms_package_duration_type', true);
        $included_assessments = get_post_meta($post->ID, '_lms_package_assessments', true);
        $included_courses = get_post_meta($post->ID, '_lms_package_courses', true);
        
        if (!is_array($included_assessments)) {
            $included_assessments = array();
        }
        if (!is_array($included_courses)) {
            $included_courses = array();
        }
        
        $assessments = get_posts(array(
            'post_type' => 'lms_assessment',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $courses = get_posts(array(
            'post_type' => 'lms_course',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="lms_package_price">' . __('Price ($)', 'lms-auth') . '</label></th>';
        echo '<td><input type="number" step="0.01" name="lms_package_price" value="' . esc_attr($price) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="lms_package_duration">' . __('Access Duration', 'lms-auth') . '</label></th>';
        echo '<td>';
        echo '<input type="number" name="lms_package_duration" value="' . esc_attr($duration ?: 12) . '" class="small-text"> ';
        echo '<select name="lms_package_duration_type">';
        echo '<option value="months" ' . selected($duration_type, 'months', false) . '>' . __('Months', 'lms-auth') . '</option>';
        echo '<option value="days" ' . selected($duration_type, 'days', false) . '>' . __('Days', 'lms-auth') . '</option>';
        echo '<option value="years" ' . selected($duration_type, 'years', false) . '>' . __('Years', 'lms-auth') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label>' . __('Included Assessments', 'lms-auth') . '</label></th>';
        echo '<td>';
        foreach ($assessments as $assessment) {
            $checked = in_array($assessment->ID, $included_assessments) ? 'checked' : '';
            echo '<label><input type="checkbox" name="lms_package_assessments[]" value="' . $assessment->ID . '" ' . $checked . '> ' . esc_html($assessment->post_title) . '</label><br>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label>' . __('Included Courses', 'lms-auth') . '</label></th>';
        echo '<td>';
        foreach ($courses as $course) {
            $checked = in_array($course->ID, $included_courses) ? 'checked' : '';
            echo '<label><input type="checkbox" name="lms_package_courses[]" value="' . $course->ID . '" ' . $checked . '> ' . esc_html($course->post_title) . '</label><br>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    public function save_post_meta($post_id) {
        // Check if it's an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $post_type = get_post_type($post_id);
        
        switch ($post_type) {
            case 'lms_course':
                $this->save_course_meta($post_id);
                break;
            case 'lms_lesson':
                $this->save_lesson_meta($post_id);
                break;
            case 'lms_assessment':
                $this->save_assessment_meta($post_id);
                break;
            case 'lms_question':
                $this->save_question_meta($post_id);
                break;
            case 'lms_package':
                $this->save_package_meta($post_id);
                break;
        }
    }
    
    private function save_course_meta($post_id) {
        if (!isset($_POST['lms_course_meta_nonce']) || !wp_verify_nonce($_POST['lms_course_meta_nonce'], 'lms_course_meta')) {
            return;
        }
        
        $fields = array(
            '_lms_course_is_free' => 'sanitize_text_field',
            '_lms_course_price' => 'floatval',
            '_lms_course_duration' => 'intval',
            '_lms_course_difficulty' => 'sanitize_text_field',
            '_lms_course_objectives' => 'sanitize_textarea_field',
            '_lms_course_requirements' => 'sanitize_textarea_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            $key = str_replace('_lms_course_', '', $field);
            if (isset($_POST['lms_course_' . $key])) {
                $value = call_user_func($sanitize_func, $_POST['lms_course_' . $key]);
                update_post_meta($post_id, $field, $value);
            }
        }
    }
    
    private function save_lesson_meta($post_id) {
        if (!isset($_POST['lms_lesson_meta_nonce']) || !wp_verify_nonce($_POST['lms_lesson_meta_nonce'], 'lms_lesson_meta')) {
            return;
        }
        
        $fields = array(
            '_lms_lesson_course' => 'intval',
            '_lms_lesson_type' => 'sanitize_text_field',
            '_lms_lesson_video_url' => 'esc_url_raw',
            '_lms_lesson_duration' => 'intval',
            '_lms_lesson_is_preview' => 'sanitize_text_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            $key = str_replace('_lms_lesson_', '', $field);
            if (isset($_POST['lms_lesson_' . $key])) {
                $value = call_user_func($sanitize_func, $_POST['lms_lesson_' . $key]);
                update_post_meta($post_id, $field, $value);
            }
        }
    }
    
    private function save_assessment_meta($post_id) {
        if (!isset($_POST['lms_assessment_meta_nonce']) || !wp_verify_nonce($_POST['lms_assessment_meta_nonce'], 'lms_assessment_meta')) {
            return;
        }
        
        $fields = array(
            '_lms_assessment_time_limit_type' => 'sanitize_text_field',
            '_lms_assessment_total_time' => 'intval',
            '_lms_assessment_per_question_time' => 'intval',
            '_lms_assessment_pass_percentage' => 'intval',
            '_lms_assessment_max_attempts' => 'intval',
            '_lms_assessment_randomize_questions' => 'sanitize_text_field',
            '_lms_assessment_show_correct_answers' => 'sanitize_text_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            $key = str_replace('_lms_assessment_', '', $field);
            if (isset($_POST['lms_assessment_' . $key])) {
                $value = call_user_func($sanitize_func, $_POST['lms_assessment_' . $key]);
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // Save questions
        if (isset($_POST['lms_assessment_questions']) && is_array($_POST['lms_assessment_questions'])) {
            $questions = array();
            foreach ($_POST['lms_assessment_questions'] as $question_data) {
                $question = array(
                    'type' => sanitize_text_field($question_data['type']),
                    'question' => sanitize_textarea_field($question_data['question']),
                    'correct_answer' => sanitize_text_field($question_data['correct_answer']),
                    'explanation' => sanitize_textarea_field($question_data['explanation']),
                    'points' => intval($question_data['points'])
                );
                
                if (isset($question_data['options']) && is_array($question_data['options'])) {
                    $question['options'] = array_map('sanitize_text_field', $question_data['options']);
                }
                
                $questions[] = $question;
            }
            update_post_meta($post_id, '_lms_assessment_questions', $questions);
        }
    }
    
    private function save_question_meta($post_id) {
        if (!isset($_POST['lms_question_meta_nonce']) || !wp_verify_nonce($_POST['lms_question_meta_nonce'], 'lms_question_meta')) {
            return;
        }
        
        $fields = array(
            '_lms_question_type' => 'sanitize_text_field',
            '_lms_question_correct_answer' => 'sanitize_text_field',
            '_lms_question_explanation' => 'sanitize_textarea_field',
            '_lms_question_points' => 'intval'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            $key = str_replace('_lms_question_', '', $field);
            if (isset($_POST['lms_question_' . $key])) {
                $value = call_user_func($sanitize_func, $_POST['lms_question_' . $key]);
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // Save options
        if (isset($_POST['lms_question_options']) && is_array($_POST['lms_question_options'])) {
            $options = array_map('sanitize_text_field', $_POST['lms_question_options']);
            update_post_meta($post_id, '_lms_question_options', $options);
        }
    }
    
    private function save_package_meta($post_id) {
        if (!isset($_POST['lms_package_meta_nonce']) || !wp_verify_nonce($_POST['lms_package_meta_nonce'], 'lms_package_meta')) {
            return;
        }
        
        $fields = array(
            '_lms_package_price' => 'floatval',
            '_lms_package_duration' => 'intval',
            '_lms_package_duration_type' => 'sanitize_text_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            $key = str_replace('_lms_package_', '', $field);
            if (isset($_POST['lms_package_' . $key])) {
                $value = call_user_func($sanitize_func, $_POST['lms_package_' . $key]);
                update_post_meta($post_id, $field, $value);
            }
        }
        
        // Save included assessments
        if (isset($_POST['lms_package_assessments']) && is_array($_POST['lms_package_assessments'])) {
            $assessments = array_map('intval', $_POST['lms_package_assessments']);
            update_post_meta($post_id, '_lms_package_assessments', $assessments);
        } else {
            delete_post_meta($post_id, '_lms_package_assessments');
        }
        
        // Save included courses
        if (isset($_POST['lms_package_courses']) && is_array($_POST['lms_package_courses'])) {
            $courses = array_map('intval', $_POST['lms_package_courses']);
            update_post_meta($post_id, '_lms_package_courses', $courses);
        } else {
            delete_post_meta($post_id, '_lms_package_courses');
        }
    }
}

