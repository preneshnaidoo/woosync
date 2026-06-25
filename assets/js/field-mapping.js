/**
 * Amrod Sync v3.0 - Field Mapping Manager
 * Enhanced with marketing field detection and tabbed interface support.
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
                    const mapping = response.data.mapping;
                    const sample = response.data.sample_product;
                    showDetectionResultsField(fields, mapping, sample);
                    populateMappingFields(mapping, sample);
                } else {
                    alert('❌ Failed to detect fields: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error connecting to server');
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
                } else {
                    alert('❌ Failed to test mapping: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error connecting to server');
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
                } else {
                    alert('❌ Failed to save mapping');
                }
            },
            error: function() {
                alert('❌ Error connecting to server');
            }
        });

        e.preventDefault();
    });

    function getFormMapping() {
        const mapping = {};
        $('input[name^="mapping["]').each(function() {
            const name = $(this).attr('name');
            const field = name.match(/mapping\[(\w+)\]/)[1];
            const value = $(this).val();
            if (value) {
                mapping[field] = value;
            }
        });
        return mapping;
    }

    function showDetectionResultsField(fields, mapping, sample) {
        // Build field category breakdown
        const categories = {
            core: ['sku', 'name', 'price', 'sale_price', 'description', 'short_description', 'stock', 'image', 'images'],
            categories: ['categories'],
            attributes: ['brand', 'colour', 'size'],
            marketing: ['clearance', 'deal_of_day', 'banner_image', 'catalog_pdf', 'special_message', 'sort_order']
        };

        const fieldLabels = {
            sku: 'SKU',
            name: 'Product Name',
            price: 'Regular Price',
            sale_price: 'Sale Price',
            description: 'Description',
            short_description: 'Short Description',
            stock: 'Stock Quantity',
            image: 'Main Image',
            images: 'Image Gallery',
            categories: 'Categories',
            brand: 'Brand',
            colour: 'Colour',
            size: 'Size',
            clearance: 'Clearance Flag',
            deal_of_day: 'Deal of Day',
            banner_image: 'Banner Image',
            catalog_pdf: 'Catalog PDF',
            special_message: 'Special Message',
            sort_order: 'Sort Order'
        };

        let html = '<div class="alert alert-success"><strong>🔍 Detection Results</strong><br>';
        html += '<small>Found ' + fields.length + ' fields in Amrod API</small><br><br>';

        // Group detected fields by category
        for (const [cat, fields_list] of Object.entries(categories)) {
            const detected = fields_list.filter(f => mapping[f]).map(f => {
                const value = sample && mapping[f] ? sample[mapping[f]] : '';
                const displayValue = typeof value === 'object' ? JSON.stringify(value) : String(value).substring(0, 50);
                return '<code>' + mapping[f] + '</code> → ' + fieldLabels[f] + (displayValue ? ' <small>("' + displayValue + '")</small>' : '');
            });

            if (detected.length > 0) {
                const catIcons = { core: '📦', categories: '📂', attributes: '🏷️', marketing: '📣' };
                const catLabels = { core: 'Core Fields', categories: 'Categories', attributes: 'Attributes', marketing: 'Marketing' };
                html += '<strong>' + catIcons[cat] + ' ' + catLabels[cat] + ':</strong><br>';
                html += detected.join('<br>') + '<br><br>';
            }
        }

        html += '</div>';
        
        // Remove old results and add new
        $('#mappingTable').prev('.alert-success').remove();
        $('#mappingTable').before(html);
    }

    function populateMappingFields(mapping, sample) {
        for (const [wcKey, amrodField] of Object.entries(mapping)) {
            const $input = $('input[name="mapping[' + wcKey + ']"]');
            if ($input.length) {
                $input.val(amrodField);
                
                // Show sample value in the last column
                const $row = $input.closest('tr');
                if (sample && sample[amrodField] !== undefined) {
                    let value = sample[amrodField];
                    if (typeof value === 'object') {
                        value = JSON.stringify(value);
                    } else {
                        value = String(value).substring(0, 80);
                    }
                    $row.find('td:last').html('<small class="text-success">' + value + '</small>');
                }
            }
        }
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
