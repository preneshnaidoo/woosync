/**
 * Amrod Sync v2.0 - Progress Tracking & Live Updates
 */

jQuery(function($) {
    // Handle fetch token button
    $(document).on('click', 'button[name="action"][value="fetch_token"]', function(e) {
        e.preventDefault();
        
        // Show progress
        $('#syncProgress').show();
        showProgressBar(0, 'Fetching token from Amrod API...');

        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_fetch_token',
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showProgressBar(100, '✅ Token fetched successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showProgressBar(0, '❌ Failed: ' + response.data);
                }
            },
            error: function() {
                showProgressBar(0, '❌ AJAX Error');
            }
        });
    });

    // Handle run sync button
    $(document).on('click', 'button[name="action"][value="run_sync"]', function(e) {
        e.preventDefault();

        const batchSize = $('[name="batch_size"]').val() || 200;
        const mode = $('[name="sync_mode"]:checked').val();
        const limit = mode === 'batch' ? $('[name="batch_limit"]').val() : 0;

        $('#syncProgress').show();
        startSync(0, batchSize, mode, limit);
    });

    function startSync(offset, batchSize, mode, limit) {
        $.ajax({
            url: amrodSyncData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amrod_sync_batch',
                offset: offset,
                batch_size: batchSize,
                nonce: amrodSyncData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const percent = Math.round((data.processed_total / data.total) * 100);
                    
                    showProgressBar(percent, `Loaded: ${data.processed_total}/${data.total} (${percent}%)`);

                    // Append to log
                    $('#syncLog').prepend(
                        `<div class="text-monospace small mb-2">✅ Batch ${Math.floor(offset/batchSize)}: ${data.processed} items synced</div>`
                    );

                    // Continue if more batches
                    if (data.more && (!limit || offset + batchSize < limit)) {
                        startSync(data.next_offset, batchSize, mode, limit);
                    } else {
                        showProgressBar(100, '✅ Sync complete!');
                        setTimeout(() => location.reload(), 1500);
                    }
                }
            }
        });
    }

    function showProgressBar(percent, message) {
        $('#progressBar').css('width', percent + '%').text(percent + '%');
        $('#syncDetails').html('<div class="alert alert-info">' + message + '</div>');
    }
});

function showProgressBar(percent, message) {
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressText').textContent = percent + '%';
    document.getElementById('syncDetails').innerHTML = '<div class="alert alert-info">' + message + '</div>';
}
