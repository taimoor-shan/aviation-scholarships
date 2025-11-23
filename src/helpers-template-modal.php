<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Wrapper function to display scholarships in a grid
 */
function render_scholarships_grid_modal($scholarship_ids) {
    if (empty($scholarship_ids)) {
        return '<p class="avs-no-results">No scholarships found.</p>';
    }

    ob_start();
    ?>
    <div class="avs-scholarships-grid">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card_modal($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a single scholarship card with modal (Improved UI - Essential Info Only)
 */
function render_scholarship_card_modal($post_id) {

    // Essential fields for card display
    $title      = get_the_title($post_id);
    $deadline   = get_field('sch_deadline', $post_id);
    $amount     = get_field('sch_max_amount', $post_id);
    $awards     = get_field('sch_num_awards', $post_id);
    $elig       = get_field('sch_eligibility', $post_id);
    $location   = get_field('sch_location', $post_id);
    $link       = get_field('sch_link', $post_id);
    
    // Detailed fields for modal
    $gpa        = get_field('sch_gpa', $post_id);
    $affiliation = get_field('sch_affiliation', $post_id);
    $age        = get_field('sch_age', $post_id);
    $college_program = get_field('sch_college_program', $post_id);
    $status     = get_field('sch_status', $post_id);

    $category   = wp_get_post_terms($post_id, 'sch_category');
    $licenses   = wp_get_post_terms($post_id, 'license_type');

    // Format deadline
    $deadline_formatted = $deadline ? date('M j, Y', strtotime($deadline)) : 'N/A';
    $deadline_full = $deadline ? date('F j, Y', strtotime($deadline)) : 'Not specified';
    
    // Days until deadline
    $days_until = '';
    if ($deadline) {
        $days = floor((strtotime($deadline) - time()) / 86400);
        if ($days > 0) {
            $days_until = $days . ' days left';
        } elseif ($days === 0) {
            $days_until = 'Due today';
        } else {
            $days_until = 'Expired';
        }
    }

    // Unique modal ID
    $modal_id = 'scholarship-modal-' . $post_id;

    // Eligibility labels
    $elig_labels = [
        'every' => 'Everyone',
        'female' => 'Female Only',
        'minority' => 'Minority'
    ];
    $elig_display = $elig_labels[$elig] ?? ucfirst($elig);

    ob_start();
    ?>
  
    <article class="avs-scholarship-card avs-card-modal-version">
        <!-- Header Section -->
        <div class="avs-card-header">
            <?php if (!empty($category)) : ?>
                <div class="avs-category mb-2">
                    <span class="avs-category-label"><?= esc_html($category[0]->name); ?></span>
                </div>
            <?php endif; ?>
            <h3 class="avs-card-title"><?= esc_html($title); ?></h3> 
        </div>

        <!-- Amount Highlight -->
        <div class="avs-amount-section">
            <div class="avs-amount-value">
                <?php if ($amount) : ?>
                    <span class="avs-currency">$</span><?= number_format($amount); ?>
                <?php else : ?>
                    <span class="avs-amount-varies">Varies</span>
                <?php endif; ?>
            </div>
            <div class="avs-amount-label">Maximum Award</div>
        </div>

        <!-- Essential Details Grid -->
        <div class="avs-details-grid avs-essential-only">
            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Deadline</div>
                    <div class="avs-detail-value">
                        <?= esc_html($deadline_formatted); ?>
                        <?php if ($days_until) : ?>
                            <span class="avs-deadline-countdown">(<?= esc_html($days_until); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Eligibility</div>
                    <div class="avs-detail-value"><?= esc_html($elig_display); ?></div>
                </div>
            </div>

            <?php if ($awards) : ?>
            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Awards</div>
                    <div class="avs-detail-value"><?= esc_html($awards); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($location) : ?>
            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Location</div>
                    <div class="avs-detail-value"><?= esc_html($location); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="avs-card-footer avs-dual-buttons">
            <button type="button" class="avs-details-btn" data-bs-toggle="modal" data-bs-target="#<?= esc_attr($modal_id); ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                    <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                </svg>
                <span>View Details</span>
            </button>
            <a href="<?= esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="avs-apply-btn">
                <span>Apply Now</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path fill-rule="evenodd" d="M1 8a.5.5 0 01.5-.5h11.793l-3.147-3.146a.5.5 0 01.708-.708l4 4a.5.5 0 010 .708l-4 4a.5.5 0 01-.708-.708L13.293 8.5H1.5A.5.5 0 011 8z"/>
                </svg>
            </a>
        </div>
    </article>

    <!-- Bootstrap Modal -->
    <div class="modal fade avs-scholarship-modal" id="<?= esc_attr($modal_id); ?>" tabindex="-1" aria-labelledby="<?= esc_attr($modal_id); ?>-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="avs-modal-header-content">
                        <?php if (!empty($category)) : ?>
                            <span class="avs-modal-category"><?= esc_html($category[0]->name); ?></span>
                        <?php endif; ?>
                        <h2 class="modal-title" id="<?= esc_attr($modal_id); ?>-label"><?= esc_html($title); ?></h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    
                    <!-- Amount Highlight in Modal -->
                    <div class="avs-modal-amount-section">
                        <div class="avs-amount-large">
                            <?php if ($amount) : ?>
                                $<?= number_format($amount); ?>
                            <?php else : ?>
                                <span class="avs-varies">Amount Varies</span>
                            <?php endif; ?>
                        </div>
                        <div class="avs-amount-subtitle">Maximum Award Amount</div>
                    </div>

                    <!-- Comprehensive Details Grid -->
                    <div class="avs-modal-details-grid">
                        <div class="avs-modal-section">
                            <h4 class="avs-section-title">Essential Information</h4>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Deadline:</span>
                                <span class="avs-detail-value">
                                    <?= esc_html($deadline_full); ?>
                                    <?php if ($days_until) : ?>
                                        <span class="avs-badge avs-deadline-badge"><?= esc_html($days_until); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Eligibility:</span>
                                <span class="avs-detail-value"><?= esc_html($elig_display); ?></span>
                            </div>
                            <?php if ($awards) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Number of Awards:</span>
                                <span class="avs-detail-value"><?= esc_html($awards); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($location) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Location:</span>
                                <span class="avs-detail-value"><?= esc_html($location); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($status) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Status:</span>
                                <span class="avs-detail-value avs-text-capitalize">
                                    <span class="avs-badge avs-status-<?= esc_attr($status); ?>"><?= esc_html(ucfirst($status)); ?></span>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($gpa || $age || $college_program || $affiliation) : ?>
                        <div class="avs-modal-section">
                            <h4 class="avs-section-title">Requirements</h4>
                            <?php if ($gpa) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Required GPA:</span>
                                <span class="avs-detail-value"><?= esc_html($gpa); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($age) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Age Requirement:</span>
                                <span class="avs-detail-value"><?= esc_html($age); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($college_program) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">College Program:</span>
                                <span class="avs-detail-value"><?= esc_html($college_program); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($affiliation) : ?>
                            <div class="avs-detail-row">
                                <span class="avs-detail-label">Affiliation:</span>
                                <span class="avs-detail-value"><?= esc_html($affiliation); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($licenses)) : ?>
                        <div class="avs-modal-section">
                            <h4 class="avs-section-title">Applicable License Types</h4>
                            <div class="avs-licenses-grid">
                                <?php foreach ($licenses as $lic) : ?>
                                    <span class="avs-license-badge"><?= esc_html($lic->name); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary avs-modal-close" data-bs-dismiss="modal">Close</button>
                    <a href="<?= esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary avs-modal-apply">
                        Apply Now
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path fill-rule="evenodd" d="M1 8a.5.5 0 01.5-.5h11.793l-3.147-3.146a.5.5 0 01.708-.708l4 4a.5.5 0 010 .708l-4 4a.5.5 0 01-.708-.708L13.293 8.5H1.5A.5.5 0 011 8z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
