/**
 * LMS Admin JavaScript
 * Handles admin panel interactions and AJAX requests
 */

(function($) {
    'use strict';

    // Global admin object
    const LMSAdmin = {
        init: function() {
            this.initTabs();
            this.initAnalytics();
            this.initUsers();
            this.initReports();
            this.initSettings();
            this.initDashboard();
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const target = $(this).attr('href');
                
                // Remove active class from all tabs and content
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                
                // Add active class to clicked tab and corresponding content
                $(this).addClass('nav-tab-active');
                $(target).addClass('active');
            });

            // Show first tab by default
            $('.nav-tab:first').trigger('click');
        },

        /**
         * Initialize analytics functionality
         */
        initAnalytics: function() {
            this.loadAnalyticsCharts();
            
            // Handle date range changes
            $('#analytics-period').on('change', () => {
                this.loadAnalyticsCharts();
            });

            // Handle export buttons
            $('.export-analytics').on('click', function(e) {
                e.preventDefault();
                LMSAdmin.exportAnalytics($(this).data('type'));
            });
        },

        /**
         * Load analytics charts
         */
        loadAnalyticsCharts: function() {
            const period = $('#analytics-period').val() || '7';
            
            // Show loading indicators
            $('.chart-container').each(function() {
                $(this).find('canvas').before('<div class="lms-loading"></div>');
            });

            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lms_get_analytics_data',
                    period: period,
                    nonce: lms_admin_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCharts(response.data);
                    } else {
                        this.showError('Failed to load analytics data');
                    }
                },
                error: () => {
                    this.showError('Error loading analytics data');
                },
                complete: () => {
                    $('.lms-loading').remove();
                }
            });
        },

        /**
         * Render analytics charts
         */
        renderCharts: function(data) {
            // User Registration Chart
            if (data.registrations) {
                this.createLineChart('registrationsChart', {
                    labels: data.registrations.labels,
                    datasets: [{
                        label: 'New Registrations',
                        data: data.registrations.data,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        fill: true
                    }]
                });
            }

            // User Activity Chart
            if (data.activity) {
                this.createBarChart('activityChart', {
                    labels: data.activity.labels,
                    datasets: [{
                        label: 'Daily Active Users',
                        data: data.activity.data,
                        backgroundColor: '#46b450'
                    }]
                });
            }

            // Role Distribution Chart
            if (data.roles) {
                this.createPieChart('rolesChart', {
                    labels: data.roles.labels,
                    datasets: [{
                        data: data.roles.data,
                        backgroundColor: ['#0073aa', '#46b450', '#ffb900', '#dc3232']
                    }]
                });
            }
        },

        /**
         * Create line chart
         */
        createLineChart: function(canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        /**
         * Create bar chart
         */
        createBarChart: function(canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        /**
         * Create pie chart
         */
        createPieChart: function(canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            new Chart(ctx, {
                type: 'pie',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        /**
         * Initialize user management functionality
         */
        initUsers: function() {
            // View user details modal
            $('.view-user-details').on('click', function(e) {
                e.preventDefault();
                const userId = $(this).data('user-id');
                LMSAdmin.showUserDetails(userId);
            });

            // Filter users
            $('#user-role-filter').on('change', function() {
                const role = $(this).val();
                LMSAdmin.filterUsers(role);
            });

            // Search users
            $('#user-search').on('input', debounce(function() {
                const search = $(this).val();
                LMSAdmin.searchUsers(search);
            }, 300));
        },

        /**
         * Show user details in modal
         */
        showUserDetails: function(userId) {
            const modal = this.createModal('User Details', '<div class="lms-loading"></div>');
            
            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lms_get_user_details',
                    user_id: userId,
                    nonce: lms_admin_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        modal.find('.lms-modal-content').html(this.buildUserDetailsHTML(response.data));
                    } else {
                        modal.find('.lms-modal-content').html('<p>Error loading user details.</p>');
                    }
                },
                error: () => {
                    modal.find('.lms-modal-content').html('<p>Error loading user details.</p>');
                }
            });
        },

        /**
         * Build user details HTML
         */
        buildUserDetailsHTML: function(user) {
            return `
                <div class="lms-modal-close">&times;</div>
                <h2>User Details</h2>
                <table class="form-table">
                    <tr>
                        <th>Name:</th>
                        <td>${user.display_name}</td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td>${user.user_email}</td>
                    </tr>
                    <tr>
                        <th>Role:</th>
                        <td>${user.role}</td>
                    </tr>
                    <tr>
                        <th>Registration Date:</th>
                        <td>${user.user_registered}</td>
                    </tr>
                    <tr>
                        <th>Last Login:</th>
                        <td>${user.last_login || 'Never'}</td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><span class="status-${user.status}">${user.status}</span></td>
                    </tr>
                </table>
            `;
        },

        /**
         * Initialize reports functionality
         */
        initReports: function() {
            // Export buttons
            $('.export-btn').on('click', function(e) {
                e.preventDefault();
                const type = $(this).data('export-type');
                LMSAdmin.exportData(type, $(this));
            });
        },

        /**
         * Export data
         */
        exportData: function(type, button) {
            const originalText = button.text();
            button.html('<span class="lms-loading"></span> Exporting...').prop('disabled', true);

            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lms_export_data',
                    type: type,
                    nonce: lms_admin_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Create download link
                        const link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = response.data.filename;
                        link.click();
                        
                        this.showSuccess('Export completed successfully!');
                    } else {
                        this.showError(response.data || 'Export failed');
                    }
                },
                error: () => {
                    this.showError('Export failed');
                },
                complete: () => {
                    button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Initialize settings functionality
         */
        initSettings: function() {
            // Test OpenAI connection
            $('#test-openai').on('click', function(e) {
                e.preventDefault();
                LMSAdmin.testOpenAI($(this));
            });

            // Settings form submission
            $('#lms-settings-form').on('submit', function(e) {
                e.preventDefault();
                LMSAdmin.saveSettings($(this));
            });
        },

        /**
         * Test OpenAI connection
         */
        testOpenAI: function(button) {
            const apiKey = $('#openai_api_key').val();
            if (!apiKey) {
                this.showError('Please enter an API key first.');
                return;
            }

            const originalText = button.text();
            button.html('<span class="lms-loading"></span> Testing...').prop('disabled', true);

            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lms_test_openai',
                    api_key: apiKey,
                    nonce: lms_admin_ajax.nonce
                },
                success: (response) => {
                    const resultDiv = $('#openai-test-result');
                    if (response.success) {
                        resultDiv.html('<div class="lms-notice success">✓ OpenAI connection successful!</div>');
                    } else {
                        resultDiv.html(`<div class="lms-notice error">✗ Connection failed: ${response.data}</div>`);
                    }
                },
                error: () => {
                    $('#openai-test-result').html('<div class="lms-notice error">✗ Connection test failed</div>');
                },
                complete: () => {
                    button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Save settings
         */
        saveSettings: function(form) {
            const submitButton = form.find('input[type="submit"]');
            const originalText = submitButton.val();
            
            submitButton.val('Saving...').prop('disabled', true);

            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=lms_save_settings&nonce=' + lms_admin_ajax.nonce,
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Settings saved successfully!');
                    } else {
                        this.showError(response.data || 'Failed to save settings');
                    }
                },
                error: () => {
                    this.showError('Failed to save settings');
                },
                complete: () => {
                    submitButton.val(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Initialize dashboard functionality
         */
        initDashboard: function() {
            // Refresh dashboard stats
            $('.refresh-stats').on('click', function(e) {
                e.preventDefault();
                LMSAdmin.refreshDashboardStats();
            });

            // Auto-refresh every 5 minutes
            setInterval(() => {
                this.refreshDashboardStats();
            }, 300000);
        },

        /**
         * Refresh dashboard statistics
         */
        refreshDashboardStats: function() {
            $('.lms-stat-widget').each(function() {
                $(this).addClass('loading');
            });

            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lms_refresh_dashboard_stats',
                    nonce: lms_admin_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateDashboardStats(response.data);
                    }
                },
                complete: () => {
                    $('.lms-stat-widget').removeClass('loading');
                }
            });
        },

        /**
         * Update dashboard statistics
         */
        updateDashboardStats: function(stats) {
            Object.keys(stats).forEach(key => {
                const widget = $(`[data-stat="${key}"]`);
                if (widget.length) {
                    widget.find('h3').text(stats[key]);
                }
            });
        },

        /**
         * Create modal
         */
        createModal: function(title, content) {
            const modal = $(`
                <div class="lms-modal">
                    <div class="lms-modal-content">
                        <div class="lms-modal-close">&times;</div>
                        <h2>${title}</h2>
                        ${content}
                    </div>
                </div>
            `);

            $('body').append(modal);
            modal.show();

            // Close modal functionality
            modal.on('click', '.lms-modal-close, .lms-modal', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });

            return modal;
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type = 'info') {
            const notice = $(`<div class="lms-notice ${type}">${message}</div>`);
            $('.wrap h1').after(notice);
            
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
        },

        /**
         * Filter users by role
         */
        filterUsers: function(role) {
            const tableRows = $('.users-table-container tbody tr');
            
            if (!role) {
                tableRows.show();
                return;
            }

            tableRows.each(function() {
                const userRole = $(this).find('.user-role').text().toLowerCase();
                if (userRole.includes(role.toLowerCase())) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        /**
         * Search users
         */
        searchUsers: function(search) {
            const tableRows = $('.users-table-container tbody tr');
            
            if (!search) {
                tableRows.show();
                return;
            }

            tableRows.each(function() {
                const text = $(this).text().toLowerCase();
                if (text.includes(search.toLowerCase())) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        /**
         * Export analytics data
         */
        exportAnalytics: function(type) {
            const period = $('#analytics-period').val() || '7';
            
            $.ajax({
                url: lms_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lms_export_analytics',
                    type: type,
                    period: period,
                    nonce: lms_admin_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = response.data.filename;
                        link.click();
                    } else {
                        this.showError('Export failed');
                    }
                },
                error: () => {
                    this.showError('Export failed');
                }
            });
        }
    };

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize when document is ready
    $(document).ready(function() {
        LMSAdmin.init();
    });

    // Make LMSAdmin globally available
    window.LMSAdmin = LMSAdmin;

})(jQuery);

