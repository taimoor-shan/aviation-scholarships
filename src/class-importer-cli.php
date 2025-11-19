<?php
namespace Aviation_Scholarships\CLI;

if (!defined('WPINC')) {
    die;
}

// Only load in WP-CLI context
if (defined('WP_CLI') && WP_CLI) {

    class Import_CLI_Command {

        /**
         * Constructor
         */
        public function __construct() {
            $this->importer = new \Aviation_Scholarships\Importer();
        }

        /**
         * Import a CSV file into the Scholarships CPT.
         *
         * ## OPTIONS
         *
         * <file>
         * : Local file path to the CSV file to import.
         *
         * ## EXAMPLES
         *
         *    wp aviation import-csv /path/to/file.csv
         *
         */
        public function import_csv($args, $assoc_args) {

            list($file) = $args;

            if (empty($file)) {
                \WP_CLI::error("You must provide a file path. Example:\nwp aviation import-csv /path/to/file.csv");
            }

            if (!file_exists($file)) {
                \WP_CLI::error("File not found: {$file}");
            }

            \WP_CLI::log("Starting import from file: {$file}");

            $result = $this->importer->import_csv_file($file);

            if (!$result) {
                \WP_CLI::error("Import failed.");
            }

            $created = $result['created'] ?? 0;
            $updated = $result['updated'] ?? 0;
            $errors  = $result['errors'] ?? [];

            \WP_CLI::success("Import completed. Created: {$created}, Updated: {$updated}");

            if (!empty($errors)) {
                \WP_CLI::warning("Some rows had issues:");
                foreach ($errors as $err) {
                    \WP_CLI::log("- {$err}");
                }
            }
        }
    }

    // Register the command
    \WP_CLI::add_command('aviation import-csv', '\Aviation_Scholarships\CLI\Import_CLI_Command');
}
