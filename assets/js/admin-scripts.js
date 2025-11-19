/**
 * Aviation Scholarships - Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    $(document).ready(function() {
        
        // Import form validation
        initImportFormValidation();
        
        // Copy webhook URL functionality
        initWebhookCopy();
        
        // Import confirmation
        initImportConfirmation();
        
        // Auto-dismiss notices
        initNoticeDismiss();
        
    });

    /**
     * Validate import form before submission
     */
    function initImportFormValidation() {
        $('form[action*="admin-post.php"]').on('submit', function(e) {
            var csvUrl = $('input[name="csv_url"]').val();
            var csvFile = $('input[name="csv_file"]')[0];
            
            // Check if either URL or file is provided
            if (!csvUrl && (!csvFile || !csvFile.files.length)) {
                e.preventDefault();
                alert('Please provide either a CSV URL or upload a CSV file.');
                return false;
            }
            
            // Add loading state
            $(this).addClass('avs-loading');
            $(this).find('button[type="submit"]').prop('disabled', true);
            
            return true;
        });
    }

    /**
     * Copy webhook URL to clipboard
     */
    function initWebhookCopy() {
        // Add copy button next to webhook URL
        if ($('.avs-webhook-code').length) {
            var $webhookCode = $('.avs-webhook-code');
            var $copyBtn = $('<button type="button" class="button button-small" style="margin-left: 10px;">Copy URL</button>');
            
            $webhookCode.after($copyBtn);
            
            $copyBtn.on('click', function() {
                var webhookUrl = $webhookCode.text().trim();
                
                // Copy to clipboard
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(webhookUrl).then(function() {
                        $copyBtn.text('✓ Copied!');
                        setTimeout(function() {
                            $copyBtn.text('Copy URL');
                        }, 2000);
                    }).catch(function() {
                        fallbackCopyToClipboard(webhookUrl);
                    });
                } else {
                    fallbackCopyToClipboard(webhookUrl);
                }
            });
        }
    }

    /**
     * Fallback copy method for older browsers
     */
    function fallbackCopyToClipboard(text) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand('copy');
            alert('Webhook URL copied to clipboard!');
        } catch (err) {
            alert('Failed to copy. Please copy manually: ' + text);
        }
        
        $temp.remove();
    }

    /**
     * Add confirmation for large imports
     */
    function initImportConfirmation() {
        $('button[type="submit"]').filter(function() {
            return $(this).closest('form[action*="admin-post.php"]').length > 0;
        }).on('click', function(e) {
            var $form = $(this).closest('form');
            var csvFile = $form.find('input[name="csv_file"]')[0];
            
            // Check file size if file upload
            if (csvFile && csvFile.files.length) {
                var fileSize = csvFile.files[0].size;
                var fileSizeMB = (fileSize / (1024 * 1024)).toFixed(2);
                
                if (fileSize > 5 * 1024 * 1024) { // > 5MB
                    if (!confirm('You are importing a large file (' + fileSizeMB + ' MB). This may take several minutes. Continue?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    }

    /**
     * Auto-dismiss success notices after 5 seconds
     */
    function initNoticeDismiss() {
        $('.notice.is-dismissible').each(function() {
            var $notice = $(this);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        });
    }

    /**
     * AJAX Import (optional enhancement for future)
     */
    window.AVS_Admin = {
        /**
         * Trigger manual import via AJAX
         */
        triggerImport: function(csvUrl) {
            if (!csvUrl) {
                console.error('CSV URL is required');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'avs_ajax_import',
                    csv_url: csvUrl,
                    nonce: AVS_Admin_Data.nonce
                },
                beforeSend: function() {
                    console.log('Import started...');
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Import completed:', response.data);
                    } else {
                        console.error('Import failed:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        },

        /**
         * Refresh logs table
         */
        refreshLogs: function() {
            location.reload();
        }
    };

})(jQuery);
