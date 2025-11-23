<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Wrapper function to display scholarships in a grid (Compact version with Bootstrap modal)
 */
function render_scholarships_grid_compact($scholarship_ids) {
    if (empty($scholarship_ids)) {
        return '<p class="avs-no-results">No scholarships found.</p>';
    }

    ob_start();
    ?>
    <div class="avs-scholarships-grid">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card_compact($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a single scholarship card (Compact version with Bootstrap modal)
 * Essential info on card: Title, Deadline, Amount, Category, Eligibility, Location, Number of Awards
 * Detailed info in modal: GPA, Affiliation, Age, College Program, License Types
 */
function render_scholarship_card_compact($post_id) {
    
    // Essential fields for card
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
    
    $category   = wp_get_post_terms($post_id, 'sch_category');
    $licenses   = wp_get_post_terms($post_id, 'license_type');
    
    // Format deadline
    $deadline_formatted = $deadline ? date('M j, Y', strtotime($deadline)) : 'N/A';
    
    // Generate unique modal ID
    $modal_id = 'scholarship-modal-' . $post_id;
    
    ob_start();
    ?>
  
    <article class="avs-scholarship-card avs-compact">
        <!-- Header Section -->
        <div class="avs-card-header">
            <?php if (!empty($category)) : ?>
                <div class="avs-category mb-3">
                    <span class="avs-category-label"><?= esc_html($category[0]->name); ?></span>
                </div>
            <?php endif; ?>
            <h3 class="avs-card-title"><?= esc_html($title); ?></h3> 
        </div>

        <!-- Amount Highlight -->
        <div class="avs-amount-section">
            <div class="avs-amount-value">
                <?php if ($amount) : ?>
                    <span class="">$</span><?= number_format($amount); ?>
                <?php else : ?>
                    <span class="avs-amount-varies">Varies</span>
                <?php endif; ?>
            </div>
            <div class="avs-amount-label">Maximum Award</div>
        </div>

        <!-- Essential Details Only -->
        <div class="avs-details-grid avs-compact-grid">
            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Deadline</div>
                    <div class="avs-detail-value"><?= esc_html($deadline_formatted); ?></div>
                </div>
            </div>

            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Eligibility</div>
                    <div class="avs-detail-value avs-text-capitalize"><?= esc_html(ucfirst($elig)); ?></div>
                </div>
            </div>

            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Awards</div>
                    <div class="avs-detail-value"><?= esc_html($awards ?: '1'); ?></div>
                </div>
            </div>

            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Location</div>
                    <div class="avs-detail-value"><?= esc_html($location ?: 'Any'); ?></div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="avs-card-footer avs-dual-buttons">
            <button type="button" class="avs-details-btn" data-bs-toggle="modal" data-bs-target="#<?= esc_attr($modal_id); ?>">
                <span>View Details</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                    <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                </svg>
            </button>
            <a href="<?= esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="avs-apply-btn">
                <span>Apply Now</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path fill-rule="evenodd" d="M1 8a.5.5 0 01.5-.5h11.793l-3.147-3.146a.5.5 0 01.708-.708l4 4a.5.5 0 010 .708l-4 4a.5.5 0 01-.708-.708L13.293 8.5H1.5A.5.5 0 011 8z"/>
                </svg>
            </a>
        </div>
    </article>

    <!-- Bootstrap Modal for Detailed Information -->
    <div class="modal fade" id="<?= esc_attr($modal_id); ?>" tabindex="-1" aria-labelledby="<?= esc_attr($modal_id); ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content avs-modal-content">
                <div class="modal-header avs-modal-header">
                    <h5 class="modal-title avs-modal-title" id="<?= esc_attr($modal_id); ?>Label">
                        <?= esc_html($title); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body avs-modal-body">
                    
                    <!-- Summary Section -->
                    <div class="avs-modal-summary">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Maximum Award</div>
                                    <div class="avs-modal-detail-value">
                                        <?php if ($amount) : ?>
                                            $<?= number_format($amount); ?>
                                        <?php else : ?>
                                            Varies
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Deadline</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($deadline_formatted); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Number of Awards</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($awards ?: '1'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Location</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($location ?: 'Any'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Eligibility</div>
                                    <div class="avs-modal-detail-value avs-text-capitalize"><?= esc_html(ucfirst($elig)); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($category)) : ?>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Category</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($category[0]->name); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Additional Details Section -->
                    <?php if ($gpa || $affiliation || $age || $college_program) : ?>
                    <div class="avs-modal-section">
                        <h6 class="avs-modal-section-title">Additional Requirements</h6>
                        <div class="row g-3">
                            <?php if ($gpa) : ?>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Minimum CGPA</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($gpa); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($affiliation) : ?>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Affiliation</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($affiliation); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($age) : ?>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Age Requirement</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($age); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($college_program) : ?>
                            <div class="col-md-6">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">College Program</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($college_program); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- License Types Section -->
                    <?php if (!empty($licenses)) : ?>
                    <div class="avs-modal-section">
                        <h6 class="avs-modal-section-title">Accepted License Types</h6>
                        <div class="avs-modal-licenses">
                            <?php foreach ($licenses as $lic) : ?>
                                <span class="avs-modal-license-tag"><?= esc_html($lic->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer avs-modal-footer">
                    <button type="button" class="btn btn-secondary avs-modal-close-btn" data-bs-dismiss="modal">Close</button>
                    <a href="<?= esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary avs-modal-apply-btn">
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
