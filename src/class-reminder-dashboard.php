<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) die;

/**
 * Email Reminder Dashboard Page
 * 
 * Dedicated admin page for managing email reminder settings and monitoring.
 * Inspired by Dizetech Reminder plugin architecture.
 */
class Reminder_Dashboard {

    /**
     * Initialize the dashboard
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Register admin_post handlers
        add_action('admin_post_avs_send_test_email', [__CLASS__, 'handle_test_email']);
        add_action('admin_post_avs_debug_favorites', [__CLASS__, 'handle_debug_favorites']);
        
        // Intercept test email submissions
        add_action('admin_init', function() {
            if (isset($_POST['action']) && $_POST['action'] === 'avs_send_test_email') {
                self::handle_test_email();
            }
        }, 1);
    }

    /**
     * Register admin menu page
     */
    public static function register_menu() {
        add_submenu_page(
            'edit.php?post_type=scholarship',
            'Email Reminders Dashboard',
            'Email Reminders',
            'manage_options',
            'avs-reminder-dashboard',
            [__CLASS__, 'render_dashboard_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Reminder Settings Group
        register_setting('avs_reminder_settings_group', 'avs_email_reminders_enabled', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('avs_reminder_settings_group', 'avs_reminder_from_name', [
            'type' => 'string',
            'default' => get_bloginfo('name'),
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('avs_reminder_settings_group', 'avs_reminder_from_email', [
            'type' => 'string',
            'default' => get_option('admin_email'),
            'sanitize_callback' => 'sanitize_email'
        ]);
        
        // Email Template Settings
        register_setting('avs_reminder_templates_group', 'avs_reminder_30day_subject', [
            'type' => 'string',
            'default' => 'Scholarship Deadline Reminder - 30 Days',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('avs_reminder_templates_group', 'avs_reminder_30day_message', [
            'type' => 'string',
            'default' => self::get_default_template(),
            'sanitize_callback' => 'wp_kses_post'
        ]);
        
        register_setting('avs_reminder_templates_group', 'avs_reminder_15day_subject', [
            'type' => 'string',
            'default' => 'Scholarship Deadline Reminder - 15 Days',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('avs_reminder_templates_group', 'avs_reminder_15day_message', [
            'type' => 'string',
            'default' => self::get_default_template(),
            'sanitize_callback' => 'wp_kses_post'
        ]);
        
        register_setting('avs_reminder_templates_group', 'avs_reminder_5day_subject', [
            'type' => 'string',
            'default' => 'Scholarship Deadline Reminder - 5 Days',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('avs_reminder_templates_group', 'avs_reminder_5day_message', [
            'type' => 'string',
            'default' => self::get_default_template(),
            'sanitize_callback' => 'wp_kses_post'
        ]);
    }

    /**
     * Get default email template
     */
    private static function get_default_template() {
        return "Hi {user_name},\n\nThis is a friendly reminder that the scholarship \"{scholarship_title}\" has a deadline coming up in {days_remaining} days.\n\nDeadline: {deadline_date}\n\nDon't miss this opportunity!\n\nBest regards,\n{site_name}";
    }

    /**
     * Render the dashboard page
     */
    public static function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Handle notifications
        self::display_notifications();

        $reminders_enabled = get_option('avs_email_reminders_enabled', 'yes');
        $from_name = get_option('avs_reminder_from_name', get_bloginfo('name'));
        $from_email = get_option('avs_reminder_from_email', get_option('admin_email'));

        // Get statistics
        $stats = [];
        if (class_exists('Aviation_Scholarships\\Reminder_Manager')) {
            $manager = new \Aviation_Scholarships\Reminder_Manager();
            $stats = $manager->get_statistics();
        }

        ?>
        <div class="wrap avs-reminder-dashboard">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-email-alt" style="font-size: 28px; margin-right: 10px; vertical-align: middle;"></span>
                Email Reminders Dashboard
            </h1>
            
            <p class="description" style="margin-top: 10px; font-size: 14px;">
                Manage automated email reminders for scholarship deadlines. Users will be notified 30, 15, and 5 days before their saved scholarship deadlines.
            </p>

            <hr class="wp-header-end">

            <!-- Overview Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
                
                <!-- Status Card -->
                <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid <?php echo $reminders_enabled === 'yes' ? '#00a32a' : '#dba617'; ?>; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <span class="dashicons dashicons-admin-settings" style="font-size: 24px; color: #787c82; margin-right: 10px;"></span>
                        <h3 style="margin: 0; font-size: 14px; color: #787c82;">System Status</h3>
                    </div>
                    <p style="font-size: 24px; font-weight: 600; margin: 10px 0 5px 0; color: <?php echo $reminders_enabled === 'yes' ? '#00a32a' : '#dba617'; ?>;">
                        <?php echo $reminders_enabled === 'yes' ? 'Active' : 'Disabled'; ?>
                    </p>
                    <p style="margin: 0; color: #646970; font-size: 13px;">
                        <?php echo $reminders_enabled === 'yes' ? 'Daily cron running at 9:00 AM' : 'Email reminders are currently disabled'; ?>
                    </p>
                </div>

                <!-- Total Sent Card -->
                <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <span class="dashicons dashicons-email" style="font-size: 24px; color: #787c82; margin-right: 10px;"></span>
                        <h3 style="margin: 0; font-size: 14px; color: #787c82;">Total Reminders Sent</h3>
                    </div>
                    <p style="font-size: 24px; font-weight: 600; margin: 10px 0 5px 0;">
                        <?php echo number_format(intval($stats['total_sent'] ?? 0)); ?>
                    </p>
                    <p style="margin: 0; color: #646970; font-size: 13px;">
                        All-time email reminders delivered
                    </p>
                </div>

                <!-- Last 30 Days Card -->
                <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #72aee6; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <span class="dashicons dashicons-calendar-alt" style="font-size: 24px; color: #787c82; margin-right: 10px;"></span>
                        <h3 style="margin: 0; font-size: 14px; color: #787c82;">Recent Activity</h3>
                    </div>
                    <p style="font-size: 24px; font-weight: 600; margin: 10px 0 5px 0;">
                        <?php echo number_format(intval($stats['last_30_days'] ?? 0)); ?>
                    </p>
                    <p style="margin: 0; color: #646970; font-size: 13px;">
                        Reminders sent in last 30 days
                    </p>
                </div>

                <!-- Next Run Card -->
                <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #9a6eff; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <span class="dashicons dashicons-clock" style="font-size: 24px; color: #787c82; margin-right: 10px;"></span>
                        <h3 style="margin: 0; font-size: 14px; color: #787c82;">Next Scheduled Run</h3>
                    </div>
                    <p style="font-size: 16px; font-weight: 600; margin: 10px 0 5px 0;">
                        <?php echo esc_html($stats['next_scheduled_formatted'] ?? 'Not scheduled'); ?>
                    </p>
                    <p style="margin: 0; color: #646970; font-size: 13px;">
                        Last run: <?php echo esc_html($stats['last_run'] ?? 'Never'); ?>
                    </p>
                </div>

            </div>

            <!-- Settings Tabs -->
            <?php
            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
            ?>
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=scholarship&page=avs-reminder-dashboard&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    General Settings
                </a>
                <a href="?post_type=scholarship&page=avs-reminder-dashboard&tab=templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    Email Templates
                </a>
                <a href="?post_type=scholarship&page=avs-reminder-dashboard&tab=testing" class="nav-tab <?php echo $active_tab === 'testing' ? 'nav-tab-active' : ''; ?>">
                    Testing & Tools
                </a>
            </h2>

            <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <?php
                switch ($active_tab) {
                    case 'templates':
                        self::render_templates_tab();
                        break;
                    case 'testing':
                        self::render_testing_tab();
                        break;
                    default:
                        self::render_general_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display notifications
     */
    private static function display_notifications() {
        // Test email notification
        if (isset($_GET['test_email'])) {
            $test_result = $_GET['test_email'];
            $test_email_to = isset($_GET['test_email_to']) ? urldecode($_GET['test_email_to']) : get_option('admin_email');
            
            if ($test_result === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Test email sent successfully!</strong> Check your inbox at ' . esc_html($test_email_to) . '</p></div>';
            } elseif ($test_result === 'invalid') {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Invalid email address.</strong> Please enter a valid email address.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Failed to send test email.</strong> Please check your email configuration.</p></div>';
            }
        }
        
        // Reminder test notification with detailed results
        if (isset($_GET['reminder_test'])) {
            $reminder_result = $_GET['reminder_test'];
            
            if ($reminder_result === 'success') {
                $emails_sent = isset($_GET['emails_sent']) ? intval($_GET['emails_sent']) : 0;
                $duration = isset($_GET['duration']) ? floatval($_GET['duration']) : 0;
                $breakdown = isset($_GET['breakdown']) ? json_decode(urldecode($_GET['breakdown']), true) : array();
                
                if ($emails_sent > 0) {
                    $breakdown_html = '';
                    if (!empty($breakdown)) {
                        $parts = array();
                        if (isset($breakdown['30_days']) && $breakdown['30_days'] > 0) {
                            $parts[] = '<strong>' . $breakdown['30_days'] . '</strong> for 30-day reminders';
                        }
                        if (isset($breakdown['15_days']) && $breakdown['15_days'] > 0) {
                            $parts[] = '<strong>' . $breakdown['15_days'] . '</strong> for 15-day reminders';
                        }
                        if (isset($breakdown['5_days']) && $breakdown['5_days'] > 0) {
                            $parts[] = '<strong>' . $breakdown['5_days'] . '</strong> for 5-day reminders';
                        }
                        if (!empty($parts)) {
                            $breakdown_html = '<br><small>Breakdown: ' . implode(', ', $parts) . '</small>';
                        }
                    }
                    
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>✓ Reminder check completed successfully!</strong><br>';
                    echo 'Sent <strong>' . esc_html($emails_sent) . '</strong> reminder email(s) in ' . esc_html($duration) . ' seconds.';
                    echo $breakdown_html;
                    echo '</p></div>';
                } else {
                    echo '<div class="notice notice-info is-dismissible">';
                    echo '<p><strong>ℹ Reminder check completed.</strong><br>';
                    echo 'No emails were sent. Possible reasons:<br>';
                    echo '<small>';
                    echo '• No scholarships have deadlines exactly 30, 15, or 5 days from today<br>';
                    echo '• No users have favorited scholarships with upcoming deadlines<br>';
                    echo '• All due reminders have already been sent (check prevents duplicates)';
                    echo '</small>';
                    echo '</p></div>';
                }
            } elseif ($reminder_result === 'disabled') {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>⚠ Email reminders are disabled.</strong><br>';
                echo 'Please enable email reminders in the <a href="?post_type=scholarship&page=avs-reminder-dashboard&tab=general">General Settings</a> tab to send reminder emails.';
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Reminder check failed.</strong> Please check your error logs for details.</p></div>';
            }
        }

        // Settings saved notification
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved successfully!</strong></p></div>';
        }
        
        // Debug complete notification
        if (isset($_GET['debug']) && $_GET['debug'] === 'complete') {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>🔍 Debug information written to log!</strong><br>';
            echo 'Check your <code>wp-content/debug.log</code> file for detailed favorites data inspection.<br>';
            echo '<small>Look for "AVS DEBUG: FAVORITES DATA INSPECTION" in the log.</small>';
            echo '</p></div>';
        }
    }

    /**
     * Render General Settings Tab
     */
    private static function render_general_tab() {
        $reminders_enabled = get_option('avs_email_reminders_enabled', 'yes');
        $from_name = get_option('avs_reminder_from_name', get_bloginfo('name'));
        $from_email = get_option('avs_reminder_from_email', get_option('admin_email'));
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('avs_reminder_settings_group'); ?>
            
            <h2>Email Reminder Configuration</h2>
            <p class="description">Configure when and how email reminders are sent to users for their saved scholarship deadlines.</p>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="avs_email_reminders_enabled">Enable Email Reminders</label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="avs_email_reminders_enabled" value="yes" <?php checked($reminders_enabled, 'yes'); ?>>
                                    <strong>Yes</strong> - Daily cron runs at 9:00 AM
                                </label>
                                <br>
                                <label style="margin-top: 10px; display: block;">
                                    <input type="radio" name="avs_email_reminders_enabled" value="no" <?php checked($reminders_enabled, 'no'); ?>>
                                    <strong>No</strong> - Disable all email reminders
                                </label>
                            </fieldset>
                            <p class="description">When enabled, users receive notifications 30, 15, and 5 days before their saved scholarship deadlines.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="avs_reminder_from_name">From Name</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="avs_reminder_from_name" 
                                   name="avs_reminder_from_name" 
                                   value="<?php echo esc_attr($from_name); ?>" 
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            <p class="description">The name that appears in the "From" field of reminder emails. Leave blank to use site name.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="avs_reminder_from_email">From Email Address</label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="avs_reminder_from_email" 
                                   name="avs_reminder_from_email" 
                                   value="<?php echo esc_attr($from_email); ?>" 
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <p class="description">The email address that reminder emails are sent from. Leave blank to use admin email.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2>Reminder Schedule</h2>
            <p class="description">Users receive three reminder emails at the following intervals before the scholarship deadline:</p>
            
            <div style="background: #f6f7f7; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                <ul style="margin: 0; list-style-position: inside;">
                    <li style="margin-bottom: 8px;"><strong>30 Days Before</strong> - First reminder for early planning</li>
                    <li style="margin-bottom: 8px;"><strong>15 Days Before</strong> - Mid-point reminder to start preparation</li>
                    <li style="margin-bottom: 8px;"><strong>5 Days Before</strong> - Final urgent reminder</li>
                </ul>
            </div>

            <p class="description">
                <strong>Note:</strong> Reminders are only sent to users who have saved/favorited scholarships using the Favorites plugin. 
                Each reminder is sent only once per user per scholarship.
            </p>

            <?php submit_button('Save General Settings'); ?>
        </form>
        <?php
    }

    /**
     * Render Email Templates Tab
     */
    private static function render_templates_tab() {
        $subject_30 = get_option('avs_reminder_30day_subject', 'Scholarship Deadline Reminder - 30 Days');
        $message_30 = get_option('avs_reminder_30day_message', self::get_default_template());
        
        $subject_15 = get_option('avs_reminder_15day_subject', 'Scholarship Deadline Reminder - 15 Days');
        $message_15 = get_option('avs_reminder_15day_message', self::get_default_template());
        
        $subject_5 = get_option('avs_reminder_5day_subject', 'Scholarship Deadline Reminder - 5 Days');
        $message_5 = get_option('avs_reminder_5day_message', self::get_default_template());
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('avs_reminder_templates_group'); ?>
            
            <h2>Email Templates</h2>
            <p class="description">Customize the content of reminder emails. You can use these placeholders:</p>
            
            <div style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <strong>Available Placeholders:</strong><br>
                <code>{user_name}</code> - Recipient's name &nbsp;|&nbsp;
                <code>{scholarship_title}</code> - Scholarship name &nbsp;|&nbsp;
                <code>{deadline_date}</code> - Deadline date &nbsp;|&nbsp;
                <code>{days_remaining}</code> - Days until deadline &nbsp;|&nbsp;
                <code>{site_name}</code> - Your site name &nbsp;|&nbsp;
                <code>{scholarship_link}</code> - Link to scholarship page
            </div>

            <!-- 30-Day Reminder Template -->
            <div style="background: #fff; border: 1px solid #dcdcde; padding: 20px; margin: 20px 0;">
                <h3 style="margin-top: 0; border-bottom: 1px solid #dcdcde; padding-bottom: 10px;">
                    <span class="dashicons dashicons-calendar" style="color: #2271b1;"></span> 30-Day Reminder Template
                </h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="avs_reminder_30day_subject">Email Subject</label></th>
                        <td>
                            <input type="text" 
                                   id="avs_reminder_30day_subject" 
                                   name="avs_reminder_30day_subject" 
                                   value="<?php echo esc_attr($subject_30); ?>" 
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="avs_reminder_30day_message">Email Message</label></th>
                        <td>
                            <textarea id="avs_reminder_30day_message" 
                                      name="avs_reminder_30day_message" 
                                      rows="8" 
                                      class="large-text code"><?php echo esc_textarea($message_30); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 15-Day Reminder Template -->
            <div style="background: #fff; border: 1px solid #dcdcde; padding: 20px; margin: 20px 0;">
                <h3 style="margin-top: 0; border-bottom: 1px solid #dcdcde; padding-bottom: 10px;">
                    <span class="dashicons dashicons-calendar" style="color: #d63638;"></span> 15-Day Reminder Template
                </h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="avs_reminder_15day_subject">Email Subject</label></th>
                        <td>
                            <input type="text" 
                                   id="avs_reminder_15day_subject" 
                                   name="avs_reminder_15day_subject" 
                                   value="<?php echo esc_attr($subject_15); ?>" 
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="avs_reminder_15day_message">Email Message</label></th>
                        <td>
                            <textarea id="avs_reminder_15day_message" 
                                      name="avs_reminder_15day_message" 
                                      rows="8" 
                                      class="large-text code"><?php echo esc_textarea($message_15); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 5-Day Reminder Template -->
            <div style="background: #fff; border: 1px solid #dcdcde; padding: 20px; margin: 20px 0;">
                <h3 style="margin-top: 0; border-bottom: 1px solid #dcdcde; padding-bottom: 10px;">
                    <span class="dashicons dashicons-warning" style="color: #dba617;"></span> 5-Day Urgent Reminder Template
                </h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="avs_reminder_5day_subject">Email Subject</label></th>
                        <td>
                            <input type="text" 
                                   id="avs_reminder_5day_subject" 
                                   name="avs_reminder_5day_subject" 
                                   value="<?php echo esc_attr($subject_5); ?>" 
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="avs_reminder_5day_message">Email Message</label></th>
                        <td>
                            <textarea id="avs_reminder_5day_message" 
                                      name="avs_reminder_5day_message" 
                                      rows="8" 
                                      class="large-text code"><?php echo esc_textarea($message_5); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button('Save Email Templates'); ?>
        </form>
        <?php
    }

    /**
     * Render Testing & Tools Tab
     */
    private static function render_testing_tab() {
        ?>
        <h2>Testing & Diagnostic Tools</h2>
        <p class="description">Test your email configuration and manually trigger reminder checks.</p>

        <!-- Test Email Tool -->
        <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0;">
                <span class="dashicons dashicons-email-alt" style="color: #2271b1;"></span> Send Test Email
            </h3>
            <p>Send a sample reminder email to verify your email configuration is working correctly.</p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;">
                <?php wp_nonce_field('avs_send_test_email_nonce'); ?>
                <input type="hidden" name="action" value="avs_send_test_email" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_email_address">Recipient Email</label></th>
                        <td>
                            <input type="email" 
                                   id="test_email_address" 
                                   name="test_email_address" 
                                   value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                   class="regular-text"
                                   required
                                   placeholder="recipient@example.com">
                            <p class="description">A sample reminder email will be sent to this address.</p>
                        </td>
                    </tr>
                </table>
                
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-email" style="vertical-align: middle;"></span> Send Test Email
                </button>
            </form>
        </div>

        <!-- Manual Reminder Check -->
        <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0;">
                <span class="dashicons dashicons-controls-play" style="color: #00a32a;"></span> Manual Reminder Check
            </h3>
            <p>Manually trigger the reminder check process. This will scan for due reminders and send emails immediately.</p>
            
            <p style="margin: 15px 0;">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=avs_test_reminder'), 'avs_test_reminder_nonce')); ?>" 
                   class="button button-secondary"
                   onclick="return confirm('This will manually trigger the reminder check process and send any due reminder emails. Continue?');">
                    <span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span> Run Reminder Check Now
                </a>
                
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=avs_debug_favorites'), 'avs_debug_favorites_nonce')); ?>" 
                   class="button button-secondary"
                   style="margin-left: 10px;">
                    <span class="dashicons dashicons-search" style="vertical-align: middle;"></span> Debug Favorites Data
                </a>
            </p>
            
            <div style="background: #f6f7f7; border-left: 4px solid #72aee6; padding: 15px; margin-top: 15px;">
                <strong>Note:</strong> This runs the same process as the daily cron job. It will only send reminders that are actually due based on scholarship deadlines and user favorites.
            </div>
        </div>

        <!-- WordPress Cron Information -->
        <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0;">
                <span class="dashicons dashicons-clock" style="color: #9a6eff;"></span> WordPress Cron Information
            </h3>
            <p>The email reminder system uses WordPress's built-in cron to schedule daily checks at 9:00 AM.</p>
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <strong>⚠️ Important:</strong> WordPress cron only runs when someone visits your site. For guaranteed delivery, you may want to set up a server cron job to ping wp-cron.php every few minutes.
            </div>

            <details style="margin-top: 15px;">
                <summary style="cursor: pointer; font-weight: 600; color: #2271b1;">
                    Show server cron setup instructions
                </summary>
                <div style="margin-top: 15px; padding: 15px; background: #f6f7f7; border-radius: 4px;">
                    <p><strong>Add this to your server's crontab to run every 5 minutes:</strong></p>
                    <code style="display: block; padding: 10px; background: #fff; border: 1px solid #dcdcde; margin: 10px 0;">
                        */5 * * * * wget -q -O - "<?php echo esc_url(site_url('wp-cron.php')); ?>" &gt; /dev/null 2&gt;&1
                    </code>
                    <p style="margin-top: 10px;"><strong>Or using curl:</strong></p>
                    <code style="display: block; padding: 10px; background: #fff; border: 1px solid #dcdcde; margin: 10px 0;">
                        */5 * * * * curl -s "<?php echo esc_url(site_url('wp-cron.php')); ?>" &gt; /dev/null 2&gt;&1
                    </code>
                    <p style="margin-top: 15px;">
                        <a href="https://www.youtube.com/results?search_query=how+to+add+cron+job+cpanel" target="_blank">
                            Watch a tutorial on setting up cron jobs in cPanel →
                        </a>
                    </p>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Handle test email submission
     */
    public static function handle_test_email() {
        // Clear any previous import errors to prevent confusion
        delete_transient('avs_import_error');
        
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'avs_send_test_email_nonce')) {
            wp_die('Security check failed');
        }

        // Get and validate email address
        $to = isset($_POST['test_email_address']) ? sanitize_email($_POST['test_email_address']) : '';
        
        if (!is_email($to)) {
            wp_redirect(add_query_arg(array(
                'page' => 'avs-reminder-dashboard',
                'tab' => 'testing',
                'test_email' => 'invalid'
            ), admin_url('edit.php?post_type=scholarship')));
            exit;
        }

        // Send test email
        if (class_exists('Aviation_Scholarships\\Reminder_Email')) {
            $email_handler = new \Aviation_Scholarships\Reminder_Email();
            $sent = $email_handler->send_test_email($to);

            wp_redirect(add_query_arg(array(
                'page' => 'avs-reminder-dashboard',
                'tab' => 'testing',
                'test_email' => $sent ? 'success' : 'failed',
                'test_email_to' => urlencode($to)
            ), admin_url('edit.php?post_type=scholarship')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'avs-reminder-dashboard',
                'tab' => 'testing',
                'test_email' => 'failed'
            ), admin_url('edit.php?post_type=scholarship')));
        }  
        exit;
    }
    
    /**
     * Debug favorites data - shows what's actually stored in the database
     */
    public static function handle_debug_favorites() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'avs_debug_favorites_nonce')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        
        error_log("======= AVS DEBUG: FAVORITES DATA INSPECTION =======");
        
        // 1. Check if any users have simplefavorites meta
        $users_with_favorites = $wpdb->get_results(
            "SELECT user_id, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'simplefavorites'
            LIMIT 10"
        );
        
        error_log("Total users with 'simplefavorites' meta: " . count($users_with_favorites));
        
        if (empty($users_with_favorites)) {
            error_log("NO USERS found with 'simplefavorites' meta key!");
            error_log("This means favorites are either not being saved, or using a different meta key.");
            
            // Check for alternative meta keys
            $alt_keys = $wpdb->get_results(
                "SELECT DISTINCT meta_key 
                FROM {$wpdb->usermeta} 
                WHERE meta_key LIKE '%favor%' 
                OR meta_key LIKE '%bookmark%' 
                OR meta_key LIKE '%save%'"
            );
            
            if (!empty($alt_keys)) {
                error_log("Found alternative favorite-related meta keys:");
                foreach ($alt_keys as $key_row) {
                    error_log("  - " . $key_row->meta_key);
                }
            }
        } else {
            error_log("Found " . count($users_with_favorites) . " user(s) with favorites data:");
            
            foreach ($users_with_favorites as $row) {
                $user = get_userdata($row->user_id);
                $user_login = $user ? $user->user_login : 'Unknown';
                
                error_log("\n--- User ID: {$row->user_id} ({$user_login}) ---");
                error_log("Raw meta_value: " . $row->meta_value);
                
                $favorites = maybe_unserialize($row->meta_value);
                error_log("Unserialized structure:");
                error_log(print_r($favorites, true));
                
                // Try to extract scholarship IDs
                if (is_array($favorites)) {
                    foreach ($favorites as $site_data) {
                        if (isset($site_data['posts']) && is_array($site_data['posts'])) {
                            error_log("  Scholarship IDs in this user's favorites: " . implode(', ', $site_data['posts']));
                        }
                    }
                }
            }
        }
        
        // 2. Check current user's favorites
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            error_log("\n--- CURRENT USER (ID: {$current_user_id}) ---");
            $current_favorites = get_user_meta($current_user_id, 'simplefavorites', true);
            
            if (empty($current_favorites)) {
                error_log("Current user has NO favorites saved.");
            } else {
                error_log("Current user favorites:");
                error_log(print_r($current_favorites, true));
            }
        }
        
        // 3. Check scholarship 1455 specifically
        error_log("\n--- CHECKING SCHOLARSHIP ID 1455 ---");
        $scholarship = get_post(1455);
        if ($scholarship) {
            error_log("Scholarship 1455 exists: " . $scholarship->post_title);
            error_log("Status: " . $scholarship->post_status);
            $deadline = get_field('sch_deadline', 1455);
            $status = get_field('sch_status', 1455);
            error_log("Deadline: " . $deadline);
            error_log("ACF Status: " . $status);
        } else {
            error_log("Scholarship 1455 does NOT exist!");
        }
        
        // Check who should have favorited it
        $users_who_favorited = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_value
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'simplefavorites'
            AND meta_value LIKE %s",
            '%"1455"%'
        ));
        
        error_log("Users with scholarship 1455 in favorites (via LIKE query): " . count($users_who_favorited));
        if (!empty($users_who_favorited)) {
            foreach ($users_who_favorited as $row) {
                error_log("  User ID " . $row->user_id . " has 1455 in meta_value");
            }
        }
        
        error_log("======= END DEBUG =======");
        
        // Redirect back with success message
        wp_redirect(add_query_arg(array(
            'page' => 'avs-reminder-dashboard',
            'tab' => 'testing',
            'debug' => 'complete'
        ), admin_url('edit.php?post_type=scholarship')));
        exit;
    }
}
