<?php

namespace LMS_Auth\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Course_Builder {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_course_builder_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_save_course_structure', array($this, 'save_course_structure'));
        add_action('wp_ajax_get_course_structure', array($this, 'get_course_structure'));
        add_action('wp_ajax_duplicate_lesson', array($this, 'duplicate_lesson'));
        add_action('wp_ajax_reorder_lessons', array($this, 'reorder_lessons'));
        add_action('wp_ajax_bulk_edit_lessons', array($this, 'bulk_edit_lessons'));
        add_action('wp_ajax_generate_course_outline', array($this, 'generate_course_outline'));
        add_action('add_meta_boxes', array($this, 'add_course_builder_meta_box'));
        add_filter('post_row_actions', array($this, 'add_course_row_actions'), 10, 2);
    }
    
    public function add_course_builder_menu() {
        add_submenu_page(
            'edit.php?post_type=lms_course',
            __('Course Builder', 'lms-auth'),
            __('Course Builder', 'lms-auth'),
            'edit_posts',
            'course-builder',
            array($this, 'course_builder_page')
        );
    }
    
    public function enqueue_scripts() {
        if (is_admin() || $this->is_course_builder_page()) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_media();
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if (($post_type === 'lms_course' && in_array($hook, array('post.php', 'post-new.php'))) 
            || strpos($hook, 'course-builder') !== false) {
            
            wp_enqueue_script('lms-course-builder', 
                LMS_AUTH_PLUGIN_URL . 'assets/js/course-builder.js', 
                array('jquery', 'jquery-ui-sortable', 'wp-media'), 
                LMS_AUTH_VERSION, 
                true
            );
            
            wp_enqueue_style('lms-course-builder', 
                LMS_AUTH_PLUGIN_URL . 'assets/css/course-builder.css', 
                array(), 
                LMS_AUTH_VERSION
            );
            
            wp_localize_script('lms-course-builder', 'lms_course_builder', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lms_course_builder_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this lesson?', 'lms-auth'),
                    'saving' => __('Saving...', 'lms-auth'),
                    'saved' => __('Saved!', 'lms-auth'),
                    'error' => __('Error occurred while saving', 'lms-auth'),
                    'add_section' => __('Add Section', 'lms-auth'),
                    'add_lesson' => __('Add Lesson', 'lms-auth'),
                    'section_title' => __('Section Title', 'lms-auth'),
                    'lesson_title' => __('Lesson Title', 'lms-auth')
                )
            ));
        }
    }
    
    public function add_course_builder_meta_box() {
        add_meta_box(
            'lms_course_builder',
            __('Course Builder', 'lms-auth'),
            array($this, 'course_builder_meta_box'),
            'lms_course',
            'normal',
            'high'
        );
    }
    
    public function course_builder_meta_box($post) {
        wp_nonce_field('lms_course_builder_meta', 'lms_course_builder_meta_nonce');
        
        $course_structure = get_post_meta($post->ID, '_lms_course_structure', true);
        if (!$course_structure) {
            $course_structure = array();
        }
        
        echo '<div id="lms-course-builder-container">';
        echo '<div class="course-builder-toolbar">';
        echo '<button type="button" class="button button-primary" id="add-section">' . __('Add Section', 'lms-auth') . '</button> ';
        echo '<button type="button" class="button" id="add-lesson">' . __('Add Lesson', 'lms-auth') . '</button> ';
        echo '<button type="button" class="button" id="bulk-actions">' . __('Bulk Actions', 'lms-auth') . '</button> ';
        echo '<button type="button" class="button" id="preview-course">' . __('Preview Course', 'lms-auth') . '</button> ';
        echo '<button type="button" class="button" id="auto-generate">' . __('AI Generate Outline', 'lms-auth') . '</button>';
        echo '</div>';
        
        echo '<div id="course-structure" data-course-id="' . $post->ID . '">';
        $this->render_course_structure($course_structure, $post->ID);
        echo '</div>';
        
        echo '<div class="course-builder-sidebar">';
        echo '<h3>' . __('Course Statistics', 'lms-auth') . '</h3>';
        $this->render_course_stats($post->ID);
        echo '</div>';
        
        echo '</div>';
        
        // Hidden templates for JavaScript
        $this->render_templates();
    }
    
    private function render_course_structure($structure, $course_id) {
        if (empty($structure)) {
            echo '<div class="empty-course">';
            echo '<p>' . __('No content yet. Start building your course by adding sections and lessons.', 'lms-auth') . '</p>';
            echo '</div>';
            return;
        }
        
        foreach ($structure as $section_index => $section) {
            $this->render_section($section, $section_index, $course_id);
        }
    }
    
    private function render_section($section, $index, $course_id) {
        echo '<div class="course-section" data-section-index="' . $index . '">';
        echo '<div class="section-header">';
        echo '<div class="section-drag-handle">⋮⋮</div>';
        echo '<input type="text" class="section-title" value="' . esc_attr($section['title'] ?? '') . '" placeholder="' . __('Section Title', 'lms-auth') . '">';
        echo '<div class="section-actions">';
        echo '<button type="button" class="button-link add-lesson-to-section">' . __('Add Lesson', 'lms-auth') . '</button>';
        echo '<button type="button" class="button-link duplicate-section">' . __('Duplicate', 'lms-auth') . '</button>';
        echo '<button type="button" class="button-link delete-section text-danger">' . __('Delete', 'lms-auth') . '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="section-description">';
        echo '<textarea class="section-desc" placeholder="' . __('Section description (optional)', 'lms-auth') . '">' . esc_textarea($section['description'] ?? '') . '</textarea>';
        echo '</div>';
        
        echo '<div class="section-lessons sortable-lessons">';
        if (!empty($section['lessons'])) {
            foreach ($section['lessons'] as $lesson_index => $lesson) {
                $this->render_lesson($lesson, $lesson_index, $course_id);
            }
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_lesson($lesson, $index, $course_id) {
        $lesson_id = $lesson['id'] ?? 0;
        $lesson_post = $lesson_id ? get_post($lesson_id) : null;
        
        echo '<div class="course-lesson" data-lesson-index="' . $index . '" data-lesson-id="' . $lesson_id . '">';
        echo '<div class="lesson-drag-handle">⋮</div>';
        
        echo '<div class="lesson-content">';
        echo '<div class="lesson-header">';
        
        // Lesson type icon
        $lesson_type = $lesson['type'] ?? 'text';
        $type_icons = array(
            'video' => '🎥',
            'text' => '📄',
            'quiz' => '❓',
            'assignment' => '📝',
            'download' => '📎'
        );
        echo '<span class="lesson-type-icon">' . ($type_icons[$lesson_type] ?? '📄') . '</span>';
        
        echo '<input type="text" class="lesson-title" value="' . esc_attr($lesson['title'] ?? '') . '" placeholder="' . __('Lesson Title', 'lms-auth') . '">';
        
        echo '<div class="lesson-meta">';
        if ($lesson_post) {
            echo '<span class="lesson-status status-' . $lesson_post->post_status . '">' . ucfirst($lesson_post->post_status) . '</span>';
        }
        echo '<span class="lesson-duration">' . ($lesson['duration'] ?? '0') . ' min</span>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="lesson-controls">';
        echo '<select class="lesson-type-select">';
        echo '<option value="text"' . selected($lesson_type, 'text', false) . '>' . __('Text/Article', 'lms-auth') . '</option>';
        echo '<option value="video"' . selected($lesson_type, 'video', false) . '>' . __('Video', 'lms-auth') . '</option>';
        echo '<option value="quiz"' . selected($lesson_type, 'quiz', false) . '>' . __('Quiz', 'lms-auth') . '</option>';
        echo '<option value="assignment"' . selected($lesson_type, 'assignment', false) . '>' . __('Assignment', 'lms-auth') . '</option>';
        echo '<option value="download"' . selected($lesson_type, 'download', false) . '>' . __('Download', 'lms-auth') . '</option>';
        echo '</select>';
        
        echo '<input type="number" class="lesson-duration-input" value="' . esc_attr($lesson['duration'] ?? 0) . '" placeholder="Duration (min)" min="0">';
        
        echo '<label><input type="checkbox" class="lesson-preview"' . checked($lesson['is_preview'] ?? false, true, false) . '> ' . __('Free Preview', 'lms-auth') . '</label>';
        echo '</div>';
        
        // Lesson content based on type
        echo '<div class="lesson-details lesson-type-' . $lesson_type . '">';
        
        switch ($lesson_type) {
            case 'video':
                echo '<input type="url" class="lesson-video-url" value="' . esc_attr($lesson['video_url'] ?? '') . '" placeholder="' . __('Video URL', 'lms-auth') . '">';
                echo '<button type="button" class="button upload-video">' . __('Upload Video', 'lms-auth') . '</button>';
                break;
            case 'text':
                echo '<textarea class="lesson-content-text" placeholder="' . __('Lesson content...', 'lms-auth') . '">' . esc_textarea($lesson['content'] ?? '') . '</textarea>';
                break;
            case 'quiz':
                echo '<div class="quiz-builder">';
                echo '<p>' . __('Quiz questions will be managed separately in the Assessment section.', 'lms-auth') . '</p>';
                echo '</div>';
                break;
            case 'assignment':
                echo '<textarea class="assignment-instructions" placeholder="' . __('Assignment instructions...', 'lms-auth') . '">' . esc_textarea($lesson['instructions'] ?? '') . '</textarea>';
                echo '<input type="text" class="assignment-file-types" value="' . esc_attr($lesson['allowed_file_types'] ?? 'pdf,doc,docx') . '" placeholder="' . __('Allowed file types (comma separated)', 'lms-auth') . '">';
                break;
            case 'download':
                echo '<input type="url" class="download-file-url" value="' . esc_attr($lesson['file_url'] ?? '') . '" placeholder="' . __('File URL', 'lms-auth') . '">';
                echo '<button type="button" class="button upload-file">' . __('Upload File', 'lms-auth') . '</button>';
                break;
        }
        
        echo '</div>';
        
        echo '<div class="lesson-actions">';
        if ($lesson_id) {
            echo '<a href="' . get_edit_post_link($lesson_id) . '" class="button-link">' . __('Edit Full', 'lms-auth') . '</a> | ';
        }
        echo '<button type="button" class="button-link duplicate-lesson">' . __('Duplicate', 'lms-auth') . '</button> | ';
        echo '<button type="button" class="button-link delete-lesson text-danger">' . __('Delete', 'lms-auth') . '</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    private function render_course_stats($course_id) {
        $lessons = get_posts(array(
            'post_type' => 'lms_lesson',
            'meta_query' => array(
                array(
                    'key' => '_lms_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        $total_duration = 0;
        $published_lessons = 0;
        $draft_lessons = 0;
        
        foreach ($lessons as $lesson) {
            $duration = get_post_meta($lesson->ID, '_lms_lesson_duration', true);
            $total_duration += (int)$duration;
            
            if ($lesson->post_status === 'publish') {
                $published_lessons++;
            } else {
                $draft_lessons++;
            }
        }
        
        echo '<div class="course-stats">';
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . count($lessons) . '</span>';
        echo '<span class="stat-label">' . __('Total Lessons', 'lms-auth') . '</span>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $published_lessons . '</span>';
        echo '<span class="stat-label">' . __('Published', 'lms-auth') . '</span>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $draft_lessons . '</span>';
        echo '<span class="stat-label">' . __('Drafts', 'lms-auth') . '</span>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . round($total_duration / 60, 1) . 'h</span>';
        echo '<span class="stat-label">' . __('Total Duration', 'lms-auth') . '</span>';
        echo '</div>';
        echo '</div>';
    }
    
    private function render_templates() {
        echo '<script type="text/template" id="section-template">';
        echo '<div class="course-section" data-section-index="{{index}}">';
        echo '<div class="section-header">';
        echo '<div class="section-drag-handle">⋮⋮</div>';
        echo '<input type="text" class="section-title" value="" placeholder="' . __('Section Title', 'lms-auth') . '">';
        echo '<div class="section-actions">';
        echo '<button type="button" class="button-link add-lesson-to-section">' . __('Add Lesson', 'lms-auth') . '</button>';
        echo '<button type="button" class="button-link duplicate-section">' . __('Duplicate', 'lms-auth') . '</button>';
        echo '<button type="button" class="button-link delete-section text-danger">' . __('Delete', 'lms-auth') . '</button>';
        echo '</div></div>';
        echo '<div class="section-description"><textarea class="section-desc" placeholder="' . __('Section description', 'lms-auth') . '"></textarea></div>';
        echo '<div class="section-lessons sortable-lessons"></div></div>';
        echo '</script>';
        
        echo '<script type="text/template" id="lesson-template">';
        echo '<div class="course-lesson" data-lesson-index="{{index}}" data-lesson-id="0">';
        echo '<div class="lesson-drag-handle">⋮</div>';
        echo '<div class="lesson-content">';
        echo '<div class="lesson-header">';
        echo '<span class="lesson-type-icon">📄</span>';
        echo '<input type="text" class="lesson-title" value="" placeholder="' . __('Lesson Title', 'lms-auth') . '">';
        echo '<div class="lesson-meta"><span class="lesson-duration">0 min</span></div>';
        echo '</div>';
        echo '<div class="lesson-controls">';
        echo '<select class="lesson-type-select">';
        echo '<option value="text">' . __('Text/Article', 'lms-auth') . '</option>';
        echo '<option value="video">' . __('Video', 'lms-auth') . '</option>';
        echo '<option value="quiz">' . __('Quiz', 'lms-auth') . '</option>';
        echo '<option value="assignment">' . __('Assignment', 'lms-auth') . '</option>';
        echo '<option value="download">' . __('Download', 'lms-auth') . '</option>';
        echo '</select>';
        echo '<input type="number" class="lesson-duration-input" value="0" placeholder="Duration (min)" min="0">';
        echo '<label><input type="checkbox" class="lesson-preview"> ' . __('Free Preview', 'lms-auth') . '</label>';
        echo '</div>';
        echo '<div class="lesson-details lesson-type-text">';
        echo '<textarea class="lesson-content-text" placeholder="' . __('Lesson content...', 'lms-auth') . '"></textarea>';
        echo '</div>';
        echo '<div class="lesson-actions">';
        echo '<button type="button" class="button-link duplicate-lesson">' . __('Duplicate', 'lms-auth') . '</button> | ';
        echo '<button type="button" class="button-link delete-lesson text-danger">' . __('Delete', 'lms-auth') . '</button>';
        echo '</div></div></div>';
        echo '</script>';
    }
    
    public function course_builder_page() {
        $courses = get_posts(array(
            'post_type' => 'lms_course',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'private')
        ));
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Course Builder', 'lms-auth') . '</h1>';
        
        if (empty($courses)) {
            echo '<div class="notice notice-info">';
            echo '<p>' . sprintf(__('No courses found. <a href="%s">Create your first course</a> to get started.', 'lms-auth'), admin_url('post-new.php?post_type=lms_course')) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="course-builder-dashboard">';
            
            echo '<div class="course-grid">';
            foreach ($courses as $course) {
                $this->render_course_card($course);
            }
            echo '</div>';
            
            echo '<div class="course-builder-actions">';
            echo '<a href="' . admin_url('post-new.php?post_type=lms_course') . '" class="button button-primary button-hero">' . __('Create New Course', 'lms-auth') . '</a>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    private function render_course_card($course) {
        $lessons_count = $this->get_course_lessons_count($course->ID);
        $total_duration = $this->get_course_total_duration($course->ID);
        $thumbnail = get_the_post_thumbnail_url($course->ID, 'medium');
        $edit_url = admin_url('post.php?post=' . $course->ID . '&action=edit');
        
        echo '<div class="course-card">';
        
        if ($thumbnail) {
            echo '<div class="course-thumbnail"><img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($course->post_title) . '"></div>';
        } else {
            echo '<div class="course-thumbnail placeholder">📚</div>';
        }
        
        echo '<div class="course-info">';
        echo '<h3><a href="' . $edit_url . '">' . esc_html($course->post_title) . '</a></h3>';
        echo '<p class="course-status status-' . $course->post_status . '">' . ucfirst($course->post_status) . '</p>';
        
        echo '<div class="course-meta">';
        echo '<span class="lessons-count">' . sprintf(_n('%d lesson', '%d lessons', $lessons_count, 'lms-auth'), $lessons_count) . '</span>';
        echo '<span class="course-duration">' . sprintf(__('%s hours', 'lms-auth'), number_format($total_duration / 60, 1)) . '</span>';
        echo '</div>';
        
        echo '<div class="course-actions">';
        echo '<a href="' . $edit_url . '" class="button">' . __('Edit Course', 'lms-auth') . '</a>';
        echo '<a href="' . get_permalink($course->ID) . '" class="button" target="_blank">' . __('Preview', 'lms-auth') . '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    private function get_course_lessons_count($course_id) {
        $lessons = get_posts(array(
            'post_type' => 'lms_lesson',
            'meta_query' => array(
                array(
                    'key' => '_lms_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        return count($lessons);
    }
    
    private function get_course_total_duration($course_id) {
        $lessons = get_posts(array(
            'post_type' => 'lms_lesson',
            'meta_query' => array(
                array(
                    'key' => '_lms_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        $total_duration = 0;
        foreach ($lessons as $lesson) {
            $duration = get_post_meta($lesson->ID, '_lms_lesson_duration', true);
            $total_duration += (int)$duration;
        }
        
        return $total_duration;
    }
    
    public function add_course_row_actions($actions, $post) {
        if ($post->post_type === 'lms_course') {
            $actions['course_builder'] = '<a href="' . admin_url('edit.php?post_type=lms_course&page=course-builder&course_id=' . $post->ID) . '">' . __('Course Builder', 'lms-auth') . '</a>';
        }
        return $actions;
    }
    
    // AJAX Handlers
    public function save_course_structure() {
        check_ajax_referer('lms_course_builder_nonce', 'nonce');
        
        $course_id = intval($_POST['course_id']);
        $structure = $_POST['structure'];
        
        if (!current_user_can('edit_post', $course_id)) {
            wp_die(__('You do not have permission to edit this course.', 'lms-auth'));
        }
        
        // Sanitize and save structure
        $sanitized_structure = $this->sanitize_course_structure($structure);
        update_post_meta($course_id, '_lms_course_structure', $sanitized_structure);
        
        // Create/update actual lesson posts
        $this->sync_lessons_with_structure($course_id, $sanitized_structure);
        
        wp_send_json_success(array(
            'message' => __('Course structure saved successfully.', 'lms-auth')
        ));
    }
    
    public function get_course_structure() {
        check_ajax_referer('lms_course_builder_nonce', 'nonce');
        
        $course_id = intval($_POST['course_id']);
        $structure = get_post_meta($course_id, '_lms_course_structure', true);
        
        wp_send_json_success($structure ?: array());
    }
    
    public function duplicate_lesson() {
        check_ajax_referer('lms_course_builder_nonce', 'nonce');
        
        $lesson_id = intval($_POST['lesson_id']);
        $original_lesson = get_post($lesson_id);
        
        if (!$original_lesson) {
            wp_send_json_error(__('Lesson not found.', 'lms-auth'));
        }
        
        // Create duplicate
        $new_lesson_data = array(
            'post_title' => $original_lesson->post_title . ' (Copy)',
            'post_content' => $original_lesson->post_content,
            'post_status' => 'draft',
            'post_type' => 'lms_lesson',
            'post_author' => get_current_user_id()
        );
        
        $new_lesson_id = wp_insert_post($new_lesson_data);
        
        if ($new_lesson_id) {
            // Copy meta data
            $meta_data = get_post_meta($lesson_id);
            foreach ($meta_data as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($new_lesson_id, $key, $value);
                }
            }
            
            wp_send_json_success(array(
                'lesson_id' => $new_lesson_id,
                'message' => __('Lesson duplicated successfully.', 'lms-auth')
            ));
        } else {
            wp_send_json_error(__('Failed to duplicate lesson.', 'lms-auth'));
        }
    }
    
    public function reorder_lessons() {
        check_ajax_referer('lms_course_builder_nonce', 'nonce');
        
        $course_id = intval($_POST['course_id']);
        $lesson_order = array_map('intval', $_POST['lesson_order']);
        
        foreach ($lesson_order as $index => $lesson_id) {
            update_post_meta($lesson_id, '_lms_lesson_order', $index);
        }
        
        wp_send_json_success(__('Lessons reordered successfully.', 'lms-auth'));
    }
    
    public function bulk_edit_lessons() {
        check_ajax_referer('lms_course_builder_nonce', 'nonce');
        
        $lesson_ids = array_map('intval', $_POST['lesson_ids']);
        $action = sanitize_text_field($_POST['bulk_action']);
        
        $updated_count = 0;
        
        foreach ($lesson_ids as $lesson_id) {
            switch ($action) {
                case 'publish':
                    wp_update_post(array('ID' => $lesson_id, 'post_status' => 'publish'));
                    $updated_count++;
                    break;
                case 'draft':
                    wp_update_post(array('ID' => $lesson_id, 'post_status' => 'draft'));
                    $updated_count++;
                    break;
                case 'delete':
                    wp_delete_post($lesson_id, true);
                    $updated_count++;
                    break;
            }
        }
        
        wp_send_json_success(array(
            'updated_count' => $updated_count,
            'message' => sprintf(__('%d lessons updated successfully.', 'lms-auth'), $updated_count)
        ));
    }
    
    public function generate_course_outline() {
        check_ajax_referer('lms_course_builder_nonce', 'nonce');
        
        $course_id = intval($_POST['course_id']);
        $course_title = sanitize_text_field($_POST['course_title']);
        $course_description = sanitize_textarea_field($_POST['course_description']);
        $target_audience = sanitize_text_field($_POST['target_audience']);
        
        // This would integrate with AI service for generating course outline
        // For now, we'll provide a basic template
        $generated_structure = $this->generate_basic_course_outline($course_title, $course_description, $target_audience);
        
        wp_send_json_success(array(
            'structure' => $generated_structure,
            'message' => __('Course outline generated successfully.', 'lms-auth')
        ));
    }
    
    private function generate_basic_course_outline($title, $description, $audience) {
        // Basic template for course structure
        return array(
            array(
                'title' => __('Introduction', 'lms-auth'),
                'description' => __('Welcome to the course and overview of what students will learn.', 'lms-auth'),
                'lessons' => array(
                    array(
                        'title' => __('Welcome & Course Overview', 'lms-auth'),
                        'type' => 'video',
                        'duration' => 10,
                        'is_preview' => true
                    ),
                    array(
                        'title' => __('What You Will Learn', 'lms-auth'),
                        'type' => 'text',
                        'duration' => 5,
                        'is_preview' => true
                    )
                )
            ),
            array(
                'title' => __('Core Concepts', 'lms-auth'),
                'description' => __('Fundamental concepts and principles.', 'lms-auth'),
                'lessons' => array(
                    array(
                        'title' => __('Key Concept 1', 'lms-auth'),
                        'type' => 'video',
                        'duration' => 15
                    ),
                    array(
                        'title' => __('Key Concept 2', 'lms-auth'),
                        'type' => 'video',
                        'duration' => 15
                    ),
                    array(
                        'title' => __('Practice Exercise', 'lms-auth'),
                        'type' => 'assignment',
                        'duration' => 30
                    )
                )
            ),
            array(
                'title' => __('Practical Application', 'lms-auth'),
                'description' => __('Apply what you have learned in real-world scenarios.', 'lms-auth'),
                'lessons' => array(
                    array(
                        'title' => __('Hands-on Project', 'lms-auth'),
                        'type' => 'assignment',
                        'duration' => 60
                    ),
                    array(
                        'title' => __('Case Study Review', 'lms-auth'),
                        'type' => 'text',
                        'duration' => 20
                    )
                )
            ),
            array(
                'title' => __('Assessment & Conclusion', 'lms-auth'),
                'description' => __('Test your knowledge and wrap up the course.', 'lms-auth'),
                'lessons' => array(
                    array(
                        'title' => __('Final Quiz', 'lms-auth'),
                        'type' => 'quiz',
                        'duration' => 30
                    ),
                    array(
                        'title' => __('Course Summary & Next Steps', 'lms-auth'),
                        'type' => 'video',
                        'duration' => 10
                    )
                )
            )
        );
    }
    
    private function sanitize_course_structure($structure) {
        if (!is_array($structure)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($structure as $section) {
            if (!is_array($section)) continue;
            
            $sanitized_section = array(
                'title' => sanitize_text_field($section['title'] ?? ''),
                'description' => sanitize_textarea_field($section['description'] ?? ''),
                'lessons' => array()
            );
            
            if (isset($section['lessons']) && is_array($section['lessons'])) {
                foreach ($section['lessons'] as $lesson) {
                    if (!is_array($lesson)) continue;
                    
                    $sanitized_lesson = array(
                        'id' => intval($lesson['id'] ?? 0),
                        'title' => sanitize_text_field($lesson['title'] ?? ''),
                        'type' => sanitize_text_field($lesson['type'] ?? 'text'),
                        'duration' => intval($lesson['duration'] ?? 0),
                        'is_preview' => !empty($lesson['is_preview']),
                        'content' => sanitize_textarea_field($lesson['content'] ?? ''),
                        'video_url' => esc_url_raw($lesson['video_url'] ?? ''),
                        'file_url' => esc_url_raw($lesson['file_url'] ?? ''),
                        'instructions' => sanitize_textarea_field($lesson['instructions'] ?? ''),
                        'allowed_file_types' => sanitize_text_field($lesson['allowed_file_types'] ?? '')
                    );
                    
                    $sanitized_section['lessons'][] = $sanitized_lesson;
                }
            }
            
            $sanitized[] = $sanitized_section;
        }
        
        return $sanitized;
    }
    
    private function sync_lessons_with_structure($course_id, $structure) {
        foreach ($structure as $section_index => $section) {
            if (empty($section['lessons'])) continue;
            
            foreach ($section['lessons'] as $lesson_index => $lesson) {
                $lesson_id = $lesson['id'];
                
                $lesson_data = array(
                    'post_title' => $lesson['title'],
                    'post_content' => $lesson['content'],
                    'post_type' => 'lms_lesson',
                    'post_status' => 'draft'
                );
                
                if ($lesson_id) {
                    $lesson_data['ID'] = $lesson_id;
                    wp_update_post($lesson_data);
                } else {
                    $lesson_id = wp_insert_post($lesson_data);
                    
                    // Update the structure with the new lesson ID
                    $structure[$section_index]['lessons'][$lesson_index]['id'] = $lesson_id;
                }
                
                // Update lesson meta
                update_post_meta($lesson_id, '_lms_lesson_course', $course_id);
                update_post_meta($lesson_id, '_lms_lesson_type', $lesson['type']);
                update_post_meta($lesson_id, '_lms_lesson_duration', $lesson['duration']);
                update_post_meta($lesson_id, '_lms_lesson_is_preview', $lesson['is_preview'] ? '1' : '0');
                update_post_meta($lesson_id, '_lms_lesson_video_url', $lesson['video_url']);
                update_post_meta($lesson_id, '_lms_lesson_section_index', $section_index);
                update_post_meta($lesson_id, '_lms_lesson_order', $lesson_index);
            }
        }
        
        // Update the structure with any new lesson IDs
        update_post_meta($course_id, '_lms_course_structure', $structure);
    }
    
    private function is_course_builder_page() {
        return isset($_GET['page']) && $_GET['page'] === 'course-builder';
    }
}

