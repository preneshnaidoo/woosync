/**
 * WooSync v3.2 - Tiered Pricing System
 * Handles role tiers, customer search, inline editing, and price preview
 */

jQuery(function($) {
    // ===== ROLE TIERS INLINE EDITING =====
    $(document).on('click', '.markup-value', function(e) {
        e.preventDefault();
        var $td = $(this);
        var currentValue = $td.data('value') || 0;
        var roleSlug = $td.data('role');
        
        $td.html('<input type="number" class="form-control form-control-sm markup-input" value="' + currentValue + '" min="0" max="500" step="1" style="width: 80px;">');
        $td.find('input').focus().select();
    });

    $(document).on('blur', '.markup-input', function() {
        saveRoleMarkup($(this));
    });

    $(document).on('keypress', '.markup-input', function(e) {
        if (e.which === 13) {
            saveRoleMarkup($(this));
        }
    });

    function saveRoleMarkup($input) {
        var newValue = parseFloat($input.val()) || 0;
        var roleSlug = $input.closest('td').data('role');
        var $td = $input.closest('td');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_save_role_markup',
                nonce: amrodSyncData.nonce,
                role: roleSlug,
                markup: newValue
            },
            success: function(response) {
                if (response.success) {
                    $td.html('<span class="markup-value" data-value="' + newValue + '" data-role="' + roleSlug + '">' + newValue + '%</span>');
                    showToast('Role markup updated!', 'success');
                } else {
                    showToast('Failed to save: ' + response.data, 'error');
                }
            },
            error: function() {
                showToast('AJAX Error', 'error');
            }
        });
    }

    // ===== TOGGLE ROLE MARKUP ENABLED/DISABLED =====
    $(document).on('change', '.role-markup-toggle', function() {
        var roleSlug = $(this).data('role');
        var enabled = $(this).prop('checked') ? 1 : 0;
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_toggle_role_markup',
                nonce: amrodSyncData.nonce,
                role: roleSlug,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    showToast('Role status updated!', 'success');
                }
            }
        });
    });

    // ===== APPLY BLANKET TO ALL =====
    $('#applyBlanketAll').on('click', function(e) {
        e.preventDefault();
        var blanketMarkup = $('#defaultMarkup').val() || 30;
        
        if (!confirm('Apply ' + blanketMarkup + '% markup to all roles?')) return;
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_apply_blanket_to_all',
                nonce: amrodSyncData.nonce,
                markup: blanketMarkup
            },
            success: function(response) {
                if (response.success) {
                    showToast('Blanket markup applied to all roles!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Failed: ' + response.data, 'error');
                }
            }
        });
    });

    // ===== CUSTOMER SEARCH =====
    var customerSearchTimeout;
    $('#customerSearch').on('input', function() {
        var query = $(this).val();
        
        clearTimeout(customerSearchTimeout);
        if (query.length < 2) {
            $('#customerSearchResults').hide();
            return;
        }
        
        customerSearchTimeout = setTimeout(function() {
            searchCustomers(query);
        }, 300);
    });

    function searchCustomers(query) {
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_search_customers',
                nonce: amrodSyncData.nonce,
                query: query
            },
            success: function(response) {
                if (response.success) {
                    displayCustomerResults(response.data.customers);
                }
            }
        });
    }

    function displayCustomerResults(customers) {
        var $results = $('#customerSearchResults');
        if (customers.length === 0) {
            $results.html('<div class="list-group-item text-muted">No customers found</div>').show();
            return;
        }
        
        var html = '';
        customers.forEach(function(customer) {
            html += '<a href="#" class="list-group-item list-group-item-action customer-result" data-id="' + customer.id + '">' +
                '<strong>' + customer.name + '</strong> ' +
                '<span class="text-muted">(' + customer.email + ')</span> ' +
                '<span class="badge bg-' + (customer.markup > 0 ? 'success' : 'secondary') + ' float-end">' + customer.markup + '%</span>' +
            '</a>';
        });
        
        $results.html(html).show();
    }

    // ===== SELECT CUSTOMER FOR EDITING =====
    $(document).on('click', '.customer-result', function(e) {
        e.preventDefault();
        var customerId = $(this).data('id');
        var customerName = $(this).find('strong').text();
        
        $('#selectedCustomerId').val(customerId);
        $('#selectedCustomerName').text(customerName);
        $('#customerSearchResults').hide();
        $('#customerSearch').val('');
        
        // Load current markup for this customer
        loadCustomerMarkup(customerId);
    });

    function loadCustomerMarkup(customerId) {
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_get_customer_markup',
                nonce: amrodSyncData.nonce,
                customer_id: customerId
            },
            success: function(response) {
                if (response.success) {
                    $('#customerMarkup').val(response.data.markup);
                    $('#customerTier').val(response.data.tier || 'none');
                }
            }
        });
    }

    // ===== SAVE CUSTOMER MARKUP =====
    $('#saveCustomerMarkup').on('click', function() {
        var customerId = $('#selectedCustomerId').val();
        var markup = $('#customerMarkup').val();
        var tier = $('#customerTier').val();
        
        if (!customerId) {
            showToast('Please select a customer first', 'warning');
            return;
        }
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_save_customer_markup',
                nonce: amrodSyncData.nonce,
                customer_id: customerId,
                markup: markup,
                tier: tier
            },
            success: function(response) {
                if (response.success) {
                    showToast('Customer markup saved!', 'success');
                } else {
                    showToast('Failed: ' + response.data, 'error');
                }
            }
        });
    });

    // ===== BULK SELECT CUSTOMERS =====
    $(document).on('change', '.customer-checkbox', function() {
        var selected = [];
        $('.customer-checkbox:checked').each(function() {
            selected.push($(this).val());
        });
        $('#bulkSelectedCustomers').val(JSON.stringify(selected));
        $('#bulkCount').text(selected.length);
    });

    // ===== BULK UPDATE MARKUP =====
    $('#bulkUpdateMarkup').on('click', function() {
        var selected = JSON.parse($('#bulkSelectedCustomers').val() || '[]');
        var markup = $('#bulkMarkup').val();
        
        if (selected.length === 0) {
            showToast('Please select customers first', 'warning');
            return;
        }
        
        if (!markup) {
            showToast('Please enter a markup percentage', 'warning');
            return;
        }
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_bulk_update_customers',
                nonce: amrodSyncData.nonce,
                customer_ids: selected,
                markup: markup
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.updated + ' customers updated!', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            }
        });
    });

    // ===== IMPORT CUSTOMERS FROM CSV =====
    $('#importCustomersBtn').on('click', function() {
        $('#importModal').modal('show');
    });

    $('#importCustomersForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast(response.data.imported + ' customers imported!', 'success');
                    $('#importModal').modal('hide');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Import failed: ' + response.data, 'error');
                }
            }
        });
    });

    // ===== PRICE PREVIEW =====
    $('#previewProduct, #previewCustomer, #previewRole').on('change', function() {
        updatePricePreview();
    });

    function updatePricePreview() {
        var productId = $('#previewProduct').val();
        var customerId = $('#previewCustomer').val();
        var roleSlug = $('#previewRole').val();
        
        if (!productId) {
            $('#pricePreviewResult').html('<div class="text-muted">Select a product to preview pricing</div>');
            return;
        }
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_calculate_price',
                nonce: amrodSyncData.nonce,
                product_id: productId,
                customer_id: customerId || '',
                role: roleSlug || ''
            },
            success: function(response) {
                if (response.success) {
                    displayPricePreview(response.data);
                }
            }
        });
    }

    function displayPricePreview(data) {
        var html = '<div class="row text-center">';
        html += '<div class="col-md-3"><div class="p-3 bg-light rounded"><small class="text-muted">Supplier Price</small><h4 class="mb-0">R' + data.supplier_price.toFixed(2) + '</h4></div></div>';
        html += '<div class="col-md-3"><div class="p-3 bg-light rounded"><small class="text-muted">Your Cost</small><h4 class="mb-0">R' + data.base_price.toFixed(2) + '</h4></div></div>';
        html += '<div class="col-md-3"><div class="p-3 bg-primary text-white rounded"><small>Customer Sees</small><h4 class="mb-0">R' + data.display_price.toFixed(2) + '</h4></div></div>';
        html += '<div class="col-md-3"><div class="p-3 bg-success text-white rounded"><small>Profit/Unit</small><h4 class="mb-0">R' + data.profit.toFixed(2) + '</h4><small>' + data.margin_percent + '% margin</small></div></div>';
        html += '</div>';
        html += '<div class="mt-3 text-center"><span class="badge bg-secondary">Markup: ' + data.markup_percent + '%</span></div>';
        
        $('#pricePreviewResult').html(html);
    }

    // ===== TOGGLE TIERED PRICING =====
    $('#tieredPricingEnabled').on('change', function() {
        var enabled = $(this).prop('checked') ? 1 : 0;
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_toggle_tiered_pricing',
                nonce: amrodSyncData.nonce,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    showToast('Tiered pricing ' + (enabled ? 'enabled' : 'disabled') + '!', 'success');
                }
            }
        });
    });

    // ===== SAVE PRICING RULES =====
    $('#savePricingRules').on('click', function() {
        var rules = {
            minimum_margin: $('#minimumMargin').val(),
            maximum_discount: $('#maximumDiscount').val(),
            clearance_minimum: $('#clearanceMinimum').val()
        };
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woosync_save_pricing_rules',
                nonce: amrodSyncData.nonce,
                rules: rules
            },
            success: function(response) {
                if (response.success) {
                    showToast('Pricing rules saved!', 'success');
                }
            }
        });
    });

    // ===== TOAST NOTIFICATIONS =====
    function showToast(message, type) {
        var toastClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
        var html = '<div class="toast align-items-center text-white ' + toastClass + ' border-0" role="alert" aria-live="assertive" aria-atomic="true" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">' +
            '<div class="d-flex"><div class="toast-body">' + message + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
        
        var $toast = $(html);
        $('body').append($toast);
        var bsToast = new bootstrap.Toast($toast);
        bsToast.show();
        $toast.on('hidden.bs.toast', function() { $(this).remove(); });
    }

    // ===== SEARCH PRODUCTS FOR PREVIEW =====
    var productSearchTimeout;
    $('#productSearch').on('input', function() {
        var query = $(this).val();
        
        clearTimeout(productSearchTimeout);
        if (query.length < 2) return;
        
        productSearchTimeout = setTimeout(function() {
            $.ajax({
                url: amrodSyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woosync_search_products_for_preview',
                    nonce: amrodSyncData.nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success) {
                        displayProductResults(response.data.products);
                    }
                }
            });
        }, 300);
    });

    function displayProductResults(products) {
        var $results = $('#productSearchResults');
        if (products.length === 0) {
            $results.html('<div class="list-group-item text-muted">No products found</div>').show();
            return;
        }
        
        var html = '';
        products.slice(0, 10).forEach(function(product) {
            html += '<a href="#" class="list-group-item list-group-item-action product-result" data-id="' + product.id + '">' +
                '<strong>' + product.name + '</strong> ' +
                '<span class="text-muted">(SKU: ' + product.sku + ')</span> ' +
                '<span class="badge bg-info float-end">R' + product.price + '</span>' +
            '</a>';
        });
        
        $results.html(html).show();
    }

    $(document).on('click', '.product-result', function(e) {
        e.preventDefault();
        var productId = $(this).data('id');
        var productName = $(this).find('strong').text();
        
        $('#previewProduct').val(productId);
        $('#productSearch').val(productName);
        $('#productSearchResults').hide();
        updatePricePreview();
    });
});
