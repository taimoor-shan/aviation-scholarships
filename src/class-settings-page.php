<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) die;

class Settings_Page {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
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

            <?php if ($import_done && $summary): ?>
                <div class="notice notice-success is-dismissible avs-import-summary">
                    <p><strong>Import Completed!</strong></p>
                    <ul>
                        <li>Created: <?php echo intval($summary['created'] ?? 0); ?></li>
                        <li>Updated: <?php echo intval($summary['updated'] ?? 0); ?></li>
                        <?php if (!empty($summary['errors'])): ?>
                            <li>Errors: <?php echo count($summary['errors']); ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php if (!empty($summary['errors'])): ?>
                        <details>
                            <summary>View Errors</summary>
                            <ul>
                                <?php foreach (array_slice($summary['errors'], 0, 10) as $err): ?>
                                    <li><code><?php echo esc_html($err); ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
                <?php delete_transient('avs_last_import_summary'); ?>
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
}
