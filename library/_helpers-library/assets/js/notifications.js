/**
 * Notifications Script
 */
WhereUsed.HelpersLibrary = (typeof WhereUsed.HelpersLibrary === 'undefined') ? {} : WhereUsed.HelpersLibrary;

// Use jQuery shorthand
(function ($) {

    WhereUsed.HelpersLibrary.notifications = {

        /**
         * Set listeners when the script loads
         */
        init: function () {
            let header = $('.privacy-settings-header');
            header.on('click', '.notifications-bell', WhereUsed.HelpersLibrary.notifications.toggle);
            header.on('click', '.notifications li .read-status', WhereUsed.HelpersLibrary.notifications.markRead);
            header.on('click', '.notifications li:first-child .mark-all-read', WhereUsed.HelpersLibrary.notifications.markAllRead);
        },

        /**
         * Displays the list of notifications when the bell is clicked
         */
        toggle: function (e) {

            e.preventDefault();

            let notifications = $('.notifications');

            if (notifications.hasClass('on')) {
                // Hide notifications
                notifications.removeClass('on');

                // Unbind click
                $(window).off('click');

            } else {
                // Show notifications
                notifications.addClass('on');

                // Hide notifications when click anywhere else
                $(window).on('click', function (event) {
                    if (!$(event.target).closest('.notifications').length && !$(event.target).hasClass('dashicons-bell') && !$(event.target).closest('.dashicons-bell').length) {
                        notifications.removeClass('on');
                        // Unbind click
                        $(window).off('click');
                    }
                });
            }

        },

        /**
         * Marks a notification as read
         */
        markRead: function () {

            let checkbox = $(this);
            let notification = checkbox.closest('li');

            if (checkbox.is(':checked')) {
                // mark as read
                notification.removeClass('unread');
            } else {
                // mark unread
                notification.addClass('unread');
            }

            WhereUsed.HelpersLibrary.notifications.updateRead();

        },

        /**
         * Marks all notifications as read
         */
        markAllRead: function () {

            // Check all checkboxes
            $('.notifications li input[type=checkbox]').prop('checked', true);

            // Visually indicate they are read
            $('.notifications li').removeClass('unread');

            WhereUsed.HelpersLibrary.notifications.updateRead();

        },

        /**
         * Update notification read statuses
         */
        updateRead: function () {

            let checkboxes = $('.notifications li input[type=checkbox]');
            let action = $('.notifications').data('action');
            let nonce = $('.notifications').data('nonce');
            let checked = [];

            checkboxes.each(function () {
                checked.push($(this).is(':checked'));
            });

            WhereUsed.HelpersLibrary.notifications.updateCount();

            // Disable checkboxes
            checkboxes.attr('disabled', true);

            $.ajax({
                type: "post",
                dataType: "html",
                url: WhereUsedHelpersLibraryAjax.ajaxURL,
                data: {
                    action: action,
                    nonce: nonce,
                    checked: checked,
                },
                success: function (json) {
                    let response = JSON.parse(json);

                    $('.notifications').data('nonce', response.nonce);

                    //console.log('response: ' + response);
                    checkboxes.removeAttr("disabled");
                },
                fail: function () {
                    console.log('Ajax failed. Please try again.');
                }
            });

        },

        /**
         * Updates the unread count bubble
         */
        updateCount: function () {

            let checkboxes = $('.notifications li input[type=checkbox]');
            let unread = 0;

            checkboxes.each(function () {
                if (!$(this).is(':checked')) {
                    unread = unread + 1;
                }
            });

            let bell = $('.notifications-bell');

            bell.find('.count').html(unread);

            if (unread > 0) {
                bell.addClass('on');
            } else {
                bell.removeClass('on');
            }

        },

    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        WhereUsed.HelpersLibrary.notifications.init();
    });

})(jQuery);