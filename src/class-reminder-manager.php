<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Scholarship Deadline Reminder Manager
 * 
 * This is the main class that orchestrates the entire email reminder system.
 * It handles:
 * - Daily cron job scheduling and execution
 * - Finding users with saved scholarships that have approaching deadlines
 * - Checking if reminders have already been sent
 * - Batching and sending reminder emails
 * - Performance optimization for large user bases
 * 
 * CRON SCHEDULE:
 * - Runs daily at 9:00 AM server time
 * - Can be customized via WordPress cron settings
 * - Uses WordPress's built-in wp-cron system
 * 
 * REMINDER INTERVALS:
 * - 30 days before deadline
 * - 15 days before deadline
 * - 5 days before deadline
 * 
 * INTEGRATION WITH FAVORITES PLUGIN:
 * - Reads from 'simplefavorites' user meta
 * - Supports multi-site installations
 * - Only processes 'scholarship' post type favorites
 * 
 * @since 1.1.0
 */
class Reminder_Manager {

    /**
     * Database handler
     * @var Reminder_Database
     */
    private $db;

    /**
     * Email handler
     * @var Reminder_Email
     */
    private $email;

    /**
     * Cron hook name
     * @var string
     */
    private $cron_hook = 'avs_daily_reminder_check';

    /**
     * Reminder intervals in days
     * @var array
     */
    private $reminder_intervals = array(30, 15, 5);

    /**
     * Maximum emails to send per cron run (prevent timeout)
     * @var int
     */
    private $batch_limit = 50;

    /**
     * Constructor - initializes dependencies
     */
    public function __construct() {
        $this->db = new Reminder_Database();
        $this->email = new Reminder_Email();
    }

    /**
     * Initialize the reminder system
     * Sets up cron schedule and hooks
     */
    public static function init() {
        $instance = new self();
        
        // Schedule cron job if enabled
        add_action('admin_init', array($instance, 'schedule_cron'));
        
        // Hook the daily check function
        add_action($instance->cron_hook, array($instance, 'process_daily_reminders'));
        
        // Admin hooks for manual testing
        add_action('admin_post_avs_test_reminder', array($instance, 'handle_test_reminder'));
        
        return $instance;
    }

    /**
     * Schedule the daily cron job
     * Only schedules if not already scheduled and feature is enabled
     */
    public function schedule_cron() {
        $enabled = get_option('avs_email_reminders_enabled', 'yes');
        
        if ($enabled === 'yes') {
            if (!wp_next_scheduled($this->cron_hook)) {
                // Schedule for 9:00 AM daily
                $timestamp = strtotime('tomorrow 09:00:00');
                wp_schedule_event($timestamp, 'daily', $this->cron_hook);
                error_log("AVS Reminder: Cron job scheduled for daily at 9:00 AM");
            }
        } else {
            // Remove scheduled event if disabled
            $timestamp = wp_next_scheduled($this->cron_hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $this->cron_hook);
                error_log("AVS Reminder: Cron job unscheduled");
            }
        }
    }

    /**
     * Main processing function - runs daily via cron
     * This is the heart of the reminder system
     * 
     * Process Flow:
     * 1. Check if feature is enabled
     * 2. For each reminder interval (30, 15, 5 days):
     *    a. Calculate target deadline date
     *    b. Find scholarships with matching deadlines
     *    c. For each scholarship, find users who favorited it
     *    d. Check if reminder already sent
     *    e. Send email and log reminder
     * 3. Process in batches to prevent timeout
     * 4. Log statistics
     */
    public function process_daily_reminders() {
        // Check if enabled
        if (get_option('avs_email_reminders_enabled', 'yes') !== 'yes') {
            error_log("AVS Reminder: Email reminders are disabled");
            return array(
                'success' => false,
                'message' => 'Email reminders are currently disabled in settings',
                'emails_sent' => 0,
                'breakdown' => array('30_days' => 0, '15_days' => 0, '5_days' => 0)
            );
        }

        error_log("AVS Reminder: Starting daily reminder process");
        $start_time = microtime(true);
        $total_sent = 0;
        $breakdown = array();

        // Process each reminder interval
        foreach ($this->reminder_intervals as $days) {
            $reminder_type = "{$days}_days";
            $sent_count = $this->process_reminder_interval($days, $reminder_type);
            $breakdown[$reminder_type] = $sent_count;
            $total_sent += $sent_count;

            // Check batch limit
            if ($total_sent >= $this->batch_limit) {
                error_log("AVS Reminder: Batch limit reached ({$this->batch_limit}), stopping for today");
                break;
            }
        }

        $duration = round(microtime(true) - $start_time, 2);
        error_log("AVS Reminder: Completed. Sent {$total_sent} emails in {$duration} seconds");

        // Update last run timestamp
        update_option('avs_reminders_last_run', current_time('mysql'));
        
        // Return detailed results for user feedback
        return array(
            'success' => true,
            'emails_sent' => $total_sent,
            'breakdown' => $breakdown,
            'duration' => $duration,
            'message' => $this->format_results_message($total_sent, $breakdown)
        );
    }

    /**
     * Process reminders for a specific interval
     * 
     * @param int $days Number of days before deadline
     * @param string $reminder_type Reminder type identifier
     * @return int Number of emails sent
     */
    private function process_reminder_interval($days, $reminder_type) {
        $sent_count = 0;

        // Calculate target deadline date
        $target_date = date('Y-m-d', strtotime("+{$days} days"));

        // Find scholarships with deadlines matching this date
        $scholarships = $this->get_scholarships_by_deadline($target_date);

        if (empty($scholarships)) {
            error_log("AVS Reminder: No scholarships found for {$reminder_type} ({$target_date})");
            return 0;
        }

        error_log("AVS Reminder: Found " . count($scholarships) . " scholarships for {$reminder_type}");
        if (!empty($scholarships)) {
            error_log("AVS Reminder: Scholarship IDs: " . implode(', ', $scholarships));
        }

        // For each scholarship, find users who favorited it
        foreach ($scholarships as $scholarship_id) {
            $users = $this->get_users_who_favorited($scholarship_id);

            foreach ($users as $user_id) {
                // Check if we've already sent this reminder
                if ($this->db->reminder_exists($user_id, $scholarship_id, $reminder_type)) {
                    continue; // Skip, already sent
                }

                // Group scholarships by user for batch emails
                if (!isset($user_scholarships[$user_id])) {
                    $user_scholarships[$user_id] = array();
                }
                $user_scholarships[$user_id][] = $scholarship_id;
            }
        }

        // Send batch emails to each user
        if (isset($user_scholarships)) {
            foreach ($user_scholarships as $user_id => $scholarship_ids) {
                $success = $this->send_user_reminder($user_id, $scholarship_ids, $reminder_type, $target_date);
                if ($success) {
                    $sent_count++;
                }

                // Stop if batch limit reached
                if ($sent_count >= $this->batch_limit) {
                    break;
                }
            }
        }

        return $sent_count;
    }

    /**
     * Get scholarships with a specific deadline date
     * Only includes active scholarships
     * 
     * @param string $deadline_date Date in Y-m-d format
     * @return array Array of scholarship post IDs
     */
    private function get_scholarships_by_deadline($deadline_date) {
        $args = array(
            'post_type' => 'scholarship',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'sch_deadline',
                    'value' => $deadline_date,
                    'compare' => '=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'sch_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        );

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Get all users who have favorited a specific scholarship
     * Queries user meta for 'simplefavorites'
     * 
     * @param int $scholarship_id Scholarship post ID
     * @return array Array of user IDs
     */
    private function get_users_who_favorited($scholarship_id) {
        global $wpdb;

        error_log("AVS Reminder: Looking for users who favorited scholarship ID: {$scholarship_id}");

        // Query user meta for favorites containing this scholarship
        // The Favorites plugin stores favorites as serialized arrays with integer values
        // Search for both string and integer representations
        $users = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'simplefavorites' 
            AND (meta_value LIKE %s OR meta_value LIKE %s)",
            '%i:' . intval($scholarship_id) . ';%',
            '%"' . $scholarship_id . '"%'
        ));

        error_log("AVS Reminder: Database query found " . count($users) . " potential user(s)");

        // Additional validation: check if scholarship is actually in their favorites
        $validated_users = array();
        foreach ($users as $user_id) {
            $favorites = get_user_meta($user_id, 'simplefavorites', true);
            
            error_log("AVS Reminder: Checking user ID {$user_id}, favorites data type: " . gettype($favorites));
            
            if (!is_array($favorites)) {
                error_log("AVS Reminder: User {$user_id} - favorites is not an array, skipping");
                continue;
            }

            error_log("AVS Reminder: User {$user_id} - favorites structure: " . print_r($favorites, true));

            // Check if this scholarship is in their favorites
            // Favorites plugin stores site_id as key, posts as array
            $found = false;
            foreach ($favorites as $site_favorites) {
                if (isset($site_favorites['posts']) && in_array($scholarship_id, $site_favorites['posts'])) {
                    $validated_users[] = $user_id;
                    $found = true;
                    error_log("AVS Reminder: User {$user_id} - VALIDATED! Scholarship {$scholarship_id} found in their favorites");
                    break;
                }
            }
            
            if (!$found) {
                error_log("AVS Reminder: User {$user_id} - scholarship {$scholarship_id} NOT found in favorites array");
            }
        }

        error_log("AVS Reminder: Total validated users: " . count($validated_users));
        return $validated_users;
    }

    /**
     * Send reminder email to a user for their saved scholarships
     * 
     * @param int $user_id WordPress user ID
     * @param array $scholarship_ids Array of scholarship post IDs
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @param string $deadline_date Deadline date in Y-m-d format
     * @return bool True if email sent successfully
     */
    private function send_user_reminder($user_id, $scholarship_ids, $reminder_type, $deadline_date) {
        // Prepare scholarship data for email
        $scholarships_data = array();
        foreach ($scholarship_ids as $scholarship_id) {
            $data = $this->email->prepare_scholarship_data($scholarship_id);
            if ($data) {
                $scholarships_data[] = $data;
            }
        }

        if (empty($scholarships_data)) {
            return false;
        }

        // Send the email
        $sent = $this->email->send_reminder($user_id, $scholarships_data, $reminder_type);

        // If successful, record in database
        if ($sent) {
            foreach ($scholarship_ids as $scholarship_id) {
                $this->db->add_reminder($user_id, $scholarship_id, $reminder_type, $deadline_date);
            }
        }

        return $sent;
    }

    /**
     * Manual trigger for testing (admin only)
     * URL: wp-admin/admin-post.php?action=avs_test_reminder
     */
    public function handle_test_reminder() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce for security
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'avs_test_reminder_nonce')) {
            wp_die('Security check failed');
        }

        // Run the reminder process and get results
        $results = $this->process_daily_reminders();
        
        // Prepare redirect parameters based on results
        $redirect_args = array(
            'page' => 'avs-reminder-dashboard',
            'tab' => 'testing'
        );
        
        if ($results['success']) {
            $redirect_args['reminder_test'] = 'success';
            $redirect_args['emails_sent'] = $results['emails_sent'];
            $redirect_args['duration'] = $results['duration'];
            // Encode breakdown as JSON for detailed display
            $redirect_args['breakdown'] = urlencode(json_encode($results['breakdown']));
        } else {
            $redirect_args['reminder_test'] = 'disabled';
        }

        // Redirect back to dashboard with results
        wp_redirect(add_query_arg(
            $redirect_args,
            admin_url('edit.php?post_type=scholarship')
        ));
        exit;
    }
    
    /**
     * Format results message for user display
     * 
     * @param int $total_sent Total emails sent
     * @param array $breakdown Breakdown by reminder type
     * @return string Formatted message
     */
    private function format_results_message($total_sent, $breakdown) {
        if ($total_sent === 0) {
            return 'No reminder emails were sent. This could mean: (1) No scholarships have deadlines 30, 15, or 5 days from today, (2) No users have favorited scholarships with upcoming deadlines, or (3) All due reminders have already been sent.';
        }
        
        $parts = array();
        if (isset($breakdown['30_days']) && $breakdown['30_days'] > 0) {
            $parts[] = "{$breakdown['30_days']} for 30-day deadline";
        }
        if (isset($breakdown['15_days']) && $breakdown['15_days'] > 0) {
            $parts[] = "{$breakdown['15_days']} for 15-day deadline";
        }
        if (isset($breakdown['5_days']) && $breakdown['5_days'] > 0) {
            $parts[] = "{$breakdown['5_days']} for 5-day deadline";
        }
        
        $breakdown_text = !empty($parts) ? ' (' . implode(', ', $parts) . ')' : '';
        return "Successfully sent {$total_sent} reminder email(s){$breakdown_text}.";
    }

    /**
     * Get reminder statistics for admin dashboard
     * 
     * @return array Statistics array
     */
    public function get_statistics() {
        $stats = $this->db->get_statistics();
        $stats['last_run'] = get_option('avs_reminders_last_run', 'Never');
        $stats['next_scheduled'] = wp_next_scheduled($this->cron_hook);
        
        if ($stats['next_scheduled']) {
            $stats['next_scheduled_formatted'] = date('Y-m-d H:i:s', $stats['next_scheduled']);
        } else {
            $stats['next_scheduled_formatted'] = 'Not scheduled';
        }

        return $stats;
    }

    /**
     * Cleanup function - remove old reminder records
     * Should be called periodically to prevent database bloat
     * 
     * @param int $days Remove records older than this many days (default 180)
     */
    public function cleanup_old_records($days = 180) {
        $deleted = $this->db->cleanup_old_reminders($days);
        error_log("AVS Reminder: Cleaned up {$deleted} old reminder records");
        return $deleted;
    }

    /**
     * Force clear all reminders for a scholarship (when deadline changes)
     * 
     * @param int $scholarship_id Scholarship post ID
     */
    public function reset_scholarship_reminders($scholarship_id) {
        return $this->db->delete_scholarship_reminders($scholarship_id);
    }

    /**
     * Get database instance (for external access if needed)
     * 
     * @return Reminder_Database
     */
    public function get_database() {
        return $this->db;
    }

    /**
     * Get email instance (for testing)
     * 
     * @return Reminder_Email
     */
    public function get_email_handler() {
        return $this->email;
    }
}
