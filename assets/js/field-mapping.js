/**
 * WooSync Field Mapping JavaScript
 */
(function($) {
    'use strict';

    const FieldMapping = {
        detectedFields: [],
        sampleData: null,
        
        init: function() {
            this.bindEvents();
            this.initApiFieldsList();
        },
        
        bindEvents: function() {
            // Auto-detect button
            $('#autoDetectMappingBtn').on('click', $.proxy(this.autoDetectFields, this));
            
            // Test mapping button
            $('#testMappingBtn').on('click', $.proxy(this.testMapping, this));
            
            // Reset mapping button
            $('#resetMappingBtn').on('click', $.proxy(this.resetMapping, this));
            
            // API field input changes
            $(document).on('change', '.api-field-input', $.proxy(this.updateConfidence, this));
        },
        
        initApiFieldsList: function() {
            // Pre-populate datalist with template fields
            const templates = window.woosyncVendorTemplates || {};
            const activeVendor = window.woosyncActiveVendor || 'amrod';
            const template = templates[activeVendor] || templates.amrod;
            
            if (template && template.detected_fields) {
                template.detected_fields.forEach(function(field) {
                    $('#api-fields-list').append(
                        $('<option>').attr('value', field)
                    );
                });
            }
        },
        
        autoDetectFields: function() {
            const $btn = $('#autoDetectMappingBtn');
            $btn.prop('disabled', true).html('⏳ Detecting...');
            
            const data = {
                action: 'woosync_auto_detect_fields',
                nonce: woosyncData.nonce
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        FieldMapping.handleAutoDetectSuccess(response.data);
                    } else {
                        alert('Auto-detect failed: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(function() {
                    alert('Auto-detect failed: Network error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('🔍 Auto-Detect from API');
                });
        },
        
        handleAutoDetectSuccess: function(data) {
            this.detectedFields = data.api_fields || [];
            this.sampleData = data.sample || {};
            const mappings = data.mappings || {};
            
            // Update table rows with detected mappings
            $('#mappingTable tbody tr').each(function() {
                const $row = $(this);
                const wcKey = $row.data('wc-field');
                const mapping = mappings[wcKey];
                
                if (mapping) {
                    $row.find('.api-field-input').val(mapping.api_field);
                    $row.find('.badge').removeClass('bg-success bg-warning bg-danger bg-secondary')
                        .addClass('bg-' + (mapping.confidence === 'high' ? 'success' : mapping.confidence === 'medium' ? 'warning' : 'danger'))
                        .html(FieldMapping.getConfidenceIcon(mapping.confidence) + ' ' + FieldMapping.getConfidenceLabel(mapping.confidence));
                    
                    // Update sample value
                    const sampleValue = FieldMapping.getSampleValue(FieldMapping.sampleData, mapping.api_field);
                    $row.find('.sample-value').text(sampleValue);
                }
            });
            
            // Show success message
            FieldMapping.showMessage('Auto-detection complete! Review the mappings and save.', 'success');
        },
        
        getConfidenceIcon: function(confidence) {
            const icons = { high: '✅', medium: '⚠️', low: '❌' };
            return icons[confidence] || '❌';
        },
        
        getConfidenceLabel: function(confidence) {
            const labels = { high: 'High', medium: 'Medium', low: 'Low' };
            return labels[confidence] || 'Low';
        },
        
        getSampleValue: function(sample, apiField) {
            if (!sample || !apiField) return '-';
            
            const keys = apiField.split('.');
            let value = sample;
            
            for (const key of keys) {
                if (value && typeof value === 'object' && key in value) {
                    value = value[key];
                } else {
                    return '-';
                }
            }
            
            if (Array.isArray(value)) {
                return value.length > 0 ? (typeof value[0] === 'string' ? value[0] : JSON.stringify(value[0]).substring(0, 30)) : '-';
            }
            if (typeof value === 'object') {
                return JSON.stringify(value).substring(0, 30) + '...';
            }
            return String(value).substring(0, 50);
        },
        
        updateConfidence: function(e) {
            const $row = $(e.currentTarget).closest('tr');
            const wcKey = $row.data('wc-field');
            const apiField = $(e.currentTarget).val();
            
            if (apiField) {
                // Set to medium confidence for manual changes
                $row.find('.badge').removeClass('bg-success bg-warning bg-danger bg-secondary')
                    .addClass('bg-warning')
                    .html('⚠️ Medium');
                $row.find('.sample-value').text(FieldMapping.getSampleValue(FieldMapping.sampleData, apiField));
            } else {
                $row.find('.badge').removeClass('bg-success bg-warning bg-danger bg-secondary')
                    .addClass('bg-secondary')
                    .html('Not mapped');
                $row.find('.sample-value').text('-');
            }
        },
        
        testMapping: function() {
            const $btn = $('#testMappingBtn');
            $btn.prop('disabled', true).html('⏳ Testing...');
            
            const data = {
                action: 'woosync_test_mapping',
                nonce: woosyncData.nonce
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        FieldMapping.showTestResult(response.data);
                    } else {
                        alert('Test failed: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(function() {
                    alert('Test failed: Network error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('🧪 Test Mapping');
                });
        },
        
        showTestResult: function(data) {
            const $result = $('#mappingTestResult');
            const $data = $('#testProductData');
            
            const formatted = JSON.stringify(data, null, 2);
            $data.text(formatted);
            $result.show();
        },
        
        resetMapping: function() {
            if (!confirm('Reset all field mappings to vendor template defaults?')) {
                return;
            }
            
            const templates = window.woosyncVendorTemplates || {};
            const activeVendor = window.woosyncActiveVendor || 'amrod';
            const template = templates[activeVendor] || templates.amrod;
            
            if (template && template.field_mapping_template) {
                $('#mappingTable tbody tr').each(function() {
                    const $row = $(this);
                    const wcKey = $row.data('wc-field');
                    const defaultMapping = template.field_mapping_template[wcKey];
                    
                    if (defaultMapping) {
                        $row.find('.api-field-input').val(defaultMapping);
                        $row.find('.badge').removeClass('bg-success bg-warning bg-danger bg-secondary')
                            .addClass('bg-success')
                            .html('✅ High');
                    } else {
                        $row.find('.api-field-input').val('');
                        $row.find('.badge').removeClass('bg-success bg-warning bg-danger bg-secondary')
                            .addClass('bg-secondary')
                            .html('Not mapped');
                    }
                });
                
                FieldMapping.showMessage('Mappings reset to template defaults.', 'info');
            }
        },
        
        showMessage: function(message, type) {
            const alertClass = {
                success: 'alert-success',
                error: 'alert-danger',
                warning: 'alert-warning',
                info: 'alert-info'
            }[type] || 'alert-info';
            
            const $alert = $('<div class="alert ' + alertClass + ' alert-dismissible fade show">')
                .html('<strong>' + message + '</strong>')
                .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
            
            $('#fieldMappingForm').prepend($alert);
            
            setTimeout(function() {
                $alert.alert('close');
            }, 5000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#mappingTable').length) {
            FieldMapping.init();
        }
    });
    
})(jQuery);
