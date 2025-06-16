/* Course Builder JavaScript */

(function($) {
    'use strict';
    
    var CourseBuilder = {
        
        init: function() {
            this.bindEvents();
            this.initSortables();
            this.initMediaUpload();
        },
        
        bindEvents: function() {
            // Toolbar buttons
            $('#add-section').on('click', this.addSection.bind(this));
            $('#add-lesson').on('click', this.addLesson.bind(this));
            $('#bulk-actions').on('click', this.showBulkActions.bind(this));
            $('#preview-course').on('click', this.previewCourse.bind(this));
            $('#auto-generate').on('click', this.showGenerateDialog.bind(this));
            
            // Section events
            $(document).on('click', '.add-lesson-to-section', this.addLessonToSection.bind(this));
            $(document).on('click', '.duplicate-section', this.duplicateSection.bind(this));
            $(document).on('click', '.delete-section', this.deleteSection.bind(this));
            
            // Lesson events
            $(document).on('change', '.lesson-type-select', this.changeNotes.bind(this));
            $(document).on('change', '.lesson-duration-input', this.updateDuration.bind(this));
            $(document).on('click', '.duplicate-lesson', this.duplicateLesson.bind(this));
            $(document).on('click', '.delete-lesson', this.deleteLesson.bind(this));
            
            // Upload events
            $(document).on('click', '.upload-video', this.uploadVideo.bind(this));
            $(document).on('click', '.upload-file', this.uploadFile.bind(this));
            
            // Auto-save
            $(document).on('input change', '#course-structure input, #course-structure textarea, #course-structure select', 
                this.debounce(this.saveStructure.bind(this), 2000)
            );
            
            // Form submission
            $('#post').on('submit', this.onFormSubmit.bind(this));
        },
        
        initSortables: function() {
            // Make sections sortable
            $('#course-structure').sortable({
                items: '.course-section',
                handle: '.section-drag-handle',
                placeholder: 'section-placeholder',
                tolerance: 'pointer',
                update: this.saveStructure.bind(this)
            });
            
            // Make lessons sortable within sections
            $(document).on('mouseenter', '.section-lessons', function() {
                if (!$(this).hasClass('ui-sortable')) {
                    $(this).sortable({
                        items: '.course-lesson',
                        handle: '.lesson-drag-handle',
                        placeholder: 'lesson-placeholder',
                        tolerance: 'pointer',
                        connectWith: '.section-lessons',
                        update: CourseBuilder.saveStructure.bind(CourseBuilder)
                    });
                }
            });
        },
        
        initMediaUpload: function() {
            // Initialize WordPress media uploader
            this.mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Select Media',
                button: {
                    text: 'Use this media'
                },
                multiple: false
            });
        },
        
        addSection: function(e) {
            e.preventDefault();
            
            var template = $('#section-template').html();
            var sectionCount = $('.course-section').length;
            var html = template.replace(/{{index}}/g, sectionCount);
            
            $('#course-structure .empty-course').remove();
            $('#course-structure').append(html);
            
            // Focus on new section title
            $('.course-section:last .section-title').focus();
            
            this.saveStructure();
        },
        
        addLesson: function(e) {
            e.preventDefault();
            
            // Add to first section or create one if none exists
            var $firstSection = $('.course-section:first');
            if ($firstSection.length === 0) {
                this.addSection(e);
                $firstSection = $('.course-section:first');
            }
            
            this.addLessonToSection.call($firstSection.find('.add-lesson-to-section')[0], e);
        },
        
        addLessonToSection: function(e) {
            e.preventDefault();
            
            var $section = $(this).closest('.course-section');
            var $lessonContainer = $section.find('.section-lessons');
            
            var template = $('#lesson-template').html();
            var lessonCount = $lessonContainer.find('.course-lesson').length;
            var html = template.replace(/{{index}}/g, lessonCount);
            
            $lessonContainer.append(html);
            
            // Focus on new lesson title
            $lessonContainer.find('.course-lesson:last .lesson-title').focus();
            
            this.saveStructure();
        },
        
        duplicateSection: function(e) {
            e.preventDefault();
            
            var $section = $(this).closest('.course-section');
            var $clone = $section.clone();
            
            // Update indices and clear IDs
            $clone.find('.section-title').val($clone.find('.section-title').val() + ' (Copy)');
            $clone.find('[data-lesson-id]').attr('data-lesson-id', '0');
            
            $section.after($clone);
            this.saveStructure();
        },
        
        deleteSection: function(e) {
            e.preventDefault();
            
            if (!confirm(lms_course_builder.strings.confirm_delete)) {
                return;
            }
            
            $(this).closest('.course-section').fadeOut(300, function() {
                $(this).remove();
                CourseBuilder.saveStructure();
                
                // Show empty message if no sections left
                if ($('.course-section').length === 0) {
                    $('#course-structure').html('<div class="empty-course"><p>No content yet. Start building your course by adding sections and lessons.</p></div>');
                }
            });
        },
        
        changeLessonType: function(e) {
            var $select = $(this);
            var $lesson = $select.closest('.course-lesson');
            var type = $select.val();
            
            // Hide all lesson details
            $lesson.find('.lesson-details').removeClass('active');
            
            // Show relevant lesson details
            $lesson.find('.lesson-type-' + type).addClass('active');
            
            // Update lesson icon
            var icons = {
                'video': '🎥',
                'text': '📄',
                'quiz': '❓',
                'assignment': '📝',
                'download': '📎'
            };
            
            $lesson.find('.lesson-type-icon').text(icons[type] || '📄');
            
            this.saveStructure();
        },
        
        updateDuration: function(e) {
            var $input = $(this);
            var $lesson = $input.closest('.course-lesson');
            var duration = $input.val();
            
            $lesson.find('.lesson-duration').text(duration + ' min');
            
            this.saveStructure();
        },
        
        duplicateLesson: function(e) {
            e.preventDefault();
            
            var $lesson = $(this).closest('.course-lesson');
            var lessonId = $lesson.data('lesson-id');
            
            if (lessonId && lessonId !== '0') {
                // AJAX duplicate if lesson exists
                this.ajaxDuplicateLesson(lessonId, $lesson);
            } else {
                // Simple clone for new lessons
                var $clone = $lesson.clone();
                $clone.find('.lesson-title').val($clone.find('.lesson-title').val() + ' (Copy)');
                $clone.attr('data-lesson-id', '0');
                $lesson.after($clone);
                this.saveStructure();
            }
        },
        
        deleteLesson: function(e) {
            e.preventDefault();
            
            if (!confirm(lms_course_builder.strings.confirm_delete)) {
                return;
            }
            
            $(this).closest('.course-lesson').fadeOut(300, function() {
                $(this).remove();
                CourseBuilder.saveStructure();
            });
        },
        
        uploadVideo: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('.lesson-video-url');
            
            this.mediaUploader.on('select', function() {
                var attachment = CourseBuilder.mediaUploader.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                CourseBuilder.saveStructure();
            });
            
            this.mediaUploader.open();
        },
        
        uploadFile: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('.download-file-url');
            
            this.mediaUploader.on('select', function() {
                var attachment = CourseBuilder.mediaUploader.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                CourseBuilder.saveStructure();
            });
            
            this.mediaUploader.open();
        },
        
        showBulkActions: function(e) {
            e.preventDefault();
            
            var html = '<div id="bulk-actions-modal" class="lms-modal">' +
                '<div class="modal-content">' +
                '<h3>Bulk Actions</h3>' +
                '<div class="bulk-action-options">' +
                '<label><input type="radio" name="bulk_action" value="publish"> Publish selected lessons</label><br>' +
                '<label><input type="radio" name="bulk_action" value="draft"> Set as draft</label><br>' +
                '<label><input type="radio" name="bulk_action" value="delete"> Delete selected lessons</label><br>' +
                '</div>' +
                '<div class="lesson-selection">' +
                '<h4>Select Lessons:</h4>';
            
            $('.course-lesson').each(function() {
                var $lesson = $(this);
                var title = $lesson.find('.lesson-title').val() || 'Untitled Lesson';
                var lessonId = $lesson.data('lesson-id');
                
                if (lessonId && lessonId !== '0') {
                    html += '<label><input type="checkbox" class="lesson-checkbox" value="' + lessonId + '"> ' + title + '</label><br>';
                }
            });
            
            html += '</div>' +
                '<div class="modal-actions">' +
                '<button type="button" class="button button-primary" id="execute-bulk-action">Execute</button>' +
                '<button type="button" class="button" id="close-bulk-modal">Cancel</button>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(html);
            
            $('#close-bulk-modal').on('click', function() {
                $('#bulk-actions-modal').remove();
            });
            
            $('#execute-bulk-action').on('click', this.executeBulkAction.bind(this));
        },
        
        executeBulkAction: function(e) {
            e.preventDefault();
            
            var action = $('input[name="bulk_action"]:checked').val();
            var lessonIds = [];
            
            $('.lesson-checkbox:checked').each(function() {
                lessonIds.push($(this).val());
            });
            
            if (!action || lessonIds.length === 0) {
                alert('Please select an action and at least one lesson.');
                return;
            }
            
            $.ajax({
                url: lms_course_builder.ajax_url,
                type: 'POST',
                data: {
                    action: 'bulk_edit_lessons',
                    nonce: lms_course_builder.nonce,
                    bulk_action: action,
                    lesson_ids: lessonIds
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while performing bulk action.');
                }
            });
            
            $('#bulk-actions-modal').remove();
        },
        
        previewCourse: function(e) {
            e.preventDefault();
            
            var courseId = $('#course-structure').data('course-id');
            if (courseId) {
                window.open('/course-preview/' + courseId, '_blank');
            }
        },
        
        showGenerateDialog: function(e) {
            e.preventDefault();
            
            var courseTitle = $('#title').val() || '';
            var courseDescription = $('#content').val() || '';
            
            var html = '<div id="generate-modal" class="lms-modal">' +
                '<div class="modal-content">' +
                '<h3>AI Generate Course Outline</h3>' +
                '<div class="generate-form">' +
                '<label>Course Title:</label>' +
                '<input type="text" id="generate-title" value="' + courseTitle + '" class="widefat"><br><br>' +
                '<label>Course Description:</label>' +
                '<textarea id="generate-description" class="widefat" rows="4">' + courseDescription + '</textarea><br><br>' +
                '<label>Target Audience:</label>' +
                '<input type="text" id="generate-audience" placeholder="e.g., Beginners, Intermediate developers" class="widefat"><br><br>' +
                '</div>' +
                '<div class="modal-actions">' +
                '<button type="button" class="button button-primary" id="generate-outline">Generate Outline</button>' +
                '<button type="button" class="button" id="close-generate-modal">Cancel</button>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(html);
            
            $('#close-generate-modal').on('click', function() {
                $('#generate-modal').remove();
            });
            
            $('#generate-outline').on('click', this.generateCourseOutline.bind(this));
        },
        
        generateCourseOutline: function(e) {
            e.preventDefault();
            
            var courseId = $('#course-structure').data('course-id');
            var title = $('#generate-title').val();
            var description = $('#generate-description').val();
            var audience = $('#generate-audience').val();
            
            $('#generate-outline').text('Generating...').prop('disabled', true);
            
            $.ajax({
                url: lms_course_builder.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_course_outline',
                    nonce: lms_course_builder.nonce,
                    course_id: courseId,
                    course_title: title,
                    course_description: description,
                    target_audience: audience
                },
                success: function(response) {
                    if (response.success) {
                        CourseBuilder.loadStructure(response.data.structure);
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + response.data);
                    }
                    $('#generate-modal').remove();
                },
                error: function() {
                    alert('An error occurred while generating the outline.');
                    $('#generate-modal').remove();
                }
            });
        },
        
        ajaxDuplicateLesson: function(lessonId, $lesson) {
            $.ajax({
                url: lms_course_builder.ajax_url,
                type: 'POST',
                data: {
                    action: 'duplicate_lesson',
                    nonce: lms_course_builder.nonce,
                    lesson_id: lessonId
                },
                success: function(response) {
                    if (response.success) {
                        // Clone the lesson and update with new ID
                        var $clone = $lesson.clone();
                        $clone.find('.lesson-title').val($clone.find('.lesson-title').val() + ' (Copy)');
                        $clone.attr('data-lesson-id', response.data.lesson_id);
                        $lesson.after($clone);
                        
                        CourseBuilder.saveStructure();
                    } else {
                        alert('Error duplicating lesson: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while duplicating the lesson.');
                }
            });
        },
        
        loadStructure: function(structure) {
            // Clear current structure
            $('#course-structure').empty();
            
            if (!structure || structure.length === 0) {
                $('#course-structure').html('<div class="empty-course"><p>No content yet. Start building your course by adding sections and lessons.</p></div>');
                return;
            }
            
            // Render structure
            $.each(structure, function(sectionIndex, section) {
                CourseBuilder.renderSection(section, sectionIndex);
            });
            
            this.initSortables();
        },
        
        renderSection: function(section, sectionIndex) {
            var sectionHtml = '<div class="course-section" data-section-index="' + sectionIndex + '">';
            sectionHtml += '<div class="section-header">';
            sectionHtml += '<div class="section-drag-handle">⋮⋮</div>';
            sectionHtml += '<input type="text" class="section-title" value="' + (section.title || '') + '" placeholder="Section Title">';
            sectionHtml += '<div class="section-actions">';
            sectionHtml += '<button type="button" class="button-link add-lesson-to-section">Add Lesson</button>';
            sectionHtml += '<button type="button" class="button-link duplicate-section">Duplicate</button>';
            sectionHtml += '<button type="button" class="button-link delete-section text-danger">Delete</button>';
            sectionHtml += '</div></div>';
            sectionHtml += '<div class="section-description"><textarea class="section-desc" placeholder="Section description">' + (section.description || '') + '</textarea></div>';
            sectionHtml += '<div class="section-lessons sortable-lessons">';
            
            if (section.lessons && section.lessons.length > 0) {
                $.each(section.lessons, function(lessonIndex, lesson) {
                    sectionHtml += CourseBuilder.renderLesson(lesson, lessonIndex);
                });
            }
            
            sectionHtml += '</div></div>';
            
            $('#course-structure').append(sectionHtml);
        },
        
        renderLesson: function(lesson, lessonIndex) {
            var icons = {
                'video': '🎥',
                'text': '📄',
                'quiz': '❓',
                'assignment': '📝',
                'download': '📎'
            };
            
            var lessonType = lesson.type || 'text';
            var icon = icons[lessonType] || '📄';
            
            var html = '<div class="course-lesson" data-lesson-index="' + lessonIndex + '" data-lesson-id="' + (lesson.id || '0') + '">';
            html += '<div class="lesson-drag-handle">⋮</div>';
            html += '<div class="lesson-content">';
            html += '<div class="lesson-header">';
            html += '<span class="lesson-type-icon">' + icon + '</span>';
            html += '<input type="text" class="lesson-title" value="' + (lesson.title || '') + '" placeholder="Lesson Title">';
            html += '<div class="lesson-meta">';
            html += '<span class="lesson-duration">' + (lesson.duration || '0') + ' min</span>';
            html += '</div></div>';
            
            // Add lesson controls and details based on type
            // ... (similar to PHP render_lesson method)
            
            html += '</div></div>';
            
            return html;
        },
        
        getStructureData: function() {
            var structure = [];
            
            $('#course-structure .course-section').each(function() {
                var $section = $(this);
                var section = {
                    title: $section.find('.section-title').val(),
                    description: $section.find('.section-desc').val(),
                    lessons: []
                };
                
                $section.find('.course-lesson').each(function() {
                    var $lesson = $(this);
                    var lesson = {
                        id: $lesson.data('lesson-id') || 0,
                        title: $lesson.find('.lesson-title').val(),
                        type: $lesson.find('.lesson-type-select').val(),
                        duration: parseInt($lesson.find('.lesson-duration-input').val()) || 0,
                        is_preview: $lesson.find('.lesson-preview').is(':checked'),
                        content: $lesson.find('.lesson-content-text').val() || '',
                        video_url: $lesson.find('.lesson-video-url').val() || '',
                        file_url: $lesson.find('.download-file-url').val() || '',
                        instructions: $lesson.find('.assignment-instructions').val() || '',
                        allowed_file_types: $lesson.find('.assignment-file-types').val() || ''
                    };
                    
                    section.lessons.push(lesson);
                });
                
                structure.push(section);
            });
            
            return structure;
        },
        
        saveStructure: function() {
            var courseId = $('#course-structure').data('course-id');
            if (!courseId) return;
            
            var structure = this.getStructureData();
            
            // Show saving indicator
            this.showSavingIndicator(lms_course_builder.strings.saving);
            
            $.ajax({
                url: lms_course_builder.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_course_structure',
                    nonce: lms_course_builder.nonce,
                    course_id: courseId,
                    structure: structure
                },
                success: function(response) {
                    if (response.success) {
                        CourseBuilder.showSavingIndicator(lms_course_builder.strings.saved, 'success');
                    } else {
                        CourseBuilder.showSavingIndicator(lms_course_builder.strings.error, 'error');
                    }
                },
                error: function() {
                    CourseBuilder.showSavingIndicator(lms_course_builder.strings.error, 'error');
                }
            });
        },
        
        showSavingIndicator: function(message, type) {
            $('#save-indicator').remove();
            
            var className = 'save-indicator';
            if (type === 'success') className += ' success';
            if (type === 'error') className += ' error';
            
            var $indicator = $('<div id="save-indicator" class="' + className + '">' + message + '</div>');
            $('body').append($indicator);
            
            if (type === 'success' || type === 'error') {
                setTimeout(function() {
                    $indicator.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 2000);
            }
        },
        
        onFormSubmit: function(e) {
            // Save structure before form submission
            this.saveStructure();
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#course-structure').length > 0) {
            CourseBuilder.init();
        }
    });
    
})(jQuery);

