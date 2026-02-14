(function($) {
    'use strict';

    $(document).ready(function() {
        const $syncBtn = $('#swayam-ai-chatbot-sync-btn');
        const $testBtn = $('#swayam-ai-chatbot-test-btn');
        const $status = $('#swayam-ai-chatbot-sync-status');
        const $lastSync = $('#swayam-ai-chatbot-last-sync');
        const $indexedCount = $('#swayam-ai-chatbot-indexed-count');

        // Sync All Content
        $syncBtn.on('click', function() {
            $syncBtn.prop('disabled', true);
            $status.removeClass('success error').text(swayamAiChatbotAdmin.strings.syncing);

            $.ajax({
                url: swayamAiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'swayam_ai_chatbot_sync_all',
                    nonce: swayamAiChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(swayamAiChatbotAdmin.strings.syncComplete);
                        $lastSync.text(response.data.last_sync);
                        $indexedCount.text(response.data.indexed_count);
                    } else {
                        $status.addClass('error').text(response.data.message || swayamAiChatbotAdmin.strings.syncError);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Sync error:', error);
                    $status.addClass('error').text(swayamAiChatbotAdmin.strings.syncError);
                },
                complete: function() {
                    $syncBtn.prop('disabled', false);
                }
            });
        });

        // Test Connections
        $testBtn.on('click', function() {
            $testBtn.prop('disabled', true);
            $status.removeClass('success error').text(swayamAiChatbotAdmin.strings.testing);

            $.ajax({
                url: swayamAiChatbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'swayam_ai_chatbot_test_connection',
                    nonce: swayamAiChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const results = response.data.results;
                        const errors = response.data.errors;

                        let html = '<div class="swayam-ai-chatbot-connection-results">';
                        html += '<p><strong>Ollama:</strong> ';
                        if (results.ollama) {
                            html += '<span class="success">Connected</span>';
                        } else {
                            html += '<span class="error">Failed - ' + (errors.ollama || 'Unknown error') + '</span>';
                        }
                        html += '</p>';

                        html += '<p><strong>Elasticsearch:</strong> ';
                        if (results.elasticsearch) {
                            html += '<span class="success">Connected</span>';
                        } else {
                            html += '<span class="error">Failed - ' + (errors.elasticsearch || 'Unknown error') + '</span>';
                        }
                        html += '</p></div>';

                        $status.html(html);

                        if (results.ollama && results.elasticsearch) {
                            $status.addClass('success');
                        } else {
                            $status.addClass('error');
                        }
                    } else {
                        $status.addClass('error').text(swayamAiChatbotAdmin.strings.testError);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Test error:', error);
                    $status.addClass('error').text(swayamAiChatbotAdmin.strings.testError);
                },
                complete: function() {
                    $testBtn.prop('disabled', false);
                }
            });
        });
    });
})(jQuery);
