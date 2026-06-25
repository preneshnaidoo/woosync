/**
 * WooSync Product Optimizer JavaScript
 * SEO Scanner, Scoring, Batch Actions, and Schema Generation
 */
(function($) {
    'use strict';

    const ProductOptimizer = {
        scanResults: [],
        currentProduct: null,
        isScanning: false,

        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        bindEvents: function() {
            // Run scan button
            $('#runProductScan').on('click', $.proxy(this.runScan, this));
            
            // Quick fix buttons
            $(document).on('click', '.quick-fix-btn', $.proxy(this.applyQuickFix, this));
            
            // Batch action buttons
            $('#batchGenerateDescriptions').on('click', $.proxy(this.batchGenerateDescriptions, this));
            $('#batchOptimizeTitles').on('click', $.proxy(this.batchOptimizeTitles, this));
            $('#batchAddAltText').on('click', $.proxy(this.batchAddAltText, this));
            $('#batchSetBrands').on('click', $.proxy(this.batchSetBrands, this));
            $('#batchMapTaxonomy').on('click', $.proxy(this.batchMapTaxonomy, this));
            
            // Generate schema button
            $(document).on('click', '.generate-schema-btn', $.proxy(this.generateSchema, this));
            
            // Export CSV button
            $('#exportQualityReport').on('click', $.proxy(this.exportCSV, this));
            
            // Product row click
            $(document).on('click', '.product-scan-row', $.proxy(this.showProductDetails, this));
            
            // Select all checkbox
            $('#selectAllProducts').on('change', $.proxy(this.toggleSelectAll, this));
            
            // Apply selected fixes
            $('#applySelectedFixes').on('click', $.proxy(this.applySelectedFixes, this));
        },

        initTooltips: function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        },

        // ===== SEO PRODUCT SCANNER =====
        runScan: function() {
            if (this.isScanning) return;
            
            var $btn = $('#runProductScan');
            $btn.prop('disabled', true).html('⏳ Scanning...');
            this.isScanning = true;
            
            var data = {
                action: 'woosync_scan_products',
                nonce: woosyncData.nonce
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        ProductOptimizer.displayScanResults(response.data);
                    } else {
                        ProductOptimizer.showMessage('Scan failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    ProductOptimizer.showMessage('Scan failed: Network error', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('🔍 Run Full Scan');
                    ProductOptimizer.isScanning = false;
                });
        },

        displayScanResults: function(data) {
            this.scanResults = data.products || [];
            var overallScore = data.overall_score || 0;
            var scoreClass = overallScore >= 70 ? 'success' : (overallScore >= 40 ? 'warning' : 'danger');
            
            // Update stats
            $('#avgQualityScore').text(overallScore + '%');
            $('#avgQualityScore').removeClass('text-success text-warning text-danger')
                .addClass('text-' + scoreClass);
            
            $('#productsScanned').text(data.total_products || 0);
            $('#productsNeedingAttention').text(data.needs_attention || 0);
            $('#productsReadyForShopping').text(data.shopping_ready || 0);
            
            // Render product table
            var html = '';
            var categories = {};
            
            this.scanResults.forEach(function(product) {
                var scoreColor = product.score >= 70 ? 'success' : (product.score >= 40 ? 'warning' : 'danger');
                var scoreBadge = '<span class="badge bg-' + scoreColor + '">' + product.score + '%</span>';
                
                html += '<tr class="product-scan-row" data-product-id="' + product.id + '" style="cursor:pointer;">';
                html += '<td><input type="checkbox" class="product-checkbox" value="' + product.id + '"></td>';
                html += '<td><strong>' + product.name + '</strong><br><small class="text-muted">SKU: ' + product.sku + '</small></td>';
                html += '<td>' + scoreBadge + '</td>';
                html += '<td>' + ProductOptimizer.renderCheckmarks(product.checks) + '</td>';
                html += '<td><button class="btn btn-sm btn-outline-primary generate-schema-btn" data-product-id="' + product.id + '">📋 Schema</button></td>';
                html += '</tr>';
                
                // Group by category
                var cat = product.category || 'Uncategorized';
                if (!categories[cat]) categories[cat] = { total: 0, score: 0 };
                categories[cat].total++;
                categories[cat].score += product.score;
            });
            
            $('#scanResultsBody').html(html);
            $('#scanResultsTable').show();
            
            // Render category breakdown
            var catHtml = '';
            Object.keys(categories).forEach(function(cat) {
                var avgScore = Math.round(categories[cat].score / categories[cat].total);
                var catScoreColor = avgScore >= 70 ? 'success' : (avgScore >= 40 ? 'warning' : 'danger');
                catHtml += '<div class="col-md-4 mb-3">';
                catHtml += '<div class="card bg-dark">';
                catHtml += '<div class="card-body py-2">';
                catHtml += '<div class="d-flex justify-content-between align-items-center">';
                catHtml += '<span>' + cat + '</span>';
                catHtml += '<span class="badge bg-' + catScoreColor + '">' + avgScore + '%</span>';
                catHtml += '</div>';
                catHtml += '<small class="text-muted">' + categories[cat].total + ' products</small>';
                catHtml += '</div></div></div>';
            });
            $('#categoryBreakdown').html(catHtml);
            
            // Show score summary
            $('#scoreSummary').show();
            
            ProductOptimizer.showMessage('Scan complete! ' + (data.total_products || 0) + ' products analyzed.', 'success');
        },

        renderCheckmarks: function(checks) {
            var html = '<div class="d-flex flex-wrap gap-1">';
            
            var items = [
                { key: 'title', icon: '📝', label: 'Title' },
                { key: 'description', icon: '📄', label: 'Description' },
                { key: 'image', icon: '🖼️', label: 'Image' },
                { key: 'alt_text', icon: '🏷️', label: 'Alt Text' },
                { key: 'price', icon: '💰', label: 'Price' },
                { key: 'brand', icon: '🏷️', label: 'Brand' },
                { key: 'taxonomy', icon: '📁', label: 'Taxonomy' },
                { key: 'schema', icon: '📋', label: 'Schema' }
            ];
            
            items.forEach(function(item) {
                var status = checks[item.key] ? '✅' : '❌';
                var title = checks[item.key] ? item.label + ' OK' : item.label + ' Missing';
                html += '<span title="' + title + '">' + status + '</span>';
            });
            
            html += '</div>';
            return html;
        },

        // ===== QUICK FIX ACTIONS =====
        applyQuickFix: function(e) {
            var $btn = $(e.currentTarget);
            var productId = $btn.data('product-id');
            var fixType = $btn.data('fix-type');
            
            $btn.prop('disabled', true).html('⏳ Processing...');
            
            var data = {
                action: 'woosync_apply_quick_fix',
                nonce: woosyncData.nonce,
                product_id: productId,
                fix_type: fixType
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        ProductOptimizer.showMessage('Quick fix applied successfully!', 'success');
                        // Update the row score
                        if (response.data.new_score !== undefined) {
                            var $row = $btn.closest('tr');
                            var scoreColor = response.data.new_score >= 70 ? 'success' : (response.data.new_score >= 40 ? 'warning' : 'danger');
                            $row.find('.badge').removeClass('bg-success bg-warning bg-danger')
                                .addClass('bg-' + scoreColor)
                                .text(response.data.new_score + '%');
                        }
                    } else {
                        ProductOptimizer.showMessage('Quick fix failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    ProductOptimizer.showMessage('Quick fix failed: Network error', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html(ProductOptimizer.getFixButtonText(fixType));
                });
        },

        getFixButtonText: function(fixType) {
            var texts = {
                'generate_description': '✨ Generate Description',
                'optimize_title': '✂️ Optimize Title',
                'add_alt_text': '🏷️ Add Alt Text',
                'set_brand': '🏷️ Set Brand',
                'map_taxonomy': '📁 Map Taxonomy'
            };
            return texts[fixType] || 'Apply Fix';
        },

        // ===== BATCH ACTIONS =====
        batchGenerateDescriptions: function() {
            this.runBatchAction('batch_generate_descriptions', 'Generating descriptions...', 'Descriptions generated for %d products!');
        },

        batchOptimizeTitles: function() {
            this.runBatchAction('batch_optimize_titles', 'Optimizing titles...', 'Titles optimized for %d products!');
        },

        batchAddAltText: function() {
            this.runBatchAction('batch_add_alt_text', 'Adding alt text...', 'Alt text added to %d products!');
        },

        batchSetBrands: function() {
            this.runBatchAction('batch_set_brands', 'Setting brands...', 'Brands set for %d products!');
        },

        batchMapTaxonomy: function() {
            this.runBatchAction('batch_map_taxonomy', 'Mapping taxonomy...', 'Taxonomy mapped for %d products!');
        },

        runBatchAction: function(action, loadingText, successText) {
            var $btn = $('#' + action);
            if ($btn.length === 0) $btn = $(document).find('#' + action);
            
            $btn.prop('disabled', true).html('⏳ ' + loadingText);
            
            var selectedIds = ProductOptimizer.getSelectedProductIds();
            
            var data = {
                action: 'woosync_batch_action',
                nonce: woosyncData.nonce,
                batch_action: action,
                product_ids: selectedIds
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        var count = response.data.updated || 0;
                        ProductOptimizer.showMessage(successText.replace('%d', count), 'success');
                        // Re-run scan to update scores
                        setTimeout(function() { ProductOptimizer.runScan(); }, 1000);
                    } else {
                        ProductOptimizer.showMessage('Batch action failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    ProductOptimizer.showMessage('Batch action failed: Network error', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html(ProductOptimizer.getBatchButtonText(action));
                });
        },

        getBatchButtonText: function(action) {
            var texts = {
                'batch_generate_descriptions': '✨ Generate Descriptions',
                'batch_optimize_titles': '✂️ Optimize Titles',
                'batch_add_alt_text': '🏷️ Add Alt Text',
                'batch_set_brands': '🏷️ Set Brands',
                'batch_map_taxonomy': '📁 Map Taxonomy'
            };
            return texts[action] || 'Apply to Selected';
        },

        getSelectedProductIds: function() {
            var ids = [];
            $('.product-checkbox:checked').each(function() {
                ids.push($(this).val());
            });
            return ids;
        },

        toggleSelectAll: function(e) {
            var checked = $(e.currentTarget).prop('checked');
            $('.product-checkbox').prop('checked', checked);
        },

        applySelectedFixes: function() {
            var selectedFixes = [];
            $('.fix-checkbox:checked').each(function() {
                selectedFixes.push($(this).val());
            });
            
            if (selectedFixes.length === 0) {
                ProductOptimizer.showMessage('Please select at least one fix type.', 'warning');
                return;
            }
            
            var selectedIds = ProductOptimizer.getSelectedProductIds();
            if (selectedIds.length === 0) {
                ProductOptimizer.showMessage('Please select at least one product.', 'warning');
                return;
            }
            
            var $btn = $('#applySelectedFixes');
            $btn.prop('disabled', true).html('⏳ Processing...');
            
            var data = {
                action: 'woosync_batch_action',
                nonce: woosyncData.nonce,
                batch_action: 'batch_apply_fixes',
                product_ids: selectedIds,
                fix_types: selectedFixes
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        ProductOptimizer.showMessage('Selected fixes applied to ' + (response.data.updated || 0) + ' products!', 'success');
                        setTimeout(function() { ProductOptimizer.runScan(); }, 1000);
                    } else {
                        ProductOptimizer.showMessage('Failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    ProductOptimizer.showMessage('Network error', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('✅ Apply Selected Fixes');
                });
        },

        // ===== SCHEMA GENERATOR =====
        generateSchema: function(e) {
            var $btn = $(e.currentTarget);
            var productId = $btn.data('product-id');
            
            $btn.prop('disabled', true).html('⏳ Generating...');
            
            var data = {
                action: 'woosync_generate_schema',
                nonce: woosyncData.nonce,
                product_id: productId
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success && response.data.schema) {
                        ProductOptimizer.showSchemaModal(response.data);
                    } else {
                        ProductOptimizer.showMessage('Schema generation failed', 'error');
                    }
                })
                .fail(function() {
                    ProductOptimizer.showMessage('Schema generation failed: Network error', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('📋 Schema');
                });
        },

        showSchemaModal: function(data) {
            $('#schemaProductName').text(data.product_name);
            $('#schemaOutput').text(JSON.stringify(data.schema, null, 2));
            $('#schemaModal').modal('show');
        },

        // ===== EXPORT CSV =====
        exportCSV: function() {
            if (this.scanResults.length === 0) {
                ProductOptimizer.showMessage('Run a scan first before exporting.', 'warning');
                return;
            }
            
            var csv = 'SKU,Product Name,Score,Title OK,Description OK,Image OK,Alt Text OK,Price OK,Brand OK,Taxonomy OK,Schema OK\n';
            
            this.scanResults.forEach(function(product) {
                var checks = product.checks || {};
                csv += '"' + product.sku + '",';
                csv += '"' + product.name.replace(/"/g, '""') + '",';
                csv += product.score + ',';
                csv += (checks.title ? 'Yes' : 'No') + ',';
                csv += (checks.description ? 'Yes' : 'No') + ',';
                csv += (checks.image ? 'Yes' : 'No') + ',';
                csv += (checks.alt_text ? 'Yes' : 'No') + ',';
                csv += (checks.price ? 'Yes' : 'No') + ',';
                csv += (checks.brand ? 'Yes' : 'No') + ',';
                csv += (checks.taxonomy ? 'Yes' : 'No') + ',';
                csv += (checks.schema ? 'Yes' : 'No') + '\n';
            });
            
            var blob = new Blob([csv], { type: 'text/csv' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'product-quality-report-' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            URL.revokeObjectURL(url);
            
            ProductOptimizer.showMessage('CSV exported successfully!', 'success');
        },

        // ===== PRODUCT DETAILS =====
        showProductDetails: function(e) {
            var productId = $(e.currentTarget).data('product-id');
            var product = this.scanResults.find(function(p) { return p.id == productId; });
            
            if (!product) return;
            
            this.currentProduct = product;
            
            $('#detailProductName').text(product.name);
            $('#detailProductSku').text('SKU: ' + product.sku);
            $('#detailScore').text(product.score + '%');
            $('#detailScore').removeClass('text-success text-warning text-danger')
                .addClass('text-' + (product.score >= 70 ? 'success' : (product.score >= 40 ? 'warning' : 'danger')));
            
            // Render check details
            var checksHtml = '';
            var checks = product.checks || {};
            
            var checkItems = [
                { key: 'title', label: 'Product Title', detail: product.title_length ? product.title_length + ' chars' : 'Missing' },
                { key: 'description', label: 'Product Description', detail: product.description_length ? product.description_length + ' chars' : 'Missing' },
                { key: 'image', label: 'Product Image', detail: product.image_size || 'Missing' },
                { key: 'alt_text', label: 'Alt Text', detail: product.alt_text || 'Missing' },
                { key: 'price', label: 'Price', detail: product.price || 'Not set' },
                { key: 'brand', label: 'Brand', detail: product.brand || 'Not set' },
                { key: 'taxonomy', label: 'Google Taxonomy', detail: product.taxonomy || 'Not mapped' },
                { key: 'schema', label: 'Schema Markup', detail: product.has_schema ? 'Present' : 'Missing' }
            ];
            
            checkItems.forEach(function(item) {
                var status = checks[item.key] ? '✅' : '❌';
                var statusClass = checks[item.key] ? 'text-success' : 'text-danger';
                checksHtml += '<div class="d-flex justify-content-between py-2 border-bottom border-secondary">';
                checksHtml += '<span>' + status + ' ' + item.label + '</span>';
                checksHtml += '<span class="' + statusClass + '"><small>' + item.detail + '</small></span>';
                checksHtml += '</div>';
            });
            
            $('#productCheckDetails').html(checksHtml);
            
            // Render quick fix buttons
            var fixHtml = '';
            if (!checks.title) fixHtml += '<button class="btn btn-sm btn-outline-primary quick-fix-btn me-2 mb-2" data-product-id="' + product.id + '" data-fix-type="optimize_title">✂️ Optimize Title</button>';
            if (!checks.description) fixHtml += '<button class="btn btn-sm btn-outline-primary quick-fix-btn me-2 mb-2" data-product-id="' + product.id + '" data-fix-type="generate_description">✨ Generate Description</button>';
            if (!checks.alt_text) fixHtml += '<button class="btn btn-sm btn-outline-primary quick-fix-btn me-2 mb-2" data-product-id="' + product.id + '" data-fix-type="add_alt_text">🏷️ Add Alt Text</button>';
            if (!checks.brand) fixHtml += '<button class="btn btn-sm btn-outline-primary quick-fix-btn me-2 mb-2" data-product-id="' + product.id + '" data-fix-type="set_brand">🏷️ Set Brand</button>';
            if (!checks.taxonomy) fixHtml += '<button class="btn btn-sm btn-outline-primary quick-fix-btn me-2 mb-2" data-product-id="' + product.id + '" data-fix-type="map_taxonomy">📁 Map Taxonomy</button>';
            fixHtml += '<button class="btn btn-sm btn-success mb-2 generate-schema-btn" data-product-id="' + product.id + '">📋 Generate Schema</button>';
            
            $('#productQuickFixes').html(fixHtml);
            
            $('#productDetailsModal').modal('show');
        },

        // ===== UTILITY =====
        showMessage: function(message, type) {
            var alertClass = {
                success: 'alert-success',
                error: 'alert-danger',
                warning: 'alert-warning',
                info: 'alert-info'
            }[type] || 'alert-info';
            
            var $alert = $('<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">')
                .html('<strong>' + message + '</strong>')
                .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
            
            $('#optimizerAlerts').html($alert);
            
            setTimeout(function() {
                $alert.alert('close');
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#productOptimizerTab').length) {
            ProductOptimizer.init();
        }
    });

})(jQuery);
