/**
 * Amrod Sync v2.0 - Field Mapping Manager
 */

jQuery(function($) {
    // Auto-detect fields
    $('#autoDetectBtn').on('click', function() {
        $(this).prop('disabled', true).html('🔍 Detecting...');

        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_auto_detect_fields',
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const fields = response.data.fields;
                    showDetectionResultsField(fields);
                }
            },
            complete: function() {
                $('#autoDetectBtn').prop('disabled', false).html('🔍 Auto-Detect Fields from Amrod');
            }
        });
    });

    // Test mapping
    $('#testMappingBtn').on('click', function() {
        const mapping = getFormMapping();

        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_test_field_mapping',
                mapping: mapping,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showTestResult(response.data.preview);
                }
            }
        });
    });

    // Save mapping
    $('#fieldMappingForm').on('submit', function(e) {
        const mapping = getFormMapping();
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_save_field_mapping',
                mapping: mapping,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Field mapping saved successfully!');
                }
            }
        });

        e.preventDefault();
    });

    function getFormMapping() {
        const mapping = {};
        $('input[name^="mapping"]').each(function() {
            const name = $(this).attr('name');
            const field = name.match(/mapping\[(\w+)\]/)[1];
            mapping[field] = $(this).val();
        });
        return mapping;
    }

    function showDetectionResultsField(fields) {
        const fieldsHtml = '<div class="alert alert-success">Found fields: <code>' + fields.join(', ') + '</code></div>';
        $('#mappingTable').before(fieldsHtml);
    }

    function showTestResult(preview) {
        let html = '<ul>';
        for (const key in preview) {
            html += '<li><strong>' + key + ':</strong> ' + preview[key] + '</li>';
        }
        html += '</ul>';

        $('#testProductData').html(html);
        $('#mappingTestResult').show();
    }
});
