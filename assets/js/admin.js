/**
 * WooSync Admin JavaScript
 *
 * @package WooSync
 */

(function($) {
    'use strict';

    var WooSyncAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Credentials form
            $(document).on('submit', '#woosync-credentials-form', this.saveCredentials.bind(this));
            $(document).on('click', '#woosync-test-connection', this.testConnection.bind(this));

            // Field mapping
            $(document).on('click', '#woosync-save-mapping', this.saveFieldMapping.bind(this));
            $(document).on('click', '#woosync-auto-detect', this.autoDetectFields.bind(this));

            // Endpoints
            $(document).on('click', '.woosync-save-endpoint', this.saveEndpoint.bind(this));

            // Log
            $(document).on('click', '#woosync-clear-log', this.clearLog.bind(this));
        },

        testConnection: function(e) {
            e.preventDefault();
            var $form = $('#woosync-credentials-form');
            var $result = $('#woosync-connection-result');
            var $button = $('#woosync-test-connection');

            $button.prop('disabled', true).text(woosyncData.strings.testing);
            $result.removeClass('notice-error notice-success').hide();

            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'woosync_test_connection'});

            $.ajax({
                url: woosyncData.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $result.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                    } else {
                        $result.addClass('notice-error').html('<p>' + response.data + '</p>').show();
                    }
                },
                error: function() {
                    $result.addClass('notice-error').html('<p>Request failed. Please try again.</p>').show();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        saveCredentials: function(e) {
            e.preventDefault();
            var $form = $('#woosync-credentials-form');
            var $result = $('#woosync-connection-result');
            var $button = $form.find('button[type="submit"]');

            $button.prop('disabled', true).text(woosyncData.strings.saving);
            $result.removeClass('notice-error notice-success').hide();

            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'woosync_save_credentials_simple'});

            $.ajax({
                url: woosyncData.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $result.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                    } else {
                        $result.addClass('notice-error').html('<p>' + response.data + '</p>').show();
                    }
                },
                error: function() {
                    $result.addClass('notice-error').html('<p>Request failed. Please try again.</p>').show();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Credentials');
                }
            });
        },

        saveFieldMapping: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            var mapping = {};

            $('.woosync-field-mapping').each(function() {
                var $input = $(this);
                var wcField = $input.data('wc-field');
                var value = $input.val();
                if (value) {
                    mapping[wcField] = value;
                }
            });

            $button.prop('disabled', true).text(woosyncData.strings.saving);

            $.ajax({
                url: woosyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_field_mapping',
                    nonce: woosyncData.nonce,
                    mapping: mapping
                },
                success: function(response) {
                    if (response.success) {
                        alert(woosyncData.strings.saved);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Field Mapping');
                }
            });
        },

        autoDetectFields: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);

            $button.prop('disabled', true).text('Detecting...');

            $.ajax({
                url: woosyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_auto_detect_fields',
                    nonce: woosyncData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var mapping = response.data.mapping;
                        $.each(mapping, function(wcField, apiField) {
                            $('.woosync-field-mapping[data-wc-field="' + wcField + '"]').val(apiField);
                        });
                        alert('Fields auto-detected! Review and save.');
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Auto-Detect Fields');
                }
            });
        },

        saveEndpoint: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            var endpoint = $button.data('endpoint');
            var $checkbox = $('.woosync-endpoint-toggle[data-endpoint="' + endpoint + '"]');
            var enabled = $checkbox.is(':checked') ? 1 : 0;

            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: woosyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_endpoint',
                    nonce: woosyncData.nonce,
                    endpoint: endpoint,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('Saved!').addClass('button-primary');
                        setTimeout(function() {
                            $button.text('Save').removeClass('button-primary');
                        }, 2000);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        clearLog: function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to clear the sync log?')) {
                return;
            }

            var $button = $(e.currentTarget);
            $button.prop('disabled', true).text('Clearing...');

            // Note: This would need a backend AJAX handler to actually clear the log
            // For now, just reload the page
            location.reload();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WooSyncAdmin.init();
    });

})(jQuery);
