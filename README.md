# LMS Authentication System

A comprehensive WordPress plugin for Learning Management System with advanced authentication, course management, assessment system, and subscription-based access control.

## 🚀 New Features & Improvements

### Performance Enhancements
- **Cache Manager**: Intelligent caching system for courses, assessments, and user data
- **Performance Monitor**: Real-time monitoring of page load times, memory usage, and database queries
- **Database Optimization**: Automatic index creation and query optimization
- **Data Cleanup**: Automated cleanup of old analytics and security logs

### Security Improvements
- **Security Manager**: Advanced security features including rate limiting and IP blocking
- **Data Encryption**: Secure encryption for sensitive user data
- **Failed Login Protection**: Automatic IP blocking after failed login attempts
- **Input Validation**: Enhanced data sanitization and validation

### API Integration
- **REST API**: Complete REST API for mobile app integration
- **Token Authentication**: Secure JWT-like token system for API access
- **Rate Limiting**: API rate limiting to prevent abuse
- **Comprehensive Endpoints**: Full CRUD operations for courses, assessments, and user data

### Notification System
- **Real-time Notifications**: In-app notification system with live updates
- **Email Notifications**: Customizable email templates for various events
- **Push Notifications**: Support for Firebase Cloud Messaging
- **Notification Preferences**: User-configurable notification settings

## Features

### 🔐 Authentication & Access Control
- **Custom User Roles**: Student, Instructor, Institution
- **Social Login Integration**: Google, Facebook, Apple Sign-In
- **Role-based Dashboard Redirection**
- **Subscription-based Access Control**
- **Secure AJAX Authentication**
- **Advanced Security Features**: Rate limiting, IP blocking, encryption

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
- **Interactive Course Viewer** with notes, bookmarks, and progress tracking

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
- **Performance Monitoring**

### 🎨 Frontend Experience
- **Role-specific Dashboards**
- **Responsive Design**
- **AJAX-powered Interactions**
- **Progress Tracking**
- **Modern UI/UX**
- **Real-time Notifications**

### 🔧 Performance & Optimization
- **Intelligent Caching System**
- **Database Query Optimization**
- **Memory Usage Monitoring**
- **Automatic Data Cleanup**
- **Performance Debug Information**

### 🛡️ Security Features
- **Rate Limiting**: Prevent brute force attacks
- **IP Blocking**: Automatic blocking of malicious IPs
- **Data Encryption**: Secure storage of sensitive information
- **Security Event Logging**: Comprehensive security audit trail
- **Input Validation**: Advanced XSS and injection protection

### 📱 API & Mobile Support
- **Complete REST API**: Full functionality available via API
- **Token Authentication**: Secure API access
- **Mobile App Ready**: Perfect for React Native, Flutter apps
- **Real-time Sync**: Live data synchronization

### 🔔 Notification System
- **In-app Notifications**: Real-time notification bell with dropdown
- **Email Notifications**: Customizable email templates
- **Push Notifications**: Firebase Cloud Messaging support
- **User Preferences**: Granular notification control

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

### Push Notifications Setup
1. Create Firebase project
2. Generate FCM server key
3. Add FCM API key to plugin settings
4. Configure client-side Firebase SDK

## Usage

### For Institutions
1. **Create Packages**: Define subscription packages with included courses/assessments
2. **Manage Users**: Assign roles, monitor activity
3. **View Analytics**: Access comprehensive reporting dashboard
4. **Export Data**: Download user data, results, and analytics
5. **Monitor Performance**: Track system performance and security

### For Instructors
1. **Create Courses**: Build comprehensive course content
2. **Design Assessments**: Create manual or AI-generated questions
3. **Manage Students**: Track progress and performance
4. **Monitor Results**: View assessment analytics
5. **Receive Notifications**: Get alerts for student activities

### For Students
1. **Subscribe to Packages**: Choose appropriate subscription
2. **Enroll in Courses**: Access included content
3. **Take Assessments**: Complete evaluations
4. **Track Progress**: Monitor learning journey
5. **Interactive Learning**: Use notes, bookmarks, and progress tracking

## API Documentation

### Authentication
```bash
# Login
POST /wp-json/lms-auth/v1/auth/login
{
  "username": "user@example.com",
  "password": "password"
}

# Register
POST /wp-json/lms-auth/v1/auth/register
{
  "username": "newuser",
  "email": "user@example.com",
  "password": "password",
  "role": "lms_student"
}
```

### Courses
```bash
# Get courses
GET /wp-json/lms-auth/v1/courses
Authorization: Bearer {token}

# Get single course
GET /wp-json/lms-auth/v1/courses/{id}
Authorization: Bearer {token}
```

### Assessments
```bash
# Start assessment
POST /wp-json/lms-auth/v1/assessments/{id}/start
Authorization: Bearer {token}

# Submit assessment
POST /wp-json/lms-auth/v1/assessments/{id}/submit
Authorization: Bearer {token}
{
  "answers": {
    "0": {"answer": "option1"},
    "1": {"answer": "true"}
  }
}
```

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
- **lms_notifications**: User notifications
- **lms_security_logs**: Security event logging

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
[lms_assessment id="123"]    // Specific assessment
```

## Hooks and Filters

### Actions
```php
do_action('lms_auth_after_login', $user_id);
do_action('lms_auth_after_registration', $user_id);
do_action('lms_assessment_completed', $user_id, $assessment_id, $result);
do_action('lms_course_completed', $user_id, $course_id);
do_action('lms_course_enrolled', $user_id, $course_id);
do_action('lms_clear_cache');
```

### Filters
```php
apply_filters('lms_auth_redirect_url', $url, $user_id);
apply_filters('lms_assessment_questions', $questions, $assessment_id);
apply_filters('lms_package_features', $features, $package_id);
apply_filters('lms_notification_email_template', $template, $type);
```

## Performance Optimization

### Caching
- **Object Caching**: WordPress object cache integration
- **Query Caching**: Database query result caching
- **User Data Caching**: Course enrollments, progress, results
- **Cache Invalidation**: Smart cache clearing on data updates

### Database Optimization
- **Automatic Indexing**: Creates indexes for common queries
- **Query Monitoring**: Logs slow queries for optimization
- **Data Cleanup**: Removes old analytics and logs
- **Connection Pooling**: Efficient database connections

### Memory Management
- **Memory Monitoring**: Tracks memory usage and alerts on high usage
- **Garbage Collection**: Automatic cleanup of temporary data
- **Resource Optimization**: Efficient resource allocation

## Security Features

### Rate Limiting
- **Login Attempts**: 5 attempts per 15 minutes
- **API Calls**: 100 requests per hour
- **Assessment Attempts**: 10 attempts per hour

### Data Protection
- **Encryption**: AES-256-CBC encryption for sensitive data
- **Secure Tokens**: Cryptographically secure token generation
- **Input Validation**: Comprehensive XSS and injection protection
- **CSRF Protection**: Nonce verification for all forms

### Monitoring
- **Security Logs**: Comprehensive audit trail
- **Failed Login Tracking**: Automatic IP blocking
- **Suspicious Activity Detection**: Real-time threat monitoring

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

4. **Performance Issues**
   - Enable caching
   - Check database indexes
   - Monitor memory usage
   - Review slow query logs

5. **Notification Issues**
   - Verify email settings
   - Check FCM configuration
   - Test notification preferences

### Debug Mode
Enable WordPress debug mode in wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Performance Monitoring
Access performance information by adding `?debug=1` to any URL when logged in as administrator.

## Support

For support and documentation:
- Check WordPress error logs
- Review plugin settings
- Test in a staging environment
- Ensure all dependencies are met
- Monitor performance metrics
- Review security logs

## License

GPL v2 or later

## Changelog

### Version 1.1.0
- Added comprehensive caching system
- Implemented advanced security features
- Added REST API with token authentication
- Created notification system with real-time updates
- Added performance monitoring and optimization
- Implemented data encryption and security logging
- Added mobile app support via API
- Enhanced user experience with notifications
- Improved database performance with automatic indexing
- Added cleanup routines for old data

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

**Note**: This plugin provides a complete foundation for a Learning Management System with enterprise-level features including performance monitoring, advanced security, API integration, and real-time notifications. All payment gateway integrations should be thoroughly tested in sandbox environments before going live.