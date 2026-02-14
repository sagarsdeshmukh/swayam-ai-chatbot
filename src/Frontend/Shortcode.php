<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Frontend;

defined('ABSPATH') || exit;

class Shortcode
{
    private bool $assetsEnqueued = false;

    public function render(array $atts = []): string
    {
        $settings = get_option('swayam_ai_chatbot_settings', []);

        $atts = shortcode_atts([
            'title' => $settings['chat_title'] ?? __('Ask me anything', 'swayam-ai-chatbot'),
            'placeholder' => $settings['chat_placeholder'] ?? __('Type your question...', 'swayam-ai-chatbot'),
            'class' => '',
        ], $atts, 'swayam_ai_chatbot');

        // Ensure assets are loaded
        $this->enqueueAssets();

        ob_start();
        $this->renderChatWidget($atts, 'inline');
        return ob_get_clean();
    }

    public function enqueueAssets(): void
    {
        if ($this->assetsEnqueued) {
            return;
        }

        wp_enqueue_style(
            'swayam-ai-chatbot',
            SWAYAM_AI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
            [],
            SWAYAM_AI_CHATBOT_VERSION
        );

        wp_enqueue_script(
            'swayam-ai-chatbot',
            SWAYAM_AI_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js',
            [],
            SWAYAM_AI_CHATBOT_VERSION,
            true
        );

        wp_localize_script('swayam-ai-chatbot', 'swayamAiChatbot', [
            'apiUrl' => rest_url('swayam-ai-chatbot/v1/chat'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'thinking' => __('Thinking...', 'swayam-ai-chatbot'),
                'error' => __('Sorry, something went wrong. Please try again.', 'swayam-ai-chatbot'),
                'networkError' => __('Network error. Please check your connection.', 'swayam-ai-chatbot'),
                'sources' => __('Sources:', 'swayam-ai-chatbot'),
            ],
        ]);

        $this->assetsEnqueued = true;
    }

    public function renderChatWidget(array $atts, string $mode = 'inline'): void
    {
        $widgetClass = 'swayam-ai-chatbot-widget';
        if ($mode === 'floating') {
            $widgetClass .= ' swayam-ai-chatbot-floating';
        }
        if (!empty($atts['class'])) {
            $widgetClass .= ' ' . esc_attr($atts['class']);
        }
        ?>
        <div class="<?php echo esc_attr($widgetClass); ?>" data-mode="<?php echo esc_attr($mode); ?>">
            <div class="swayam-ai-chatbot-header">
                <span class="swayam-ai-chatbot-title"><?php echo esc_html($atts['title']); ?></span>
                <?php if ($mode === 'floating') : ?>
                    <button type="button" class="swayam-ai-chatbot-close" aria-label="<?php esc_attr_e('Close chat', 'swayam-ai-chatbot'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>

            <div class="swayam-ai-chatbot-messages" role="log" aria-live="polite">
                <div class="swayam-ai-chatbot-welcome">
                    <p><?php esc_html_e('Hello! I can help answer questions about our content. What would you like to know?', 'swayam-ai-chatbot'); ?></p>
                </div>
            </div>

            <form class="swayam-ai-chatbot-form" role="search">
                <div class="swayam-ai-chatbot-input-wrapper">
                    <input
                        type="text"
                        class="swayam-ai-chatbot-input"
                        placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                        aria-label="<?php esc_attr_e('Your question', 'swayam-ai-chatbot'); ?>"
                        autocomplete="off"
                    >
                    <button type="submit" class="swayam-ai-chatbot-submit" aria-label="<?php esc_attr_e('Send question', 'swayam-ai-chatbot'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}
