<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Render a single scholarship card (Bootstrap compatible)
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

    ob_start();
    ?>

    <div class="card shadow-sm border-0 rounded-3 mb-4 scholarship-card">
      <div class="card-body p-4">

        <h5 class="card-title mb-2 fw-bold"><?= esc_html($title); ?></h5>

        <div class="mb-3">
            <?php if (!empty($category)) : ?>
                <span class="badge bg-primary me-1"><?= esc_html($category[0]->name); ?></span>
            <?php endif; ?>

            <?php if (!empty($licenses)) :
                foreach ($licenses as $lic) : ?>
                    <span class="badge bg-light text-dark border me-1"><?= esc_html($lic->name); ?></span>
                <?php endforeach;
            endif; ?>
        </div>

        <div class="d-flex flex-wrap mb-3">
            <div class="me-4 mb-2">
                <small class="text-muted d-block">Deadline</small>
                <span class="fw-semibold"><?= esc_html($deadline); ?></span>
            </div>
            <div class="mb-2">
                <small class="text-muted d-block">Max Amount</small>
                <span class="fw-semibold">$<?= number_format($amount); ?></span>
            </div>
        </div>

        <div class="d-flex flex-wrap mb-3">
            <div class="me-4 mb-2">
                <small class="text-muted d-block">Awards</small>
                <span class="fw-semibold"><?= esc_html($awards); ?></span>
            </div>
            <div class="mb-2">
                <small class="text-muted d-block">Eligibility</small>
                <span class="fw-semibold text-capitalize"><?= esc_html($elig); ?></span>
            </div>
        </div>

        <?php if ($location) : ?>
            <div class="mb-3">
                <small class="text-muted d-block">Location</small>
                <span class="fw-semibold"><?= esc_html($location); ?></span>
            </div>
        <?php endif; ?>

        <a href="<?= esc_url($link); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
            Apply Now →
        </a>

      </div>
    </div>

    <?php
    return ob_get_clean();
}
