<?php

namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

// Removed: render_scholarships_grid_compact() - Layout wrapping now handled by shortcode

/**
 * Render a single scholarship card (Compact version with Bootstrap modal)
 * Essential info on card: Title, Deadline, Amount, Category, Eligibility, Location, Number of Awards
 * Detailed info in modal: GPA, Affiliation, Age, College Program, License Types
 * 
 * @param int $post_id The scholarship post ID
 * @param bool $include_modal Whether to include the modal HTML (default: true for grid, false for carousel)
 * @return string The card HTML (and optionally modal HTML)
 */
function render_scholarship_card_compact($post_id, $include_modal = true)
{

    // Essential fields for card
    $title      = get_the_title($post_id);
    $deadline   = get_field('sch_deadline', $post_id);
    $amount     = get_field('sch_max_amount', $post_id);
    $awards     = get_field('sch_num_awards', $post_id);
    $elig       = get_field('sch_eligibility', $post_id);
    $location   = get_field('sch_location', $post_id);
    $link       = get_field('sch_link', $post_id);
    $status     = get_field('sch_status', $post_id) ?: 'active'; // Default to active if not set

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

    // Determine site_id for favorites plugin
    global $blog_id;
    $site_id = is_multisite() ? $blog_id : 1;

    // Check if post is favorited
    $user_repo = new \Favorites\Entities\User\UserRepository();
    $is_favorited = $user_repo->isFavorite($post_id, $site_id);

    // Get favorite count
    $fav_count_obj = new \Favorites\Entities\Post\FavoriteCount();
    $fav_count = $fav_count_obj->getCount($post_id, $site_id);

    ob_start();
?>

    <article class="avs-scholarship-card avs-compact">
        <!-- Header Section -->
        <div class="avs-card-header">

            <div class="avs-category mb-3 d-flex gap-3 justify-content-between">
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($status) : ?>
                        <span class="avs-status-badge avs-status-<?= esc_attr($status); ?>"><?= esc_html(ucfirst($status)); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($category)) : ?>
                        <span class="avs-category-label"><?= esc_html($category[0]->name); ?></span>
                    <?php endif; ?>
                </div>
                <?php echo do_shortcode('[favorite_button post_id="' . $post_id . '" site_id="' . $site_id . '"]'); ?>
            </div>
            <h3 class="avs-card-title"><?= esc_html($title); ?></h3>
        </div>
        <!-- Amount Highlight -->


        <!-- Essential Details Only -->
        <div class="avs-details-grid avs-compact-grid">
            <div class="avs-detail-item">
                <div class="avs-detail-content">
                    <p class="avs-detail-label">Maximum Award</p>
                    <p class="avs-detail-value">
                        <?php if ($amount) : ?>
                            $<?= number_format($amount); ?>
                        <?php else : ?>
                            Unknown
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($awards) : ?>
                <div class="avs-detail-item">
                    <div class="avs-detail-content">
                        <p class="avs-detail-label">Awards</p>
                        <p class="avs-detail-value"><?= esc_html($awards); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($deadline && $deadline_formatted !== 'N/A') : ?>
                <div class="avs-detail-item">
                    <div class="avs-detail-content">
                        <p class="avs-detail-label">Deadline</p>
                        <p class="avs-detail-value"><?= esc_html($deadline_formatted); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($elig) : ?>
                <div class="avs-detail-item">
                    <div class="avs-detail-content">
                        <p class="avs-detail-label">Gender</p>
                        <p class="avs-detail-value avs-text-capitalize"><?= esc_html(ucfirst($elig)); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="avs-card-footer avs-dual-buttons">
            <a href="<?= esc_url($link); ?>" target="_blank" rel="noopener noreferrer" class="avs-apply-btn">
                <span>Apply Now</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4H4v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5M9 15L20 4m-5 0h5v5" />
                </svg>
            </a>
            <button type="button" class="avs-details-btn" data-bs-toggle="modal" data-bs-target="#<?= esc_attr($modal_id); ?>">
                <span>View Details</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                    <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                        <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0" />
                        <path d="M2 12c1.6-4.097 5.336-7 10-7s8.4 2.903 10 7c-1.6 4.097-5.336 7-10 7s-8.4-2.903-10-7" />
                    </g>
                </svg>
            </button>

        </div>
    </article>

    <?php if ($include_modal) : ?>
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
                        <div class="">
                            <div class="row g-3 avs-modal-summary">
                                <div class="col-md-6 col-lg-4">
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
                                <div class="col-md-6 col-lg-4">
                                    <div class="avs-modal-detail-item">
                                        <div class="avs-modal-detail-label">Deadline</div>
                                        <div class="avs-modal-detail-value"><?= esc_html($deadline_formatted); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <div class="avs-modal-detail-item">
                                        <div class="avs-modal-detail-label">Number of Awards</div>
                                        <div class="avs-modal-detail-value"><?= esc_html($awards ?: '1'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <div class="avs-modal-detail-item">
                                        <div class="avs-modal-detail-label">Location</div>
                                        <div class="avs-modal-detail-value"><?= esc_html($location ?: 'Any'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
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
                                <h6 class="avs-modal-section-title">Additional Requirements:</h6>
                                <div class="row g-3 avs-modal-req">
                                    <?php if ($college_program) : ?>
                                        <div class="col-md-6 col-lg-6">
                                            <div class="avs-modal-detail-item">
                                                <div class="avs-modal-detail-label">College Program</div>
                                                <div class="avs-modal-detail-value"><?= esc_html($college_program); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($gpa) : ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="avs-modal-detail-item">
                                                <div class="avs-modal-detail-label">Minimum CGPA</div>
                                                <div class="avs-modal-detail-value"><?= esc_html($gpa); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($affiliation) : ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="avs-modal-detail-item">
                                                <div class="avs-modal-detail-label">Affiliation</div>
                                                <div class="avs-modal-detail-value"><?= esc_html($affiliation); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($age) : ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="avs-modal-detail-item">
                                                <div class="avs-modal-detail-label">Age Requirement</div>
                                                <div class="avs-modal-detail-value"><?= esc_html($age); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- License Types Section -->
                        <?php if (!empty($licenses)) : ?>
                            <div class="avs-modal-section">
                                <h6 class="avs-modal-section-title">Accepted License Types:</h6>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4H4v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5M9 15L20 4m-5 0h5v5" />
                            </svg>

                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php
    return ob_get_clean();
}

/**
 * Render modal for a scholarship card (to be placed outside carousel)
 * 
 * @param int $post_id The scholarship post ID
 * @return string The modal HTML
 */
function render_scholarship_modal($post_id)
{
    // Get all necessary data
    $title      = get_the_title($post_id);
    $deadline   = get_field('sch_deadline', $post_id);
    $amount     = get_field('sch_max_amount', $post_id);
    $awards     = get_field('sch_num_awards', $post_id);
    $elig       = get_field('sch_eligibility', $post_id);
    $location   = get_field('sch_location', $post_id);
    $link       = get_field('sch_link', $post_id);
    $status     = get_field('sch_status', $post_id) ?: 'active'; // Default to active if not set
    $gpa        = get_field('sch_gpa', $post_id);
    $affiliation = get_field('sch_affiliation', $post_id);
    $age        = get_field('sch_age', $post_id);
    $college_program = get_field('sch_college_program', $post_id);
    $category   = wp_get_post_terms($post_id, 'sch_category');
    $licenses   = wp_get_post_terms($post_id, 'license_type');

    $deadline_formatted = $deadline ? date('M j, Y', strtotime($deadline)) : 'N/A';
    $modal_id = 'scholarship-modal-' . $post_id;

    ob_start();
?>
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
                    <div class="">
                        <div class="row g-3 avs-modal-summary">
                            <div class="col-md-6 col-lg-4">
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
                            <div class="col-md-6 col-lg-4">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Deadline</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($deadline_formatted); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Number of Awards</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($awards ?: '1'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Location</div>
                                    <div class="avs-modal-detail-value"><?= esc_html($location ?: 'Any'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="avs-modal-detail-item">
                                    <div class="avs-modal-detail-label">Eligibility</div>
                                    <div class="avs-modal-detail-value avs-text-capitalize"><?= esc_html(ucfirst($elig)); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($category)) : ?>
                                <div class="col-md-6 col-lg-4">
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
                            <h6 class="avs-modal-section-title mb-3 mb-lg-4">Additional Requirements:</h6>
                            <div class="row g-3 avs-modal-req">
                                 <?php if ($college_program) : ?>
                                    <div class="col-md-6 col-lg-6">
                                        <div class="avs-modal-detail-item">
                                            <div class="avs-modal-detail-label">College Program</div>
                                            <div class="avs-modal-detail-value"><?= esc_html($college_program); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($gpa) : ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="avs-modal-detail-item">
                                            <div class="avs-modal-detail-label">Minimum CGPA</div>
                                            <div class="avs-modal-detail-value"><?= esc_html($gpa); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($affiliation) : ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="avs-modal-detail-item">
                                            <div class="avs-modal-detail-label">Affiliation</div>
                                            <div class="avs-modal-detail-value"><?= esc_html($affiliation); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($age) : ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="avs-modal-detail-item">
                                            <div class="avs-modal-detail-label">Age Requirement</div>
                                            <div class="avs-modal-detail-value"><?= esc_html($age); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                               
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- License Types Section -->
                    <?php if (!empty($licenses)) : ?>
                        <div class="avs-modal-section">
                            <h6 class="avs-modal-section-title">Accepted License Types:</h6>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4H4v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5M9 15L20 4m-5 0h5v5" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}
