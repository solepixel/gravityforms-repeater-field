/**
 * Admin JavaScript for Gravity Forms Repeater Field
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Gravity Forms Repeater Field Admin Handler
     */
    class GFRepeaterAdmin {
        constructor() {
            this.init();
        }

        /**
         * Initialize admin functionality
         */
        init() {
            this.bindEvents();
            this.initializeAdminDisplay();
        }

        /**
         * Bind admin events
         */
        bindEvents() {
            // Handle entry detail display
            $(document).on('click', '.gf-repeater-instance-toggle', this.handleInstanceToggle.bind(this));

            // Handle repeater navigation
            $(document).on('click', '.gf-repeater-admin-nav', this.handleAdminNavigation.bind(this));
        }

        /**
         * Initialize admin display
         */
        initializeAdminDisplay() {
            // Initialize repeater entry displays
            $('.gf-repeater-entry-display').each((index, element) => {
                this.initializeRepeaterDisplay($(element));
            });
        }

        /**
         * Initialize repeater display
         */
        initializeRepeaterDisplay($display) {
            // Set up navigation controls
            this.setupNavigationControls($display);

            // Set up instance toggles
            this.setupInstanceToggles($display);
        }

        /**
         * Set up navigation controls
         */
        setupNavigationControls($display) {
            const $instances = $display.find('.gf-repeater-instance');

            if ($instances.length > 1) {
                // Add navigation controls
                const $nav = $('<div class="gf-repeater-admin-nav"></div>');
                $nav.append('<button type="button" class="gf-repeater-nav-prev" disabled>← Previous</button>');
                $nav.append('<span class="gf-repeater-nav-info">Instance 1 of ' + $instances.length + '</span>');
                $nav.append('<button type="button" class="gf-repeater-nav-next">Next →</button>');

                $display.prepend($nav);

                // Hide all instances except the first
                $instances.hide().first().show();
            }
        }

        /**
         * Set up instance toggles
         */
        setupInstanceToggles($display) {
            $display.find('.gf-repeater-instance').each((index, element) => {
                const $instance = $(element);
                const $header = $instance.find('h5');

                if ($header.length > 0) {
                    $header.addClass('gf-repeater-instance-toggle').attr('data-instance', index);
                }
            });
        }

        /**
         * Handle instance toggle
         */
        handleInstanceToggle(e) {
            e.preventDefault();

            const $header = $(e.currentTarget);
            const $instance = $header.closest('.gf-repeater-instance');
            const $fields = $instance.find('.gf-repeater-fields');

            if ($fields.is(':visible')) {
                $fields.slideUp();
                $header.removeClass('expanded');
            } else {
                $fields.slideDown();
                $header.addClass('expanded');
            }
        }

        /**
         * Handle admin navigation
         */
        handleAdminNavigation(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $display = $button.closest('.gf-repeater-entry-display');
            const direction = $button.hasClass('gf-repeater-nav-prev') ? -1 : 1;

            this.navigateInstances($display, direction);
        }

        /**
         * Navigate between instances
         */
        navigateInstances($display, direction) {
            const $instances = $display.find('.gf-repeater-instance');
            const $current = $instances.filter(':visible');
            const currentIndex = $instances.index($current);
            const newIndex = currentIndex + direction;

            if (newIndex >= 0 && newIndex < $instances.length) {
                // Hide current instance
                $current.fadeOut(200, function() {
                    // Show new instance
                    $instances.eq(newIndex).fadeIn(200);

                    // Update navigation controls
                    updateNavigationControls($display, newIndex, $instances.length);
                });
            }
        }

        /**
         * Update navigation controls
         */
        updateNavigationControls($display, currentIndex, totalInstances) {
            const $nav = $display.find('.gf-repeater-admin-nav');
            const $prevBtn = $nav.find('.gf-repeater-nav-prev');
            const $nextBtn = $nav.find('.gf-repeater-nav-next');
            const $info = $nav.find('.gf-repeater-nav-info');

            // Update button states
            $prevBtn.prop('disabled', currentIndex === 0);
            $nextBtn.prop('disabled', currentIndex >= totalInstances - 1);

            // Update info text
            $info.text(`Instance ${currentIndex + 1} of ${totalInstances}`);
        }

        /**
         * Format repeater data for display
         */
        formatRepeaterData(data) {
            if (!data || !Array.isArray(data)) {
                return '<div class="gf-repeater-empty">No data available</div>';
            }

            let output = '<div class="gf-repeater-instances">';

            data.forEach((instance, index) => {
                output += '<div class="gf-repeater-instance">';
                output += `<h5 class="gf-repeater-instance-toggle">Instance ${index + 1}</h5>`;
                output += '<div class="gf-repeater-fields">';

                Object.entries(instance).forEach(([fieldId, value]) => {
                    output += '<div class="gf-repeater-field">';
                    output += `<label>Field ${fieldId}:</label> `;
                    output += `<span>${this.escapeHtml(value)}</span>`;
                    output += '</div>';
                });

                output += '</div>';
                output += '</div>';
            });

            output += '</div>';

            return output;
        }

        /**
         * Escape HTML for safe display
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Update navigation controls
     */
    function updateNavigationControls($display, currentIndex, totalInstances) {
        const $nav = $display.find('.gf-repeater-admin-nav');
        const $prevBtn = $nav.find('.gf-repeater-nav-prev');
        const $nextBtn = $nav.find('.gf-repeater-nav-next');
        const $info = $nav.find('.gf-repeater-nav-info');

        // Update button states
        $prevBtn.prop('disabled', currentIndex === 0);
        $nextBtn.prop('disabled', currentIndex >= totalInstances - 1);

        // Update info text
        $info.text(`Instance ${currentIndex + 1} of ${totalInstances}`);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new GFRepeaterAdmin();
    });

})(jQuery);
