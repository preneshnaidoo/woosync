/**
 * WooSync v3.3 - Connect & Map Page with Tier Pricing
 * Two-column layout with live preview, tier display, and margin calculator
 */

jQuery(function($) {
    // Test Connection Button
    $('#testConnectionBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Testing...');
        
        $('#connectionStatus').removeClass('alert-success alert-danger').hide();
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_test_connection',
                username: $('#apiUsername').val(),
                password: $('#apiPassword').val(),
                customer_code: $('#vendorCode').val(),
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#connectionStatus').addClass('alert-success').html('✅ ' + response.data.message).show();
                } else {
                    $('#connectionStatus').addClass('alert-danger').html('❌ ' + response.data).show();
                }
            },
            error: function() {
                $('#connectionStatus').addClass('alert-danger').html('❌ Connection error').show();
            },
            complete: function() {
                btn.prop('disabled', false).html('🔗 Test Connection');
            }
        });
    });

    // Save Credentials Button
    $('#saveCredentialsBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Saving...');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_save_credentials_simple',
                username: $('#apiUsername').val(),
                password: $('#apiPassword').val(),
                customer_code: $('#vendorCode').val(),
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Credentials saved successfully!');
                    location.reload();
                } else {
                    alert('❌ Failed to save: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error saving credentials');
            },
            complete: function() {
                btn.prop('disabled', false).html('💾 Save Credentials');
            }
        });
    });

    // Refresh Tier Status Button
    $('#refreshTierBtn').on('click', function() {
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
                btn.prop('disabled', false).html('🔄 Refresh Tier Status');
            }
        });
    });

    // Auto-Detect Fields Button
    $('#autoDetectBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Detecting...');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_auto_detect_fields',
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateMappingFields(response.data.mapping);
                    showDetectionResults(response.data);
                } else {
                    alert('❌ Failed to detect fields: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error connecting to server');
            },
            complete: function() {
                btn.prop('disabled', false).html('🔍 Auto-Detect');
            }
        });
    });

    // Save Mapping Button
    $('#saveMappingBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Saving...');
        
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
                } else {
                    alert('❌ Failed to save mapping');
                }
            },
            error: function() {
                alert('❌ Error saving mapping');
            },
            complete: function() {
                btn.prop('disabled', false).html('💾 Save Mapping');
            }
        });
    });

    // Product Search with Fuzzy Matching
    let searchTimeout;
    $('#productSearch').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();
        
        if (query.length < 2) {
            $('#searchResults').hide();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300);
    });

    $('#searchBtn').on('click', function() {
        const query = $('#productSearch').val();
        if (query.length >= 1) {
            performSearch(query, true);
        }
    });

    $('#productSearch').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const query = $(this).val();
            if (query.length >= 1) {
                performSearch(query, true);
            }
        }
    });

    function performSearch(query, showAll = false) {
        $('#searchResults').html('<div class="list-group-item text-center py-3"><span class="spinner-border spinner-border-sm"></span> Searching...</div>').show();
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_search_products',
                query: query,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success && response.data.products.length > 0) {
                    let html = '';
                    response.data.products.forEach(function(product) {
                        html += '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center" data-product="' + product.sku + '">';
                        if (product.image) {
                            html += '<img src="' + product.image + '" width="40" height="40" class="me-2 rounded" style="object-fit: cover;">';
                        } else {
                            html += '<div class="me-2" style="width:40px;height:40px;background:#eee;display:flex;align-items:center;justify-content:center;">📦</div>';
                        }
                        html += '<div class="flex-grow-1">';
                        html += '<div class="fw-bold">' + product.name + '</div>';
                        html += '<small class="text-muted">SKU: ' + product.sku + ' | Price: R' + product.price + '</small>';
                        html += '</div>';
                        html += '</button>';
                    });
                    $('#searchResults').html(html).show();
                    
                    // Click handler for search results
                    $('#searchResults button').on('click', function() {
                        const sku = $(this).data('product');
                        loadProductPreview(sku);
                        $('#searchResults').hide();
                    });
                } else {
                    $('#searchResults').html('<div class="list-group-item text-center text-muted py-3">No products found for "' + query + '"</div>').show();
                }
            },
            error: function() {
                $('#searchResults').html('<div class="list-group-item text-center text-danger py-3">Search error</div>').show();
            }
        });
    }

    function loadProductPreview(sku) {
        $('#productPreview').html('<div class="text-center py-5"><span class="spinner-border spinner-border-sm"></span> Loading preview...</div>');
        $('#tierPricingBreakdown').hide();
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_get_product_preview_tier',
                product_code: sku,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showProductPreview(response.data);
                    $('#syncThisProductBtn').prop('disabled', false).data('sku', sku);
                } else {
                    $('#productPreview').html('<div class="text-center text-danger py-5">Failed to load preview</div>');
                }
            },
            error: function() {
                $('#productPreview').html('<div class="text-center text-danger py-5">Error loading preview</div>');
            }
        });
    }

    function showProductPreview(product) {
        let html = '<div class="row">';
        
        // Image
        html += '<div class="col-md-4 text-center">';
        if (product.image) {
            html += '<img src="' + product.image + '" class="img-fluid rounded mb-2" style="max-height: 200px;">';
        } else {
            html += '<div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height: 200px;"><span style="font-size: 48px;">📦</span></div>';
        }
        html += '</div>';
        
        // Product Details
        html += '<div class="col-md-8">';
        html += '<h5 class="mb-2">' + product.name + '</h5>';
        html += '<p class="mb-1"><strong>SKU:</strong> ' + product.sku + '</p>';
        html += '<p class="mb-1"><strong>Price:</strong> <span class="text-success fw-bold">R' + product.price + '</span></p>';
        html += '<p class="mb-1"><strong>Category:</strong> ' + (product.category || 'Uncategorized') + '</p>';
        if (product.brand) {
            html += '<p class="mb-1"><strong>Brand:</strong> ' + product.brand + '</p>';
        }
        if (product.colour) {
            html += '<p class="mb-1"><strong>Colour:</strong> ' + product.colour + '</p>';
        }
        html += '<p class="mb-1"><strong>Stock:</strong> ' + product.stock + '</p>';
        
        // Tier indicator
        if (product.tier && product.tier !== 'Standard') {
            html += '<p class="mb-1"><strong>Tier:</strong> <span class="tier-badge tier-' + product.tier.toLowerCase() + '">' + product.tier + '</span></p>';
        }
        
        html += '</div>';
        
        // Description
        if (product.description) {
            html += '<div class="col-12 mt-3">';
            html += '<hr>';
            html += '<small class="text-muted">' + product.description.substring(0, 200) + (product.description.length > 200 ? '...' : '') + '</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $('#productPreview').html(html);
        
        // Show tier pricing breakdown if available
        if (product.tier_price !== undefined) {
            showTierPricingBreakdown(product);
        }
    }

    function showTierPricingBreakdown(product) {
        const tierPrice = parseFloat(product.tier_price) || 0;
        const markupPercent = parseFloat(product.markup_percent) || 30;
        const markupAmount = parseFloat(product.markup_amount) || 0;
        const customerPrice = parseFloat(product.customer_price) || 0;
        const margin = parseFloat(product.your_margin) || 0;
        
        $('#tierPriceDisplay').text('R' + tierPrice.toFixed(2));
        $('#markupPercentDisplay').text(markupPercent);
        $('#markupAmountDisplay').text('+R' + markupAmount.toFixed(2));
        $('#customerPriceDisplay').text('R' + customerPrice.toFixed(2));
        $('#marginDisplay').text('R' + margin.toFixed(2));
        
        $('#tierPricingBreakdown').show();
    }

    // Sync This Product Button
    $('#syncThisProductBtn').on('click', function() {
        const btn = $(this);
        const sku = btn.data('sku');
        
        if (!sku) return;
        
        btn.prop('disabled', true).html('⏳ Syncing...');
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_sync_single_product',
                product_code: sku,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Product synced successfully!');
                } else {
                    alert('❌ ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error syncing product');
            },
            complete: function() {
                btn.prop('disabled', false).html('📦 Sync This Product');
            }
        });
    });

    // Sync All Products Button
    $('#syncAllProductsBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('⏳ Syncing All...');
        
        if (confirm('Start syncing all products? This may take a while.')) {
            $.ajax({
                url: amrodSyncData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amrod_sync_batch',
                    offset: 0,
                    batch_size: 200,
                    nonce: amrodSyncData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        alert('✅ Sync complete! ' + data.processed_total + ' products synced.');
                        location.reload();
                    } else {
                        alert('❌ Sync failed: ' + response.data.error);
                        btn.prop('disabled', false).html('▶️ Sync All Products');
                    }
                },
                error: function() {
                    alert('❌ Sync error');
                    btn.prop('disabled', false).html('▶️ Sync All Products');
                }
            });
        } else {
            btn.prop('disabled', false).html('▶️ Sync All Products');
        }
    });

    // Endpoint Toggle
    $('.endpoint-toggle').on('change', function() {
        const endpoint = $(this).data('endpoint');
        const enabled = $(this).is(':checked') ? 1 : 0;
        
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_save_endpoint',
                endpoint: endpoint,
                enabled: enabled,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Endpoint updated');
                }
            }
        });
    });

    // Helper Functions
    function getFormMapping() {
        const mapping = {};
        $('input[name^="mapping["]').each(function() {
            const name = $(this).attr('name');
            const field = name.match(/mapping\[(\w+)\]/);
            if (field) {
                const value = $(this).val();
                if (value) {
                    mapping[field[1]] = value;
                }
            }
        });
        return mapping;
    }

    function populateMappingFields(mapping) {
        for (const [wcKey, amrodField] of Object.entries(mapping)) {
            const $input = $('input[name="mapping[' + wcKey + ']"]');
            if ($input.length) {
                $input.val(amrodField);
            }
        }
    }

    function showDetectionResults(data) {
        const fieldLabels = {
            sku: 'SKU',
            name: 'Product Name',
            price: 'Price',
            sale_price: 'Sale Price',
            description: 'Description',
            categories: 'Categories',
            brand: 'Brand',
            colour: 'Colour',
            stock: 'Stock',
            image: 'Image'
        };

        let html = '<div class="alert alert-success mt-3"><strong>🔍 Detection Results</strong><br>';
        html += '<small>Found ' + data.fields.length + ' fields. Detected mappings:</small><br><br>';
        
        let detected = [];
        for (const [key, value] of Object.entries(data.mapping)) {
            detected.push('<code>' + value + '</code> → ' + (fieldLabels[key] || key));
        }
        
        html += detected.join('<br>');
        html += '</div>';
        
        $('.card').first().after(html);
        
        setTimeout(function() {
            $('.alert-success').filter(function() {
                return $(this).text().indexOf('Detection Results') !== -1;
            }).fadeOut();
        }, 5000);
    }

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});
