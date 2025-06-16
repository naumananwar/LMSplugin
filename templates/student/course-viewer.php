<?php
/**
 * Student Course Viewer Template
 * Interactive course consumption with lessons, progress tracking, and engagement features
 */

// Ensure user is logged in and is a student
if (!is_user_logged_in() || !current_user_can('student')) {
    wp_redirect(wp_login_url());
    exit;
}

$user_id = get_current_user_id();
$course_id = get_query_var('course_id', get_the_ID());
$lesson_id = get_query_var('lesson_id', '');

// Get course data
$course = get_post($course_id);
if (!$course || $course->post_type !== 'lms_course') {
    wp_redirect(home_url());
    exit;
}

// Check enrollment
global $wpdb;
$enrollment_table = $wpdb->prefix . 'lms_course_enrollments';
$is_enrolled = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $enrollment_table WHERE user_id = %d AND course_id = %d AND status = 'enrolled'",
    $user_id, $course_id
));

if (!$is_enrolled) {
    wp_redirect(home_url('/courses/'));
    exit;
}

// Get course lessons organized by sections
$lessons = get_posts(array(
    'post_type' => 'lms_lesson',
    'meta_query' => array(
        array(
            'key' => '_lms_lesson_course',
            'value' => $course_id,
            'compare' => '='
        )
    ),
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'posts_per_page' => -1
));

// Get current lesson or default to first lesson
if ($lesson_id) {
    $current_lesson = get_post($lesson_id);
} else if (!empty($lessons)) {
    $current_lesson = $lessons[0];
    $lesson_id = $current_lesson->ID;
} else {
    $current_lesson = null;
}

// Get lesson progress
$progress_table = $wpdb->prefix . 'lms_lesson_progress';
$lesson_progress = array();
foreach ($lessons as $lesson) {
    $progress = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $progress_table WHERE user_id = %d AND lesson_id = %d",
        $user_id, $lesson->ID
    ));
    $lesson_progress[$lesson->ID] = $progress;
}

// Get course notes
$notes_table = $wpdb->prefix . 'lms_course_notes';
$course_notes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $notes_table WHERE user_id = %d AND course_id = %d ORDER BY created_at DESC",
    $user_id, $course_id
));

// Get bookmarks
$bookmarks_table = $wpdb->prefix . 'lms_lesson_bookmarks';
$bookmarks = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $bookmarks_table WHERE user_id = %d AND course_id = %d ORDER BY created_at DESC",
    $user_id, $course_id
));

// Calculate overall progress
$total_lessons = count($lessons);
$completed_lessons = count(array_filter($lesson_progress, function($p) {
    return $p && $p->status === 'completed';
}));
$overall_progress = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
?>

<div class="lms-course-viewer">
    <!-- Course Header -->
    <div class="course-header">
        <div class="course-header-content">
            <div class="course-info">
                <h1><?php echo esc_html($course->post_title); ?></h1>
                <div class="course-meta">
                    <span class="instructor">By <?php echo get_the_author_meta('display_name', $course->post_author); ?></span>
                    <span class="progress">Progress: <?php echo $overall_progress; ?>%</span>
                    <span class="lessons-count"><?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?> lessons</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $overall_progress; ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="course-actions">
                <button class="btn-toggle-sidebar" id="toggleSidebar">
                    <i class="icon-menu"></i>
                </button>
                <button class="btn-toggle-notes" id="toggleNotes">
                    <i class="icon-notes"></i> Notes
                </button>
                <button class="btn-certificate" id="downloadCertificate" <?php echo $overall_progress < 100 ? 'disabled' : ''; ?>>
                    <i class="icon-certificate"></i> Certificate
                </button>
            </div>
        </div>
    </div>

    <div class="course-content-wrapper">
        <!-- Course Sidebar -->
        <div class="course-sidebar" id="courseSidebar">
            <div class="sidebar-content">
                <div class="course-navigation">
                    <h3>Course Content</h3>
                    <div class="lessons-list">
                        <?php 
                        $current_section = '';
                        foreach ($lessons as $index => $lesson): 
                            $lesson_section = get_post_meta($lesson->ID, '_lms_lesson_section', true) ?: 'Main Content';
                            $lesson_type = get_post_meta($lesson->ID, '_lms_lesson_type', true) ?: 'text';
                            $lesson_duration = get_post_meta($lesson->ID, '_lms_lesson_duration', true);
                            $is_preview = get_post_meta($lesson->ID, '_lms_lesson_is_preview', true);
                            $progress = $lesson_progress[$lesson->ID] ?? null;
                            
                            if ($lesson_section !== $current_section):
                                if ($current_section !== '') echo '</div></div>';
                                $current_section = $lesson_section;
                        ?>
                            <div class="lesson-section">
                                <div class="section-header">
                                    <h4><?php echo esc_html($lesson_section); ?></h4>
                                </div>
                                <div class="section-lessons">
                        <?php endif; ?>
                                    <div class="lesson-item <?php echo $lesson->ID == $lesson_id ? 'active' : ''; ?> <?php echo $progress && $progress->status === 'completed' ? 'completed' : ''; ?>" 
                                         data-lesson-id="<?php echo $lesson->ID; ?>">
                                        <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson->ID; ?>" class="lesson-link">
                                            <div class="lesson-info">
                                                <div class="lesson-status">
                                                    <?php if ($progress && $progress->status === 'completed'): ?>
                                                        <i class="icon-check-circle completed"></i>
                                                    <?php elseif ($progress && $progress->status === 'in_progress'): ?>
                                                        <i class="icon-play-circle in-progress"></i>
                                                    <?php else: ?>
                                                        <i class="icon-circle not-started"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="lesson-details">
                                                    <span class="lesson-title"><?php echo esc_html($lesson->post_title); ?></span>
                                                    <div class="lesson-meta">
                                                        <span class="lesson-type icon-<?php echo $lesson_type; ?>"></span>
                                                        <?php if ($lesson_duration): ?>
                                                            <span class="lesson-duration"><?php echo $lesson_duration; ?>min</span>
                                                        <?php endif; ?>
                                                        <?php if ($is_preview): ?>
                                                            <span class="preview-badge">Preview</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                        <div class="lesson-actions">
                                            <button class="btn-bookmark" data-lesson-id="<?php echo $lesson->ID; ?>" 
                                                    title="Bookmark this lesson">
                                                <i class="icon-bookmark"></i>
                                            </button>
                                        </div>
                                    </div>
                        <?php endforeach; ?>
                        <?php if ($current_section !== ''): ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Access -->
                <div class="quick-access">
                    <h4>Quick Access</h4>
                    <div class="quick-links">
                        <a href="#" class="quick-link" id="showBookmarks">
                            <i class="icon-bookmark"></i> Bookmarks (<?php echo count($bookmarks); ?>)
                        </a>
                        <a href="#" class="quick-link" id="showNotes">
                            <i class="icon-notes"></i> Notes (<?php echo count($course_notes); ?>)
                        </a>
                        <a href="#" class="quick-link" id="showProgress">
                            <i class="icon-chart"></i> Progress Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="course-main-content" id="courseMainContent">
            <?php if ($current_lesson): ?>
                <!-- Lesson Content -->
                <div class="lesson-content">
                    <div class="lesson-header">
                        <div class="lesson-info">
                            <h2><?php echo esc_html($current_lesson->post_title); ?></h2>
                            <div class="lesson-meta">
                                <?php 
                                $lesson_type = get_post_meta($current_lesson->ID, '_lms_lesson_type', true);
                                $lesson_duration = get_post_meta($current_lesson->ID, '_lms_lesson_duration', true);
                                $video_url = get_post_meta($current_lesson->ID, '_lms_lesson_video_url', true);
                                $current_progress = $lesson_progress[$current_lesson->ID] ?? null;
                                ?>
                                <span class="lesson-type"><?php echo ucfirst($lesson_type); ?></span>
                                <?php if ($lesson_duration): ?>
                                    <span class="duration"><?php echo $lesson_duration; ?> minutes</span>
                                <?php endif; ?>
                                <span class="status <?php echo $current_progress ? $current_progress->status : 'not-started'; ?>">
                                    <?php echo $current_progress ? ucfirst(str_replace('_', ' ', $current_progress->status)) : 'Not Started'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="lesson-actions">
                            <button class="btn-mark-complete" id="markComplete" 
                                    data-lesson-id="<?php echo $current_lesson->ID; ?>"
                                    <?php echo $current_progress && $current_progress->status === 'completed' ? 'disabled' : ''; ?>>
                                <?php echo $current_progress && $current_progress->status === 'completed' ? 'Completed' : 'Mark Complete'; ?>
                            </button>
                            <button class="btn-add-note" id="addNote">
                                <i class="icon-plus"></i> Add Note
                            </button>
                        </div>
                    </div>

                    <!-- Lesson Media/Content -->
                    <div class="lesson-media">
                        <?php if ($lesson_type === 'video' && $video_url): ?>
                            <div class="video-container">
                                <video controls id="lessonVideo" data-lesson-id="<?php echo $current_lesson->ID; ?>">
                                    <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                                <div class="video-controls">
                                    <button class="speed-control" data-speed="0.5">0.5x</button>
                                    <button class="speed-control active" data-speed="1">1x</button>
                                    <button class="speed-control" data-speed="1.25">1.25x</button>
                                    <button class="speed-control" data-speed="1.5">1.5x</button>
                                    <button class="speed-control" data-speed="2">2x</button>
                                    <button class="btn-transcript" id="toggleTranscript">Transcript</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lesson Text Content -->
                    <div class="lesson-text-content">
                        <?php echo apply_filters('the_content', $current_lesson->post_content); ?>
                    </div>

                    <!-- Lesson Navigation -->
                    <div class="lesson-navigation">
                        <?php 
                        $current_index = array_search($current_lesson->ID, array_column($lessons, 'ID'));
                        $prev_lesson = $current_index > 0 ? $lessons[$current_index - 1] : null;
                        $next_lesson = $current_index < count($lessons) - 1 ? $lessons[$current_index + 1] : null;
                        ?>
                        <div class="nav-buttons">
                            <?php if ($prev_lesson): ?>
                                <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $prev_lesson->ID; ?>" 
                                   class="btn btn-secondary prev-lesson">
                                    <i class="icon-arrow-left"></i> Previous: <?php echo esc_html($prev_lesson->post_title); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($next_lesson): ?>
                                <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $next_lesson->ID; ?>" 
                                   class="btn btn-primary next-lesson">
                                    Next: <?php echo esc_html($next_lesson->post_title); ?> <i class="icon-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-content">
                    <h2>No lessons available</h2>
                    <p>This course doesn't have any lessons yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notes Panel -->
        <div class="notes-panel" id="notesPanel">
            <div class="notes-header">
                <h3>Course Notes</h3>
                <button class="btn-close" id="closeNotes">
                    <i class="icon-x"></i>
                </button>
            </div>
            
            <!-- Add Note Form -->
            <div class="add-note-form" id="addNoteForm" style="display: none;">
                <form id="noteForm">
                    <textarea id="noteContent" placeholder="Add your note here..." rows="4"></textarea>
                    <div class="note-meta">
                        <select id="noteType">
                            <option value="general">General Note</option>
                            <option value="question">Question</option>
                            <option value="important">Important</option>
                            <option value="summary">Summary</option>
                        </select>
                        <input type="number" id="noteTimestamp" placeholder="Time (seconds)" min="0" 
                               title="Add timestamp for video notes">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelNote">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Note</button>
                    </div>
                </form>
            </div>
            
            <!-- Notes List -->
            <div class="notes-list" id="notesList">
                <?php foreach ($course_notes as $note): ?>
                    <div class="note-item" data-note-id="<?php echo $note->id; ?>">
                        <div class="note-header">
                            <span class="note-type <?php echo $note->note_type; ?>"><?php echo ucfirst($note->note_type); ?></span>
                            <?php if ($note->timestamp): ?>
                                <span class="note-timestamp"><?php echo gmdate('H:i:s', $note->timestamp); ?></span>
                            <?php endif; ?>
                            <span class="note-date"><?php echo date('M j, Y', strtotime($note->created_at)); ?></span>
                        </div>
                        <div class="note-content">
                            <?php echo nl2br(esc_html($note->content)); ?>
                        </div>
                        <div class="note-actions">
                            <button class="btn-edit-note" data-note-id="<?php echo $note->id; ?>">Edit</button>
                            <button class="btn-delete-note" data-note-id="<?php echo $note->id; ?>">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($course_notes)): ?>
                    <div class="empty-notes">
                        <p>No notes yet. Click "Add Note" to create your first note.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal" id="progressModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Course Progress Report</h3>
                <button class="btn-close" id="closeProgressModal">
                    <i class="icon-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="progress-stats">
                    <div class="stat-card">
                        <h4><?php echo $overall_progress; ?>%</h4>
                        <p>Overall Progress</p>
                    </div>
                    <div class="stat-card">
                        <h4><?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?></h4>
                        <p>Lessons Completed</p>
                    </div>
                    <div class="stat-card">
                        <h4><?php echo count($course_notes); ?></h4>
                        <p>Notes Created</p>
                    </div>
                    <div class="stat-card">
                        <h4><?php echo count($bookmarks); ?></h4>
                        <p>Bookmarks Added</p>
                    </div>
                </div>
                
                <div class="progress-chart">
                    <h4>Weekly Progress</h4>
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookmarks Modal -->
    <div class="modal" id="bookmarksModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bookmarks</h3>
                <button class="btn-close" id="closeBookmarksModal">
                    <i class="icon-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="bookmarks-list">
                    <?php foreach ($bookmarks as $bookmark): 
                        $bookmark_lesson = get_post($bookmark->lesson_id);
                    ?>
                        <div class="bookmark-item" data-bookmark-id="<?php echo $bookmark->id; ?>">
                            <div class="bookmark-info">
                                <h5><?php echo esc_html($bookmark_lesson->post_title); ?></h5>
                                <?php if ($bookmark->timestamp): ?>
                                    <span class="timestamp">At <?php echo gmdate('H:i:s', $bookmark->timestamp); ?></span>
                                <?php endif; ?>
                                <?php if ($bookmark->note): ?>
                                    <p class="bookmark-note"><?php echo esc_html($bookmark->note); ?></p>
                                <?php endif; ?>
                                <span class="bookmark-date"><?php echo date('M j, Y', strtotime($bookmark->created_at)); ?></span>
                            </div>
                            <div class="bookmark-actions">
                                <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $bookmark->lesson_id; ?>" 
                                   class="btn btn-primary btn-sm">Go to Lesson</a>
                                <button class="btn-delete-bookmark btn-sm" data-bookmark-id="<?php echo $bookmark->id; ?>">
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($bookmarks)): ?>
                        <div class="empty-bookmarks">
                            <p>No bookmarks yet. Use the bookmark button on lessons to save important moments.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.lms-course-viewer {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.course-header {
    background: #fff;
    border-bottom: 1px solid #e1e5e9;
    padding: 1rem 2rem;
    position: sticky;
    top: 0;
    z-index: 100;
}

.course-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
}

.course-info h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #2d3748;
}

.course-meta {
    display: flex;
    gap: 1rem;
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.progress-bar-container {
    width: 300px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4299e1, #3182ce);
    transition: width 0.3s ease;
}

.course-actions {
    display: flex;
    gap: 0.5rem;
}

.course-actions button {
    padding: 0.5rem 1rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.course-actions button:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.course-actions button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.course-content-wrapper {
    display: flex;
    flex: 1;
    height: calc(100vh - 120px);
    overflow: hidden;
}

.course-sidebar {
    width: 350px;
    background: #f7fafc;
    border-right: 1px solid #e2e8f0;
    overflow-y: auto;
    transition: transform 0.3s ease;
}

.course-sidebar.hidden {
    transform: translateX(-100%);
}

.sidebar-content {
    padding: 1.5rem;
}

.course-navigation h3 {
    margin: 0 0 1rem 0;
    color: #2d3748;
    font-size: 1.125rem;
}

.lesson-section {
    margin-bottom: 1.5rem;
}

.section-header h4 {
    margin: 0 0 0.75rem 0;
    color: #4a5568;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.lesson-item {
    margin-bottom: 0.5rem;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.lesson-item:hover {
    border-color: #cbd5e0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.lesson-item.active {
    border-color: #4299e1;
    background: #ebf8ff;
}

.lesson-item.completed {
    background: #f0fff4;
    border-color: #68d391;
}

.lesson-link {
    display: block;
    padding: 1rem;
    text-decoration: none;
    color: inherit;
}

.lesson-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.lesson-status i {
    font-size: 1.25rem;
}

.lesson-status .completed {
    color: #38a169;
}

.lesson-status .in-progress {
    color: #3182ce;
}

.lesson-status .not-started {
    color: #a0aec0;
}

.lesson-details {
    flex: 1;
}

.lesson-title {
    display: block;
    font-weight: 500;
    color: #2d3748;
    margin-bottom: 0.25rem;
}

.lesson-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: #6b7280;
}

.preview-badge {
    background: #fbb6ce;
    color: #702459;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.625rem;
    font-weight: 500;
}

.lesson-actions {
    padding: 0 1rem 1rem 1rem;
}

.btn-bookmark {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 3px;
    transition: color 0.2s;
}

.btn-bookmark:hover {
    color: #f59e0b;
}

.quick-access {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}

.quick-access h4 {
    margin: 0 0 1rem 0;
    color: #4a5568;
    font-size: 0.875rem;
    font-weight: 600;
}

.quick-links {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.quick-link {
    padding: 0.75rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #4a5568;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.quick-link:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
    text-decoration: none;
}

.course-main-content {
    flex: 1;
    overflow-y: auto;
    background: #fff;
}

.lesson-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.lesson-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.lesson-header h2 {
    margin: 0 0 0.5rem 0;
    color: #2d3748;
    font-size: 1.875rem;
}

.lesson-meta {
    display: flex;
    gap: 1rem;
    color: #6b7280;
    font-size: 0.875rem;
}

.lesson-meta .status {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 500;
}

.lesson-meta .status.completed {
    background: #c6f6d5;
    color: #2f855a;
}

.lesson-meta .status.in_progress {
    background: #bee3f8;
    color: #2c5aa0;
}

.lesson-meta .status.not-started {
    background: #e2e8f0;
    color: #4a5568;
}

.lesson-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-mark-complete {
    padding: 0.75rem 1.5rem;
    background: #48bb78;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-mark-complete:hover:not(:disabled) {
    background: #38a169;
}

.btn-mark-complete:disabled {
    background: #a0aec0;
    cursor: not-allowed;
}

.btn-add-note {
    padding: 0.75rem 1.5rem;
    background: #4299e1;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-add-note:hover {
    background: #3182ce;
}

.video-container {
    margin-bottom: 2rem;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.video-container video {
    width: 100%;
    max-height: 400px;
}

.video-controls {
    padding: 1rem;
    background: #2d3748;
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.speed-control {
    padding: 0.375rem 0.75rem;
    background: #4a5568;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.2s;
}

.speed-control:hover,
.speed-control.active {
    background: #4299e1;
}

.btn-transcript {
    margin-left: auto;
    padding: 0.375rem 0.75rem;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-transcript:hover {
    background: #4a5568;
}

.lesson-text-content {
    line-height: 1.6;
    color: #4a5568;
    margin-bottom: 3rem;
}

.lesson-text-content h3,
.lesson-text-content h4,
.lesson-text-content h5 {
    color: #2d3748;
    margin: 2rem 0 1rem 0;
}

.lesson-text-content p {
    margin-bottom: 1rem;
}

.lesson-text-content ul,
.lesson-text-content ol {
    margin: 1rem 0;
    padding-left: 2rem;
}

.lesson-text-content li {
    margin-bottom: 0.5rem;
}

.lesson-navigation {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

.nav-buttons {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.nav-buttons .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-buttons .btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
    border: 1px solid #cbd5e0;
}

.nav-buttons .btn-secondary:hover {
    background: #cbd5e0;
    text-decoration: none;
}

.nav-buttons .btn-primary {
    background: #4299e1;
    color: white;
    border: 1px solid #3182ce;
    margin-left: auto;
}

.nav-buttons .btn-primary:hover {
    background: #3182ce;
    text-decoration: none;
}

.notes-panel {
    width: 400px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.notes-panel.active {
    display: flex;
}

.notes-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notes-header h3 {
    margin: 0;
    color: #2d3748;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 3px;
    transition: color 0.2s;
}

.btn-close:hover {
    color: #2d3748;
}

.add-note-form {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.add-note-form textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    resize: vertical;
    font-family: inherit;
    margin-bottom: 1rem;
}

.note-meta {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.note-meta select,
.note-meta input {
    padding: 0.5rem;
    border: 1px solid #cbd5e0;
    border-radius: 4px;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.form-actions .btn {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}

.form-actions .btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
    border: 1px solid #cbd5e0;
}

.form-actions .btn-primary {
    background: #4299e1;
    color: white;
    border: 1px solid #3182ce;
}

.notes-list {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.note-item {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f7fafc;
    border-radius: 8px;
    border-left: 4px solid #cbd5e0;
}

.note-item.question {
    border-left-color: #f59e0b;
}

.note-item.important {
    border-left-color: #ef4444;
}

.note-item.summary {
    border-left-color: #8b5cf6;
}

.note-header {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.75rem;
    color: #6b7280;
}

.note-type {
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 500;
    text-transform: uppercase;
}

.note-type.general {
    background: #e2e8f0;
    color: #4a5568;
}

.note-type.question {
    background: #fef3c7;
    color: #92400e;
}

.note-type.important {
    background: #fecaca;
    color: #991b1b;
}

.note-type.summary {
    background: #e9d5ff;
    color: #6b21a8;
}

.note-content {
    color: #2d3748;
    line-height: 1.5;
    margin-bottom: 0.75rem;
}

.note-actions {
    display: flex;
    gap: 0.5rem;
}

.note-actions button {
    padding: 0.25rem 0.5rem;
    border: none;
    background: none;
    color: #6b7280;
    font-size: 0.75rem;
    cursor: pointer;
    border-radius: 3px;
    transition: color 0.2s;
}

.note-actions button:hover {
    color: #2d3748;
}

.empty-notes {
    text-align: center;
    color: #6b7280;
    padding: 2rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
}

.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    text-align: center;
    padding: 1.5rem;
    background: #f7fafc;
    border-radius: 8px;
}

.stat-card h4 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    color: #2d3748;
    font-weight: 700;
}

.stat-card p {
    margin: 0;
    color: #6b7280;
    font-size: 0.875rem;
}

.progress-chart {
    margin-top: 2rem;
}

.progress-chart h4 {
    margin: 0 0 1rem 0;
    color: #2d3748;
}

.bookmarks-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.bookmark-item {
    padding: 1rem;
    background: #f7fafc;
    border-radius: 8px;
    border-left: 4px solid #4299e1;
}

.bookmark-info h5 {
    margin: 0 0 0.5rem 0;
    color: #2d3748;
}

.bookmark-info .timestamp {
    color: #4299e1;
    font-weight: 500;
    font-size: 0.875rem;
}

.bookmark-note {
    margin: 0.5rem 0;
    color: #4a5568;
    font-style: italic;
}

.bookmark-date {
    color: #6b7280;
    font-size: 0.75rem;
}

.bookmark-actions {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 4px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.empty-bookmarks {
    text-align: center;
    color: #6b7280;
    padding: 2rem;
}

.no-content {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

@media (max-width: 768px) {
    .course-content-wrapper {
        flex-direction: column;
    }
    
    .course-sidebar {
        width: 100%;
        height: auto;
        order: 2;
    }
    
    .course-main-content {
        order: 1;
    }
    
    .notes-panel {
        width: 100%;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 200;
    }
    
    .lesson-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .nav-buttons {
        flex-direction: column;
    }
    
    .nav-buttons .btn-primary {
        margin-left: 0;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle sidebar
    $('#toggleSidebar').on('click', function() {
        $('#courseSidebar').toggleClass('hidden');
        $('#courseMainContent').toggleClass('full-width');
    });
    
    // Toggle notes panel
    $('#toggleNotes, #addNote').on('click', function() {
        $('#notesPanel').toggleClass('active');
        if ($('#notesPanel').hasClass('active')) {
            $('#addNoteForm').show();
        }
    });
    
    $('#closeNotes').on('click', function() {
        $('#notesPanel').removeClass('active');
        $('#addNoteForm').hide();
    });
    
    // Video controls
    $('.speed-control').on('click', function() {
        var speed = parseFloat($(this).data('speed'));
        var video = document.getElementById('lessonVideo');
        if (video) {
            video.playbackRate = speed;
            $('.speed-control').removeClass('active');
            $(this).addClass('active');
        }
    });
    
    // Video progress tracking
    $('#lessonVideo').on('timeupdate', function() {
        var video = this;
        var lessonId = $(video).data('lesson-id');
        var progress = (video.currentTime / video.duration) * 100;
        
        // Update progress every 10%
        if (progress > 0 && progress % 10 < 1) {
            updateLessonProgress(lessonId, video.currentTime, progress);
        }
    });
    
    // Mark lesson complete
    $('#markComplete').on('click', function() {
        var lessonId = $(this).data('lesson-id');
        markLessonComplete(lessonId);
    });
    
    // Note form handling
    $('#noteForm').on('submit', function(e) {
        e.preventDefault();
        var content = $('#noteContent').val().trim();
        var type = $('#noteType').val();
        var timestamp = $('#noteTimestamp').val();
        
        if (content) {
            saveNote(content, type, timestamp);
        }
    });
    
    $('#cancelNote').on('click', function() {
        $('#addNoteForm').hide();
        $('#noteForm')[0].reset();
    });
    
    // Bookmark functionality
    $('.btn-bookmark').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var lessonId = $(this).data('lesson-id');
        addBookmark(lessonId);
    });
    
    // Delete note
    $(document).on('click', '.btn-delete-note', function() {
        var noteId = $(this).data('note-id');
        if (confirm('Are you sure you want to delete this note?')) {
            deleteNote(noteId);
        }
    });
    
    // Delete bookmark
    $(document).on('click', '.btn-delete-bookmark', function() {
        var bookmarkId = $(this).data('bookmark-id');
        if (confirm('Are you sure you want to delete this bookmark?')) {
            deleteBookmark(bookmarkId);
        }
    });
    
    // Show progress modal
    $('#showProgress').on('click', function(e) {
        e.preventDefault();
        $('#progressModal').addClass('active');
        loadProgressChart();
    });
    
    $('#closeProgressModal').on('click', function() {
        $('#progressModal').removeClass('active');
    });
    
    // Show bookmarks modal
    $('#showBookmarks').on('click', function(e) {
        e.preventDefault();
        $('#bookmarksModal').addClass('active');
    });
    
    $('#closeBookmarksModal').on('click', function() {
        $('#bookmarksModal').removeClass('active');
    });
    
    // Show notes
    $('#showNotes').on('click', function(e) {
        e.preventDefault();
        $('#notesPanel').addClass('active');
    });
    
    // Close modals on outside click
    $('.modal').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
        }
    });
    
    // Auto-hide note form timestamp for video lessons
    var currentLessonType = '<?php echo $lesson_type ?? ''; ?>';
    if (currentLessonType === 'video') {
        $('#noteTimestamp').show().attr('placeholder', 'Video timestamp (seconds)');
    } else {
        $('#noteTimestamp').hide();
    }
    
    // Functions
    function updateLessonProgress(lessonId, currentTime, progress) {
        $.post(ajaxurl, {
            action: 'lms_update_lesson_progress',
            lesson_id: lessonId,
            current_time: currentTime,
            progress: progress,
            nonce: '<?php echo wp_create_nonce('lms_lesson_progress'); ?>'
        });
    }
    
    function markLessonComplete(lessonId) {
        $.post(ajaxurl, {
            action: 'lms_mark_lesson_complete',
            lesson_id: lessonId,
            nonce: '<?php echo wp_create_nonce('lms_lesson_complete'); ?>'
        }, function(response) {
            if (response.success) {
                $('#markComplete').text('Completed').prop('disabled', true);
                $(`.lesson-item[data-lesson-id="${lessonId}"]`).addClass('completed');
                location.reload(); // Refresh to update progress
            }
        });
    }
    
    function saveNote(content, type, timestamp) {
        $.post(ajaxurl, {
            action: 'lms_save_course_note',
            course_id: '<?php echo $course_id; ?>',
            lesson_id: '<?php echo $lesson_id; ?>',
            content: content,
            note_type: type,
            timestamp: timestamp,
            nonce: '<?php echo wp_create_nonce('lms_course_note'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload(); // Refresh to show new note
            }
        });
    }
    
    function deleteNote(noteId) {
        $.post(ajaxurl, {
            action: 'lms_delete_course_note',
            note_id: noteId,
            nonce: '<?php echo wp_create_nonce('lms_delete_note'); ?>'
        }, function(response) {
            if (response.success) {
                $(`.note-item[data-note-id="${noteId}"]`).remove();
            }
        });
    }
    
    function addBookmark(lessonId) {
        var video = document.getElementById('lessonVideo');
        var timestamp = video ? video.currentTime : null;
        var note = prompt('Add a note for this bookmark (optional):');
        
        $.post(ajaxurl, {
            action: 'lms_add_bookmark',
            course_id: '<?php echo $course_id; ?>',
            lesson_id: lessonId,
            timestamp: timestamp,
            note: note,
            nonce: '<?php echo wp_create_nonce('lms_bookmark'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Bookmark added successfully!');
            }
        });
    }
    
    function deleteBookmark(bookmarkId) {
        $.post(ajaxurl, {
            action: 'lms_delete_bookmark',
            bookmark_id: bookmarkId,
            nonce: '<?php echo wp_create_nonce('lms_delete_bookmark'); ?>'
        }, function(response) {
            if (response.success) {
                $(`.bookmark-item[data-bookmark-id="${bookmarkId}"]`).remove();
            }
        });
    }
    
    function loadProgressChart() {
        // This would load Chart.js and create a progress chart
        // Implementation depends on your specific requirements
        console.log('Loading progress chart...');
    }
});
</script>

