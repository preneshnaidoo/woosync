/**
 * WooSync Supplier Tier Settings - Tier card, refresh, and savings display
 */
(function($) {
    'use strict';

    var TierSettings = {
        initialized: false,

        init: function() {
            if (this.initialized) return;
            this.bindEvents();
            this.initialized = true;
        },

        bindEvents: function() {
            var self = this;

            // Refresh tier status
            $(document).on('click', '#refreshTierBtn', function(e) {
                e.preventDefault();
                self.refreshTierStatus();
            });

            // Upgrade tier button
            $(document).on('click', '#upgradeTierBtn', function(e) {
                e.preventDefault();
                self.upgradeTier();
            });

            // Manual tier override
            $(document).on('change', '#manualTierOverride', function() {
                self.saveManualTier();
            });

            // Tier product preview toggle
            $(document).on('click', '.tier-preview-toggle', function(e) {
                e.preventDefault();
                var productId = $(this).data('product');
                self.toggleProductPreview(productId);
            });

            // View tier benefits
            $(document).on('click', '#viewTierBenefitsBtn', function(e) {
                e.preventDefault();
                self.showTierBenefits();
            });
        },

        refreshTierStatus: function() {
            var btn = $('#refreshTierBtn');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...');

            $.ajax({
                url: woosyncTier.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_refresh_tier_status',
                    nonce: woosyncTier.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateTierCard(response.data);
                        woosyncTier.showNotice('Tier status refreshed', 'success');
                    } else {
                        woosyncTier.showNotice('Failed to refresh tier status', 'error');
                    }
                    btn.prop('disabled', false).html('🔄 Refresh Tier Status');
                },
                error: function() {
                    woosyncTier.showNotice('Network error', 'error');
                    btn.prop('disabled', false).html('🔄 Refresh Tier Status');
                }
            });
        },

        updateTierCard: function(data) {
            var tier = data.tier || 'Standard';
            var tierClass = this.getTierClass(tier);
            var tierIcon = this.getTierIcon(tier);

            $('#tierName').text(tier);
            $('#tierBadge').removeClass('tier-gold tier-silver tier-bronze tier-platinum tier-standard')
                         .addClass(tierClass);
            $('#tierStatus').text(data.status || 'Active');
            $('#tierSince').text(data.since || 'N/A');
            $('#tierExpiry').text(data.expiry || 'N/A');
            $('#tierNotes').text(data.notes || 'No notes');

            // Update savings display
            if (data.savings) {
                $('#tierSavingsTotal').text('R' + data.savings.total.toLocaleString());
                $('#tierSavingsProducts').text(data.savings.products + ' products');
            }
        },

        getTierClass: function(tier) {
            var classes = {
                'Gold': 'tier-gold',
                'Silver': 'tier-silver',
                'Bronze': 'tier-bronze',
                'Platinum': 'tier-platinum',
                'Standard': 'tier-standard'
            };
            return classes[tier] || 'tier-standard';
        },

        getTierIcon: function(tier) {
            var icons = {
                'Gold': '🏆',
                'Silver': '🥈',
                'Bronze': '🥉',
                'Platinum': '💎',
                'Standard': '📦'
            };
            return icons[tier] || '📦';
        },

        upgradeTier: function() {
            // Open external upgrade link
            var vendor = woosyncTier.vendorName || 'Amrod';
            var upgradeUrl = 'https://' + vendor.toLowerCase() + '.co.za/tier-upgrade';
            window.open(upgradeUrl, '_blank');
        },

        saveManualTier: function() {
            var tier = $('#manualTierOverride').val();

            $.ajax({
                url: woosyncTier.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_manual_tier',
                    nonce: woosyncTier.nonce,
                    tier: tier
                },
                success: function(response) {
                    if (response.success) {
                        woosyncTier.showNotice('Manual tier override saved', 'success');
                        self.refreshTierStatus();
                    } else {
                        woosyncTier.showNotice('Failed to save tier', 'error');
                    }
                }
            });
        },

        toggleProductPreview: function(productId) {
            var panel = $('#tierPreviewPanel_' + productId);
            if (panel.length) {
                panel.toggle();
            }
        },

        showTierBenefits: function() {
            $.ajax({
                url: woosyncTier.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_get_tier_benefits',
                    nonce: woosyncTier.nonce
                },
                success: function(response) {
                    if (response.success) {
                        woosyncTier.renderTierBenefits(response.data);
                        $('#tierBenefitsModal').modal('show');
                    } else {
                        woosyncTier.showNotice('Failed to load benefits', 'error');
                    }
                }
            });
        },

        loadTierSavings: function() {
            $.ajax({
                url: woosyncTier.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_get_tier_savings',
                    nonce: woosyncTier.nonce
                },
                success: function(response) {
                    if (response.success) {
                        woosyncTier.renderTierSavings(response.data);
                    }
                }
            });
        }
    };

    // Helper functions exposed globally
    window.woosyncTier = {
        ajaxUrl: '',
        nonce: '',
        vendorName: '',
        showNotice: function(message, type) {
            var alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-danger' : 'alert-warning');
            var html = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                       message +
                       '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                       '</div>';
            $('#tierNotice').html(html);
            setTimeout(function() {
                $('#tierNotice').html('');
            }, 5000);
        },
        renderTierSavings: function(data) {
            var html = '<div class="tier-savings-widget">' +
                       '<div class="savings-total">' +
                       '<div class="savings-amount">R' + data.total_savings.toLocaleString() + '</div>' +
                       '<div class="savings-label">Total Savings</div>' +
                       '</div>' +
                       '<div class="savings-detail">' +
                       'Your ' + data.tier + ' tier saves you R' + data.total_savings.toLocaleString() + ' across ' + data.products + ' products' +
                       '</div>' +
                       '</div>';
            $('#tierSavingsWidget').html(html);

            // Build benefits table
            var tableHtml = '<table class="table table-sm"><thead><tr><th>Product</th><th>Standard Price</th><th>Your Tier Price</th><th>Savings/Unit</th><th>Total Savings</th></tr></thead><tbody>';
            $.each(data.products_detail, function(i, product) {
                tableHtml += '<tr>';
                tableHtml += '<td>' + product.name + '</td>';
                tableHtml += '<td>R' + product.standard_price + '</td>';
                tableHtml += '<td>R' + product.tier_price + '</td>';
                tableHtml += '<td class="text-success">R' + product.savings_per_unit + '</td>';
                tableHtml += '<td class="text-success">R' + product.total_savings + '</td>';
                tableHtml += '</tr>';
            });
            tableHtml += '</tbody></table>';
            $('#tierSavingsTable').html(tableHtml);
        },
        renderTierBenefits: function(data) {
            var html = '<div class="tier-benefits-content">' +
                       '<h5>' + data.tier + ' Tier Benefits</h5>' +
                       '<ul class="list-group">';
            $.each(data.benefits, function(i, benefit) {
                html += '<li class="list-group-item">' + benefit + '</li>';
            });
            html += '</ul>';
            html += '<div class="mt-3 text-muted">';
            html += '<p><strong>Your pricing level:</strong> ' + data.tier + '</p>';
            html += '<p><strong>Status:</strong> ' + data.status + '</p>';
            html += '<p><strong>Active since:</strong> ' + data.since + '</p>';
            html += '</div>';
            $('#tierBenefitsContent').html(html);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        TierSettings.init();
        TierSettings.loadTierSavings();
    });

    // Expose globally
    window.TierSettings = TierSettings;

})(jQuery);
