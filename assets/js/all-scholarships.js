/**
 * All Scholarships - Interactive Features
 * Version: 1.0.0
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initFilterToggle();
        initSortingChange();
        initScrollToTop();
    }

    /**
     * Toggle filters visibility on mobile
     */
    function initFilterToggle() {
        const toggleBtn = document.getElementById('avs-toggle-filters');
        const filtersForm = document.getElementById('avs-filters-form');
        
        if (!toggleBtn || !filtersForm) return;

        toggleBtn.addEventListener('click', function() {
            filtersForm.classList.toggle('active');
            
            // Update button text
            const buttonText = this.querySelector('span');
            if (buttonText) {
                buttonText.textContent = filtersForm.classList.contains('active') ? 'Hide Filters' : 'Filters';
            }
        });
    }

    /**
     * Handle sorting dropdown change
     */
    function initSortingChange() {
        // Function is called from inline onchange attribute
        // Making it globally available
        window.avsChangeSorting = function(sortValue) {
            const url = new URL(window.location.href);
            url.searchParams.set('avs_sort', sortValue);
            url.searchParams.delete('avs_page'); // Reset to page 1 when sorting changes
            window.location.href = url.toString();
        };
    }

    /**
     * Scroll to top when pagination is clicked
     */
    function initScrollToTop() {
        const paginationLinks = document.querySelectorAll('.avs-page-link');
        
        if (!paginationLinks.length) return;

        paginationLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Smooth scroll to results section
                const resultsSection = document.querySelector('.avs-all-scholarships-container');
                if (resultsSection) {
                    const offsetTop = resultsSection.offsetTop - 100; // 100px offset for fixed headers
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    /**
     * Optional: Add loading state when filtering/sorting
     */
    function initFormSubmitLoading() {
        const filterForm = document.getElementById('avs-filters-form');
        const resultsSection = document.querySelector('.avs-scholarships-results');
        
        if (!filterForm || !resultsSection) return;

        filterForm.addEventListener('submit', function() {
            resultsSection.classList.add('loading');
        });
    }

})();
