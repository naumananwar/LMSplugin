/**
 * LMS Authentication System Frontend JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        LMSAuth.init();
    });
    
    // Main LMS Auth object
    window.LMSAuth = {
        
        // Initialize all frontend functionality
        init: function() {
            this.initAjaxForms();
            this.initDashboards();
            this.initPackages();
            this.initAssessments();
            this.initCharts();
            this.initUtilities();
        },
        
        // Initialize AJAX forms
        initAjaxForms: function() {
            // Login form
            $(document).on('submit', '#lms-login-form-element', function(e) {
                e.preventDefault();
                LMSAuth.handleLogin($(this));
            });
            
            // Registration form
            $(document).on('submit', '#lms-register-form-element', function(e) {
                e.preventDefault();
                LMSAuth.handleRegistration($(this));
            });
            
            // Social login buttons
            $(document).on('click', '#google-login, #google-register', function(e) {
                e.preventDefault();
                LMSAuth.handleGoogleLogin();
            });
            
            $(document).on('click', '#facebook-login, #facebook-register', function(e) {
                e.preventDefault();
                LMSAuth.handleFacebookLogin();
            });
            
            $(document).on('click', '#apple-login, #apple-register', function(e) {
                e.preventDefault();
                LMSAuth.handleAppleLogin();
            });
        },
        
        // Handle login form submission
        handleLogin: function($form) {
            var $submitBtn = $form.find('button[type="submit"]');
            var $messages = $('#lms-login-messages');
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Logging in...');
            $messages.empty();
            
            var formData = $form.serialize();
            formData += '&action=lms_login_user';
            
            $.post(lms_ajax.ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        $messages.html('<div class="alert alert-success">' + response.data.message + '</div>');
                        // Redirect after a short delay
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    } else {
                        $messages.html('<div class="alert alert-error">' + response.data + '</div>');
                        $submitBtn.prop('disabled', false).html('Login');
                    }
                })
                .fail(function() {
                    $messages.html('<div class="alert alert-error">An error occurred. Please try again.</div>');
                    $submitBtn.prop('disabled', false).html('Login');
                });
        },
        
        // Handle registration form submission
        handleRegistration: function($form) {
            var $submitBtn = $form.find('button[type="submit"]');
            var $messages = $('#lms-register-messages');
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Creating Account...');
            $messages.empty();
            
            var formData = $form.serialize();
            formData += '&action=lms_register_user';
            
            $.post(lms_ajax.ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        $messages.html('<div class="alert alert-success">' + response.data.message + '</div>');
                        // Redirect after a short delay
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    } else {
                        $messages.html('<div class="alert alert-error">' + response.data + '</div>');
                        $submitBtn.prop('disabled', false).html('Register');
                    }
                })
                .fail(function() {
                    $messages.html('<div class="alert alert-error">An error occurred. Please try again.</div>');
                    $submitBtn.prop('disabled', false).html('Register');
                });
        },
        
        // Handle Google login
        handleGoogleLogin: function() {
            if (typeof gapi === 'undefined') {
                alert('Google Sign-In is not loaded. Please refresh the page.');
                return;
            }
            
            var auth2 = gapi.auth2.getAuthInstance();
            if (!auth2) {
                alert('Google Sign-In is not initialized. Please refresh the page.');
                return;
            }
            
            auth2.signIn().then(function(googleUser) {
                var id_token = googleUser.getAuthResponse().id_token;
                
                $.post(lms_ajax.ajaxurl, {
                    action: 'lms_social_login',
                    provider: 'google',
                    token: id_token,
                    nonce: lms_ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        $('#lms-login-messages, #lms-register-messages')
                            .html('<div class="alert alert-error">' + response.data + '</div>');
                    }
                })
                .fail(function() {
                    $('#lms-login-messages, #lms-register-messages')
                        .html('<div class="alert alert-error">Google login failed. Please try again.</div>');
                });
            }).catch(function(error) {
                console.error('Google Sign-In error:', error);
            });
        },
        
        // Handle Facebook login
        handleFacebookLogin: function() {
            if (typeof FB === 'undefined') {
                alert('Facebook SDK is not loaded. Please refresh the page.');
                return;
            }
            
            FB.login(function(response) {
                if (response.status === 'connected') {
                    $.post(lms_ajax.ajaxurl, {
                        action: 'lms_social_login',
                        provider: 'facebook',
                        token: response.authResponse.accessToken,
                        nonce: lms_ajax.nonce
                    })
                    .done(function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            $('#lms-login-messages, #lms-register-messages')
                                .html('<div class="alert alert-error">' + response.data + '</div>');
                        }
                    })
                    .fail(function() {
                        $('#lms-login-messages, #lms-register-messages')
                            .html('<div class="alert alert-error">Facebook login failed. Please try again.</div>');
                    });
                } else {
                    console.log('Facebook login was cancelled or failed.');
                }
            }, {scope: 'email'});
        },
        
        // Handle Apple login
        handleAppleLogin: function() {
            // Apple Sign-In implementation would go here
            // This requires more complex setup with Apple's JS SDK
            alert('Apple Sign-In is not yet implemented.');
        },
        
        // Initialize dashboard functionality
        initDashboards: function() {
            // Show/hide analytics section
            $(document).on('click', '.show-analytics', function(e) {
                e.preventDefault();
                var $analytics = $('#analytics');
                $analytics.toggle();
                
                if ($analytics.is(':visible')) {
                    LMSAuth.loadAnalyticsCharts();
                }
            });
            
            // Export data buttons
            $(document).on('click', '.export-btn', function() {
                var type = $(this).data('type');
                var exportUrl = lms_ajax.ajaxurl + '?action=lms_export_data&type=' + type + '&nonce=' + lms_ajax.nonce;
                window.location.href = exportUrl;
            });
            
            // View assessment results
            $(document).on('click', '.view-results', function(e) {
                e.preventDefault();
                var assessmentId = $(this).data('assessment-id');
                LMSAuth.showAssessmentResults(assessmentId);
            });
            
            // Auto-refresh dashboard stats every 5 minutes
            setInterval(function() {
                LMSAuth.refreshDashboardStats();
            }, 300000); // 5 minutes
        },
        
        // Initialize package subscription functionality
        initPackages: function() {
            $(document).on('click', '.subscribe-btn', function() {
                var packageId = $(this).data('package-id');
                var $btn = $(this);
                
                // Check if user is logged in
                if (!LMSAuth.isUserLoggedIn()) {
                    if (confirm('You need to be logged in to subscribe. Would you like to go to the login page?')) {
                        window.location.href = '/lms-login';
                    }
                    return;
                }
                
                // Show loading state
                $btn.prop('disabled', true).html('<span class="spinner"></span> Processing...');
                
                // Handle subscription
                LMSAuth.handlePackageSubscription(packageId, $btn);
            });
        },
        
        // Handle package subscription
        handlePackageSubscription: function(packageId, $btn) {
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_subscribe_package',
                package_id: packageId,
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    if (response.data.payment_url) {
                        // Redirect to payment gateway
                        window.location.href = response.data.payment_url;
                    } else {
                        // Free package or already subscribed
                        alert(response.data.message);
                        location.reload();
                    }
                } else {
                    alert('Subscription failed: ' + response.data);
                    $btn.prop('disabled', false).html('Subscribe Now');
                }
            })
            .fail(function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('Subscribe Now');
            });
        },
        
        // Initialize assessment functionality
        initAssessments: function() {
            // Start assessment
            $(document).on('click', '.start-assessment', function(e) {
                e.preventDefault();
                var assessmentId = $(this).data('assessment-id');
                LMSAuth.startAssessment(assessmentId);
            });
            
            // Submit assessment answer
            $(document).on('click', '.submit-answer', function() {
                var questionId = $(this).data('question-id');
                var answer = LMSAuth.getSelectedAnswer(questionId);
                LMSAuth.submitAnswer(questionId, answer);
            });
            
            // Assessment timer
            if ($('.assessment-timer').length) {
                LMSAuth.startAssessmentTimer();
            }
            
            // Auto-save assessment progress
            setInterval(function() {
                if ($('.assessment-form').length) {
                    LMSAuth.autoSaveAssessment();
                }
            }, 30000); // Every 30 seconds
        },
        
        // Start assessment
        startAssessment: function(assessmentId) {
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_start_assessment',
                assessment_id: assessmentId,
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Redirect to assessment page
                    window.location.href = response.data.assessment_url;
                } else {
                    alert('Failed to start assessment: ' + response.data);
                }
            })
            .fail(function() {
                alert('An error occurred. Please try again.');
            });
        },
        
        // Get selected answer for a question
        getSelectedAnswer: function(questionId) {
            var $question = $('.question-' + questionId);
            var answer = '';
            
            if ($question.find('input[type="radio"]:checked').length) {
                answer = $question.find('input[type="radio"]:checked').val();
            } else if ($question.find('input[type="checkbox"]:checked').length) {
                var answers = [];
                $question.find('input[type="checkbox"]:checked').each(function() {
                    answers.push($(this).val());
                });
                answer = answers.join(',');
            } else if ($question.find('textarea, input[type="text"]').length) {
                answer = $question.find('textarea, input[type="text"]').val();
            }
            
            return answer;
        },
        
        // Submit assessment answer
        submitAnswer: function(questionId, answer) {
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_submit_answer',
                question_id: questionId,
                answer: answer,
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Mark question as answered
                    $('.question-' + questionId).addClass('answered');
                    
                    // Update progress
                    LMSAuth.updateAssessmentProgress();
                } else {
                    alert('Failed to submit answer: ' + response.data);
                }
            })
            .fail(function() {
                console.log('Failed to submit answer for question ' + questionId);
            });
        },
        
        // Start assessment timer
        startAssessmentTimer: function() {
            var $timer = $('.assessment-timer');
            var timeLeft = parseInt($timer.data('time-left'));
            
            if (timeLeft <= 0) return;
            
            var timer = setInterval(function() {
                timeLeft--;
                
                var minutes = Math.floor(timeLeft / 60);
                var seconds = timeLeft % 60;
                
                $timer.text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    LMSAuth.submitAssessment(true); // Auto-submit when time is up
                }
            }, 1000);
        },
        
        // Auto-save assessment progress
        autoSaveAssessment: function() {
            var formData = $('.assessment-form').serialize();
            formData += '&action=lms_autosave_assessment&nonce=' + lms_ajax.nonce;
            
            $.post(lms_ajax.ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        $('.autosave-indicator').text('Saved').fadeIn().delay(2000).fadeOut();
                    }
                })
                .fail(function() {
                    console.log('Auto-save failed');
                });
        },
        
        // Update assessment progress
        updateAssessmentProgress: function() {
            var totalQuestions = $('.assessment-question').length;
            var answeredQuestions = $('.assessment-question.answered').length;
            var progress = Math.round((answeredQuestions / totalQuestions) * 100);
            
            $('.progress-fill').css('width', progress + '%');
            $('.progress-text').text(progress + '% Complete');
        },
        
        // Submit assessment
        submitAssessment: function(autoSubmit) {
            if (!autoSubmit) {
                if (!confirm('Are you sure you want to submit your assessment? You cannot change your answers after submission.')) {
                    return;
                }
            }
            
            var formData = $('.assessment-form').serialize();
            formData += '&action=lms_submit_assessment&nonce=' + lms_ajax.nonce;
            
            $.post(lms_ajax.ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Redirect to results page
                        window.location.href = response.data.results_url;
                    } else {
                        alert('Failed to submit assessment: ' + response.data);
                    }
                })
                .fail(function() {
                    alert('An error occurred while submitting the assessment.');
                });
        },
        
        // Initialize charts (using Chart.js)
        initCharts: function() {
            // This will be called when analytics section is shown
        },
        
        // Load analytics charts
        loadAnalyticsCharts: function() {
            // Load attempts per day chart
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_get_analytics_data',
                type: 'attempts_per_day',
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    LMSAuth.createAttemptsChart(response.data);
                }
            });
            
            // Load results distribution chart
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_get_analytics_data',
                type: 'results_distribution',
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    LMSAuth.createResultsChart(response.data);
                }
            });
        },
        
        // Create attempts chart
        createAttemptsChart: function(data) {
            if (typeof Chart === 'undefined') {
                console.log('Chart.js is not loaded');
                return;
            }
            
            var ctx = document.getElementById('attempts-chart');
            if (!ctx) return;
            
            new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Attempts',
                        data: data.values,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        // Create results chart
        createResultsChart: function(data) {
            if (typeof Chart === 'undefined') {
                console.log('Chart.js is not loaded');
                return;
            }
            
            var ctx = document.getElementById('results-chart');
            if (!ctx) return;
            
            new Chart(ctx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Passed', 'Failed', 'In Progress'],
                    datasets: [{
                        data: [data.passed, data.failed, data.in_progress],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(255, 205, 86, 0.2)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true
                }
            });
        },
        
        // Show assessment results modal/popup
        showAssessmentResults: function(assessmentId) {
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_get_assessment_results',
                assessment_id: assessmentId,
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Create modal or redirect to results page
                    LMSAuth.createResultsModal(response.data);
                } else {
                    alert('Failed to load results: ' + response.data);
                }
            })
            .fail(function() {
                alert('An error occurred while loading results.');
            });
        },
        
        // Create results modal
        createResultsModal: function(results) {
            var modalHtml = '<div id="results-modal" class="modal">' +
                '<div class="modal-content">' +
                '<span class="close">&times;</span>' +
                '<h2>Assessment Results</h2>' +
                '<div class="results-content">' + results.html + '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
            
            // Show modal
            $('#results-modal').fadeIn();
            
            // Close modal events
            $(document).on('click', '#results-modal .close, #results-modal', function(e) {
                if (e.target === this) {
                    $('#results-modal').fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        },
        
        // Refresh dashboard stats
        refreshDashboardStats: function() {
            if (!$('.dashboard-stats').length) return;
            
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_refresh_dashboard_stats',
                nonce: lms_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Update stat cards
                    $.each(response.data.stats, function(key, value) {
                        $('.stat-card[data-stat="' + key + '"] h3').text(value);
                    });
                }
            })
            .fail(function() {
                console.log('Failed to refresh dashboard stats');
            });
        },
        
        // Initialize utility functions
        initUtilities: function() {
            // Smooth scrolling for anchor links
            $(document).on('click', 'a[href^="#"]', function(e) {
                var target = $($(this).attr('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 500);
                }
            });
            
            // Tooltip initialization
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip();
            }
            
            // Form validation helpers
            LMSAuth.initFormValidation();
        },
        
        // Initialize form validation
        initFormValidation: function() {
            // Real-time validation
            $(document).on('blur', 'input[type="email"]', function() {
                var email = $(this).val();
                if (email && !LMSAuth.isValidEmail(email)) {
                    $(this).addClass('invalid').next('.error-message').remove();
                    $(this).after('<span class="error-message">Please enter a valid email address</span>');
                } else {
                    $(this).removeClass('invalid').next('.error-message').remove();
                }
            });
            
            $(document).on('blur', 'input[type="password"]', function() {
                var password = $(this).val();
                if (password && password.length < 6) {
                    $(this).addClass('invalid').next('.error-message').remove();
                    $(this).after('<span class="error-message">Password must be at least 6 characters</span>');
                } else {
                    $(this).removeClass('invalid').next('.error-message').remove();
                }
            });
        },
        
        // Utility functions
        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        isUserLoggedIn: function() {
            // Check if user is logged in (you can customize this)
            return $('body').hasClass('logged-in') || $('.dashboard-header').length > 0;
        },
        
        // Show loading spinner
        showLoading: function($element) {
            $element.addClass('loading');
        },
        
        // Hide loading spinner
        hideLoading: function($element) {
            $element.removeClass('loading');
        },
        
        // Show notification
        showNotification: function(message, type) {
            type = type || 'info';
            var notificationHtml = '<div class="notification notification-' + type + '">' + message + '</div>';
            
            $('body').append(notificationHtml);
            
            $('.notification').fadeIn().delay(5000).fadeOut(function() {
                $(this).remove();
            });
        },
        
        // Debounce function for performance
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };
    
})(jQuery);

