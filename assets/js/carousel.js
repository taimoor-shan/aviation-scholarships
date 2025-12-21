/**
 * Aviation Scholarships Carousel Initialization
 * Initializes Swiper carousels for scholarship displays
 */
(function($) {
    'use strict';

    /**
     * Initialize all scholarship carousels on page load
     */
    function initScholarshipCarousels() {
        // Check if Swiper is loaded
        if (typeof Swiper === 'undefined') {
            console.error('Swiper library not loaded');
            return;
        }

        // Find all scholarship carousels
        const carouselElements = document.querySelectorAll('.avs-scholarships-carousel');
        
        if (carouselElements.length === 0) {
            return;
        }

        // Initialize each carousel
        carouselElements.forEach(function(element) {
            // Determine if this is a compact carousel
            const isCompact = element.classList.contains('avs-compact-carousel');
            
            // Configuration for regular carousel
            const regularConfig = {
                slidesPerView: 1,
                spaceBetween: 20,
                loop: false,
                grabCursor: true,
                centeredSlides: false,
                
                // Navigation arrows
                navigation: {
                    nextEl: element.parentElement.querySelector('.swiper-button-next'),
                    prevEl: element.parentElement.querySelector('.swiper-button-prev'),
                },
                
                // Pagination
                pagination: {
                    el: element.parentElement.querySelector('.swiper-pagination'),
                    clickable: true,
                    dynamicBullets: true,
                },
                
                // Responsive breakpoints
                breakpoints: {
                    // Mobile
                    320: {
                        slidesPerView: 1,
                        spaceBetween: 15,
                    },
                    // Tablet
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 20,
                    },
                    // Desktop
                    1024: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    },
                    // Large desktop
                    1400: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    }
                },
                
                // Auto height
                autoHeight: false,
                
                // Keyboard control
                keyboard: {
                    enabled: true,
                    onlyInViewport: true,
                },
                
                // Mouse wheel
                mousewheel: {
                    forceToAxis: true,
                },
                
                // Accessibility
                a11y: {
                    prevSlideMessage: 'Previous scholarship',
                    nextSlideMessage: 'Next scholarship',
                    firstSlideMessage: 'This is the first scholarship',
                    lastSlideMessage: 'This is the last scholarship',
                    paginationBulletMessage: 'Go to scholarship {{index}}',
                }
            };
            
            // Configuration for compact carousel (might want different settings)
            const compactConfig = {
                ...regularConfig,
                breakpoints: {
                    // Mobile
                    320: {
                        slidesPerView: 1,
                        spaceBetween: 15,
                    },
                    // Tablet
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 20,
                    },
                    // Desktop
                    1024: {
                        slidesPerView: 3,
                        spaceBetween: 25,
                    },
                    // Large desktop
                    1400: {
                        slidesPerView: 4,
                        spaceBetween: 30,
                    }
                }
            };
            
            // Initialize Swiper with appropriate config
            const config = isCompact ? compactConfig : regularConfig;
            const swiperInstance = new Swiper(element, config);
            
            // Store the instance on the element for later reference
            element.swiperInstance = swiperInstance;
        });
    }

    /**
     * Setup modal event handlers to prevent carousel interference
     */
    function setupModalHandlers() {
        // Find all modals on the page
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(function(modalElement) {
            // When modal is shown, disable all carousels
            modalElement.addEventListener('show.bs.modal', function() {
                const carousels = document.querySelectorAll('.avs-scholarships-carousel');
                carousels.forEach(function(carousel) {
                    if (carousel.swiperInstance) {
                        carousel.swiperInstance.disable();
                        carousel.swiperInstance.keyboard.disable();
                        // Also disable mouse/touch interactions
                        carousel.swiperInstance.allowTouchMove = false;
                    }
                });
            });
            
            // When modal is hidden, re-enable all carousels
            modalElement.addEventListener('hidden.bs.modal', function() {
                const carousels = document.querySelectorAll('.avs-scholarships-carousel');
                carousels.forEach(function(carousel) {
                    if (carousel.swiperInstance) {
                        carousel.swiperInstance.enable();
                        carousel.swiperInstance.keyboard.enable();
                        carousel.swiperInstance.allowTouchMove = true;
                    }
                });
            });
        });
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        initScholarshipCarousels();
        setupModalHandlers();
        
        // Prevent modal dialog clicks from propagating to carousel
        $(document).on('click', '.modal-dialog', function(e) {
            e.stopPropagation();
        });
        
        // Prevent modal backdrop clicks from affecting carousel
        $(document).on('click', '.modal', function(e) {
            if ($(e.target).hasClass('modal')) {
                e.stopPropagation();
            }
        });
        
        // Specifically handle close button to ensure it doesn't trigger navigation
        $(document).on('click', '.modal .btn-close', function(e) {
            // Only stop propagation, let Bootstrap handle the modal close naturally
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Disable all carousels immediately
            const carousels = document.querySelectorAll('.avs-scholarships-carousel');
            carousels.forEach(function(carousel) {
                if (carousel.swiperInstance) {
                    carousel.swiperInstance.disable();
                    carousel.swiperInstance.allowTouchMove = false;
                }
            });
            
            // Re-enable carousels after modal close animation
            setTimeout(function() {
                carousels.forEach(function(carousel) {
                    if (carousel.swiperInstance) {
                        carousel.swiperInstance.enable();
                        carousel.swiperInstance.allowTouchMove = true;
                    }
                });
            }, 400); // Bootstrap modal fade duration is 300ms, adding buffer
        });
        
        // Handle footer close button the same way
        $(document).on('click', '.modal-footer .avs-modal-close-btn', function(e) {
            // Only stop propagation, let Bootstrap handle the modal close naturally
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Disable all carousels immediately
            const carousels = document.querySelectorAll('.avs-scholarships-carousel');
            carousels.forEach(function(carousel) {
                if (carousel.swiperInstance) {
                    carousel.swiperInstance.disable();
                    carousel.swiperInstance.allowTouchMove = false;
                }
            });
            
            // Re-enable carousels after modal close animation
            setTimeout(function() {
                carousels.forEach(function(carousel) {
                    if (carousel.swiperInstance) {
                        carousel.swiperInstance.enable();
                        carousel.swiperInstance.allowTouchMove = true;
                    }
                });
            }, 400); // Bootstrap modal fade duration is 300ms, adding buffer
        });
    });

    // Re-initialize on AJAX complete (for dynamic content)
    $(document).ajaxComplete(function() {
        initScholarshipCarousels();
    });

})(jQuery);
