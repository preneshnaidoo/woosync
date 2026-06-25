/**
 * WooSync Tiered Pricing - Role tiers, customer pricing, and price preview
 */
(function($) {
    'use strict';

    var Pricing = {
        initialized: false,

        init: function() {
            if (this.initialized) return;
            this.bindEvents();
            this.initialized = true;
        },

        bindEvents: function() {
            var self = this;

            // Enable/disable toggle
            $(document).on('change', '#tieredPricingEnabled', function() {
                self.saveGlobalSettings();
            });

            // Default markup change
            $(document).on('change', '#defaultMarkup', function() {
                self.saveGlobalSettings();
            });

            // Show to logged-out users toggle
            $(document).on('change', '#showToLoggedOut', function() {
                self.saveGlobalSettings();
            });

            // Apply to all button
            $(document).on('click', '#applyToAllBtn', function(e) {
                e.preventDefault();
                self.applyToAll();
            });

            // Role tier inline editing
            $(document).on('click', '.tier-edit-btn', function(e) {
                e.preventDefault();
                var row = $(this).closest('tr');
                self.editRoleRow(row);
            });

            // Role tier save
            $(document).on('click', '.tier-save-btn', function(e) {
                e.preventDefault();
                var row = $(this).closest('tr');
                self.saveRoleRow(row);
            });

            // Role tier cancel
            $(document).on('click', '.tier-cancel-btn', function(e) {
                e.preventDefault();
                var row = $(this).closest('tr');
                self.cancelRoleEdit(row);
            });

            // Role tier toggle
            $(document).on('change', '.tier-enabled-checkbox', function() {
                var row = $(this).closest('tr');
                var role = row.data('role');
                var enabled = $(this).is(':checked');
                self.toggleRoleTier(role, enabled);
            });

            // Customer search
            $(document).on('click', '#searchCustomerBtn', function(e) {
                e.preventDefault();
                self.searchCustomers();
            });

            // Customer search enter key
            $(document).on('keypress', '#customerSearchInput', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.searchCustomers();
                }
            });

            // Add customer pricing
            $(document).on('click', '.add-customer-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('user');
                self.addCustomerPricing(userId);
            });

            // Remove customer pricing
            $(document).on('click', '.remove-customer-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('user');
                self.removeCustomerPricing(userId);
            });

            // Customer markup change
            $(document).on('change', '.customer-markup-input', function() {
                var userId = $(this).data('user');
                var markup = $(this).val();
                self.updateCustomerMarkup(userId, markup);
            });

            // Bulk actions
            $(document).on('click', '#exportPricingBtn', function(e) {
                e.preventDefault();
                self.exportPricing();
            });

            $(document).on('click', '#importPricingBtn', function(e) {
                e.preventDefault();
                $('#importPricingFile').click();
            });

            $(document).on('change', '#importPricingFile', function(e) {
                self.importPricing(e.target.files[0]);
            });

            // Price preview
            $(document).on('change', '#previewProduct, #previewCustomer', function() {
                self.updatePricePreview();
            });

            // Pricing rules save
            $(document).on('click', '#savePricingRulesBtn', function(e) {
                e.preventDefault();
                self.savePricingRules();
            });

            // Inline customer edit
            $(document).on('click', '.edit-customer-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('user');
                self.editCustomerRow(userId);
            });

            $(document).on('click', '.save-customer-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('user');
                self.saveCustomerRow(userId);
            });

            $(document).on('click', '.cancel-customer-btn', function(e) {
                e.preventDefault();
                var userId = $(this).data('user');
                self.cancelCustomerEdit(userId);
            });
        },

        saveGlobalSettings: function() {
            var enabled = $('#tieredPricingEnabled').is(':checked');
            var defaultMarkup = $('#defaultMarkup').val();
            var showToLoggedOut = $('#showToLoggedOut').is(':checked');

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_pricing_settings',
                    nonce: woosyncPricing.nonce,
                    enabled: enabled,
                    default_markup: defaultMarkup,
                    show_to_logged_out: showToLoggedOut
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.showNotice('Settings saved successfully', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to save settings', 'error');
                    }
                }
            });
        },

        applyToAll: function() {
            var btn = $('#applyToAllBtn');
            var defaultMarkup = $('#defaultMarkup').val();

            if (!confirm('Apply ' + defaultMarkup + '% markup to all existing custom pricing?')) return;

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Applying...');

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_apply_markup_to_all',
                    nonce: woosyncPricing.nonce,
                    markup: defaultMarkup
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.showNotice('Applied to ' + response.data.count + ' customers', 'success');
                        woosyncPricing.loadCustomerTable();
                    } else {
                        woosyncPricing.showNotice('Failed to apply markup', 'error');
                    }
                    btn.prop('disabled', false).html('Apply to All');
                }
            });
        },

        editRoleRow: function(row) {
            var role = row.data('role');
            var currentMarkup = row.find('.markup-value').text().replace('%', '');
            var currentEnabled = row.find('.tier-enabled-checkbox').is(':checked');

            row.find('.markup-display').hide();
            row.find('.markup-edit').html('<input type="number" class="form-control form-control-sm markup-input" value="' + currentMarkup + '" min="0" max="500">').show();
            row.find('.tier-actions .tier-edit-btn').hide();
            row.find('.tier-actions .tier-save-btn, .tier-actions .tier-cancel-btn').show();
        },

        saveRoleRow: function(row) {
            var role = row.data('role');
            var markup = row.find('.markup-input').val();
            var enabled = row.find('.tier-enabled-checkbox').is(':checked');

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_role_markup',
                    nonce: woosyncPricing.nonce,
                    role: role,
                    markup: markup,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        row.find('.markup-value').text(markup + '%');
                        row.find('.markup-display').show();
                        row.find('.markup-edit').hide();
                        row.find('.tier-actions .tier-edit-btn').show();
                        row.find('.tier-actions .tier-save-btn, .tier-actions .tier-cancel-btn').hide();
                        woosyncPricing.showNotice('Role markup saved', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to save', 'error');
                    }
                }
            });
        },

        cancelRoleEdit: function(row) {
            row.find('.markup-display').show();
            row.find('.markup-edit').hide();
            row.find('.tier-actions .tier-edit-btn').show();
            row.find('.tier-actions .tier-save-btn, .tier-actions .tier-cancel-btn').hide();
        },

        toggleRoleTier: function(role, enabled) {
            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_toggle_role_tier',
                    nonce: woosyncPricing.nonce,
                    role: role,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.showNotice('Role tier ' + (enabled ? 'enabled' : 'disabled'), 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to update', 'error');
                    }
                }
            });
        },

        searchCustomers: function() {
            var query = $('#customerSearchInput').val();

            if (query.length < 2) {
                woosyncPricing.showNotice('Enter at least 2 characters', 'warning');
                return;
            }

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_search_customers',
                    nonce: woosyncPricing.nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.renderSearchResults(response.data);
                    } else {
                        woosyncPricing.showNotice('Search failed', 'error');
                    }
                }
            });
        },

        addCustomerPricing: function(userId) {
            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_add_customer_pricing',
                    nonce: woosyncPricing.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.loadCustomerTable();
                        woosyncPricing.showNotice('Customer pricing added', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to add customer', 'error');
                    }
                }
            });
        },

        removeCustomerPricing: function(userId) {
            if (!confirm('Remove custom pricing for this customer?')) return;

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_remove_customer_pricing',
                    nonce: woosyncPricing.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.loadCustomerTable();
                        woosyncPricing.showNotice('Customer pricing removed', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to remove', 'error');
                    }
                }
            });
        },

        updateCustomerMarkup: function(userId, markup) {
            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_update_customer_markup',
                    nonce: woosyncPricing.nonce,
                    user_id: userId,
                    markup: markup
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.showNotice('Markup updated', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to update', 'error');
                    }
                }
            });
        },

        editCustomerRow: function(userId) {
            var row = $('.customer-row[data-user="' + userId + '"]');
            var currentMarkup = row.find('.markup-value').text().replace('%', '');
            var currentTier = row.find('.tier-value').text();

            row.find('.markup-display').hide();
            row.find('.markup-edit').html(
                '<input type="number" class="form-control form-control-sm customer-markup-input" value="' + currentMarkup + '" min="0" max="500" data-user="' + userId + '">' +
                '<select class="form-control form-control-sm customer-tier-input mt-1" data-user="' + userId + '">' +
                '<option value="Gold" ' + (currentTier === 'Gold' ? 'selected' : '') + '>Gold</option>' +
                '<option value="Silver" ' + (currentTier === 'Silver' ? 'selected' : '') + '>Silver</option>' +
                '<option value="Bronze" ' + (currentTier === 'Bronze' ? 'selected' : '') + '>Bronze</option>' +
                '<option value="VIP" ' + (currentTier === 'VIP' ? 'selected' : '') + '>VIP</option>' +
                '</select>'
            ).show();
            row.find('.customer-actions .edit-customer-btn').hide();
            row.find('.customer-actions .save-customer-btn, .customer-actions .cancel-customer-btn').show();
        },

        saveCustomerRow: function(userId) {
            var row = $('.customer-row[data-user="' + userId + '"]');
            var markup = row.find('.customer-markup-input').val();
            var tier = row.find('.customer-tier-input').val();

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_customer_pricing',
                    nonce: woosyncPricing.nonce,
                    user_id: userId,
                    markup: markup,
                    tier: tier
                },
                success: function(response) {
                    if (response.success) {
                        row.find('.markup-value').text(markup + '%');
                        row.find('.tier-value').text(tier);
                        row.find('.markup-display').show();
                        row.find('.markup-edit').hide();
                        row.find('.customer-actions .edit-customer-btn').show();
                        row.find('.customer-actions .save-customer-btn, .customer-actions .cancel-customer-btn').hide();
                        woosyncPricing.showNotice('Customer pricing saved', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to save', 'error');
                    }
                }
            });
        },

        cancelCustomerEdit: function(userId) {
            var row = $('.customer-row[data-user="' + userId + '"]');
            row.find('.markup-display').show();
            row.find('.markup-edit').hide();
            row.find('.customer-actions .edit-customer-btn').show();
            row.find('.customer-actions .save-customer-btn, .customer-actions .cancel-customer-btn').hide();
        },

        updatePricePreview: function() {
            var productId = $('#previewProduct').val();
            var customerId = $('#previewCustomer').val();

            if (!productId) {
                $('#pricePreviewResult').html('<div class="text-muted">Select a product to preview pricing</div>');
                return;
            }

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_get_price_preview',
                    nonce: woosyncPricing.nonce,
                    product_id: productId,
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.renderPricePreview(response.data);
                    } else {
                        woosyncPricing.showNotice('Failed to load preview', 'error');
                    }
                }
            });
        },

        savePricingRules: function() {
            var minimumMargin = $('#minimumMargin').val();
            var maximumDiscount = $('#maximumDiscount').val();
            var clearanceMinimum = $('#clearanceMinimum').val();

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_save_pricing_rules',
                    nonce: woosyncPricing.nonce,
                    minimum_margin: minimumMargin,
                    maximum_discount: maximumDiscount,
                    clearance_minimum: clearanceMinimum
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.showNotice('Pricing rules saved', 'success');
                    } else {
                        woosyncPricing.showNotice('Failed to save rules', 'error');
                    }
                }
            });
        },

        exportPricing: function() {
            window.location.href = woosyncPricing.ajaxUrl + '?action=woosync_export_pricing&nonce=' + woosyncPricing.nonce;
        },

        importPricing: function(file) {
            if (!file) return;

            var formData = new FormData();
            formData.append('action', 'woosync_import_pricing');
            formData.append('nonce', woosyncPricing.nonce);
            formData.append('file', file);

            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.loadCustomerTable();
                        woosyncPricing.showNotice('Imported ' + response.data.count + ' customers', 'success');
                    } else {
                        woosyncPricing.showNotice('Import failed: ' + response.data, 'error');
                    }
                }
            });
        }
    };

    // Helper functions exposed globally for PHP to call
    window.woosyncPricing = {
        ajaxUrl: '',
        nonce: '',
        showNotice: function(message, type) {
            var alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-danger' : 'alert-warning');
            var html = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                       message +
                       '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                       '</div>';
            $('#pricingNotice').html(html);
            setTimeout(function() {
                $('#pricingNotice').html('');
            }, 5000);
        },
        renderSearchResults: function(results) {
            var html = '';
            if (results.length === 0) {
                html = '<div class="text-muted">No customers found</div>';
            } else {
                html = '<table class="table table-sm"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead><tbody>';
                $.each(results, function(i, customer) {
                    html += '<tr>';
                    html += '<td>' + customer.name + '</td>';
                    html += '<td>' + customer.email + '</td>';
                    html += '<td>' + customer.role + '</td>';
                    html += '<td><button type="button" class="btn btn-sm btn-primary add-customer-btn" data-user="' + customer.id + '">Add Pricing</button></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
            $('#customerSearchResults').html(html);
        },
        loadCustomerTable: function() {
            $.ajax({
                url: woosyncPricing.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_get_customer_pricing',
                    nonce: woosyncPricing.nonce
                },
                success: function(response) {
                    if (response.success) {
                        woosyncPricing.renderCustomerTable(response.data);
                    }
                }
            });
        },
        renderCustomerTable: function(customers) {
            var html = '';
            if (customers.length === 0) {
                html = '<tr><td colspan="5" class="text-center text-muted">No custom pricing configured</td></tr>';
            } else {
                $.each(customers, function(i, customer) {
                    html += '<tr class="customer-row" data-user="' + customer.id + '">';
                    html += '<td>' + customer.name + '</td>';
                    html += '<td>' + customer.email + '</td>';
                    html += '<td><span class="markup-display"><span class="markup-value">' + customer.markup + '%</span></span><span class="markup-edit" style="display:none;"></span></td>';
                    html += '<td><span class="tier-value">' + customer.tier + '</span></td>';
                    html += '<td><div class="customer-actions">';
                    html += '<button type="button" class="btn btn-sm btn-outline-primary edit-customer-btn" data-user="' + customer.id + '">Edit</button> ';
                    html += '<button type="button" class="btn btn-sm btn-success save-customer-btn" data-user="' + customer.id + '" style="display:none;">Save</button> ';
                    html += '<button type="button" class="btn btn-sm btn-secondary cancel-customer-btn" data-user="' + customer.id + '" style="display:none;">Cancel</button> ';
                    html += '<button type="button" class="btn btn-sm btn-outline-danger remove-customer-btn" data-user="' + customer.id + '">Remove</button>';
                    html += '</div></td></tr>';
                });
            }
            $('#customerPricingBody').html(html);
        },
        renderPricePreview: function(data) {
            var html = '<div class="price-preview-box">' +
                       '<div class="preview-row"><span class="preview-label">Supplier Price:</span><span class="preview-value">R' + data.supplier_price + '</span></div>' +
                       '<div class="preview-row"><span class="preview-label">Your Cost:</span><span class="preview-value">R' + data.your_cost + '</span></div>' +
                       '<div class="preview-divider"></div>' +
                       '<div class="preview-row highlight"><span class="preview-label">Customer Sees:</span><span class="preview-value">R' + data.customer_price + '</span></div>' +
                       '<div class="preview-row"><span class="preview-label">Your Margin:</span><span class="preview-value text-success">R' + data.your_margin + '</span></div>' +
                       '</div>';
            $('#pricePreviewResult').html(html);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        Pricing.init();
    });

    // Expose globally
    window.Pricing = Pricing;

})(jQuery);
