/**
 * WooSync v3.4.1 - Onboarding Wizard with Dynamic Credential Fields
 * Shows different credential fields based on selected vendor (Amrod, SMD, Custom)
 */

jQuery(function($) {
    // Wizard state
    var wizardState = {
        currentStep: 0,
        totalSteps: 3,
        vendors: [],
        selectedVendor: null,
        selectedVendorId: null,
    };

    // ============================================================
    // WIZARD INITIALIZATION
    // ============================================================
    
    function initWizard() {
        // Check if wizard has been completed
        var wizardCompleted = localStorage.getItem('woosync_wizard_completed');
        
        if (wizardCompleted === 'true') {
            // Wizard already done, skip
            return;
        }
        
        // Show wizard modal on first admin load
        showWizardModal();
    }

    function showWizardModal() {
        var modalHtml = `
        <div class="modal fade" id="woosyncWizardModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content woosync-wizard-modal">
                    <!-- Progress Bar -->
                    <div class="wizard-progress-bar">
                        <div class="wizard-progress-fill" id="wizardProgressFill"></div>
                    </div>
                    
                    <!-- Modal Header -->
                    <div class="modal-header woosync-wizard-header">
                        <div class="wizard-logo-container">
                            <img src="${woosyncData.assetsUrl}images/woosync-logo.svg" alt="WooSync" height="40">
                        </div>
                        <span class="wizard-step-indicator" id="wizardStepIndicator">Step 1 of 3</span>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="modal-body woosync-wizard-body" id="wizardBody">
                        <!-- Steps will be rendered here -->
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer woosync-wizard-footer" id="wizardFooter">
                        <button type="button" class="btn btn-outline-secondary" id="wizardSkipBtn">Skip Setup</button>
                        <button type="button" class="btn btn-primary" id="wizardNextBtn">Next →</button>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        // Remove existing modal if any
        $('#woosyncWizardModal').remove();
        
        // Append modal to body
        $('body').append(modalHtml);
        
        // Store reference
        window.woosyncWizardModal = new bootstrap.Modal(document.getElementById('woosyncWizardModal'));
        
        // Render first step
        renderWizardStep(0);
        
        // Show modal
        window.woosyncWizardModal.show();
        
        // Bind events
        bindWizardEvents();
    }

    function bindWizardEvents() {
        // Next button
        $('#wizardNextBtn').on('click', function() {
            if (validateCurrentStep()) {
                wizardState.currentStep++;
                if (wizardState.currentStep >= wizardState.totalSteps) {
                    completeWizard();
                } else {
                    renderWizardStep(wizardState.currentStep);
                }
            }
        });
        
        // Skip button
        $('#wizardSkipBtn').on('click', function() {
            completeWizard();
        });
    }

    // ============================================================
    // WIZARD STEPS
    // ============================================================
    
    function renderWizardStep(stepIndex) {
        var steps = [
            renderWelcomeStep,
            renderSelectVendorStep,
            renderConnectVendorStep,
        ];
        
        if (stepIndex < steps.length) {
            steps[stepIndex]();
            updateWizardUI(stepIndex);
        }
    }

    function updateWizardUI(stepIndex) {
        // Update progress bar
        var progress = ((stepIndex + 1) / wizardState.totalSteps) * 100;
        $('#wizardProgressFill').css('width', progress + '%');
        
        // Update step indicator
        $('#wizardStepIndicator').text('Step ' + (stepIndex + 1) + ' of ' + wizardState.totalSteps);
        
        // Update buttons
        var nextBtn = $('#wizardNextBtn');
        var skipBtn = $('#wizardSkipBtn');
        
        if (stepIndex === 0) {
            skipBtn.show();
            nextBtn.text('Get Started →');
        } else if (stepIndex === wizardState.totalSteps - 1) {
            skipBtn.hide();
            nextBtn.text('Finish Setup →');
        } else {
            skipBtn.show();
            nextBtn.text('Next →');
        }
    }

    // STEP 0: Welcome Screen
    function renderWelcomeStep() {
        var html = `
        <div class="wizard-step-content wizard-welcome-step">
            <div class="wizard-hero">
                <img src="${woosyncData.assetsUrl}images/woosync-logo.svg" alt="WooSync" height="80" class="wizard-hero-logo">
                <h2 class="wizard-hero-title">Welcome to WooSync</h2>
                <p class="wizard-hero-tagline">Enterprise-grade product sync for WooCommerce — connecting you to Amrod, SMD, and other promo suppliers.</p>
            </div>
            
            <div class="wizard-features">
                <div class="wizard-feature">
                    <span class="wizard-feature-icon">📦</span>
                    <div>
                        <strong>Product Sync</strong>
                        <small>Import products, stock, prices & categories</small>
                    </div>
                </div>
                <div class="wizard-feature">
                    <span class="wizard-feature-icon">🔗</span>
                    <div>
                        <strong>API Integration</strong>
                        <small>Connect to Amrod, SMD, and more</small>
                    </div>
                </div>
                <div class="wizard-feature">
                    <span class="wizard-feature-icon">💰</span>
                    <div>
                        <strong>Smart Pricing</strong>
                        <small>Tier-based markup and margin control</small>
                    </div>
                </div>
                <div class="wizard-feature">
                    <span class="wizard-feature-icon">📣</span>
                    <div>
                        <strong>Promo Share</strong>
                        <small>One-click social sharing for deals</small>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        $('#wizardBody').html(html);
    }

    // STEP 1: Select Vendor
    function renderSelectVendorStep() {
        var vendors = woosyncData.vendorTemplates || [];
        var vendorCardsHtml = '';
        
        vendors.forEach(function(vendor) {
            // Get auth type from schemas if available
            var authType = '';
            if (woosyncData.vendorCredentialSchemas && woosyncData.vendorCredentialSchemas[vendor.id]) {
                var schema = woosyncData.vendorCredentialSchemas[vendor.id];
                authType = '<span class="vendor-auth-badge">' + (schema.auth_type === 'bearer_key' ? 'Bearer Token' : schema.auth_type === 'vendor_login' ? 'Vendor Login' : 'Custom Auth') + '</span>';
            }
            
            vendorCardsHtml += `
            <div class="vendor-card" data-vendor-id="${vendor.id}">
                <div class="vendor-card-header">
                    <span class="vendor-icon">${vendor.icon || '🏢'}</span>
                    <strong>${vendor.name}</strong>
                    ${authType}
                </div>
                <div class="vendor-card-body">
                    <p class="vendor-description">${vendor.description || ''}</p>
                    <button class="btn btn-outline-primary btn-sm vendor-select-btn" data-vendor-id="${vendor.id}">
                        Select ${vendor.name}
                    </button>
                </div>
            </div>
            `;
        });
        
        var html = `
        <div class="wizard-step-content wizard-vendor-step">
            <h3 class="wizard-step-title">Connect Your First Vendor</h3>
            <p class="wizard-step-subtitle">Choose a supplier to connect. Each vendor has different authentication requirements.</p>
            
            <div class="vendor-grid">
                ${vendorCardsHtml}
            </div>
            
            <div class="no-vendors-state" id="noVendorsState" style="display: none;">
                <div class="empty-state">
                    <div class="empty-state-icon">🔗</div>
                    <h4>No vendors connected</h4>
                    <p>Add your first supplier to get started with product syncing.</p>
                    <button class="btn btn-primary btn-lg" id="addFirstVendorBtn">
                        + Add Your First Vendor
                    </button>
                </div>
            </div>
        </div>
        `;
        
        $('#wizardBody').html(html);
        
        // Bind vendor selection
        $('.vendor-select-btn').on('click', function() {
            var vendorId = $(this).data('vendor-id');
            selectVendor(vendorId);
        });
    }

    // STEP 2: Connect Vendor (Dynamic Credential Form + Support Panel)
    function renderConnectVendorStep() {
        var vendor = wizardState.selectedVendor;
        var vendorId = wizardState.selectedVendorId;
        
        if (!vendor || !vendorId) {
            // If no vendor selected, show the "add vendor" state
            renderSelectVendorStep();
            return;
        }
        
        // Get credential schema for this vendor
        var schema = (woosyncData.vendorCredentialSchemas && woosyncData.vendorCredentialSchemas[vendorId]) 
            ? woosyncData.vendorCredentialSchemas[vendorId] 
            : woosyncData.vendorCredentialSchemas['custom'];
        
        // Render support panel based on vendor
        var supportPanelHtml = renderSupportPanel(vendor, schema);
        
        // Render dynamic credential fields
        var credentialsFormHtml = renderCredentialsForm(vendor, schema);
        
        var html = `
        <div class="wizard-step-content wizard-connect-step">
            <h3 class="wizard-step-title">Connect ${vendor.name}</h3>
            <p class="wizard-step-subtitle">${schema.description || 'Enter your API credentials below.'}</p>
            
            ${supportPanelHtml}
            
            <div class="credentials-form" id="credentialsForm">
                ${credentialsFormHtml}
                
                <div class="d-flex gap-2 mt-4">
                    <button type="button" class="btn btn-outline-primary flex-grow-1" id="wizardTestConnection">
                        🔗 Test Connection
                    </button>
                    <button type="button" class="btn btn-primary flex-grow-1" id="wizardSaveCredentials">
                        💾 Save & Connect
                    </button>
                </div>
                
                <div id="wizardConnectionStatus" class="mt-3" style="display: none;"></div>
            </div>
            
            <div class="vendor-connected-success" id="vendorConnectedSuccess" style="display: none;">
                <div class="success-animation">
                    <div class="success-icon">✅</div>
                    <h4>Vendor Connected Successfully!</h4>
                    <p>Your ${vendor.name} API is now connected. You can now start syncing products.</p>
                </div>
            </div>
        </div>
        `;
        
        $('#wizardBody').html(html);
        
        // Enable tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Bind password toggles
        $('.toggle-password').on('click', function() {
            var targetId = $(this).data('target');
            var input = $('#' + targetId);
            input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
            $(this).text(input.attr('type') === 'password' ? '👁' : '🙈');
        });
        
        // Bind test connection
        $('#wizardTestConnection').on('click', function() {
            testWizardConnection();
        });
        
        // Bind save credentials
        $('#wizardSaveCredentials').on('click', function() {
            saveWizardCredentials();
        });
    }

    // ============================================================
    // DYNAMIC CREDENTIAL FORM RENDERING
    // ============================================================
    
    function renderCredentialsForm(vendor, schema) {
        var fieldsHtml = '';
        
        schema.fields.forEach(function(field) {
            var requiredClass = field.required ? 'required-field' : 'optional-field';
            var labelClass = field.required ? 'form-label' : 'form-label';
            var helpIcon = field.help ? '<span class="help-icon" data-bs-toggle="tooltip" title="' + field.help + '">?</span>' : '';
            var placeholder = field.placeholder || '';
            var prefill = field.prefill || '';
            var inputType = field.type || 'text';
            var fieldId = 'wizard_' + field.key;
            var inputClass = 'form-control';
            
            // For password fields, add special styling
            if (inputType === 'password') {
                inputClass += ' password-field';
            }
            
            // Check if field has a prefill value
            var value = prefill || '';
            
            fieldsHtml += `
            <div class="mb-3 credential-field ${requiredClass}" data-field-key="${field.key}">
                <label class="${labelClass}" for="${fieldId}">
                    ${field.label}
                    ${helpIcon}
                </label>
                ${inputType === 'password' ? `
                <div class="input-group">
                    <input type="password" id="${fieldId}" class="${inputClass}" placeholder="${placeholder}" value="${value}" autocomplete="new-password">
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="${fieldId}">👁</button>
                </div>
                ` : `
                <input type="${inputType}" id="${fieldId}" class="${inputClass}" placeholder="${placeholder}" value="${value}">
                `}
            </div>
            `;
        });
        
        // Add vendor note if available
        var noteHtml = '';
        if (schema.support && schema.support.note) {
            noteHtml = `
            <div class="vendor-auth-note">
                <span class="note-icon">💡</span>
                <span class="note-text">${schema.support.note}</span>
            </div>
            `;
        }
        
        return fieldsHtml + noteHtml;
    }

    // ============================================================
    // SUPPORT PANEL (VENDOR-SPECIFIC)
    // ============================================================
    
    function renderSupportPanel(vendor, schema) {
        var support = schema.support || {};
        var hasSupport = support.email || support.docs || support.note;
        
        if (!hasSupport) {
            return '';
        }
        
        var supportItemsHtml = '';
        
        if (support.email) {
            var emailDisplay = support.email === '(in progress — contact SMD directly)' || support.email === '(in progress)' 
                ? '<span class="text-muted">' + support.email + '</span>'
                : '<a href="mailto:' + support.email + '" target="_blank">' + support.email + '</a>';
            
            supportItemsHtml += `
            <div class="support-item">
                <span class="support-item-icon">📧</span>
                <div>
                    <small>Email Support</small>
                    ${emailDisplay}
                </div>
            </div>
            `;
        }
        
        if (support.docs) {
            var docsDisplay = support.docs === '(in progress)' || support.docs === '(in progress — contact SMD directly)'
                ? '<span class="text-muted">' + support.docs + '</span>'
                : '<a href="' + support.docs + '" target="_blank">' + support.docs + '</a>';
            
            supportItemsHtml += `
            <div class="support-item">
                <span class="support-item-icon">📖</span>
                <div>
                    <small>Documentation</small>
                    ${docsDisplay}
                </div>
            </div>
            `;
        }
        
        var noteHtml = '';
        if (support.note) {
            noteHtml = `
            <div class="support-note">
                <span class="support-note-icon">💡</span>
                <span>${support.note}</span>
            </div>
            `;
        }
        
        var headerIcon = vendor.id === 'smd' ? '🔐' : (vendor.id === 'amrod' ? '🔑' : '📋');
        
        var html = `
        <div class="vendor-support-panel vendor-support-panel-${vendor.id}">
            <div class="support-panel-header">
                <span class="support-icon">${headerIcon}</span>
                <strong>Need help getting ${vendor.name} API credentials?</strong>
            </div>
            <div class="support-panel-body">
                ${supportItemsHtml}
                ${noteHtml}
            </div>
        </div>
        `;
        
        return html;
    }

    // ============================================================
    // VENDOR MANAGEMENT
    // ============================================================
    
    function selectVendor(vendorId) {
        var vendors = woosyncData.vendorTemplates || [];
        var vendor = vendors.find(function(v) { return v.id === vendorId; });
        
        if (vendor) {
            wizardState.selectedVendor = vendor;
            wizardState.selectedVendorId = vendorId;
            wizardState.currentStep = 2;
            renderWizardStep(2);
            updateWizardUI(2);
        }
    }

    // ============================================================
    // API CALLS (VENDOR-SPECIFIC)
    // ============================================================
    
    function testWizardConnection() {
        var btn = $('#wizardTestConnection');
        btn.prop('disabled', true).html('⏳ Testing...');
        
        // Build request data from form fields
        var requestData = {
            action: 'woosync_test_connection',
            vendor_id: wizardState.selectedVendorId,
            nonce: woosyncData.nonce
        };
        
        // Add all form fields to request
        $('.credential-field').each(function() {
            var fieldKey = $(this).data('field-key');
            var inputId = 'wizard_' + fieldKey;
            var value = $('#' + inputId).val();
            if (value) {
                requestData[fieldKey] = value;
            }
        });
        
        $.ajax({
            url: woosyncData.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                var statusEl = $('#wizardConnectionStatus');
                statusEl.show();
                
                if (response.success) {
                    statusEl.removeClass('alert-danger').addClass('alert-success')
                        .html('✅ ' + (response.data.message || 'Connection successful!'));
                } else {
                    statusEl.removeClass('alert-success').addClass('alert-danger')
                        .html('❌ ' + (response.data || 'Connection failed'));
                }
            },
            error: function(xhr, status, error) {
                $('#wizardConnectionStatus').removeClass('alert-success').addClass('alert-danger')
                    .html('❌ Connection error: ' + error).show();
            },
            complete: function() {
                btn.prop('disabled', false).html('🔗 Test Connection');
            }
        });
    }
    
    function saveWizardCredentials() {
        var btn = $('#wizardSaveCredentials');
        btn.prop('disabled', true).html('⏳ Saving...');
        
        // Build request data from form fields
        var requestData = {
            action: 'woosync_save_credentials_simple',
            vendor_id: wizardState.selectedVendorId,
            nonce: woosyncData.nonce
        };
        
        // Add all form fields to request
        $('.credential-field').each(function() {
            var fieldKey = $(this).data('field-key');
            var inputId = 'wizard_' + fieldKey;
            var value = $('#' + inputId).val();
            if (value) {
                requestData[fieldKey] = value;
            }
        });
        
        $.ajax({
            url: woosyncData.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                if (response.success) {
                    // Show success state
                    $('#credentialsForm').hide();
                    $('#vendorConnectedSuccess').show();
                    
                    // Update wizard state
                    wizardState.vendors.push(wizardState.selectedVendor);
                    
                    // Auto-advance after delay
                    setTimeout(function() {
                        completeWizard();
                    }, 2000);
                } else {
                    alert('❌ Failed to save: ' + (response.data || 'Unknown error'));
                    btn.prop('disabled', false).html('💾 Save & Connect');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Error saving credentials: ' + error);
                btn.prop('disabled', false).html('💾 Save & Connect');
            }
        });
    }

    // ============================================================
    // VALIDATION
    // ============================================================
    
    function validateCurrentStep() {
        // For now, always allow progression
        return true;
    }

    // ============================================================
    // WIZARD COMPLETION
    // ============================================================
    
    function completeWizard() {
        // Mark wizard as completed
        localStorage.setItem('woosync_wizard_completed', 'true');
        
        // Close modal
        if (window.woosyncWizardModal) {
            window.woosyncWizardModal.hide();
        }
        
        // Show success notice
        showWizardCompleteNotice();
    }
    
    function showWizardCompleteNotice() {
        var notice = `
        <div class="notice notice-success is-dismissible" id="woosyncWizardNotice">
            <p>
                <strong>✅ WooSync Setup Complete!</strong> 
                Your first vendor is connected. Head to 
                <a href="?page=amrod-sync-connect">Connect & Map</a> 
                to configure field mappings and start syncing products.
            </p>
        </div>
        `;
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 10 seconds
        setTimeout(function() {
            $('#woosyncWizardNotice').fadeOut();
        }, 10000);
    }

    // ============================================================
    // ADMIN MENU WIZARD TRIGGER
    // ============================================================
    
    // Show wizard on plugin activation
    $(document).on('woosync_activate_wizard', function() {
        localStorage.removeItem('woosync_wizard_completed');
        showWizardModal();
    });

    // Initialize on page load
    $(document).ready(function() {
        // Only show wizard on WooSync admin pages
        if (typeof woosyncData !== 'undefined' && woosyncData.isWooSyncPage) {
            initWizard();
        }
    });

    // Expose for external triggers
    window.showWooSyncWizard = showWizardModal;
    window.resetWooSyncWizard = function() {
        localStorage.removeItem('woosync_wizard_completed');
        showWizardModal();
    };
});
