/**
 * Helpers Library main script
 */
WhereUsed.HelpersLibrary = (typeof WhereUsed.HelpersLibrary === 'undefined') ? {} : WhereUsed.HelpersLibrary;

// Use jQuery shorthand
(function ($) {

    WhereUsed.HelpersLibrary.plugin = {

        /**
         * Plugin variable to detect whether user clicked the back button in the browser
         */
        userClickedBack: false,

        /**
         * This is the place the AJAX will place the content
         */
        ajaxTarget: '',

        /**
         * This dictates what action happens in AJAX
         */
        ajaxAction: '',

        /**
         * Clicked on main menu item in header
         *
         * @param e Click event
         */
        clickedMainTab: function (e) {

            // Ignore Dashboard and Help main menus
            if ($(this).hasClass('tab-dashboard') || '_blank' === $(this).attr('target')) {
                // Fully Load the link
                return;
            }

            // Prevent the click from going anywhere
            e.preventDefault();

            let link = $(this);
            let allLinks = link.closest('.privacy-settings-tabs-wrapper').find('a');

            // Reset active tab
            allLinks.removeClass('active');

            // Set this link ot active
            link.addClass('active');

            let networkTab = $('#screen-meta-links');

            if (networkTab.length > 0) {

                if (link.hasClass('tab-settings')) {
                    networkTab.removeClass('hidden');
                } else {
                    if (!networkTab.hasClass('hidden')) {
                        networkTab.addClass('hidden');
                    }
                }
            }

            WhereUsed.HelpersLibrary.plugin.ajaxTarget = 'body .content-body';
            WhereUsed.HelpersLibrary.plugin.ajaxAction = $(this).data('action');

            // Trigger AJAX
            WhereUsed.HelpersLibrary.plugin.doAjax(link);
        },

        /**
         * Runs the AJAX swapping of content
         */
        doAjax: function (link) {

            let networkAdmin = 0;

            if ($('body').hasClass('network-admin')) {
                networkAdmin = 1;
            }

            let mainTabLink = $('.privacy-settings-tabs-wrapper').find('a.active');

            // Grab the query params in array key 1
            linkParams = link.attr('href').split('?');

            let urlParams = new URLSearchParams(linkParams[1]);

            // The container that is getting the content swapped
            let target = $(WhereUsed.HelpersLibrary.plugin.ajaxTarget);

            target.fadeOut(0, function () {
                target.html('<span class="loading-text"><span class="dashicons spin dashicons-update"></span>Loading...</span>').fadeIn(200);
            });

            setTimeout(function () {

                $.ajax({
                    type: "post",
                    dataType: "html",
                    url: WhereUsedHelpersLibraryAjax.ajaxURL,
                    data: {
                        action: WhereUsed.HelpersLibrary.plugin.ajaxAction,
                        page: urlParams.get('page'),
                        tab: urlParams.get('tab'),
                        networkAdmin: networkAdmin,
                        target: WhereUsed.HelpersLibrary.plugin.ajaxTarget,
                    },
                    success: function (response) {
                        target.fadeOut(0, function () {

                            target.html(response);

                            target.fadeIn(200);

                            if (WhereUsed.HelpersLibrary.plugin.userClickedBack === false) {
                                // Update browser URL bar
                                document.title = mainTabLink.text();
                                window.history.pushState({
                                    "html": response,
                                    "pageTitle": link.text()
                                }, '', link.attr('href'));
                            }

                            // Set this to default value
                            WhereUsed.HelpersLibrary.plugin.userClickedBack = false;

                        });
                    },
                    fail: function () {
                        target.html("Ajax failed. Please try again.");
                    }
                });

            }, 500);

        },

        /**
         * Activates clicked content tab, then shows associated tab content
         */
        clickContentTabs: function (e) {

            e.preventDefault();

            WhereUsed.HelpersLibrary.plugin.toggleContentTabs($(this));
        },

        /**
         * Runs on initial load to ensure that the correct tab and tab content is active based on hash value
         */
        loadContentTabs: function () {

            let hash = window.location.hash;
            let className = '.nav-tab-' + hash.replace('#', '');

            WhereUsed.HelpersLibrary.plugin.toggleContentTabs($(className));

        },

        /**
         * Runs initially to set active tab and tab content based on whether there is an anchor tag in the URL
         */
        toggleContentTabs: function (activeTab) {

            if (activeTab.length > 0) {

                let target = activeTab.attr('href');

                // Mark all tabs inactive
                activeTab.closest('.nav-tab-wrapper').find('.nav-tab').removeClass('nav-tab-active');

                // Mark this tab active
                activeTab.addClass('nav-tab-active');

                $('.all-tab-content .tab-content').removeClass('active');
                $(target).addClass('active');

                if ('#logs' == target) {
                    WhereUsed.HelpersLibrary.plugin.scrollBottom('debug-log');
                }

            }

        },

        /**
         * Scrolls the element to the bottom
         *
         * @param elementID
         */
        scrollBottom: function (elementID) {

            element = document.getElementById(elementID)

            element.scroll({top: element.scrollHeight, behavior: "smooth"})
        }

    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {

        let body = $('body');

        body.on('click', '.nav-tab', WhereUsed.HelpersLibrary.plugin.clickContentTabs);
        body.find('.privacy-settings-tabs-wrapper').on('click', 'a', WhereUsed.HelpersLibrary.plugin.clickedMainTab);

        WhereUsed.HelpersLibrary.plugin.loadContentTabs();

        // Detect the user clicking back button so trigger AJAX call
        window.onpopstate = function (e) {
            if (e.state) {

                // Let the script know user clicked back button
                WhereUsed.HelpersLibrary.plugin.userClickedBack = true;

                let clickLink = '.tab-' + e.state.pageTitle; // create class
                clickLink = clickLink.replace(/\ /g, '-'); // remove spaces
                $(clickLink.toLowerCase()).trigger('click'); // Convert to lowercase and trigger click
            }
        };

    });

})(jQuery);