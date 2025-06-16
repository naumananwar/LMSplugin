jQuery(document).ready(function($) {
    $('.lms-assessment-container').each(function() {
        var $container = $(this);
        var assessmentId = $container.data('assessment-id');
        var nonce = $container.data('nonce');
        var ajaxUrl = $container.data('ajax-url');

        var currentAttemptId = null;
        var assessmentData = null;
        var userAnswers = {}; // To store answers like { questionIndex: { answer: 'value' } }
        var currentQuestionIndex = 0;
        var timerInterval = null;
        var questionTimerInterval = null;
        var timeSpent = 0;

        function initAssessment() {
            $container.html('<p>Loading assessment details...</p>');
            $.post(ajaxUrl, {
                action: 'lms_start_assessment',
                assessment_id: assessmentId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    assessmentData = response.data.assessment;
                    currentAttemptId = response.data.attempt_id;
                    if (response.data.existing_answers) {
                        userAnswers = response.data.existing_answers;
                    }
                    renderAssessment();
                    startTimer();
                } else {
                    $container.html('<p class="error">' + (response.data || 'Could not start assessment.') + '</p>');
                }
            }).fail(function() {
                $container.html('<p class="error">Error communicating with the server.</p>');
            });
        }

        function renderAssessment() {
            var html = '<h2>' + escapeHtml(assessmentData.title) + '</h2>';
            html += '<div id="lms-assessment-timer"></div>';
            html += '<div id="lms-assessment-questions"></div>';
            html += '<div id="lms-assessment-navigation"></div>';
            $container.html(html);
            renderQuestion(currentQuestionIndex);
        }

        function renderQuestion(index) {
            if (!assessmentData || !assessmentData.questions || index >= assessmentData.questions.length) {
                // No more questions or assessment not loaded
                return;
            }
            currentQuestionIndex = index;
            var question = assessmentData.questions[index];
            var questionHtml = '<div class="lms-question" data-question-index="' + index + '">';
            questionHtml += '<h3>Question ' + (index + 1) + ' of ' + assessmentData.questions.length + '</h3>';
            questionHtml += '<p class="lms-question-text">' + escapeHtml(question.question_text) + '</p>';
            
            // Per-question timer display (if applicable)
            if (assessmentData.time_limit_type === 'per_question' && assessmentData.per_question_time > 0) {
                questionHtml += '<div class="lms-question-timer" id="lms-qtimer-' + index + '"></div>';
                startQuestionTimer(index, assessmentData.per_question_time);
            }

            questionHtml += '<div class="lms-question-options">';
            var savedAnswerData = userAnswers[index] || {};
            var savedAnswer = savedAnswerData.answer || null;

            switch (question.type) {
                case 'multiple_choice':
                    question.options.forEach(function(option, optIndex) {
                        var isChecked = (savedAnswer === option);
                        questionHtml += '<label><input type="radio" name="question_' + index + '" value="' + escapeHtml(option) + '" ' + (isChecked ? 'checked' : '') + '> ' + escapeHtml(option) + '</label><br>';
                    });
                    break;
                case 'true_false':
                    questionHtml += '<label><input type="radio" name="question_' + index + '" value="true" ' + (savedAnswer === 'true' ? 'checked' : '') + '> True</label><br>';
                    questionHtml += '<label><input type="radio" name="question_' + index + '" value="false" ' + (savedAnswer === 'false' ? 'checked' : '') + '> False</label><br>';
                    break;
                case 'short_answer':
                    questionHtml += '<textarea name="question_' + index + '" rows="3" placeholder="Your answer...">' + escapeHtml(savedAnswer || '') + '</textarea>';
                    break;
            }
            questionHtml += '</div></div>';
            $('#lms-assessment-questions').html(questionHtml);
            renderNavigation();
        }
        
        function startQuestionTimer(questionIndex, seconds) {
            if (questionTimerInterval) {
                clearInterval(questionTimerInterval);
            }
            var timeLeft = seconds;
            var $timerDisplay = $('#lms-qtimer-' + questionIndex);
            if (!$timerDisplay.length) return;

            questionTimerInterval = setInterval(function() {
                var minutes = Math.floor(timeLeft / 60);
                var secs = timeLeft % 60;
                $timerDisplay.text('Time left for this question: ' + minutes + 'm ' + secs + 's');
                if (timeLeft <= 0) {
                    clearInterval(questionTimerInterval);
                    $timerDisplay.text('Time up for this question!');
                    saveCurrentAnswer(); // Save whatever is selected/typed
                    if (currentQuestionIndex < assessmentData.questions.length - 1) {
                        renderQuestion(currentQuestionIndex + 1);
                    } else {
                        submitAssessment(); // Auto-submit if it's the last question
                    }
                }
                timeLeft--;
            }, 1000);
        }


        function renderNavigation() {
            var navHtml = '';
            if (assessmentData.time_limit_type === 'total' && currentQuestionIndex > 0) {
                navHtml += '<button id="lms-prev-question">Previous</button> ';
            }
            if (currentQuestionIndex < assessmentData.questions.length - 1) {
                navHtml += '<button id="lms-next-question">Next</button> ';
            } else {
                navHtml += '<button id="lms-submit-assessment">Submit Assessment</button>';
            }
            $('#lms-assessment-navigation').html(navHtml);
        }

        function saveCurrentAnswer() {
            var questionIndex = currentQuestionIndex;
            var question = assessmentData.questions[questionIndex];
            var answerValue = null;

            switch (question.type) {
                case 'multiple_choice':
                case 'true_false':
                    answerValue = $('input[name="question_' + questionIndex + '"]:checked').val();
                    break;
                case 'short_answer':
                    answerValue = $('textarea[name="question_' + questionIndex + '"]').val();
                    break;
            }
            if (answerValue !== undefined && answerValue !== null) {
                 userAnswers[questionIndex] = { answer: answerValue };
            } else {
                // Ensure an entry exists even if no answer, to mark it as seen/attempted
                if (!userAnswers[questionIndex]) {
                     userAnswers[questionIndex] = { answer: null }; // Or some other placeholder
                }
            }


            // For "per_question" time, save answer immediately via AJAX
            if (assessmentData.time_limit_type === 'per_question') {
                $.post(ajaxUrl, {
                    action: 'lms_next_question', // or 'lms_save_answer' if preferred for this mode
                    attempt_id: currentAttemptId,
                    question_index: questionIndex,
                    answer_data: JSON.stringify({ answer: answerValue }),
                    nonce: nonce
                }, function(response) {
                    // Handle response if needed, e.g., log success or error
                    if (!response.success) {
                        console.error("Failed to save answer for question " + questionIndex, response.data);
                    }
                });
            }
            // For "total" time, you might save periodically or just before navigating
            // For simplicity, we'll save on navigation here.
        }

        function startTimer() {
            if (assessmentData.time_limit_type === 'total' && assessmentData.total_time > 0) {
                var totalSeconds = assessmentData.total_time * 60;
                timerInterval = setInterval(function() {
                    var minutes = Math.floor(totalSeconds / 60);
                    var seconds = totalSeconds % 60;
                    $('#lms-assessment-timer').text('Total Time Left: ' + minutes + 'm ' + seconds + 's');
                    timeSpent = (assessmentData.total_time * 60) - totalSeconds;

                    if (totalSeconds <= 0) {
                        clearInterval(timerInterval);
                        $('#lms-assessment-timer').text('Time is up!');
                        submitAssessment(); // Auto-submit
                    }
                    totalSeconds--;
                }, 1000);
            } else {
                 // For per-question time, total timeSpent is tracked by summing up time on each question
                 // or can be a simple counter if no per-question time.
                 // For now, we'll rely on the time_spent sent on final submission.
                 // If no overall timer, just track interaction time.
                var startTime = Date.now();
                timerInterval = setInterval(function() {
                    timeSpent = Math.floor((Date.now() - startTime) / 1000);
                }, 1000); // Update timeSpent every second
            }
        }

        function submitAssessment() {
            saveCurrentAnswer(); // Save the very last answer before submitting
            clearInterval(timerInterval);
            if(questionTimerInterval) clearInterval(questionTimerInterval);

            $container.html('<p>Submitting your assessment...</p>');
            $.post(ajaxUrl, {
                action: 'lms_submit_assessment',
                assessment_id: assessmentId,
                attempt_id: currentAttemptId,
                answers: JSON.stringify(userAnswers),
                time_spent: timeSpent, // Calculated time spent
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    var resultHtml = '<h2>Assessment Results</h2>';
                    resultHtml += '<p>Your Score: ' + parseFloat(response.data.score).toFixed(2) + '%</p>';
                    resultHtml += '<p>Status: ' + escapeHtml(response.data.status) + '</p>';
                    resultHtml += '<p>Correct Answers: ' + response.data.correct_answers + ' out of ' + response.data.total_questions + '</p>';
                    if (response.data.status === 'completed') {
                        resultHtml += '<p class="success">Congratulations, you passed!</p>';
                    } else {
                        resultHtml += '<p class="error">Unfortunately, you did not pass. Required: ' + response.data.pass_percentage + '%</p>';
                    }
                    // Add link to view all results or retake if allowed
                    $container.html(resultHtml);
                } else {
                    $container.html('<p class="error">' + (response.data || 'Could not submit assessment.') + '</p>');
                }
            }).fail(function() {
                $container.html('<p class="error">Error communicating with the server during submission.</p>');
            });
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return String(unsafe)
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // Event Handlers
        $container.on('click', '#lms-next-question', function() {
            saveCurrentAnswer();
            if (currentQuestionIndex < assessmentData.questions.length - 1) {
                renderQuestion(currentQuestionIndex + 1);
            }
        });

        $container.on('click', '#lms-prev-question', function() {
            // Only allow previous if not per-question time
            if (assessmentData.time_limit_type !== 'per_question') {
                saveCurrentAnswer();
                if (currentQuestionIndex > 0) {
                    renderQuestion(currentQuestionIndex - 1);
                }
            }
        });

        $container.on('click', '#lms-submit-assessment', function() {
            submitAssessment();
        });

        // Start the assessment process
        initAssessment();
    });
});
