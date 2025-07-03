jQuery(document).ready(function ($) {
    let statusInterval;
    const processStatus = {
        running: false
    };

    // Initialize product select
    if ($('#ws2v-product-selector').length) {
        $('#ws2v-product-selector').select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        action: 'ws2v_get_products',
                        nonce: ws2v_ajax.nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.data
                    };
                },
                cache: true
            },
            minimumInputLength: 3,
            placeholder: ws2v_ajax.product_placeholder,
            multiple: true,
            width: '100%'
        });
    }

    // Start processing
    $('#ws2v-start-process').on('click', function () {
        // Removed product selection validation as backend processes all products automatically
        // const productIds = $('#ws2v-product-select').val();
        // if (!productIds || productIds.length < 2) {
        //     alert('Please select at least 2 products to merge');
        //     return;
        // }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ws2v_start_process',
                nonce: ws2v_ajax.nonce,
                // No product_ids sent as backend processes all
                // product_ids: productIds 
            },
            success: function (response) {
                if (response.success) {
                    processStatus.running = true;
                    updateUI();
                    startStatusCheck();
                } else {
                    alert('Failed to start processing: ' + response.data);
                }
            },
            error: function () {
                alert('Failed to start processing. Please try again.');
            }
        });
    });

    // Stop processing
    $('#ws2v-stop-process').on('click', function () {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ws2v_stop_process',
                nonce: ws2v_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    processStatus.running = false;
                    updateUI();
                    stopStatusCheck();
                }
            }
        });
    });

    // Update UI based on process status
    function updateUI() {
        if (processStatus.running) {
            $('#ws2v-start-process').prop('disabled', true);
            $('#ws2v-stop-process').prop('disabled', false);
            $('#ws2v-product-select').prop('disabled', true);
        } else {
            $('#ws2v-start-process').prop('disabled', false);
            $('#ws2v-stop-process').prop('disabled', true);
            $('#ws2v-product-select').prop('disabled', false);
        }
    }

    // Start status check interval
    function startStatusCheck() {
        statusInterval = setInterval(checkStatus, 2000);
    }

    // Stop status check interval
    function stopStatusCheck() {
        if (statusInterval) {
            clearInterval(statusInterval);
        }
    }

    // Check process status
    function checkStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ws2v_get_status',
                nonce: ws2v_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateStats(response.data.stats, response.data.queue);
                    updateLogs(response.data.logs);

                    // Check if processing is complete
                    const queue = response.data.queue;
                    if (response.data.status !== 'running' ||
                        (queue.pending === 0 && queue.processing === 0)) {
                        processStatus.running = false;
                        updateUI();
                        stopStatusCheck();
                    }
                }
            }
        });
    }

    // Update stats display
    function updateStats(stats, queue) {
        // Update main stats
        $('#ws2v-processed-count').text(stats.processed);
        $('#ws2v-created-count').text(stats.created);
        $('#ws2v-failed-count').text(stats.failed);

        // Update queue stats
        $('#ws2v-queue-pending').text(queue.pending);
        $('#ws2v-queue-processing').text(queue.processing);
        $('#ws2v-queue-completed').text(queue.completed);
        $('#ws2v-queue-failed').text(queue.failed);

        // Update progress bar
        const total = queue.pending + queue.processing + queue.completed + queue.failed;
        if (total > 0) {
            const progress = ((queue.completed + queue.failed) / total) * 100;
            $('#ws2v-progress-bar').css('width', progress + '%');
        }
    }

    // Update logs display
    function updateLogs(logs) {
        const logContainer = $('#ws2v-log');
        logContainer.empty();

        logs.forEach(function (log) {
            const logEntry = $('<div>').addClass('ws2v-log-entry');
            if (log.type === 'error') {
                logEntry.addClass('ws2v-log-error');
            }
            logEntry.text(`[${log.time}] ${log.message}`);
            logContainer.append(logEntry);
        });

        // Auto-scroll to bottom
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    // Initial UI update
    updateUI();

    // Check initial status
    checkStatus();
});