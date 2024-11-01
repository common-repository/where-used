/**
 * Settings Scripts
 */
WhereUsed = (typeof WhereUsed === 'undefined') ? {} : WhereUsed;

// Use jQuery shorthand
(function ($) {

    WhereUsed.settings = {

        /**
         * Set listeners when the script loads
         *
         * @package Whereused
         * @since 1.0.0
         */
        init: function () {

            let body = $('body.tools-page-custom .content-body');

            body.on('change', '.cron-check-status, .cron-check-status-frequency', WhereUsed.settings.updateStatusCronDetails);

            body.on('click', '.access-tool-roles, .access-settings-roles', WhereUsed.settings.correctAccess);

            // Update the settings page details for an ajax call
            $('.privacy-settings-tabs-wrapper').on('click', '.tab-settings', WhereUsed.settings.ajaxUpdateSettings);

            // Run initially
            WhereUsed.settings.updateStatusCronDetails();
        },

        /**
         * Waits X seconds and then runs the updateStatusCronDetails() for the settings page only during AJAX load
         *
         * @package Whereused
         * @since 1.0.5
         */
        ajaxUpdateSettings: function() {

            // Delay in milliseconds
            let timeout = 3000;

            setTimeout(function () {

                // Update Status Cron Details (show/hide)
                WhereUsed.settings.updateStatusCronDetails();

            }, timeout);

        },

        /**
         * Shows and hides details about the Update Status Codes cron based on settings
         *
         * @package Whereused
         * @since 1.0.0
         */
        updateStatusCronDetails: function () {

            let cron = $('.cron-check-status-row .cron-check-status').val();

            let frequency_row = $('.cron-check-status-frequency-row');
            let dom_row = $('.cron-check-status-dom-row');
            let dow_row = $('.cron-check-status-dow-row');
            let tod_row = $('.cron-check-status-tod-row');

            if ('1' == cron) {

                // Cron enabled

                // Show rows
                frequency_row.removeClass('hidden');
                tod_row.removeClass('hidden');

                let frequency = frequency_row.find('.cron-check-status-frequency').val();

                if ('monthly' == frequency) {
                    // monthly

                    dom_row.removeClass('hidden');
                    dow_row.addClass('hidden');

                } else {
                    // Weekly or bi-weekly

                    dow_row.removeClass('hidden');
                    dom_row.addClass('hidden');
                }

            } else {

                // Cron Disabled - Hide Everything
                frequency_row.addClass('hidden');
                dom_row.addClass('hidden');
                dow_row.addClass('hidden');
                tod_row.addClass('hidden');
            }
        },

        /**
         * Force Adding Settings Access to have Tool Access and also removing Tool Access removes Settings Access
         *
         * @package Whereused
         * @since 1.0.0
         */
        correctAccess: function () {

            let checkbox = $(this);
            let tr = checkbox.closest('tr');

            if (checkbox.hasClass('access-tool-roles')) {
                // Clicked on Tool Access
                if (!checkbox.is(':checked')) {
                    // Uncheck settings as well
                    tr.find('.access-settings-roles').prop('checked', false);
                }
            } else {
                // Clicked on View/Edit Settings

                if (checkbox.is(':checked')) {
                    // check tool access as well
                    tr.find('.access-tool-roles').prop('checked', true);
                }
            }

        },
    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        WhereUsed.settings.init();
    });

})(jQuery);