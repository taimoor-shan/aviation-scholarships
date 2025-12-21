<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Email Template and Sender for Scholarship Deadline Reminders
 * 
 * This class handles the creation and sending of personalized email notifications
 * to users about their saved scholarship deadlines.
 * 
 * Works seamlessly with WP Mail SMTP and other email plugins.
 * 
 * Features:
 * - HTML and plain text email templates
 * - Personalized content with user and scholarship data
 * - Multiple scholarship support (batch emails)
 * - Customizable email content via filters
 * 
 * @since 1.1.0
 */
class Reminder_Email {

    /**
     * Send a deadline reminder email to a user
     * 
     * @param int $user_id WordPress user ID
     * @param array $scholarships Array of scholarship data
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @return bool True on success, false on failure
     */
    public function send_reminder($user_id, $scholarships, $reminder_type) {
        // Get user data
        $user = get_userdata($user_id);
        if (!$user) {
            error_log("AVS Reminder: User $user_id not found");
            return false;
        }

        // Prepare email
        $to = $user->user_email;
        $subject = $this->get_email_subject($reminder_type, count($scholarships));
        $message = $this->get_email_message($user, $scholarships, $reminder_type);
        $headers = $this->get_email_headers();

        // Allow customization via filters
        $to = apply_filters('avs_reminder_email_to', $to, $user_id, $scholarships, $reminder_type);
        $subject = apply_filters('avs_reminder_email_subject', $subject, $user_id, $scholarships, $reminder_type);
        $message = apply_filters('avs_reminder_email_message', $message, $user_id, $scholarships, $reminder_type);
        $headers = apply_filters('avs_reminder_email_headers', $headers, $user_id, $scholarships, $reminder_type);

        // Send email (works with WP Mail SMTP)
        $sent = wp_mail($to, $subject, $message, $headers);

        // Log result
        if ($sent) {
            error_log("AVS Reminder: Email sent to {$user->user_email} for {$reminder_type}");
        } else {
            error_log("AVS Reminder: Failed to send email to {$user->user_email} for {$reminder_type}");
        }

        return $sent;
    }

    /**
     * Generate email subject line
     * 
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @param int $count Number of scholarships
     * @return string Email subject
     */
    private function get_email_subject($reminder_type, $count) {
        $days = $this->get_days_from_type($reminder_type);
        
        if ($count === 1) {
            $subject = sprintf(
                __('Reminder: Scholarship Deadline in %d Days', 'aviation-scholarships'),
                $days
            );
        } else {
            $subject = sprintf(
                __('Reminder: %d Scholarship Deadlines Approaching in %d Days', 'aviation-scholarships'),
                $count,
                $days
            );
        }

        return $subject;
    }

    /**
     * Generate HTML email message
     * 
     * @param WP_User $user WordPress user object
     * @param array $scholarships Array of scholarship data
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @return string HTML email message
     */
    private function get_email_message($user, $scholarships, $reminder_type) {
        $days = $this->get_days_from_type($reminder_type);
        $first_name = $user->first_name ?: $user->display_name;
        
        // Start building HTML email
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .scholarship-item { background-color: white; margin-bottom: 15px; padding: 15px; border-left: 4px solid #0073aa; }
                .scholarship-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #0073aa; }
                .scholarship-meta { font-size: 14px; color: #666; margin-bottom: 5px; }
                .deadline-highlight { color: #d63638; font-weight: bold; }
                .cta-button { display: inline-block; background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 10px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html(sprintf(__('Scholarship Deadline Reminder - %d Days', 'aviation-scholarships'), $days)); ?></h1>
                </div>
                
                <div class="content">
                    <p><?php echo esc_html(sprintf(__('Hi %s,', 'aviation-scholarships'), $first_name)); ?></p>
                    
                    <p><?php echo esc_html(sprintf(__('This is a friendly reminder that you have %d saved scholarship%s with deadline%s approaching in %d days:', 'aviation-scholarships'), count($scholarships), count($scholarships) > 1 ? 's' : '', count($scholarships) > 1 ? 's' : '', $days)); ?></p>
                    
                    <?php foreach ($scholarships as $scholarship): ?>
                    <div class="scholarship-item">
                        <div class="scholarship-title"><?php echo esc_html($scholarship['title']); ?></div>
                        
                        <div class="scholarship-meta">
                            <strong><?php _e('Deadline:', 'aviation-scholarships'); ?></strong> 
                            <span class="deadline-highlight"><?php echo esc_html($scholarship['deadline_formatted']); ?></span>
                        </div>
                        
                        <?php if (!empty($scholarship['max_amount'])): ?>
                        <div class="scholarship-meta">
                            <strong><?php _e('Award Amount:', 'aviation-scholarships'); ?></strong> 
                            <?php echo esc_html($scholarship['max_amount']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($scholarship['eligibility'])): ?>
                        <div class="scholarship-meta">
                            <strong><?php _e('Eligibility:', 'aviation-scholarships'); ?></strong> 
                            <?php echo esc_html($scholarship['eligibility']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url($scholarship['permalink']); ?>" class="cta-button">
                            <?php _e('View Details & Apply', 'aviation-scholarships'); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                    
                    <p><strong><?php _e('Don\'t miss these opportunities!', 'aviation-scholarships'); ?></strong></p>
                    
                    <p><?php _e('Make sure to prepare your application materials and submit before the deadline.', 'aviation-scholarships'); ?></p>
                    
                    <p>
                        <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="cta-button">
                            <?php _e('View All My Saved Scholarships', 'aviation-scholarships'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="footer">
                    <p><?php _e('You are receiving this email because you saved these scholarships as reminders.', 'aviation-scholarships'); ?></p>
                    <p><?php echo esc_html(get_bloginfo('name')); ?> | <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_url(home_url('/')); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get email headers (set as HTML)
     * 
     * @return array Email headers
     */
    private function get_email_headers() {
        $from_email = get_option('admin_email');
        $from_name = get_option('blogname');
        
        return array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email)
        );
    }

    /**
     * Convert reminder type to days
     * 
     * @param string $reminder_type '30_days', '15_days', or '5_days'
     * @return int Number of days
     */
    private function get_days_from_type($reminder_type) {
        switch ($reminder_type) {
            case '30_days':
                return 30;
            case '15_days':
                return 15;
            case '5_days':
                return 5;
            default:
                return 0;
        }
    }

    /**
     * Send a test email (for admin testing)
     * 
     * @param string $to Email address to send test to
     * @return bool True on success
     */
    public function send_test_email($to) {
        // Create sample scholarship data
        $sample_scholarships = array(
            array(
                'title' => 'Aviation Excellence Scholarship',
                'deadline_formatted' => date('F j, Y', strtotime('+30 days')),
                'max_amount' => '$5,000',
                'eligibility' => 'Everyone',
                'permalink' => home_url('/scholarships/sample/')
            )
        );

        $user = wp_get_current_user();
        $subject = __('Test: Scholarship Deadline Reminder', 'aviation-scholarships');
        $message = $this->get_email_message($user, $sample_scholarships, '30_days');
        $headers = $this->get_email_headers();

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Prepare scholarship data for email template
     * 
     * @param int $scholarship_id Post ID
     * @return array Scholarship data array
     */
    public function prepare_scholarship_data($scholarship_id) {
        $scholarship = get_post($scholarship_id);
        if (!$scholarship) {
            return null;
        }

        $deadline = get_field('sch_deadline', $scholarship_id);
        $max_amount = get_field('sch_max_amount', $scholarship_id);
        $eligibility = get_field('sch_eligibility', $scholarship_id);

        // Format amount
        $formatted_amount = '';
        if ($max_amount) {
            $formatted_amount = '$' . number_format($max_amount);
        }

        // Format eligibility
        $eligibility_labels = array(
            'every' => 'Everyone',
            'female' => 'Female Only',
            'minority' => 'Minority',
            'financial_need' => 'Demonstrated Financial Need'
        );
        $formatted_eligibility = isset($eligibility_labels[$eligibility]) 
            ? $eligibility_labels[$eligibility] 
            : $eligibility;

        return array(
            'id' => $scholarship_id,
            'title' => get_the_title($scholarship_id),
            'deadline' => $deadline,
            'deadline_formatted' => date('F j, Y', strtotime($deadline)),
            'max_amount' => $formatted_amount,
            'eligibility' => $formatted_eligibility,
            'permalink' => get_permalink($scholarship_id),
            'link' => get_field('sch_link', $scholarship_id)
        );
    }
}
