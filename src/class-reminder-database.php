<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Database Management for Email Reminders
 * 
 * This class handles the creation and management of a custom database table
 * that tracks which email reminders have been sent to users for their saved scholarships.
 * 
 * Table Structure:
 * - id: Auto-increment primary key
 * - user_id: WordPress user ID
 * - scholarship_id: Post ID of the scholarship
 * - reminder_type: '30_days', '15_days', or '5_days'
 * - deadline_date: The scholarship deadline (for reference)
 * - sent_date: When the email was sent
 * 
 * @since 1.1.0
 */
class Reminder_Database {

    /**
     * Table name (without WordPress prefix)
     * @var string
     */
    private $table_name = 'avs_scholarship_reminders';

    /**
     * Full table name with WordPress prefix
     * @var string
     */
    private $full_table_name;

    /**
     * Database version for schema updates
     * @var string
     */
    private $db_version = '1.0';

    /**
     * Constructor - initializes table name with WordPress prefix
     */
    public function __construct() {
        global $wpdb;
        $this->full_table_name = $wpdb->prefix . $this->table_name;
    }

    /**
     * Create the reminders table in the database
     * Called on plugin activation
     * 
     * Uses dbDelta() for safe table creation/updates
     * Creates indexes for performance optimization
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->full_table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            scholarship_id bigint(20) unsigned NOT NULL,
            reminder_type varchar(20) NOT NULL,
            deadline_date date NOT NULL,
            sent_date datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_scholarship_type (user_id, scholarship_id, reminder_type),
            KEY deadline_date (deadline_date),
            KEY sent_date (sent_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Store database version
        update_option('avs_reminders_db_version', $this->db_version);
    }

    /**
     * Drop the reminders table
     * Called on plugin uninstall (if needed)
     */
    public function drop_table() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->full_table_name}");
        delete_option('avs_reminders_db_version');
    }

    /**
     * Check if a reminder has already been sent
     * 
     * @param int $user_id WordPress user ID
     * @param int $scholarship_id Scholarship post ID
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @return bool True if reminder was already sent, false otherwise
     */
    public function reminder_exists($user_id, $scholarship_id, $reminder_type) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->full_table_name} 
            WHERE user_id = %d 
            AND scholarship_id = %d 
            AND reminder_type = %s",
            $user_id,
            $scholarship_id,
            $reminder_type
        ));

        return ($count > 0);
    }

    /**
     * Record that a reminder email was sent
     * 
     * @param int $user_id WordPress user ID
     * @param int $scholarship_id Scholarship post ID
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @param string $deadline_date Scholarship deadline (Y-m-d format)
     * @return int|false Insert ID on success, false on failure
     */
    public function add_reminder($user_id, $scholarship_id, $reminder_type, $deadline_date) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->full_table_name,
            array(
                'user_id' => $user_id,
                'scholarship_id' => $scholarship_id,
                'reminder_type' => $reminder_type,
                'deadline_date' => $deadline_date,
                'sent_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get all reminders sent to a specific user
     * 
     * @param int $user_id WordPress user ID
     * @param int $limit Optional limit for results
     * @return array Array of reminder records
     */
    public function get_user_reminders($user_id, $limit = 100) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->full_table_name} 
            WHERE user_id = %d 
            ORDER BY sent_date DESC 
            LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get reminders for a specific scholarship
     * 
     * @param int $scholarship_id Scholarship post ID
     * @return array Array of reminder records
     */
    public function get_scholarship_reminders($scholarship_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->full_table_name} 
            WHERE scholarship_id = %d 
            ORDER BY sent_date DESC",
            $scholarship_id
        ), ARRAY_A);
    }

    /**
     * Delete old reminder records
     * Useful for cleanup and preventing table bloat
     * 
     * @param int $days Delete reminders older than this many days
     * @return int|false Number of rows deleted or false on error
     */
    public function cleanup_old_reminders($days = 180) {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->full_table_name} 
            WHERE sent_date < %s",
            $date
        ));
    }

    /**
     * Delete all reminders for a specific scholarship
     * Called when a scholarship is deleted
     * 
     * @param int $scholarship_id Scholarship post ID
     * @return int|false Number of rows deleted
     */
    public function delete_scholarship_reminders($scholarship_id) {
        global $wpdb;

        return $wpdb->delete(
            $this->full_table_name,
            array('scholarship_id' => $scholarship_id),
            array('%d')
        );
    }

    /**
     * Get statistics about sent reminders
     * Useful for admin dashboard
     * 
     * @return array Statistics array
     */
    public function get_statistics() {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->full_table_name}");
        
        $by_type = $wpdb->get_results(
            "SELECT reminder_type, COUNT(*) as count 
            FROM {$this->full_table_name} 
            GROUP BY reminder_type",
            ARRAY_A
        );

        $last_30_days = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->full_table_name} 
            WHERE sent_date >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        return array(
            'total_sent' => $total,
            'by_type' => $by_type,
            'last_30_days' => $last_30_days
        );
    }

    /**
     * Get table name
     * @return string Full table name with prefix
     */
    public function get_table_name() {
        return $this->full_table_name;
    }
}
