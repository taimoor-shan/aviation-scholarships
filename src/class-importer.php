<?php

namespace Aviation_Scholarships;

if (!defined('WPINC')) {
    die;
}

/**
 * Class Importer
 *
 * Full-featured importer: manual upload, URL pull, webhook push, and WP-CLI friendly.
 */
class Importer
{

    /**
     * Option keys
     */
    const OPTION_LOGS_KEY = 'avs_import_logs';
    const MAX_LOGS = 50;

    /**
     * Webhook secret (loaded from options)
     * @var string|null
     */
    protected $secret_key = null;

    /**
     * Importer constructor.
     * Hooks for admin form and REST webhook registration.
     */
    public function __construct()
    {
        // load secret from settings
        $this->secret_key = get_option('avs_webhook_secret', '');

        // admin manual import handler
        add_action('admin_post_avs_manual_import', [$this, 'handle_manual_import']);

        // REST webhook for Apps Script (push)
        add_action('rest_api_init', function () {
            register_rest_route('aviation/v1', '/import-webhook', [
                'methods'  => 'POST',
                'callback' => [$this, 'webhook_receive'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    /**
     * Admin manual import handler
     * Accepts either csv_url or csv_file upload via admin-post.php?action=avs_manual_import
     */
    public function handle_manual_import()
    {
        error_log('AVS: handle_manual_import() called');
        error_log('AVS: POST action = ' . (isset($_POST['action']) ? $_POST['action'] : 'not set'));
        
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        // nonce check (if present)
        if (isset($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'avs_manual_import')) {
            error_log('AVS: handle_manual_import() - nonce failed');
            wp_die('Invalid nonce', 403);
        }

        $csv_url = isset($_POST['csv_url']) ? esc_url_raw(trim($_POST['csv_url'])) : '';
        $file = isset($_FILES['csv_file']) ? $_FILES['csv_file'] : null;
        
        error_log('AVS: handle_manual_import() - csv_url: ' . $csv_url);
        error_log('AVS: handle_manual_import() - file provided: ' . ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK ? 'yes' : 'no'));

        $result = false;
        if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $tmp = $file['tmp_name'];
            $result = $this->import_csv_file($tmp);
        } elseif ($csv_url) {
            $result = $this->import_csv_url($csv_url);
        } else {
            // No CSV provided - show error and redirect
            $this->write_log(['level' => 'error', 'message' => 'No CSV provided for manual import.']);
            error_log('AVS: handle_manual_import() - no CSV provided');
            set_transient('avs_import_error', 'Please provide either a CSV URL or upload a CSV file.', 300);
            wp_redirect(admin_url('edit.php?post_type=scholarship&page=avs-import-settings'));
            exit;
        }

        // redirect back to settings with a flag and small summary in transient
        set_transient('avs_last_import_summary', $result, 300); // 5 minutes
        wp_redirect(admin_url('edit.php?post_type=scholarship&page=avs-import-settings&import_done=1'));
        exit;
    }

    /**
     * REST webhook receiver for Apps Script push.
     * Expects JSON: { secret: "...", rows: [ {col: val, ...}, ... ] }
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function webhook_receive(\WP_REST_Request $request)
    {
        $body = $request->get_json_params();

        if (empty($this->secret_key)) {
            return new \WP_REST_Response(['error' => 'Webhook not configured.'], 403);
        }

        $secret = isset($body['secret']) ? (string)$body['secret'] : '';
        if (hash_equals($this->secret_key, $secret) === false) {
            return new \WP_REST_Response(['error' => 'Invalid secret'], 401);
        }

        if (empty($body['rows']) || !is_array($body['rows'])) {
            return new \WP_REST_Response(['error' => 'Invalid payload: rows missing or incorrect.'], 400);
        }

        $rows = $body['rows'];
        $summary = $this->process_rows($rows);

        return new \WP_REST_Response(['imported' => $summary], 200);
    }

    /**
     * Import CSV by remote URL (fetches URL, writes to temp file, forwards to import_csv_file)
     *
     * @param string $url
     * @return array|false
     */
    public function import_csv_url($url)
    {
        if (empty($url)) {
            $this->write_log(['level' => 'error', 'message' => 'Empty CSV URL provided.']);
            return false;
        }

        $tmp = wp_tempnam();
        $args = ['timeout' => 60, 'headers' => ['Accept' => 'text/csv,application/octet-stream,text/plain']];
        $resp = wp_safe_remote_get($url, $args);

        if (is_wp_error($resp)) {
            $this->write_log(['level' => 'error', 'message' => 'Failed to fetch CSV URL: ' . $resp->get_error_message()]);
            return false;
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            $this->write_log(['level' => 'error', 'message' => "CSV URL returned HTTP {$code}"]);
            return false;
        }

        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) {
            $this->write_log(['level' => 'error', 'message' => 'CSV fetched but body empty.']);
            return false;
        }

        file_put_contents($tmp, $body);
        return $this->import_csv_file($tmp);
    }

    /**
     * Import CSV from a local file path
     *
     * @param string $path
     * @return array|false
     */
    public function import_csv_file($path)
    {
        if (!file_exists($path)) {
            $this->write_log(['level' => 'error', 'message' => "CSV file not found: {$path}"]);
            return false;
        }

        $rows = $this->parse_csv($path);
        if (empty($rows)) {
            $this->write_log(['level' => 'warning', 'message' => "CSV parsed but no rows found: {$path}"]);
            return ['created' => 0, 'updated' => 0, 'errors' => ['no_rows']];
        }

        return $this->process_rows($rows);
    }

    /**
     * Parse CSV file to an array of associative arrays.
     * Tries to auto-detect delimiter (comma, tab, semicolon).
     *
     * @param string $path
     * @return array
     */
    protected function parse_csv($path)
    {
        $content = file_get_contents($path);
        if ($content === false) {
            $this->write_log(['level' => 'error', 'message' => "Unable to read CSV file: {$path}"]);
            return [];
        }

        // detect delimiter using first few lines
        $first = substr($content, 0, 2048);
        $delimiters = [",", "\t", ";"];
        $best = ",";
        $max = 0;
        foreach ($delimiters as $d) {
            $count = substr_count($first, $d);
            if ($count > $max) {
                $max = $count;
                $best = $d;
            }
        }

        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) return [];

        // Use fgetcsv with detected delimiter
        $header = null;
        while (($data = fgetcsv($handle, 0, $best)) !== false) {
            // skip empty lines
            if (count($data) === 1 && trim($data[0]) === '') continue;

            if (!$header) {
                $header = array_map(function ($h) {
                    return trim($h);
                }, $data);
                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = isset($data[$i]) ? trim($data[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Core processing: map rows -> upsert posts
     *
     * @param array $rows
     * @return array summary
     */
    protected function process_rows(array $rows)
    {
        $summary = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($rows as $idx => $row) {
            // Basic validation: need at least a name
            $title = isset($row['Name of Scholarship']) ? trim($row['Name of Scholarship']) : '';
            if ($title === '') {
                $summary['errors'][] = "Row {$idx}: missing Name of Scholarship";
                continue;
            }

            try {
                // Build a stable source id
                $link = isset($row['Link']) ? trim($row['Link']) : '';
                $deadline_raw = isset($row['Deadline']) ? trim($row['Deadline']) : '';
                $source_id = $this->make_source_id($title, $link, $deadline_raw);

                // Find existing by source id meta
                $existing = get_posts([
                    'post_type'      => 'scholarship',
                    'meta_key'       => 'sch_source_id',
                    'meta_value'     => $source_id,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ]);

                $post_args = [
                    'post_title'   => sanitize_text_field($title),
                    'post_type'    => 'scholarship',
                    'post_status'  => 'publish',
                ];

                if (!empty($existing)) {
                    $post_args['ID'] = intval($existing[0]);
                    $post_id = wp_update_post($post_args, true);
                    if (is_wp_error($post_id)) {
                        $summary['errors'][] = "Row {$idx}: failed to update post - " . $post_id->get_error_message();
                        continue;
                    }
                    $summary['updated']++;
                } else {
                    $post_id = wp_insert_post($post_args, true);
                    if (is_wp_error($post_id)) {
                        $summary['errors'][] = "Row {$idx}: failed to insert post - " . $post_id->get_error_message();
                        continue;
                    }
                    $summary['created']++;
                    update_post_meta($post_id, 'sch_source_id', $source_id);
                }

                // --- Map fields to ACF/meta ---

                // Deadline -> sch_deadline (Y-m-d)
                $normalized = $this->normalize_date($deadline_raw);
                if ($normalized) {
                    // Use ACF if available, otherwise update_post_meta
                    if (function_exists('update_field')) {
                        update_field('sch_deadline', $normalized, $post_id);
                    } else {
                        update_post_meta($post_id, 'sch_deadline', $normalized);
                    }
                }

                // Number of Awards
                if (!empty($row['Number of Awards'])) {
                    $num = intval(preg_replace('/[^0-9]/', '', $row['Number of Awards']));
                    $this->update_field_safe('sch_num_awards', $num, $post_id);
                }

                // Maximum Amount
                if (!empty($row['Maximum Amount'])) {
                    $amt = intval(preg_replace('/[^0-9]/', '', $row['Maximum Amount']));
                    $this->update_field_safe('sch_max_amount', $amt, $post_id);
                }

                // GPA
                if (!empty($row['GPA'])) {
                    $this->update_field_safe('sch_gpa', sanitize_text_field($row['GPA']), $post_id);
                }

                // Affiliation
                if (!empty($row['Affiliation'])) {
                    $this->update_field_safe('sch_affiliation', sanitize_text_field($row['Affiliation']), $post_id);
                }

                // Age
                if (!empty($row['Age'])) {
                    $this->update_field_safe('sch_age', sanitize_text_field($row['Age']), $post_id);
                }


                // College Program?
                if (!empty($row['College Program?'])) {
                    $this->update_field_safe('sch_college_program', sanitize_text_field($row['College Program?']), $post_id);
                }

                // Eligibility (Female / Every / Minority)
                if (!empty($row['Female / Every / Minority'])) {
                    $elig_raw = strtolower(str_replace(' ', '', $row['Female / Every / Minority']));
                    if (strpos($elig_raw, 'female') !== false) $elig = 'female';
                    elseif (strpos($elig_raw, 'minor') !== false) $elig = 'minority';
                    else $elig = 'every';
                    $this->update_field_safe('sch_eligibility', $elig, $post_id);
                }

                // Location
                if (!empty($row['Location'])) {
                    $this->update_field_safe('sch_location', sanitize_text_field($row['Location']), $post_id);
                }

                // Link
                if (!empty($row['Link'])) {
                    $this->update_field_safe('sch_link', esc_url_raw($row['Link']), $post_id);
                }

                // Category -> sch_category taxonomy
                if (!empty($row['Category'])) {
                    $cats = array_filter(array_map('trim', explode(',', $row['Category'])));
                    $term_ids = $this->ensure_terms($cats, 'sch_category');
                    if (!empty($term_ids)) {
                        wp_set_object_terms($post_id, $term_ids, 'sch_category', false);
                    }
                }

                // License types (Lic Type 1 .. Lic Type 10)
                $licenses = [];
                for ($i = 1; $i <= 10; $i++) {
                    $col = 'Lic Type ' . $i;
                    if (!empty($row[$col])) {
                        $licenses[] = trim($row[$col]);
                    }
                }
                $licenses = array_filter($licenses);
                if (!empty($licenses)) {
                    $lic_ids = $this->ensure_terms($licenses, 'license_type');
                    if (!empty($lic_ids)) {
                        wp_set_object_terms($post_id, $lic_ids, 'license_type', false);
                    }
                }

                // Raw JSON for debugging
                $this->update_field_safe('sch_raw', wp_json_encode($row), $post_id);
            } catch (\Exception $ex) {
                $summary['errors'][] = "Row {$idx}: Exception - " . $ex->getMessage();
                $this->write_log(['level' => 'error', 'message' => "Row {$idx} exception: " . $ex->getMessage()]);
            }
        } // end foreach rows

        // write summary to log
        $this->write_log([
            'level' => 'info',
            'message' => 'Import completed',
            'summary' => $summary,
            'rows' => count($rows),
            'time' => current_time('mysql'),
        ]);

        // allow external hooks
        do_action('avs_import_summary', $summary);

        return $summary;
    }

    /**
     * Helper to normalize dates into Y-m-d. Accepts many human formats.
     *
     * @param string $value
     * @return string '' if failed or Y-m-d
     */
    protected function normalize_date($value)
    {
        $value = trim((string)$value);
        if ($value === '') return '';

        // If already in Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Try strtotime first
        $t = strtotime($value);
        if ($t !== false) {
            return date('Y-m-d', $t);
        }

        // Try appending current year (handles "January 31")
        $try = strtotime($value . ' ' . date('Y'));
        if ($try !== false) {
            return date('Y-m-d', $try);
        }

        // fail
        return '';
    }

    /**
     * Create a stable source id from title/link/deadline
     * Now uses title + deadline for uniqueness (link alone causes duplicates)
     *
     * @param string $title
     * @param string $link
     * @param string $deadline_raw
     * @return string
     */
    protected function make_source_id($title, $link = '', $deadline_raw = '')
    {
        // Use title + deadline as the unique identifier (link alone causes too many duplicates)
        $seed = trim($title) . '|' . trim($deadline_raw);
        return md5(mb_strtolower(trim($seed)));
    }

    /**
     * Ensure terms exist in taxonomy and return term ids.
     *
     * @param array $names
     * @param string $taxonomy
     * @return array term ids
     */
    protected function ensure_terms(array $names, $taxonomy)
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') continue;
            $slug = sanitize_title($name);
            $term = term_exists($slug, $taxonomy);
            if ($term === 0 || $term === null) {
                $insert = wp_insert_term($name, $taxonomy);
                if (!is_wp_error($insert) && isset($insert['term_id'])) {
                    $ids[] = intval($insert['term_id']);
                }
            } else {
                if (is_array($term) && isset($term['term_id'])) {
                    $ids[] = intval($term['term_id']);
                } elseif (is_int($term)) {
                    $ids[] = $term;
                }
            }
        }
        return $ids;
    }

    /**
     * Safe wrapper for update_field (ACF) or update_post_meta fallback.
     *
     * @param string $field_key
     * @param mixed $value
     * @param int $post_id
     */
    protected function update_field_safe($field_key, $value, $post_id)
    {
        if (function_exists('update_field')) {
            update_field($field_key, $value, $post_id);
        } else {
            update_post_meta($post_id, $field_key, $value);
        }
    }

    /**
     * Simple import logging (stores recent logs in an option).
     *
     * @param array $entry
     */
    protected function write_log(array $entry)
    {
        $logs = get_option(self::OPTION_LOGS_KEY, []);
        if (!is_array($logs)) $logs = [];

        // attach timestamp and limit size
        $entry['at'] = current_time('mysql');
        $logs[] = $entry;

        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }

        update_option(self::OPTION_LOGS_KEY, $logs);
    }

    /**
     * Retrieve import logs
     *
     * @return array
     */
    public function get_logs()
    {
        $logs = get_option(self::OPTION_LOGS_KEY, []);
        if (!is_array($logs)) $logs = [];
        return $logs;
    }

    /**
     * Public helper used by cron to run auto sync (reads settings)
     *
     * @return array|false
     */
    public function run_auto_sync()
    {
        $sheet_url = get_option('avs_sheet_url', '');
        if (empty($sheet_url)) {
            $this->write_log(['level' => 'warning', 'message' => 'Auto-sync enabled but no sheet URL configured.']);
            return false;
        }
        return $this->import_csv_url($sheet_url);
    }

    /**
     * Optional: helper for WP-CLI registration
     *
     * @return void
     */
    public static function register_wp_cli_command()
    {
        if (!defined('WP_CLI') || !WP_CLI) return;

        \WP_CLI::add_command('aviation import-csv', function ($args) {
            $file = isset($args[0]) ? $args[0] : '';
            if (empty($file) || !file_exists($file)) {
                \WP_CLI::error("CSV file not provided or not found: {$file}");
                return;
            }
            $importer = new self();
            $res = $importer->import_csv_file($file);
            \WP_CLI::success('Import finished: ' . wp_json_encode($res));
        });
    }
}
