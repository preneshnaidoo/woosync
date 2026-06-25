/**
 * WooSync v3.3 - Tier Settings JavaScript
 * Handles tier settings on Settings page and tier savings on Sync Log page
 */

jQuery(function($) {
    // Save Tier Settings Button
    $('#saveTierSettingsBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Saving...');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_save_tier_settings',
                vendor_id: 'amrod',
                tier: $('#tierSelect').val(),
                tier_notes: $('#tierNotes').val(),
                tier_pricing_endpoint: $('#tierPricingEndpoint').val(),
                markup_percent: $('#markupPercent').val(),
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Tier settings saved successfully!');
                } else {
                    alert('❌ Failed to save tier settings: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error saving tier settings');
            },
            complete: function() {
                btn.prop('disabled', false).html('💾 Save Tier Settings');
            }
        });
    });

    // Refresh Tier Status Button
    $('#refreshTierStatusBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Refreshing...');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_refresh_tier_status',
                vendor_id: 'amrod',
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Tier status refreshed! Your tier: ' + response.data.tier);
                    location.reload();
                } else {
                    alert('❌ Failed to refresh tier: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error refreshing tier status');
            },
            complete: function() {
                btn.prop('disabled', false).html('🔄 Refresh from API');
            }
        });
    });

    // Refresh Tier Savings Button (on Sync Log page)
    $('#refreshSavingsBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Loading...');
        
        $('#tierSavingsSummary').html('<div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span> Loading tier savings data...</div>');
        $('#tierSavingsBody').html('<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_get_tier_savings',
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayTierSavings(response.data);
                } else {
                    $('#tierSavingsSummary').html('<div class="alert alert-danger">❌ ' + response.data + '</div>');
                    $('#tierSavingsBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Failed to load savings</td></tr>');
                }
            },
            error: function() {
                $('#tierSavingsSummary').html('<div class="alert alert-danger">❌ Error loading tier savings</div>');
                $('#tierSavingsBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Error loading savings</td></tr>');
            },
            complete: function() {
                btn.prop('disabled', false).html('🔄 Refresh Savings');
            }
        });
    });

    function displayTierSavings(data) {
        if (!data.products || data.products.length === 0) {
            $('#tierSavingsSummary').html('<div class="alert alert-info">No tier pricing savings found for ' + data.tier + ' tier.</div>');
            $('#tierSavingsBody').html('<tr><td colspan="5" class="text-center text-muted py-4">No savings data available</td></tr>');
            return;
        }
        
        // Summary
        const totalSavings = data.total_savings || 0;
        const productCount = data.product_count || 0;
        $('#tierSavingsSummary').html(
            '<div class="alert alert-success">' +
            '<strong>💰 Your ' + data.tier + ' tier saves you R' + totalSavings.toFixed(2) + ' across ' + productCount + ' products!</strong><br>' +
            '<small>These savings are based on tier-specific pricing from your supplier.</small>' +
            '</div>'
        );
        
        // Table
        let html = '';
        data.products.forEach(function(product) {
            html += '<tr>';
            html += '<td><strong>' + product.name + '</strong><br><small class="text-muted">SKU: ' + product.code + '</small></td>';
            html += '<td><s>R' + product.standard_price.toFixed(2) + '</s></td>';
            html += '<td class="text-success fw-bold">R' + product.tier_price.toFixed(2) + '</td>';
            html += '<td class="text-warning fw-bold">-R' + product.savings_per_unit.toFixed(2) + '</td>';
            html += '<td><span class="badge bg-danger">-' + product.savings_percent + '%</span></td>';
            html += '</tr>';
        });
        
        $('#tierSavingsBody').html(html);
    }

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});
