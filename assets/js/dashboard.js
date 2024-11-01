/**
 * Dashboard Scripts
 */
WhereUsed = (typeof WhereUsed === 'undefined') ? {} : WhereUsed;

// Use jQuery shorthand
(function ($) {

    WhereUsed.dashboard = {

        /**
         * Houses all the chart objects for the dashboard
         */
        charts: {
            overviewChart: false
        },

        /**
         * Set listeners when the script loads
         *
         * @package Whereused
         * @since 1.0.0
         */
        init: function () {

            WhereUsed.dashboard.clearSearch();

            WhereUsed.dashboard.updateProgressBar(0);

            $('#find_url_usage form').on('submit', WhereUsed.dashboard.preventEmptySearch);

            let scan = $('#scan');

            // Start Scan
            scan.on('click', '.scan-link', WhereUsed.dashboard.startScan);

            // Cancel Scan
            scan.on('click', '#cancel-scan', WhereUsed.dashboard.cancelScan);

            // Initially Draw Charts
            WhereUsed.dashboard.drawCharts();

            // Redraw Charts On Metabox Rearrange
            $(document).on('postbox-moved', WhereUsed.dashboard.detectRedraw);

        },

        drawCharts: function () {

            const overviewChart = $("#overview-chart");

            let chartData = String(overviewChart.data('data'));
            chartData = chartData.split("|");

            let chartLabels = String(overviewChart.data('labels'));
            chartLabels = chartLabels.split("|");

            let chartColors = String(overviewChart.data('backgroundcolor'));
            chartColors = chartColors.split("|");

            WhereUsed.dashboard.charts.overviewChart = new Chart(overviewChart, {
                type: "doughnut",
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: chartColors
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        reDrawCharts: function () {
            WhereUsed.dashboard.charts.overviewChart.destroy();

            WhereUsed.dashboard.drawCharts();
        },

        detectRedraw: function (item) {
            WhereUsed.dashboard.reDrawCharts();
        },

        /**
         * Clears the input value on focus if the value is just a space
         *
         * @package Whereused
         * @since 1.0.0
         */
        clearSearch: function () {

            let input = $('#find_url_usage .search-box input[type="search"]');

            if (input.length > 0) {

                // Add placeholder text
                input.attr('placeholder', 'https://');

            }

        },

        /**
         * Prevents the user from making an empty search
         *
         * @package Whereused
         * @since 1.0.0
         */
        preventEmptySearch: function () {

            let form = $(this);
            let input = form.find('input[type=search');

            if (input.val() == '') {
                alert('Please use the search input box before submitting.');
                input.focus();
                return false;
            }
        },

        /**
         * User cancels a scan
         *
         * @package Whereused
         * @since 1.0.0
         */
        cancelScan: function () {

            let link = $(this);
            let nonce = link.data('nonce');
            let target = link.closest('.inside'); // All the content of the metabox

            target.fadeOut(0, function () {
                $(this).html('<span class="loading-text"><span class="dashicons spin dashicons-update-alt"></span>Cancelling Scan...</span>').fadeIn(200);
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: WhereUsedAjax.ajaxURL,
                data: {
                    action: 'whereused_scan_cancel',
                    nonce: nonce
                },
                success: function (json) {
                    let response = JSON.parse(json);

                    target.fadeOut(0, function () {

                        $(this).html(response.html);
                        $(this).fadeIn(200);

                    });
                },
                fail: function () {
                    target.html("Ajax failed. Please try again.");
                }
            });

        },

        /**
         * User initiates a scan
         *
         * @package Whereused
         * @since 1.0.0
         */
        startScan: function (e) {

            e.preventDefault();

            let link = $(this);
            let type = link.data('type');
            let nonce = link.data('nonce');
            let target = link.closest('.inside'); // All the content of the metabox

            target.fadeOut(0, function () {
                $(this).html('<span class="loading-text"><span class="dashicons spin dashicons-update"></span>Starting Scan...</span>').fadeIn(200);
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: WhereUsedAjax.ajaxURL,
                data: {
                    action: 'whereused_scan_start',
                    nonce: nonce,
                    type: type
                },
                success: function (json) {
                    let response = JSON.parse(json);
                    target.fadeOut(0, function () {

                        $(this).html(response.html);
                        $(this).fadeIn(200);

                        // Update Progress Bar
                        WhereUsed.dashboard.updateProgressBar();

                    });
                },
                fail: function () {
                    target.html("Ajax failed. Please try again.");
                }
            });

        },

        /**
         * Grabs an updated progress bar
         */
        updateProgressBar: function (timeout = 5000) {

            setTimeout(function () {

                let metabox = $('#scan');
                let target = metabox.find('.inside'); // All the content of the metabox
                let progressBar = metabox.find('#progress-bar');

                if (progressBar.length == 0) {
                    // bail
                    return;
                }

                $.ajax({
                    type: "post",
                    dataType: "html",
                    url: WhereUsedAjax.ajaxURL,
                    data: {
                        action: 'whereused_scan_progress_bar',
                        progress_only: true
                    },
                    success: function (json) {

                        let progress = JSON.parse(json);
                        let currentPercent = progress.percent;
                        let metabox = $('#scan');
                        let progressBar = metabox.find('#progress-bar');
                        let currently = progress.currently;

                        // Currently Scanning
                        if ( currently == '' ){
                            progressBar.find('.currently').html('...');
                        } else {
                            progressBar.find('.currently').html(currently);
                        }

                        // Updated Progress
                        progressBar.find('.percent').html(currentPercent + '%');
                        progressBar.find('.current-progress').css('width', currentPercent + '%');

                        if (100 == currentPercent) {
                            // We are finished!
                            target.find('.scan-message').html('Dashboard will refresh shortly.');
                            progressBar.find('.text').html('Finishing up the scan...');
                        }

                        if (progress.endDate) {
                            $('html head').append('<meta http-equiv="refresh" content="3">');
                        } else {
                            // Update progress bar
                            WhereUsed.dashboard.updateProgressBar();
                        }

                    },
                    fail: function () {
                        target.html("Ajax failed. Please try again.");
                    }
                });
            }, timeout);

        },
    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        WhereUsed.dashboard.init();
    });

})(jQuery);