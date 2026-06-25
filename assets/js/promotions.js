/**
 * WooSync Promotions JavaScript
 * Handles notification management and service cards
 */
(function($) {
    'use strict';

    /**
     * Initialize promotions functionality
     */
    function init() {
        initBannerDismiss();
        initNotificationForms();
        initServiceForms();
        initDatePickers();
        initPreview();
    }

    /**
     * Initialize banner dismiss functionality
     */
    function initBannerDismiss() {
        $(document).on('click', '.woosync-notification-dismiss', function(e) {
            e.preventDefault();
            var $banner = $(this).closest('.woosync-notification-banner');
            var notificationId = $banner.data('notification-id');

            $.ajax({
                url: woosyncPromotions.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_dismiss_notification',
                    notification_id: notificationId,
                    nonce: woosyncPromotions.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $banner.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                },
                error: function() {
                    $banner.fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            });
        });
    }

    /**
     * Initialize notification form handling
     */
    function initNotificationForms() {
        // Delete confirmation
        $(document).on('click', '.delete-notification-btn', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }
            $(this).closest('form').submit();
        });

        // Auto-dismiss toggle
        $(document).on('change', '#notification_expiry_enabled', function() {
            $('#notification_expiry').prop('disabled', !$(this).prop('checked'));
        });
    }

    /**
     * Initialize service form handling
     */
    function initServiceForms() {
        // Toggle enabled state
        $(document).on('change', '.service-enabled-toggle', function() {
            var $form = $(this).closest('form');
            $form.find('input[name="service_enabled"]').val($(this).prop('checked') ? '1' : '');
            $form.submit();
        });

        // Toggle featured state
        $(document).on('change', '.service-featured-toggle', function() {
            var $form = $(this).closest('form');
            $form.find('input[name="service_featured"]').val($(this).prop('checked') ? '1' : '');
            $form.submit();
        });
    }

    /**
     * Initialize date picker fields
     */
    function initDatePickers() {
        $('.woosync-date-picker').each(function() {
            var $input = $(this);
            var $wrapper = $input.closest('.woosync-date-wrapper');

            $input.datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                showButtonPanel: true
            });
        });
    }

    /**
     * Initialize notification preview
     */
    function initPreview() {
        $(document).on('input change', '#notification_title, #notification_message, #notification_bg_color, #notification_cta, #notification_link', function() {
            updatePreview();
        });

        function updatePreview() {
            var title = $('#notification_title').val() || 'Notification Title';
            var message = $('#notification_message').val() || 'Notification message will appear here.';
            var bgColor = $('#notification_bg_color').val() || '#1a1a2e';
            var ctaText = $('#notification_cta').val() || 'Learn More';
            var link = $('#notification_link').val();

            $('#preview_banner').css('background-color', bgColor);
            $('#preview_title').text(title);
            $('#preview_message').text(message);
            $('#preview_cta').text(ctaText);
            $('#preview_cta').toggle(!!link);
        }
    }

    /**
     * Record notification view
     */
    function recordView(notificationId) {
        $.ajax({
            url: woosyncPromotions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_record_notification_view',
                notification_id: notificationId,
                nonce: woosyncPromotions.nonce
            }
        });
    }

    /**
     * Record notification click
     */
    function recordClick(notificationId) {
        $.ajax({
            url: woosyncPromotions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_record_notification_click',
                notification_id: notificationId,
                nonce: woosyncPromotions.nonce
            }
        });
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
