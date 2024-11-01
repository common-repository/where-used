/**
 * Table Scripts
 */
WhereUsed = (typeof WhereUsed === 'undefined') ? {} : WhereUsed;

// Use jQuery shorthand
(function ($) {

    WhereUsed.table = {

        /**
         * Set listeners when the script loads
         *
         * @package WhereUsed
         * @since 1.0.0
         */
        init: function () {

            let contentBody = $('.content-body');

            contentBody.on('click', '.row-actions .check a', WhereUsed.table.checkRowStatus);

            contentBody.on('change', '.select-wrapper select', WhereUsed.table.applyFilters);
            $('.privacy-settings-tabs-wrapper').on('mouseout', 'a', WhereUsed.table.applyFilters);

            // Initially run
            WhereUsed.table.toggleFilters();

            WhereUsed.table.highlightFilters();

        },

        /**
         * Highlights current filters that are active if filters are applied
         */
        highlightFilters: function () {
            let resetLink = $('.reset-filters');

            if (resetLink.length) {
                let allFilters = $('.select-wrapper');

                allFilters.each(function () {
                    let wrapper = $(this);
                    let select = wrapper.find('select');

                    if (select.val() == '') {
                        wrapper.removeClass('active');
                    } else {
                        wrapper.addClass('active');
                    }
                });

                let searchBox = $('.search-box input[type=search]');

                if (searchBox.val() != '') {
                    searchBox.addClass('active');
                }
            }

        },

        /**
         * Submit form on filter select change
         */
        applyFilters: function () {
            WhereUsed.table.toggleFilters();

            let form = $(this).closest('form');

            // Ensure we start on page 1
            form.find('input[name="paged"]').val('1');

            form.submit();
        },

        /**
         * Shows / Hides filters based on what is currently selected
         */
        toggleFilters: function () {

            let Filters = $('.select-wrapper');
            let type = Filters.find('select[name="type"]');
            let block = Filters.find('select[name="block"]');
            let status = Filters.find('select[name="status"]');
            let redirectionLocation = Filters.find('select[name="redirection_location"]');
            let redirectionType = Filters.find('select[name="redirection_type"]');
            let redirectionColumn = $('.column-redirection');

            if (type.val() === 'block') {
                block.closest('.select-wrapper').show();

                // Reset filters
                redirectionLocation.val('');
                redirectionType.val('');
                status.val('');

                // Hide status filter
                status.closest('.select-wrapper').hide();

                // Hide the redirection column
                redirectionColumn.hide();
            } else {
                // We don't need block filter
                block.val('');
                block.closest('.select-wrapper').hide();

                // Show status filter
                status.closest('.select-wrapper').show();

                // Show redirection column
                redirectionColumn.show();
            }

        },

        /**
         * Rechecks the Redirection row's status in the display table via AJAX
         *
         * @package WhereUsed
         * @since 1.0.0
         */
        checkRowStatus: function () {

            //console.log('check status');

            let link = $(this);
            let url = link.data('check-url');
            let nonces = jQuery('#nonces');
            let nonce = nonces.data('check-status');
            //console.log(nonce);

            let allTds = link.closest('table').find('.column-to');

            //console.log(allTds);

            // hide content for all to columns that have this exact url and show loading text
            allTds.each(function (index) {
                let thisTd = $(this);
                let fullUrl = thisTd.find('.row-actions .check a').data('check-url');

                //console.log(thisTd);
                //console.log(fullUrl + ' == ' + url);

                if (fullUrl && fullUrl == url) {

                    //console.log('found a match');

                    // add marker to find tds that are loading later
                    thisTd.addClass('loading');

                    // Hide our real content temporarily
                    thisTd.find('.td-content').addClass('hidden');

                    // add loading content
                    thisTd.append('<div class="loading-content"><span class="loading-text"><span class="dashicons spin dashicons-update"></span>checking status...</span></div>').fadeIn(200);
                }
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: WhereUsedAjax.ajaxURL,
                data: {
                    action: 'whereused_scan_check_row_status',
                    nonce: nonce,
                    url: url,
                },
                success: function (response) {

                    if ('0' != response) {

                        response = JSON.parse(response);
                        let newStatusCode = String(response['to_url_status']);

                        //console.log(newStatusCode);

                        // Update form nonce from response
                        nonces.data('check-status', response['_nonce-check-status']);

                        // Update the content of TDs that are loading
                        allTdsLoading = $('.column-to.loading');

                        allTdsLoading.each(function (index) {
                            let thisTd = $(this);
                            let thisContent = thisTd.find('.td-content');
                            let status = thisContent.find('.status');
                            let code = status.find('.code');

                            // Update status code
                            code.html(newStatusCode);
                            status.removeClass('code-1xx code-2xx code-3xx code-4xx code-5xx');
                            status.addClass('code-' + newStatusCode.charAt(0) + 'xx');

                            // Remove loading content
                            thisTd.find('.loading-content').remove();

                            // Show content
                            thisContent.removeClass('hidden');

                            // Remove loading marker
                            thisTd.removeClass('loading');

                            // Indicate visually that it has updated
                            thisTd.closest('tr').addClass('updated');
                        });

                        setTimeout(function () {
                            // Remove visual indication
                            //console.log('remove visual indication');
                            $('tr').removeClass('updated');
                        }, 500);
                    }

                },
                fail: function () {
                    //console.log('Ajax failed. Please try again.');
                    target.html("Ajax failed. Please try again.");
                }
            });
        },

    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        WhereUsed.table.init();
    });

})(jQuery);