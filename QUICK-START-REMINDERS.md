# Email Reminder System - Quick Start Guide

## For Senior PHP Developer

This is a concise technical overview of the email reminder system implementation.

---

## Files Added

1. **src/class-reminder-database.php** - Database table management
2. **src/class-reminder-email.php** - Email template and sending
3. **src/class-reminder-manager.php** - Cron scheduling and orchestration
4. **Modified: src/class-settings-page.php** - Admin UI integration
5. **Modified: aviation-scholarships.php** - Plugin initialization

---

## How It Works

### 1. Daily Cron Job (9:00 AM)

```php
// Hook: avs_daily_reminder_check
// Runs: Daily at 9:00 AM server time
add_action('avs_daily_reminder_check', [$manager, 'process_daily_reminders']);
```

### 2. Process Flow

```
For each interval (30, 15, 5 days):
  ↓
Find scholarships with deadline = today + interval
  ↓
For each scholarship:
  Query users who favorited it (from user meta 'simplefavorites')
  ↓
  For each user:
    Check if reminder already sent (database lookup)
    If not: send email & record in database
```

### 3. Database Tracking

```sql
-- Prevents duplicate emails
CREATE TABLE wp_avs_scholarship_reminders (
    user_id, 
    scholarship_id, 
    reminder_type ('30_days', '15_days', '5_days'),
    sent_date
);
```

---

## Key Classes & Methods

### Reminder_Manager

```php
$manager = new \Aviation_Scholarships\Reminder_Manager();

// Main process (called by cron)
$manager->process_daily_reminders();

// Manual trigger
$manager->handle_test_reminder();

// Get stats
$stats = $manager->get_statistics();
```

### Reminder_Database

```php
$db = new \Aviation_Scholarships\Reminder_Database();

// Check if sent
$exists = $db->reminder_exists($user_id, $scholarship_id, '30_days');

// Record sent reminder
$db->add_reminder($user_id, $scholarship_id, '30_days', $deadline_date);

// Cleanup old records
$db->cleanup_old_reminders(180); // 180 days
```

### Reminder_Email

```php
$email = new \Aviation_Scholarships\Reminder_Email();

// Send reminder
$sent = $email->send_reminder($user_id, $scholarships_array, '30_days');

// Send test
$email->send_test_email('admin@example.com');
```

---

## Integration with Favorites Plugin

### How Favorites Are Stored

```php
// User meta key: 'simplefavorites'
// Structure:
array(
    1 => array(  // Site ID
        'site_id' => 1,
        'posts' => array(123, 456, 789)  // Scholarship post IDs
    )
)
```

### Finding Users Who Favorited a Scholarship

```php
global $wpdb;
$users = $wpdb->get_col($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->usermeta} 
    WHERE meta_key = 'simplefavorites' 
    AND meta_value LIKE %s",
    '%"' . $scholarship_id . '"%'
));
```

---

## WP Mail SMTP Integration

**No special code required!**

The system uses standard WordPress `wp_mail()` function:

```php
wp_mail($to, $subject, $message, $headers);
```

WP Mail SMTP plugin automatically hooks into `wp_mail()` and handles SMTP delivery.

---

## Admin Interface

**Location:** `Scholarships → Import Settings → Email Reminder Settings`

**Features:**
- Enable/disable email reminders
- View statistics (total sent, last run, next scheduled)
- Send test email
- Manually trigger reminder check

---

## Customization Hooks

```php
// Custom email subject
add_filter('avs_reminder_email_subject', function($subject, $user_id, $scholarships, $type) {
    return "Custom: " . $subject;
}, 10, 4);

// Custom email message
add_filter('avs_reminder_email_message', function($message, $user_id, $scholarships, $type) {
    // Return custom HTML template
    return $custom_html;
}, 10, 4);

// After email sent
add_action('avs_reminder_sent', function($user_id, $scholarship_ids, $type) {
    // Custom logging, webhooks, etc.
}, 10, 3);
```

---

## Configuration Options

### Change Reminder Intervals

Edit `class-reminder-manager.php`:
```php
private $reminder_intervals = array(30, 15, 5); // Days before deadline
```

### Change Batch Limit (Performance)

```php
private $batch_limit = 50; // Max emails per cron run
```

### Change Cron Schedule

```php
// In schedule_cron() method
$timestamp = strtotime('tomorrow 09:00:00'); // Change time
wp_schedule_event($timestamp, 'daily', $this->cron_hook);
```

---

## Testing Procedures

### 1. Activate Plugin
```bash
# Database table is created automatically on activation
```

### 2. Configure Settings
- Go to `Scholarships → Import Settings`
- Enable email reminders
- Save settings

### 3. Send Test Email
- Click "Send Test Email" button
- Check admin email inbox
- Verify formatting and links

### 4. Verify Cron Schedule
```php
// Check next scheduled run
$next = wp_next_scheduled('avs_daily_reminder_check');
echo date('Y-m-d H:i:s', $next);
```

### 5. Manual Trigger
- Click "Run Reminder Check Now" button
- Or visit: `wp-admin/admin-post.php?action=avs_test_reminder`

### 6. Check Logs
```php
// Enable debug logging in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// View: wp-content/debug.log
```

---

## Database Queries (Debugging)

```sql
-- View sent reminders
SELECT * FROM wp_avs_scholarship_reminders 
ORDER BY sent_date DESC LIMIT 50;

-- Count by type
SELECT reminder_type, COUNT(*) 
FROM wp_avs_scholarship_reminders 
GROUP BY reminder_type;

-- Find all users who received reminders
SELECT DISTINCT u.user_login, u.user_email, r.sent_date
FROM wp_avs_scholarship_reminders r
JOIN wp_users u ON r.user_id = u.ID
ORDER BY r.sent_date DESC;

-- Check specific user's reminders
SELECT s.post_title, r.reminder_type, r.sent_date
FROM wp_avs_scholarship_reminders r
JOIN wp_posts s ON r.scholarship_id = s.ID
WHERE r.user_id = 123
ORDER BY r.sent_date DESC;
```

---

## Performance Considerations

### For Small Sites (< 1000 users)
- Default settings are fine
- Batch limit: 50

### For Medium Sites (1000-10000 users)
- Increase batch limit: 100-200
- Enable object caching (Redis/Memcached)
- Consider system cron instead of wp-cron

### For Large Sites (> 10000 users)
- Use system cron (required)
- Batch limit: 200-500
- Add database indexes on user meta
- Consider queueing system (WP Background Processing)

### System Cron Setup

```bash
# Disable wp-cron in wp-config.php
define('DISABLE_WP_CRON', true);

# Add to server crontab
0 9 * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron
```

---

## Troubleshooting Quick Reference

| Issue | Check | Solution |
|-------|-------|----------|
| No emails sent | Settings enabled? | Enable in admin |
| | Cron scheduled? | Check statistics |
| | WP Mail SMTP configured? | Test with wp_mail() |
| Duplicate emails | Database table exists? | Check for table |
| | Reminders recorded? | Query database |
| Wrong users | Favorites plugin active? | Activate plugin |
| | User meta exists? | Check 'simplefavorites' |
| Cron not running | Site has traffic? | Use system cron |
| | wp-cron disabled? | Enable or use system cron |
| Performance issues | Too many users? | Reduce batch limit |
| | Slow queries? | Add database indexes |

---

## Common Modifications

### 1. Add More Reminder Intervals

```php
// class-reminder-manager.php
private $reminder_intervals = array(60, 30, 14, 7, 3, 1);
```

### 2. Change Email From Address

```php
// In class-reminder-email.php get_email_headers()
$from_email = 'scholarships@yoursite.com'; // Instead of admin_email
```

### 3. Add CC/BCC to Emails

```php
// In get_email_headers()
$headers[] = 'Cc: admin@yoursite.com';
$headers[] = 'Bcc: records@yoursite.com';
```

### 4. Exclude Certain Scholarships

```php
// In get_scholarships_by_deadline()
$args['meta_query'][] = array(
    'key' => 'sch_exclude_from_reminders',
    'value' => 'yes',
    'compare' => '!='
);
```

### 5. Only Send to Verified Users

```php
// In send_user_reminder()
$user = get_userdata($user_id);
if (!get_user_meta($user_id, 'email_verified', true)) {
    return false; // Skip unverified users
}
```

---

## File Structure

```
plugins/aviation-scholarships/
├── aviation-scholarships.php (modified - initialization)
├── src/
│   ├── class-reminder-database.php (new - DB management)
│   ├── class-reminder-email.php (new - email templates)
│   ├── class-reminder-manager.php (new - cron & orchestration)
│   └── class-settings-page.php (modified - admin UI)
└── EMAIL-REMINDER-IMPLEMENTATION.md (documentation)
```

---

## Security Features

✓ Nonce verification for admin actions  
✓ Capability checks (manage_options)  
✓ Prepared SQL statements  
✓ Input sanitization  
✓ Output escaping  
✓ No direct file access

---

## Next Steps

1. **Activate the plugin** (if not already active)
2. **Enable email reminders** in settings
3. **Send a test email** to verify configuration
4. **Create test data:**
   - Add scholarships with deadlines 30/15/5 days from now
   - Have a test user favorite those scholarships
5. **Run manual trigger** to test
6. **Monitor debug log** for any errors
7. **Verify emails arrive** in test user's inbox

---

## Support

For issues or questions:
1. Check debug log: `wp-content/debug.log`
2. Review full documentation: `EMAIL-REMINDER-IMPLEMENTATION.md`
3. Inspect database: `wp_avs_scholarship_reminders`
4. Test with manual trigger

---

**Version:** 1.1.0  
**Last Updated:** December 2025
