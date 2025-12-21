<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) die;

class Settings_Page {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_post_avs_send_test_email', [__CLASS__, 'handle_test_email']);
    }

    /* ------------------------------
       Add menu under Scholarships
    ------------------------------ */
    public static function register_menu() {
        add_submenu_page(
            'edit.php?post_type=scholarship',
            'Scholarship Import Settings',
            'Import Settings',
            'manage_options',
            'avs-import-settings',
            [__CLASS__, 'render_page']
        );

        add_submenu_page(
            'edit.php?post_type=scholarship',
            'Import Logs',
            'Import Logs',
            'manage_options',
            'avs-import-logs',
            [__CLASS__, 'render_logs_page']
        );
    }

    /* ------------------------------
       Register settings stored in DB
    ------------------------------ */
    public static function register_settings() {
        register_setting('avs_import_group', 'avs_sheet_url');
        register_setting('avs_import_group', 'avs_webhook_secret');
        register_setting('avs_import_group', 'avs_auto_sync'); // yes/no
        register_setting('avs_import_group', 'avs_email_reminders_enabled'); // yes/no
    }

    /* ------------------------------
       Render the admin settings page
    ------------------------------ */
    public static function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $sheet_url = get_option('avs_sheet_url');
        $secret    = get_option('avs_webhook_secret');
        $auto_sync = get_option('avs_auto_sync');

        // Check for import completion and display summary
        $import_done = isset($_GET['import_done']) ? $_GET['import_done'] : '';
        $summary = get_transient('avs_last_import_summary');

        ?>
        <div class="wrap">
            <h1>Aviation Scholarships — Import Settings</h1>

            <?php 
            // Show test email notification
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
            
            // Show import error if exists
            $import_error = get_transient('avs_import_error');
            if ($import_error) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Import Error:</strong> ' . esc_html($import_error) . '</p></div>';
                delete_transient('avs_import_error');
            }
            
            // Handle manual dismissal
            if (isset($_GET['dismiss_summary'])) {
                delete_transient('avs_last_import_summary');
                wp_redirect(admin_url('edit.php?post_type=scholarship&page=avs-import-settings'));
                exit;
            }
            
            // Show summary if exists (regardless of import_done flag)
            if ($summary): 
            ?>
                <div class="notice notice-<?php echo !empty($summary['errors']) ? 'warning' : 'success'; ?> avs-import-summary" style="position: relative;">
                    <p><strong>Import Completed!</strong></p>
                    <ul>
                        <li>Created: <?php echo intval($summary['created'] ?? 0); ?></li>
                        <li>Updated: <?php echo intval($summary['updated'] ?? 0); ?></li>
                        <?php if (!empty($summary['errors'])): ?>
                            <li style="color: #d63638;">Errors: <?php echo count($summary['errors']); ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php if (!empty($summary['errors'])): ?>
                        <details open>
                            <summary style="cursor: pointer; font-weight: 600; color: #d63638;">View All Errors (<?php echo count($summary['errors']); ?> total)</summary>
                            <div style="max-height: 300px; overflow-y: auto; margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd;">
                                <ul style="margin: 0;">
                                    <?php foreach ($summary['errors'] as $err): ?>
                                        <li><code><?php echo esc_html($err); ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </details>
                    <?php endif; ?>
                    <p>
                        <a href="<?php echo esc_url(add_query_arg('dismiss_summary', '1')); ?>" class="button button-small">Clear This Summary</a>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php" class="avs-section">
                <?php settings_fields('avs_import_group'); ?>
                <?php do_settings_sections('avs_import_group'); ?>

                <h2>Google Sheet Settings</h2>
                <p>Enter Google Sheet CSV URL (Published to Web → CSV)</p>
                <input type="url" name="avs_sheet_url" value="<?php echo esc_attr($sheet_url); ?>" style="width: 100%; max-width: 600px;" />

                <h2>Webhook Secret</h2>
                <p>Used for receiving Apps Script push updates.</p>
                <input type="text" name="avs_webhook_secret" value="<?php echo esc_attr($secret); ?>" style="width: 300px;" />

                <h2>Auto-Sync</h2>
                <select name="avs_auto_sync">
                    <option value="no" <?php selected($auto_sync, 'no'); ?>>No</option>
                    <option value="yes" <?php selected($auto_sync, 'yes'); ?>>Yes (Hourly Cron)</option>
                </select>

                <p>
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>


            <hr>

            <h2>Manual Import</h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('avs_manual_import'); ?>
                <input type="hidden" name="action" value="avs_manual_import" />

                <p><strong>CSV URL</strong></p>
                <input type="url" name="csv_url" style="width: 100%; max-width: 600px;">
                <p>or</p>

                <p><strong>Upload CSV File</strong></p>
                <input type="file" name="csv_file" accept=".csv">

                <p><button class="button button-secondary" type="submit">Run Import</button></p>
            </form>

            <hr>

            <h2>Webhook URL</h2>
            <p>Use this URL in your Google Apps Script:</p>
            <code class="avs-webhook-code">
                <?php echo esc_url(site_url('/wp-json/aviation/v1/import-webhook')); ?>
            </code>

            <hr>

            <h2>Email Reminder Settings</h2>
            <form method="post" action="options.php" class="avs-section">
                <?php settings_fields('avs_import_group'); ?>
                
                <p>Enable automatic email reminders to users for their saved scholarships with approaching deadlines (30, 15, and 5 days before).</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Email Reminders</th>
                        <td>
                            <?php $reminders_enabled = get_option('avs_email_reminders_enabled', 'yes'); ?>
                            <select name="avs_email_reminders_enabled">
                                <option value="yes" <?php selected($reminders_enabled, 'yes'); ?>>Yes (Daily Cron at 9:00 AM)</option>
                                <option value="no" <?php selected($reminders_enabled, 'no'); ?>>No</option>
                            </select>
                            <p class="description">When enabled, users will receive email notifications 30, 15, and 5 days before their saved scholarship deadlines.</p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">Save Reminder Settings</button>
                </p>
            </form>

            <?php 
            // Show reminder statistics if available
            if (class_exists('Aviation_Scholarships\\Reminder_Manager')) {
                $manager = new \Aviation_Scholarships\Reminder_Manager();
                $stats = $manager->get_statistics();
                ?>
                <div class="avs-reminder-stats" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                    <h3>Reminder Statistics</h3>
                    <table class="widefat" style="max-width: 600px;">
                        <tr>
                            <th>Total Reminders Sent</th>
                            <td><?php echo intval($stats['total_sent']); ?></td>
                        </tr>
                        <tr>
                            <th>Sent in Last 30 Days</th>
                            <td><?php echo intval($stats['last_30_days']); ?></td>
                        </tr>
                        <tr>
                            <th>Last Run</th>
                            <td><?php echo esc_html($stats['last_run']); ?></td>
                        </tr>
                        <tr>
                            <th>Next Scheduled</th>
                            <td><?php echo esc_html($stats['next_scheduled_formatted']); ?></td>
                        </tr>
                    </table>
                    
                    <p style="margin-top: 15px;">
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=avs_test_reminder')); ?>" 
                           class="button button-secondary"
                           onclick="return confirm('This will manually trigger the reminder check process. Continue?');">
                            Run Reminder Check Now
                        </a>
                    </p>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
                        <h4 style="margin-top: 0;">Send Test Email</h4>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0;">
                            <?php wp_nonce_field('avs_send_test_email_nonce'); ?>
                            <input type="hidden" name="action" value="avs_send_test_email" />
                            <p>
                                <label for="test_email_address">Email Address:</label><br>
                                <input type="email" 
                                       id="test_email_address" 
                                       name="test_email_address" 
                                       value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                       required 
                                       style="width: 300px;" 
                                       placeholder="recipient@example.com">
                            </p>
                            <p>
                                <button type="submit" class="button button-secondary">Send Test Email</button>
                                <span class="description" style="margin-left: 10px;">A sample reminder email will be sent to this address.</span>
                            </p>
                        </form>
                    </div>
                </div>
                <?php
            }
            ?>

        </div>
        <?php
    }

    /* ------------------------------
       Render the import logs viewer
    ------------------------------ */
    public static function render_logs_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $logs = get_option('avs_import_logs', []);
        if (!is_array($logs)) $logs = [];
        $logs = array_reverse($logs); // Most recent first

        ?>
        <div class="wrap">
            <h1>Import Logs</h1>
            
            <?php if (empty($logs)): ?>
                <p>No import logs found.</p>
            <?php else: ?>
                <div class="avs-logs-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Timestamp</th>
                            <th style="width: 10%;">Level</th>
                            <th style="width: 35%;">Message</th>
                            <th style="width: 40%;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['at'] ?? '—'); ?></td>
                                <td>
                                    <?php 
                                    $level = strtoupper($log['level'] ?? 'info');
                                    $level_class = strtolower($level);
                                    ?>
                                    <span class="avs-log-level <?php echo esc_attr($level_class); ?>"><?php echo esc_html($level); ?></span>
                                </td>
                                <td><?php echo esc_html($log['message'] ?? '—'); ?></td>
                                <td>
                                    <?php 
                                    if (isset($log['summary'])) {
                                        echo 'Created: ' . intval($log['summary']['created'] ?? 0) . ', ';
                                        echo 'Updated: ' . intval($log['summary']['updated'] ?? 0) . ', ';
                                        echo 'Errors: ' . count($log['summary']['errors'] ?? []);
                                    }
                                    if (isset($log['rows'])) {
                                        echo ' | Rows: ' . intval($log['rows']);
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ------------------------------
       Handle test email sending
    ------------------------------ */
    public static function handle_test_email() {
        // Log that this handler was called
        error_log('AVS: handle_test_email() called');
        
        // Security check
        if (!current_user_can('manage_options')) {
            error_log('AVS: handle_test_email() - unauthorized user');
            wp_die('Unauthorized');
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'avs_send_test_email_nonce')) {
            error_log('AVS: handle_test_email() - nonce verification failed');
            wp_die('Security check failed');
        }

        // Get and validate email address
        $to = isset($_POST['test_email_address']) ? sanitize_email($_POST['test_email_address']) : '';
        error_log('AVS: handle_test_email() - email address: ' . $to);
        
        if (!is_email($to)) {
            error_log('AVS: handle_test_email() - invalid email');
            wp_redirect(add_query_arg(array(
                'page' => 'avs-import-settings',
                'test_email' => 'invalid'
            ), admin_url('edit.php?post_type=scholarship')));
            exit;
        }

        // Send test email
        if (class_exists('Aviation_Scholarships\\Reminder_Email')) {
            error_log('AVS: handle_test_email() - sending email');
            $email_handler = new \Aviation_Scholarships\Reminder_Email();
            $sent = $email_handler->send_test_email($to);
            error_log('AVS: handle_test_email() - email sent: ' . ($sent ? 'true' : 'false'));

            // Redirect back with result
            wp_redirect(add_query_arg(array(
                'page' => 'avs-import-settings',
                'test_email' => $sent ? 'success' : 'failed',
                'test_email_to' => urlencode($to)
            ), admin_url('edit.php?post_type=scholarship')));
        } else {
            error_log('AVS: handle_test_email() - Reminder_Email class not found');
            wp_redirect(add_query_arg(array(
                'page' => 'avs-import-settings',
                'test_email' => 'failed'
            ), admin_url('edit.php?post_type=scholarship')));
        }
        exit;
    }
}
