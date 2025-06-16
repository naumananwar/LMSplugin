<?php
/**
 * Instructor Courses Management Template
 */

// Ensure user is logged in and is an instructor
if (!is_user_logged_in() || !current_user_can('instructor')) {
    wp_redirect(wp_login_url());
    exit;
}

$user = wp_get_current_user();
$user_id = $user->ID;

$error_message = '';
$success_message = '';

// Handle course creation
if (isset($_POST['create_course'])) {
    $course_title = sanitize_text_field($_POST['course_title']);
    $course_description = sanitize_textarea_field($_POST['course_description']);
    $course_category = sanitize_text_field($_POST['course_category']);
    $course_level = sanitize_text_field($_POST['course_level']);
    $course_duration = sanitize_text_field($_POST['course_duration']);
    
    if (empty($course_title) || empty($course_description)) {
        $error_message = __('Course title and description are required.', 'lms-authentication-system');
    } else {
        // Here you would insert into courses table
        // For now, just show success message
        $success_message = __('Course created successfully!', 'lms-authentication-system');
    }
}

// Get instructor's courses (placeholder data)
$courses = array(
    (object) array(
        'id' => 1,
        'title' => 'Introduction to Web Development',
        'description' => 'Learn the fundamentals of web development including HTML, CSS, and JavaScript.',
        'students_count' => 45,
        'assignments_count' => 8,
        'lessons_count' => 24,
        'status' => 'active',
        'created_date' => '2024-01-01',
        'category' => 'Programming',
        'level' => 'Beginner'
    ),
    (object) array(
        'id' => 2,
        'title' => 'Advanced PHP Programming',
        'description' => 'Deep dive into advanced PHP concepts and frameworks.',
        'students_count' => 28,
        'assignments_count' => 12,
        'lessons_count' => 32,
        'status' => 'active',
        'created_date' => '2024-01-05',
        'category' => 'Programming',
        'level' => 'Advanced'
    ),
    (object) array(
        'id' => 3,
        'title' => 'Database Design Fundamentals',
        'description' => 'Learn how to design efficient and scalable databases.',
        'students_count' => 32,
        'assignments_count' => 6,
        'lessons_count' => 18,
        'status' => 'draft',
        'created_date' => '2024-01-10',
        'category' => 'Database',
        'level' => 'Intermediate'
    )
);
?>

<div class="lms-instructor-courses">
    <div class="courses-header">
        <h1><?php _e('My Courses', 'lms-authentication-system'); ?></h1>
        <button id="create-course-btn" class="btn btn-primary"><?php _e('Create New Course', 'lms-authentication-system'); ?></button>
    </div>
    
    <?php if ($error_message): ?>
        <div class="lms-error"><?php echo esc_html($error_message); ?></div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="lms-success"><?php echo esc_html($success_message); ?></div>
    <?php endif; ?>
    
    <!-- Course Creation Modal -->
    <div id="course-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Create New Course', 'lms-authentication-system'); ?></h2>
                <span class="close">&times;</span>
            </div>
            <form method="post" action="" class="course-form">
                <div class="form-group">
                    <label for="course_title"><?php _e('Course Title', 'lms-authentication-system'); ?></label>
                    <input type="text" id="course_title" name="course_title" required>
                </div>
                
                <div class="form-group">
                    <label for="course_description"><?php _e('Course Description', 'lms-authentication-system'); ?></label>
                    <textarea id="course_description" name="course_description" rows="4" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_category"><?php _e('Category', 'lms-authentication-system'); ?></label>
                        <select id="course_category" name="course_category">
                            <option value="Programming"><?php _e('Programming', 'lms-authentication-system'); ?></option>
                            <option value="Database"><?php _e('Database', 'lms-authentication-system'); ?></option>
                            <option value="Web Design"><?php _e('Web Design', 'lms-authentication-system'); ?></option>
                            <option value="Mobile Development"><?php _e('Mobile Development', 'lms-authentication-system'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_level"><?php _e('Level', 'lms-authentication-system'); ?></label>
                        <select id="course_level" name="course_level">
                            <option value="Beginner"><?php _e('Beginner', 'lms-authentication-system'); ?></option>
                            <option value="Intermediate"><?php _e('Intermediate', 'lms-authentication-system'); ?></option>
                            <option value="Advanced"><?php _e('Advanced', 'lms-authentication-system'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="course_duration"><?php _e('Duration (weeks)', 'lms-authentication-system'); ?></label>
                    <input type="number" id="course_duration" name="course_duration" min="1" max="52">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary cancel-btn"><?php _e('Cancel', 'lms-authentication-system'); ?></button>
                    <input type="submit" name="create_course" value="<?php _e('Create Course', 'lms-authentication-system'); ?>" class="btn btn-primary">
                </div>
            </form>
        </div>
    </div>
    
    <!-- Courses List -->
    <div class="courses-filters">
        <div class="filter-group">
            <label for="status-filter"><?php _e('Status:', 'lms-authentication-system'); ?></label>
            <select id="status-filter">
                <option value="all"><?php _e('All Courses', 'lms-authentication-system'); ?></option>
                <option value="active"><?php _e('Active', 'lms-authentication-system'); ?></option>
                <option value="draft"><?php _e('Draft', 'lms-authentication-system'); ?></option>
                <option value="archived"><?php _e('Archived', 'lms-authentication-system'); ?></option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="search-courses"><?php _e('Search:', 'lms-authentication-system'); ?></label>
            <input type="text" id="search-courses" placeholder="<?php _e('Search courses...', 'lms-authentication-system'); ?>">
        </div>
    </div>
    
    <div class="courses-list">
        <?php foreach ($courses as $course): ?>
            <div class="course-item" data-status="<?php echo $course->status; ?>">
                <div class="course-info">
                    <h3><?php echo esc_html($course->title); ?></h3>
                    <p class="course-description"><?php echo esc_html($course->description); ?></p>
                    <div class="course-meta">
                        <span class="category"><?php echo esc_html($course->category); ?></span>
                        <span class="level"><?php echo esc_html($course->level); ?></span>
                        <span class="status status-<?php echo $course->status; ?>"><?php echo ucfirst($course->status); ?></span>
                    </div>
                </div>
                
                <div class="course-stats">
                    <div class="stat">
                        <strong><?php echo $course->students_count; ?></strong>
                        <span><?php _e('Students', 'lms-authentication-system'); ?></span>
                    </div>
                    <div class="stat">
                        <strong><?php echo $course->lessons_count; ?></strong>
                        <span><?php _e('Lessons', 'lms-authentication-system'); ?></span>
                    </div>
                    <div class="stat">
                        <strong><?php echo $course->assignments_count; ?></strong>
                        <span><?php _e('Assignments', 'lms-authentication-system'); ?></span>
                    </div>
                </div>
                
                <div class="course-actions">
                    <a href="#" class="btn btn-primary"><?php _e('Edit', 'lms-authentication-system'); ?></a>
                    <a href="#" class="btn btn-secondary"><?php _e('Manage', 'lms-authentication-system'); ?></a>
                    <a href="#" class="btn btn-outline"><?php _e('View', 'lms-authentication-system'); ?></a>
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle">⋮</button>
                        <div class="dropdown-menu">
                            <a href="#"><?php _e('Duplicate', 'lms-authentication-system'); ?></a>
                            <a href="#"><?php _e('Export', 'lms-authentication-system'); ?></a>
                            <a href="#"><?php _e('Analytics', 'lms-authentication-system'); ?></a>
                            <a href="#" class="danger"><?php _e('Delete', 'lms-authentication-system'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('course-modal');
    const createBtn = document.getElementById('create-course-btn');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.querySelector('.cancel-btn');
    const statusFilter = document.getElementById('status-filter');
    const searchInput = document.getElementById('search-courses');
    
    // Modal functionality
    createBtn.addEventListener('click', function() {
        modal.style.display = 'block';
    });
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Filter functionality
    statusFilter.addEventListener('change', function() {
        const filterValue = this.value;
        const courseItems = document.querySelectorAll('.course-item');
        
        courseItems.forEach(item => {
            if (filterValue === 'all' || item.dataset.status === filterValue) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Search functionality
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const courseItems = document.querySelectorAll('.course-item');
        
        courseItems.forEach(item => {
            const title = item.querySelector('h3').textContent.toLowerCase();
            const description = item.querySelector('.course-description').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Dropdown functionality
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            dropdown.classList.toggle('show');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.dropdown-toggle')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
});
</script>

