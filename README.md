# LMS Authentication System

A comprehensive WordPress plugin for Learning Management System with advanced authentication, course management, assessment system, and subscription-based access control.

## Features

### 🔐 Authentication & Access Control
- **Custom User Roles**: Student, Instructor, Institution
- **Social Login Integration**: Google, Facebook, Apple Sign-In
- **Role-based Dashboard Redirection**
- **Subscription-based Access Control**
- **Secure AJAX Authentication**

### 👥 User Management
- **Three Distinct User Roles**:
  - **Students**: Enroll in courses, take assessments, view progress
  - **Instructors**: Create courses/assessments, manage students, view analytics
  - **Institutions**: Full management access, create packages, view comprehensive analytics

### 📚 Course Management System
- **Udemy-style Course Creation**
- **Lesson Management** (Text, Video, Document)
- **Course Categories and Tags**
- **Free and Paid Courses**
- **Progress Tracking**
- **Frontend Course Creation Interface**

### 📝 Advanced Assessment System
- **Multiple Question Types**: Multiple Choice, True/False, Short Answer
- **Two Timer Types**:
  - Per-question timing (cannot go back)
  - Total assessment timing (navigation allowed)
- **AI-Powered Question Generation** (OpenAI integration)
- **File Upload for Question Generation** (DOCX, PDF, TXT)
- **Auto-save Functionality**
- **Detailed Results and Analytics**

### 💰 Subscription & Payment System
- **Assessment Packages** created by institutions
- **Stripe and PayPal Integration Ready**
- **Flexible Package Duration** (Days, Months, Years)
- **Package-based Access Control**

### 📊 Analytics & Reporting
- **Institution Dashboard Analytics**:
  - Daily attempt statistics (Bar Charts)
  - Pass/Fail/In-Progress distribution (Donut Charts)
  - User activity tracking
- **Data Export** (CSV format)
- **Real-time Statistics**

### 🎨 Frontend Experience
- **Role-specific Dashboards**
- **Responsive Design**
- **AJAX-powered Interactions**
- **Progress Tracking**
- **Modern UI/UX**

## Installation

1. **Upload the Plugin**:
   ```
   wp-content/plugins/lms-authentication-system/
   ```

2. **Activate the Plugin** through the WordPress admin panel

3. **Configure Settings**:
   - Go to WordPress Admin → LMS Settings
   - Configure Social Login APIs
   - Set up Payment Gateway credentials
   - Configure OpenAI API for question generation

## Configuration

### Social Login Setup

#### Google Sign-In
1. Create a project in Google Cloud Console
2. Enable Google+ API
3. Create OAuth 2.0 credentials
4. Add your domain to authorized origins
5. Enter Client ID in plugin settings

#### Facebook Login
1. Create an app in Facebook Developers
2. Configure Facebook Login product
3. Add your domain to valid OAuth redirect URIs
4. Enter App ID and App Secret in plugin settings

#### Apple Sign-In
1. Configure Apple Sign-In in Apple Developer Console
2. Create Service ID and Key
3. Configure domain verification
4. Enter credentials in plugin settings

### Payment Gateway Setup

#### Stripe
1. Create Stripe account
2. Get API keys from dashboard
3. Enter Public and Secret keys in plugin settings

#### PayPal
1. Create PayPal developer account
2. Create application
3. Get Client ID and Secret
4. Enter credentials in plugin settings

### OpenAI Integration
1. Create OpenAI account
2. Generate API key
3. Enter API key in plugin settings
4. Configure usage limits as needed

## Usage

### For Institutions
1. **Create Packages**: Define subscription packages with included courses/assessments
2. **Manage Users**: Assign roles, monitor activity
3. **View Analytics**: Access comprehensive reporting dashboard
4. **Export Data**: Download user data, results, and analytics

### For Instructors
1. **Create Courses**: Build comprehensive course content
2. **Design Assessments**: Create manual or AI-generated questions
3. **Manage Students**: Track progress and performance
4. **Monitor Results**: View assessment analytics

### For Students
1. **Subscribe to Packages**: Choose appropriate subscription
2. **Enroll in Courses**: Access included content
3. **Take Assessments**: Complete evaluations
4. **Track Progress**: Monitor learning journey

## Custom Post Types

- **lms_course**: Course content and structure
- **lms_lesson**: Individual lesson content
- **lms_assessment**: Assessment configuration
- **lms_question**: Individual questions
- **lms_package**: Subscription packages

## Database Tables

- **lms_assessment_results**: Store assessment scores and attempts
- **lms_user_subscriptions**: Track user subscriptions
- **lms_course_enrollments**: Course enrollment data
- **lms_lesson_progress**: Lesson completion tracking
- **lms_analytics**: Event tracking and analytics

## Shortcodes

```php
[lms_login_form]              // Login form
[lms_register_form]           // Registration form
[lms_packages]                // Subscription packages
[lms_student_dashboard]       // Student dashboard
[lms_instructor_dashboard]    // Instructor dashboard
[lms_institution_dashboard]   // Institution dashboard
[lms_course_list]            // Course listing
[lms_assessment_list]        // Assessment listing
[lms_take_assessment]        // Assessment interface
```

## Hooks and Filters

### Actions
```php
do_action('lms_auth_after_login', $user_id);
do_action('lms_auth_after_registration', $user_id);
do_action('lms_assessment_completed', $user_id, $assessment_id, $result);
do_action('lms_course_completed', $user_id, $course_id);
```

### Filters
```php
apply_filters('lms_auth_redirect_url', $url, $user_id);
apply_filters('lms_assessment_questions', $questions, $assessment_id);
apply_filters('lms_package_features', $features, $package_id);
```

## API Endpoints

### AJAX Actions
- `lms_login_user`: Handle login
- `lms_register_user`: Handle registration
- `lms_social_login`: Social media authentication
- `lms_subscribe_package`: Package subscription
- `lms_start_assessment`: Begin assessment
- `lms_submit_assessment`: Submit completed assessment
- `lms_get_analytics_data`: Fetch analytics
- `lms_export_data`: Export data

## Security Features

- **Nonce Verification**: All AJAX requests protected
- **Capability Checks**: Role-based access control
- **Data Sanitization**: Input validation and sanitization
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output escaping

## Performance Optimization

- **Lazy Loading**: Assets loaded only when needed
- **Database Indexing**: Optimized database queries
- **Caching Ready**: Compatible with caching plugins
- **Minified Assets**: Compressed CSS/JS files

## Compatibility

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.6+
- **Browsers**: Modern browsers (Chrome, Firefox, Safari, Edge)

## Development

### Object-Oriented Architecture
- **Namespace**: `LMS_Auth`
- **Autoloading**: PSR-4 compatible
- **Singleton Pattern**: Used for core classes
- **Hooks System**: WordPress-native integration

### File Structure
```
lms-authentication-system/
├── assets/
│   ├── css/
│   │   └── frontend.css
│   └── js/
│       └── frontend.js
├── includes/
│   ├── classes/
│   │   ├── Core/
│   │   │   ├── Authentication.php
│   │   │   ├── Database.php
│   │   │   ├── Post_Types.php
│   │   │   └── Roles.php
│   │   ├── Frontend/
│   │   │   └── Shortcodes.php
│   │   └── Admin/
│   └── functions/
├── templates/
│   ├── auth/
│   ├── student/
│   ├── instructor/
│   └── institution/
└── lms-authentication-system.php
```

## Customization

### Theme Integration
Copy template files to your theme:
```
your-theme/lms-templates/[template-name].php
```

### Custom Styling
Override default styles:
```css
.lms-auth-form {
    /* Your custom styles */
}
```

### Extending Functionality
```php
// Add custom user meta
add_action('lms_auth_after_registration', function($user_id) {
    update_user_meta($user_id, 'custom_field', 'value');
});

// Modify redirect URL
add_filter('lms_auth_redirect_url', function($url, $user_id) {
    return 'custom-url';
}, 10, 2);
```

## Troubleshooting

### Common Issues

1. **Social Login Not Working**
   - Check API credentials
   - Verify domain configuration
   - Ensure HTTPS is enabled

2. **Assessment Timer Issues**
   - Check JavaScript console for errors
   - Verify timezone settings
   - Ensure AJAX is working

3. **Permission Errors**
   - Check user roles and capabilities
   - Verify database permissions
   - Review error logs

### Debug Mode
Enable WordPress debug mode in wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and documentation:
- Check WordPress error logs
- Review plugin settings
- Test in a staging environment
- Ensure all dependencies are met

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- Complete authentication system
- Course and assessment management
- Analytics and reporting
- Frontend dashboards
- Social login integration
- Payment system ready
- OpenAI integration

---

**Note**: This plugin provides a complete foundation for a Learning Management System. Additional customization may be required based on specific needs. All payment gateway integrations should be thoroughly tested in sandbox environments before going live.

