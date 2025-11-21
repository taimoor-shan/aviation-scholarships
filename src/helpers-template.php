<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Wrapper function to display scholarships in a grid
 */
function render_scholarships_grid($scholarship_ids) {
    if (empty($scholarship_ids)) {
        return '<p class="avs-no-results">No scholarships found.</p>';
    }

    ob_start();
    ?>
    <div class="avs-scholarships-grid">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a single scholarship card (Modern Apple-inspired UI)
 */
function render_scholarship_card($post_id) {

    $title      = get_the_title($post_id);
    $deadline   = get_field('sch_deadline', $post_id);
    $amount     = get_field('sch_max_amount', $post_id);
    $awards     = get_field('sch_num_awards', $post_id);
    $elig       = get_field('sch_eligibility', $post_id);
    $location   = get_field('sch_location', $post_id);
    $link       = get_field('sch_link', $post_id);

    $category   = wp_get_post_terms($post_id, 'sch_category');
    $licenses   = wp_get_post_terms($post_id, 'license_type');

    // Format deadline
    $deadline_formatted = $deadline ? date('M j, Y', strtotime($deadline)) : 'N/A';

    ob_start();
    ?>
  
    <article class="avs-scholarship-card">
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

        <!-- Key Details -->
        <div class="avs-details-grid">
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
        </div>
        <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <div class="avs-detail-label">Location</div>
                    <div class="avs-detail-value"><?= esc_html($location ?: 'Any'); ?></div>
                </div>
            </div>
        <!-- License Types Section -->
        <?php if (!empty($licenses)) : ?>
            <div class="avs-licenses-section">
                <div class="avs-licenses-label">License Types</div>
                <div class="avs-licenses-list">
                    <?php foreach ($licenses as $lic) : ?>
                        <span class="avs-license-tag"><?= esc_html($lic->name); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Button -->
        <div class="avs-card-footer">
            <a href="<?= esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="avs-apply-btn">
                <span>Apply Now</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path fill-rule="evenodd" d="M1 8a.5.5 0 01.5-.5h11.793l-3.147-3.146a.5.5 0 01.708-.708l4 4a.5.5 0 010 .708l-4 4a.5.5 0 01-.708-.708L13.293 8.5H1.5A.5.5 0 011 8z"/>
                </svg>
            </a>
        </div>
    </article>

    <?php
    return ob_get_clean();
}
