# Scholarship Email Reminder System - Implementation Guide

## Overview

This document provides comprehensive technical guidance for the automated email reminder system that notifies users about upcoming scholarship deadlines.

**Version:** 1.1.0  
**Author:** Aviation Scholarships Team  
**Date:** December 2025

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Database Schema](#database-schema)
3. [Core Components](#core-components)
4. [Scheduling Mechanism](#scheduling-mechanism)
5. [Email Sending Process](#email-sending-process)
6. [Integration with Favorites Plugin](#integration-with-favorites-plugin)
7. [Performance Optimization](#performance-optimization)
8. [Testing & Debugging](#testing--debugging)
9. [Configuration](#configuration)
10. [Troubleshooting](#troubleshooting)

---

## System Architecture

### High-Level Flow

```
Daily Cron (9:00 AM)
    ↓
Reminder Manager Process
    ↓
For each interval (30, 15, 5 days):
    ↓
Find scholarships with matching deadlines
    ↓
Query users who favorited each scholarship
    ↓
Check if reminder already sent (database)
    ↓
Prepare & send batch emails
    ↓
Record sent reminders in database
```

### Components

1. **Reminder_Database** - Database table management and queries
2. **Reminder_Email** - Email template generation and sending
3. **Reminder_Manager** - Orchestration and cron scheduling
4. **Settings_Page** - Admin UI integration

---

## Database Schema

### Table: `wp_avs_scholarship_reminders`

```sql
CREATE TABLE wp_avs_scholarship_reminders (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    scholarship_id bigint(20) unsigned NOT NULL,
    reminder_type varchar(20) NOT NULL,  -- '30_days', '15_days', '5_days'
    deadline_date date NOT NULL,
    sent_date datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_scholarship_type (user_id, scholarship_id, reminder_type),
    KEY deadline_date (deadline_date),
    KEY sent_date (sent_date)
);
```

### Purpose

- **Prevents duplicate emails** - Tracks which reminders have been sent
- **Performance optimization** - Indexed for fast lookups
- **Audit trail** - Records when reminders were sent
- **Data cleanup** - Old records can be purged after 180 days

### Key Methods

```php
// Check if reminder already sent
$db->reminder_exists($user_id, $scholarship_id, $reminder_type);

// Record a sent reminder
$db->add_reminder($user_id, $scholarship_id, $reminder_type, $deadline_date);

// Cleanup old records
$db->cleanup_old_reminders($days = 180);
```

---

## Core Components

### 1. Reminder_Database (class-reminder-database.php)

**Responsibilities:**
- Create and manage database table
- Check for existing reminders
- Record sent reminders
- Cleanup operations

**Key Features:**
- Uses WordPress `dbDelta()` for safe table creation
- Prepared statements for security
- Indexed queries for performance

**Example Usage:**
```php
$db = new \Aviation_Scholarships\Reminder_Database();

// Check if reminder was sent
if (!$db->reminder_exists($user_id, $scholarship_id, '30_days')) {
    // Send reminder...
    
    // Record it
    $db->add_reminder($user_id, $scholarship_id, '30_days', $deadline_date);
}
```

---

### 2. Reminder_Email (class-reminder-email.php)

**Responsibilities:**
- Generate HTML email templates
- Format scholarship data
- Send emails via WordPress mail functions
- Provide customization hooks

**Email Template Features:**
- Responsive HTML design
- Personalized greeting with user's first name
- Scholarship details (title, deadline, amount, eligibility)
- Direct links to scholarship pages
- Professional branding

**Integration with WP Mail SMTP:**
```php
// Uses wp_mail() - automatically works with WP Mail SMTP plugin
wp_mail($to, $subject, $message, $headers);
```

**Customization Hooks:**
```php
// Customize email subject
add_filter('avs_reminder_email_subject', function($subject, $user_id, $scholarships, $type) {
    return "Custom Subject: " . $subject;
}, 10, 4);

// Customize email message
add_filter('avs_reminder_email_message', function($message, $user_id, $scholarships, $type) {
    // Modify HTML template
    return $message;
}, 10, 4);
```

---

### 3. Reminder_Manager (class-reminder-manager.php)

**Responsibilities:**
- Schedule and manage daily cron jobs
- Process reminder intervals (30, 15, 5 days)
- Find users with saved scholarships
- Orchestrate email sending
- Performance management (batching)

**Cron Schedule:**
- **Hook:** `avs_daily_reminder_check`
- **Frequency:** Daily at 9:00 AM server time
- **Recurrence:** Uses WordPress `daily` schedule

**Process Flow:**
```php
public function process_daily_reminders() {
    // 1. Check if enabled
    if (get_option('avs_email_reminders_enabled') !== 'yes') return;
    
    // 2. For each interval (30, 15, 5 days)
    foreach ($this->reminder_intervals as $days) {
        
        // 3. Calculate target deadline date
        $target_date = date('Y-m-d', strtotime("+{$days} days"));
        
        // 4. Find scholarships with this deadline
        $scholarships = $this->get_scholarships_by_deadline($target_date);
        
        // 5. For each scholarship, find users who favorited it
        foreach ($scholarships as $scholarship_id) {
            $users = $this->get_users_who_favorited($scholarship_id);
            
            // 6. Group by user for batch emails
            // 7. Send emails
            // 8. Record in database
        }
    }
}
```

---

## Scheduling Mechanism

### WordPress Cron System

The plugin uses WordPress's built-in `wp-cron` system:

```php
// Schedule daily event
wp_schedule_event(
    strtotime('tomorrow 09:00:00'), // First run
    'daily',                         // Recurrence
    'avs_daily_reminder_check'      // Hook name
);
```

### Important Notes

1. **wp-cron is pseudo-cron** - Triggered by site visits, not guaranteed timing
2. **For production**, consider using system cron:
   ```bash
   */15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```
3. **Disable wp-cron** in wp-config.php if using system cron:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

### Manual Triggering

For testing or manual runs:

```php
// Via admin URL
wp-admin/admin-post.php?action=avs_test_reminder

// Or programmatically
$manager = new \Aviation_Scholarships\Reminder_Manager();
$manager->process_daily_reminders();
```

---

## Email Sending Process

### 1. Data Retrieval

```php
// Find scholarships by deadline date
private function get_scholarships_by_deadline($deadline_date) {
    $args = array(
        'post_type' => 'scholarship',
        'meta_query' => array(
            array(
                'key' => 'sch_deadline',
                'value' => $deadline_date,
                'compare' => '=',
                'type' => 'DATE'
            )
        )
    );
    return new WP_Query($args);
}
```

### 2. User Lookup

```php
// Find users who favorited a scholarship
private function get_users_who_favorited($scholarship_id) {
    global $wpdb;
    
    // Query user meta for favorites
    $users = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'simplefavorites' 
        AND meta_value LIKE %s",
        '%"' . $scholarship_id . '"%'
    ));
    
    // Validate favorites structure
    // (Favorites plugin stores as serialized array)
    return $validated_users;
}
```

### 3. Duplicate Check

```php
// Check if reminder already sent
if ($this->db->reminder_exists($user_id, $scholarship_id, $reminder_type)) {
    continue; // Skip
}
```

### 4. Batch Processing

```php
// Group scholarships by user for single email
foreach ($scholarships as $scholarship_id) {
    foreach ($users as $user_id) {
        if (!isset($user_scholarships[$user_id])) {
            $user_scholarships[$user_id] = array();
        }
        $user_scholarships[$user_id][] = $scholarship_id;
    }
}

// Send one email per user with all their approaching deadlines
foreach ($user_scholarships as $user_id => $scholarship_ids) {
    $this->send_user_reminder($user_id, $scholarship_ids, $reminder_type);
}
```

### 5. Email Delivery

```php
// Uses WordPress wp_mail() function
wp_mail(
    $to,        // User email
    $subject,   // Personalized subject
    $message,   // HTML email body
    $headers    // Content-Type: text/html
);
```

**WP Mail SMTP Integration:**
- No special code required
- WP Mail SMTP plugin hooks into `wp_mail()`
- Automatically uses configured SMTP settings

---

## Integration with Favorites Plugin

### How Favorites are Stored

The "Favorites" plugin stores user favorites in user meta:

```php
// Meta key: 'simplefavorites'
// Meta value structure:
array(
    1 => array(  // Site ID (for multisite)
        'site_id' => 1,
        'posts' => array(123, 456, 789)  // Post IDs
    )
)
```

### Querying Favorites

```php
// Get a user's favorites
$favorites = get_user_meta($user_id, 'simplefavorites', true);

// Check if scholarship is favorited
foreach ($favorites as $site_favorites) {
    if (in_array($scholarship_id, $site_favorites['posts'])) {
        // User favorited this scholarship
    }
}
```

### Reverse Lookup (Users who favorited a post)

```php
global $wpdb;

// Find all users with this scholarship in favorites
$users = $wpdb->get_col($wpdb->prepare(
    "SELECT user_id 
    FROM {$wpdb->usermeta} 
    WHERE meta_key = 'simplefavorites' 
    AND meta_value LIKE %s",
    '%"' . $scholarship_id . '"%'
));
```

**Note:** This uses a LIKE query on serialized data. For large user bases, consider:
1. Adding a custom lookup table
2. Indexing the meta_value column
3. Caching results

---

## Performance Optimization

### 1. Batch Limiting

```php
// Maximum emails per cron run
private $batch_limit = 50;

if ($total_sent >= $this->batch_limit) {
    error_log("Batch limit reached, stopping");
    break;
}
```

**Why?** Prevents timeout on shared hosting with many users.

**Adjust for your environment:**
- Dedicated server: 200-500
- VPS: 100-200
- Shared hosting: 50-100

### 2. Database Indexes

```sql
-- Optimizes lookup queries
KEY user_scholarship_type (user_id, scholarship_id, reminder_type)
KEY deadline_date (deadline_date)
```

### 3. Query Optimization

```php
// Use 'fields' => 'ids' to return only IDs
$args = array(
    'post_type' => 'scholarship',
    'fields' => 'ids',  // Don't load full post objects
    'posts_per_page' => -1
);
```

### 4. Caching Strategies

```php
// Cache scholarship queries
$cache_key = 'avs_scholarships_' . $target_date;
$scholarships = get_transient($cache_key);

if ($scholarships === false) {
    $scholarships = $this->get_scholarships_by_deadline($target_date);
    set_transient($cache_key, $scholarships, HOUR_IN_SECONDS);
}
```

### 5. Memory Management

```php
// For large datasets
ini_set('memory_limit', '256M');

// Or process in smaller chunks
$offset = 0;
$limit = 100;
while ($users = $this->get_users_batch($offset, $limit)) {
    // Process batch
    $offset += $limit;
}
```

---

## Testing & Debugging

### Admin Testing Tools

**Location:** Settings → Import Settings → Email Reminder Settings

1. **Send Test Email**
   - Sends sample email to admin address
   - Tests email configuration
   - Verifies template rendering

2. **Run Reminder Check Now**
   - Manually triggers cron process
   - Useful for testing without waiting

### Logging

All operations are logged to WordPress debug log:

```php
// Enable debugging in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// View logs at: wp-content/debug.log
```

**Log entries:**
```
AVS Reminder: Starting daily reminder process
AVS Reminder: Found 5 scholarships for 30_days (2025-01-20)
AVS Reminder: Email sent to user@example.com for 30_days
AVS Reminder: Completed. Sent 15 emails in 2.5 seconds
```

### Database Inspection

```sql
-- View all sent reminders
SELECT * FROM wp_avs_scholarship_reminders 
ORDER BY sent_date DESC 
LIMIT 50;

-- Count reminders by type
SELECT reminder_type, COUNT(*) as count 
FROM wp_avs_scholarship_reminders 
GROUP BY reminder_type;

-- Find users who received reminders
SELECT DISTINCT user_id 
FROM wp_avs_scholarship_reminders;
```

### Testing Checklist

- [ ] Database table created successfully
- [ ] Cron job scheduled correctly
- [ ] Test email sends successfully
- [ ] Email arrives in inbox (not spam)
- [ ] Email template displays correctly
- [ ] Links in email work
- [ ] Duplicate check works (no double emails)
- [ ] Manual trigger works
- [ ] Statistics display correctly
- [ ] Settings save properly

---

## Configuration

### Admin Settings

**Location:** Scholarships → Import Settings

```php
// Email Reminders Enabled
get_option('avs_email_reminders_enabled', 'yes'); // 'yes' or 'no'
```

### Customization Options

**1. Change Reminder Intervals**

Edit `class-reminder-manager.php`:
```php
// Default: 30, 15, 5 days
private $reminder_intervals = array(30, 15, 5);

// Custom: 60, 30, 14, 7, 3, 1 days
private $reminder_intervals = array(60, 30, 14, 7, 3, 1);
```

**2. Change Cron Schedule**

```php
// Daily at 9:00 AM (default)
$timestamp = strtotime('tomorrow 09:00:00');

// Daily at 6:00 AM
$timestamp = strtotime('tomorrow 06:00:00');

// Twice daily
wp_schedule_event($timestamp, 'twicedaily', $this->cron_hook);
```

**3. Customize Email Template**

Use filters in your theme's `functions.php`:

```php
add_filter('avs_reminder_email_subject', function($subject, $user_id, $scholarships, $type) {
    $days = ($type === '30_days') ? 30 : (($type === '15_days') ? 15 : 5);
    return sprintf('[Important] %d Scholarship Deadline%s in %d Days', 
        count($scholarships), 
        count($scholarships) > 1 ? 's' : '',
        $days
    );
}, 10, 4);
```

**4. Change Batch Limit**

Edit `class-reminder-manager.php`:
```php
private $batch_limit = 50; // Change to your preference
```

---

## Troubleshooting

### Issue: Emails Not Sending

**Check:**
1. Is feature enabled? `Settings → Import Settings`
2. Is cron scheduled? Check statistics on settings page
3. Is WP Mail SMTP configured correctly?
4. Check debug log for errors
5. Test with "Send Test Email" button

**Solution:**
```php
// Verify wp_mail works
wp_mail('your@email.com', 'Test', 'Test message');

// Check cron schedule
wp_next_scheduled('avs_daily_reminder_check');
```

---

### Issue: Duplicate Emails

**Check:**
1. Database table exists
2. Reminders are being recorded
3. No multiple cron jobs scheduled

**Solution:**
```sql
-- Check for duplicates in database
SELECT user_id, scholarship_id, reminder_type, COUNT(*) 
FROM wp_avs_scholarship_reminders 
GROUP BY user_id, scholarship_id, reminder_type 
HAVING COUNT(*) > 1;
```

---

### Issue: Wrong Users Receiving Emails

**Check:**
1. Favorites plugin active
2. User meta 'simplefavorites' exists
3. Scholarship IDs match

**Debug:**
```php
// Check a user's favorites
$user_id = 123;
$favorites = get_user_meta($user_id, 'simplefavorites', true);
var_dump($favorites);
```

---

### Issue: Cron Not Running

**Check:**
1. Site has regular traffic (wp-cron requires visits)
2. Cron is not disabled in wp-config.php
3. Check next scheduled time

**Solution:**
```php
// Check next run
$next = wp_next_scheduled('avs_daily_reminder_check');
echo date('Y-m-d H:i:s', $next);

// Manually run
do_action('avs_daily_reminder_check');
```

**Better Solution:** Use system cron
```bash
# Add to server crontab
0 9 * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron
```

---

### Issue: Performance Problems

**Symptoms:**
- Slow admin pages
- Timeouts during cron
- High database load

**Solutions:**

1. **Reduce batch limit:**
   ```php
   private $batch_limit = 25; // Reduce from 50
   ```

2. **Add database indexes:**
   ```sql
   ALTER TABLE wp_usermeta ADD INDEX meta_value_idx (meta_value(100));
   ```

3. **Implement caching:**
   ```php
   $scholarships = wp_cache_get('avs_deadline_' . $date);
   if ($scholarships === false) {
       $scholarships = /* query */;
       wp_cache_set('avs_deadline_' . $date, $scholarships, '', 3600);
   }
   ```

4. **Use system cron** instead of wp-cron

---

## Advanced Topics

### Custom Email Templates

Create a template file in your theme:
```php
// themes/your-theme/aviation-scholarships/email-reminder.php
?>
<html>
<body>
    <h1>Custom Template</h1>
    <!-- Your design -->
</body>
</html>
```

Load it:
```php
add_filter('avs_reminder_email_message', function($message, $user_id, $scholarships, $type) {
    ob_start();
    include get_stylesheet_directory() . '/aviation-scholarships/email-reminder.php';
    return ob_get_clean();
}, 10, 4);
```

---

### Multi-site Support

The system supports WordPress multisite:

```php
// Current blog ID
global $blog_id;

// Process for specific site
switch_to_blog($site_id);
// ... process reminders
restore_current_blog();
```

---

### Webhook Integration

Add webhook notifications when emails are sent:

```php
add_action('avs_reminder_sent', function($user_id, $scholarship_ids, $type) {
    wp_remote_post('https://your-webhook.com/endpoint', array(
        'body' => json_encode(array(
            'user_id' => $user_id,
            'scholarships' => $scholarship_ids,
            'type' => $type
        ))
    ));
}, 10, 3);
```

---

## Security Considerations

1. **Nonce Verification** - All admin actions use nonces
2. **Capability Checks** - Only admins can manage settings
3. **Prepared Statements** - All database queries use prepared statements
4. **Input Sanitization** - All user input is sanitized
5. **Output Escaping** - All output is escaped

---

## Maintenance Tasks

### Monthly

1. Check reminder statistics
2. Review error logs
3. Test email delivery

### Quarterly

1. Clean up old reminder records:
   ```php
   $db = new \Aviation_Scholarships\Reminder_Database();
   $db->cleanup_old_reminders(180); // 6 months
   ```

2. Optimize database table:
   ```sql
   OPTIMIZE TABLE wp_avs_scholarship_reminders;
   ```

### Annually

1. Review and update email templates
2. Check for plugin compatibility
3. Update documentation

---

## Support & Resources

- **Plugin Location:** `/wp-content/plugins/aviation-scholarships/`
- **Database Table:** `wp_avs_scholarship_reminders`
- **Cron Hook:** `avs_daily_reminder_check`
- **Settings Page:** `Scholarships → Import Settings`

---

## Changelog

### Version 1.1.0 (December 2025)
- Initial implementation of email reminder system
- Added database table for tracking
- Integrated with Favorites plugin
- Added admin settings page
- Implemented daily cron scheduling

---

**End of Documentation**
