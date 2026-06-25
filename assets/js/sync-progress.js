/**
 * WooSync Sync Progress JavaScript
 */
(function($) {
    'use strict';

    const SyncProgress = {
        isRunning: false,
        currentOffset: 0,
        totalProducts: 0,
        syncMode: 'full',
        batchSize: 200,
        syncInterval: null,
        
        init: function() {
            this.bindEvents();
            this.initChart();
        },
        
        bindEvents: function() {
            // Sync form submission
            $('#syncForm').on('submit', $.proxy(this.handleSyncSubmit, this));
            
            // Set active vendor buttons
            $(document).on('click', '.set-active-btn', $.proxy(this.setActiveVendor, this));
            
            // Delete vendor buttons
            $(document).on('click', '.delete-vendor-btn', $.proxy(this.deleteVendor, this));
            
            // Test connection button
            $('#testVendorConnectionBtn').on('click', $.proxy(this.testConnection, this));
        },
        
        handleSyncSubmit: function(e) {
            e.preventDefault();
            
            if (this.isRunning) {
                return;
            }
            
            const $form = $(e.currentTarget);
            this.syncMode = $form.find('input[name="sync_mode"]:checked').val() || 'full';
            this.batchSize = parseInt($form.find('input[name="batch_size"]').val()) || 200;
            this.currentOffset = 0;
            
            this.startSync();
        },
        
        startSync: function() {
            this.isRunning = true;
            this.updateStatusDisplay('Syncing...');
            
            $('#syncProgress').show();
            $('#progressBar').css('width', '0%').addClass('progress-bar-animated');
            $('#progressText').text('0%');
            $('#syncDetails').text('Starting sync...');
            
            this.runBatch();
        },
        
        runBatch: function() {
            const data = {
                action: 'woosync_sync_batch',
                nonce: woosyncData.nonce,
                sync_mode: this.syncMode,
                batch_size: this.batchSize,
                offset: this.currentOffset
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done($.proxy(this.handleBatchResult, this))
                .fail($.proxy(this.handleBatchError, this));
        },
        
        handleBatchResult: function(response) {
            if (!response.success) {
                this.handleSyncError(response.data || 'Sync failed');
                return;
            }
            
            const result = response.data;
            this.totalProducts = result.total || 0;
            
            // Update progress
            const progress = this.totalProducts > 0 
                ? Math.round((this.currentOffset / this.totalProducts) * 100)
                : 0;
            
            $('#progressBar').css('width', progress + '%').removeClass('progress-bar-animated');
            $('#progressText').text(progress + '%');
            $('#syncDetails').html(
                `Processed: ${result.processed || 0} | ` +
                `Errors: ${result.errors || 0} | ` +
                `Total: ${this.totalProducts} | ` +
                `Offset: ${this.currentOffset}`
            );
            
            // Update visual progress
            this.updateVisualProgress(result);
            
            if (result.more) {
                this.currentOffset = result.next_offset;
                // Small delay between batches
                setTimeout($.proxy(this.runBatch, this), 500);
            } else {
                this.completeSync(result);
            }
        },
        
        handleBatchError: function() {
            this.handleSyncError('Network error during sync');
        },
        
        handleSyncError: function(message) {
            this.isRunning = false;
            this.updateStatusDisplay('Error: ' + message);
            $('#syncDetails').html('<span class="text-danger">❌ ' + message + '</span>');
            $('#progressBar').removeClass('progress-bar-animated').addClass('bg-danger');
        },
        
        completeSync: function(result) {
            this.isRunning = false;
            this.updateStatusDisplay('Sync Complete!');
            
            $('#progressBar').css('width', '100%').removeClass('progress-bar-animated');
            $('#progressText').text('100%');
            $('#syncDetails').html(
                '<span class="text-success">✅ Sync complete!</span> ' +
                `Processed ${result.processed} products, ${result.errors} errors.`
            );
            
            // Refresh page after delay
            setTimeout(function() {
                location.reload();
            }, 2000);
        },
        
        updateStatusDisplay: function(status) {
            $('#syncStatusDisplay').text(status);
        },
        
        updateVisualProgress: function(result) {
            const processed = result.processed || 0;
            const errors = result.errors || 0;
            const total = result.total || 0;
            const remaining = total - this.currentOffset;
            
            const html = `
                <div class="text-center">
                    <div class="fs-4 mb-2">${processed} / ${total}</div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: ${total > 0 ? (this.currentOffset / total * 100) : 0}%"></div>
                    </div>
                    <div class="small">
                        <span class="text-success">✅ ${processed} synced</span> | 
                        <span class="text-danger">❌ ${errors} errors</span> | 
                        <span class="text-muted">⏳ ${remaining} remaining</span>
                    </div>
                </div>
            `;
            
            $('#visualProgress').html(html);
        },
        
        initChart: function() {
            const ctx = document.getElementById('syncHistoryChart');
            if (!ctx) return;
            
            // Fetch sync history data and render chart
            // For now, create empty chart
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['No data'],
                    datasets: [{
                        label: 'Products Synced',
                        data: [0],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        setActiveVendor: function(e) {
            const $btn = $(e.currentTarget);
            const vendorId = $btn.data('vendor-id');
            
            $btn.prop('disabled', true);
            
            const data = {
                action: 'woosync_set_active_vendor',
                nonce: woosyncData.nonce,
                vendor_id: vendorId
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to set active vendor');
                        $btn.prop('disabled', false);
                    }
                })
                .fail(function() {
                    alert('Failed to set active vendor');
                    $btn.prop('disabled', false);
                });
        },
        
        deleteVendor: function(e) {
            const $btn = $(e.currentTarget);
            const vendorId = $btn.data('vendor-id');
            const vendorName = $btn.data('vendor-name');
            
            if (!confirm('Delete vendor "' + vendorName + '"? This cannot be undone.')) {
                return;
            }
            
            $btn.prop('disabled', true);
            
            const data = {
                action: 'woosync_delete_vendor',
                nonce: woosyncData.nonce,
                vendor_id: vendorId
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete vendor');
                        $btn.prop('disabled', false);
                    }
                })
                .fail(function() {
                    alert('Failed to delete vendor');
                    $btn.prop('disabled', false);
                });
        },
        
        testConnection: function() {
            const $btn = $('#testVendorConnectionBtn');
            const $result = $('#connectionTestResult');
            
            $btn.prop('disabled', true).html('⏳ Testing...');
            $result.html('');
            
            const data = {
                action: 'woosync_test_connection',
                nonce: woosyncData.nonce
            };
            
            $.post(woosyncData.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        $result.html('<div class="alert alert-success mb-0">✅ Connection successful!</div>');
                    } else {
                        $result.html('<div class="alert alert-danger mb-0">❌ ' + (response.data || 'Connection failed') + '</div>');
                    }
                })
                .fail(function() {
                    $result.html('<div class="alert alert-danger mb-0">❌ Connection failed</div>');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('🔗 Test API Connection');
                });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SyncProgress.init();
    });
    
})(jQuery);
