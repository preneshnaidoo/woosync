/**
 * WooSync Onboarding Wizard JavaScript
 */
(function($) {
    'use strict';

    const WooSyncWizard = {
        currentStep: 1,
        totalSteps: 5,
        selectedVendor: null,
        vendorData: {},
        
        init: function() {
            this.bindEvents();
            this.initTemplateSelection();
        },
        
        bindEvents: function() {
            // Navigation buttons
            $('#wizardNextBtn').on('click', $.proxy(this.nextStep, this));
            $('#wizardPrevBtn').on('click', $.proxy(this.prevStep, this));
            
            // Vendor template selection
            $('.select-vendor-btn').on('click', $.proxy(this.selectVendorTemplate, this));
            $('#addCustomVendorBtn').on('click', $.proxy(this.selectCustomVendor, this));
            
            // Test connection
            $('#testConnectionBtn').on('click', $.proxy(this.testConnection, this));
            
            // Auto-detect fields
            $('#autoDetectBtn').on('click', $.proxy(this.autoDetectFields, this));
            
            // Form submission
            $('#wizardForm').on('submit', $.proxy(this.submitForm, this));
            
            // Template list click
            $('#templateList .list-group-item').on('click', $.proxy(this.selectTemplateFromList, this));
            
            // Auth type change
            $('#authTypeSelect').on('change', $.proxy(this.updateAuthFields, this));
        },
        
        initTemplateSelection: function() {
            // Pre-fill form when template is selected
        },
        
        selectVendorTemplate: function(e) {
            const vendorSlug = $(e.currentTarget).data('vendor');
            const templates = window.woosyncVendorTemplates || {};
            const template = templates[vendorSlug];
            
            if (template) {
                this.selectedVendor = vendorSlug;
                this.vendorData = {
                    template: vendorSlug,
                    name: template.name,
                    slug: template.slug,
                    api_url: template.api_url,
                    auth_url: template.auth_url,
                    auth_type: template.auth_type
                };
                
                $('#vendorTemplate').val(vendorSlug);
                $('#vendorName').val(template.name);
                $('#vendorSlug').val(template.slug);
                $('#apiUrl').val(template.api_url);
                $('#authUrl').val(template.auth_url);
                $('#authTypeSelect').val(template.auth_type);
                
                this.updateAuthFields();
                this.nextStep();
            }
        },
        
        selectCustomVendor: function() {
            this.selectedVendor = 'custom';
            this.vendorData = {
                template: 'custom',
                name: '',
                slug: '',
                api_url: '',
                auth_url: '',
                auth_type: 'vendor_login'
            };
            
            $('#vendorTemplate').val('custom');
            this.updateAuthFields();
            this.nextStep();
        },
        
        selectTemplateFromList: function(e) {
            const template = $(e.currentTarget).data('template');
            $('#selectedTemplate').val(template);
            
            $('#templateList .list-group-item').removeClass('active');
            $(e.currentTarget).addClass('active');
            
            // Update form fields based on template
            const templates = window.woosyncVendorTemplates || {};
            if (template !== 'custom' && templates[template]) {
                const t = templates[template];
                $('input[name="api_url"]').val(t.api_url);
                $('input[name="auth_url"]').val(t.auth_url);
                $('#authTypeSelect').val(t.auth_type);
            }
            
            this.updateAuthFields();
        },
        
        updateAuthFields: function() {
            const authType = $('#authTypeSelect').val() || 'vendor_login';
            let fieldsHtml = '';
            
            switch (authType) {
                case 'vendor_login':
                    fieldsHtml = `
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordField" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">👁️</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Customer Code</label>
                            <input type="text" name="customer_code" class="form-control">
                        </div>
                    `;
                    break;
                    
                case 'api_key':
                    fieldsHtml = `
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="api_key" class="form-control" required>
                        </div>
                    `;
                    break;
                    
                case 'bearer_token':
                    fieldsHtml = `
                        <div class="mb-3">
                            <label class="form-label">Bearer Token</label>
                            <input type="password" name="bearer_token" class="form-control" required>
                        </div>
                    `;
                    break;
                    
                case 'oauth2':
                    fieldsHtml = `
                        <div class="mb-3">
                            <label class="form-label">Client ID</label>
                            <input type="text" name="client_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Client Secret</label>
                            <input type="password" name="client_secret" class="form-control" required>
                        </div>
                    `;
                    break;
            }
            
            $('#authCredentialsFields').html(fieldsHtml);
            $('#credentialsFields').html(fieldsHtml);
        },
        
        testConnection: function() {
            const $btn = $('#testConnectionBtn');
            const $status = $('#connectionStatus');
            
            $btn.prop('disabled', true).html('⏳ Testing...');
            $status.html('');
            
            const data = {
                action: 'woosync_test_connection',
                nonce: woosyncData.nonce,
                api_url: $('#apiUrl').val(),
                auth_url: $('#authUrl').val()
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        $status.html('<span class="text-success">✅ Connection successful!</span>');
                    } else {
                        $status.html('<span class="text-danger">❌ ' + (response.data || 'Connection failed') + '</span>');
                    }
                })
                .fail(function() {
                    $status.html('<span class="text-danger">❌ Connection failed</span>');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('🔗 Test Connection');
                });
        },
        
        autoDetectFields: function() {
            const $btn = $('#autoDetectBtn');
            $btn.prop('disabled', true).html('⏳ Detecting...');
            
            const data = {
                action: 'woosync_auto_detect_fields',
                nonce: woosyncData.nonce
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        wizard.renderMappingResults(response.data);
                    } else {
                        alert('Auto-detect failed: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(function() {
                    alert('Auto-detect failed: Network error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('🔍 Auto-Detect Fields');
                });
        },
        
        renderMappingResults: function(data) {
            const mappings = data.mappings || {};
            const sample = data.sample || {};
            const wcFields = window.woosyncWcFields || {};
            let html = '';
            
            for (const [wcKey, mapping] of Object.entries(mappings)) {
                const wcLabel = wcFields[wcKey]?.label || wcKey;
                const confidenceInfo = this.getConfidenceInfo(mapping.confidence);
                const sampleValue = this.getSampleValue(sample, mapping.api_field);
                
                html += `
                    <tr>
                        <td><strong>${wcLabel}</strong></td>
                        <td>
                            <input type="text" name="mapping[${wcKey}]" 
                                   value="${mapping.api_field}" 
                                   class="form-control form-control-sm">
                        </td>
                        <td>
                            <span class="badge bg-${confidenceInfo.class}">
                                ${confidenceInfo.icon} ${confidenceInfo.label}
                            </span>
                        </td>
                        <td class="small text-muted">${sampleValue}</td>
                    </tr>
                `;
            }
            
            // Add unmapped WC fields
            for (const [wcKey, config] of Object.entries(wcFields)) {
                if (!mappings[wcKey]) {
                    html += `
                        <tr>
                            <td><strong>${config.label}</strong></td>
                            <td>
                                <input type="text" name="mapping[${wcKey}]" 
                                       value="" 
                                       class="form-control form-control-sm"
                                       placeholder="Not mapped">
                            </td>
                            <td><span class="badge bg-secondary">Not mapped</span></td>
                            <td class="small text-muted">-</td>
                        </tr>
                    `;
                }
            }
            
            $('#mappingResultsBody').html(html);
        },
        
        getConfidenceInfo: function(confidence) {
            const levels = {
                high: { label: 'High', class: 'success', icon: '✅' },
                medium: { label: 'Medium', class: 'warning', icon: '⚠️' },
                low: { label: 'Low', class: 'danger', icon: '❌' }
            };
            return levels[confidence] || levels.low;
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
                return value.length > 0 ? JSON.stringify(value).substring(0, 50) + '...' : '-';
            }
            if (typeof value === 'object') {
                return JSON.stringify(value).substring(0, 50) + '...';
            }
            return String(value).substring(0, 50);
        },
        
        nextStep: function() {
            if (this.currentStep < this.totalSteps) {
                this.showStep(this.currentStep + 1);
            }
        },
        
        prevStep: function() {
            if (this.currentStep > 1) {
                this.showStep(this.currentStep - 1);
            }
        },
        
        showStep: function(step) {
            // Hide current step
            $(`.wizard-step[data-step="${this.currentStep}"]`).hide();
            
            // Show new step
            $(`.wizard-step[data-step="${step}"]`).show();
            
            // Update step indicators
            $('.step-indicator').removeClass('active');
            $(`.step-indicator[data-step="${step}"]`).addClass('active');
            $(`.step-indicator[data-step="${step}"]`).prevAll('.step-indicator').addClass('active');
            
            // Update buttons
            $('#wizardPrevBtn').prop('disabled', step === 1);
            
            if (step === this.totalSteps) {
                $('#wizardNextBtn').hide();
                $('#wizardFinishBtn').show();
            } else {
                $('#wizardNextBtn').show();
                $('#wizardFinishBtn').hide();
            }
            
            this.currentStep = step;
        },
        
        submitForm: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const formData = new FormData($form[0]);
            
            // Add wizard complete flag
            formData.append('wizard_complete', '1');
            
            $.ajax({
                url: woosyncData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        window.location.href = 'admin.php?page=woosync';
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Submission failed. Please try again.');
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#wizardForm').length) {
            window.wizard = WooSyncWizard;
            wizard.init();
        }
    });
    
})(jQuery);
