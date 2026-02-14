<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Frontend;

defined('ABSPATH') || exit;

class FloatingWidget
{
    private Shortcode $shortcode;

    public function __construct()
    {
        $this->shortcode = new Shortcode();
    }

    public function render(): void
    {
        // Don't show in admin
        if (is_admin()) {
            return;
        }

        $settings = get_option('swayam_ai_chatbot_settings', []);

        if (empty($settings['widget_enabled'])) {
            return;
        }

        $position = $settings['widget_position'] ?? 'bottom-right';

        // Enqueue assets
        $this->shortcode->enqueueAssets();

        // Add position-specific CSS
        $this->outputPositionStyles($position);

        // Render the floating toggle button
        $this->renderToggleButton();

        // Render the chat widget (hidden by default)
        $atts = [
            'title' => $settings['chat_title'] ?? __('Ask me anything', 'swayam-ai-chatbot'),
            'placeholder' => $settings['chat_placeholder'] ?? __('Type your question...', 'swayam-ai-chatbot'),
            'class' => 'swayam-ai-chatbot-position-' . $position,
        ];

        echo '<div class="swayam-ai-chatbot-floating-container swayam-ai-chatbot-position-' . esc_attr($position) . '" style="display: none;">';
        $this->shortcode->renderChatWidget($atts, 'floating');
        echo '</div>';
    }

    private function renderToggleButton(): void
    {
        $settings = get_option('swayam_ai_chatbot_settings', []);
        $position = $settings['widget_position'] ?? 'bottom-right';
        ?>
        <button
            type="button"
            class="swayam-ai-chatbot-toggle swayam-ai-chatbot-position-<?php echo esc_attr($position); ?>"
            aria-label="<?php esc_attr_e('Open chat', 'swayam-ai-chatbot'); ?>"
            aria-expanded="false"
        >
            <svg class="swayam-ai-chatbot-toggle-icon-open" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            <svg class="swayam-ai-chatbot-toggle-icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>
        <?php
    }

    private function outputPositionStyles(string $position): void
    {
        $styles = '';

        switch ($position) {
            case 'bottom-left':
                $styles = '
                    .swayam-ai-chatbot-toggle.swayam-ai-chatbot-position-bottom-left,
                    .swayam-ai-chatbot-floating-container.swayam-ai-chatbot-position-bottom-left {
                        left: 20px;
                        right: auto;
                    }
                ';
                break;
            case 'bottom-right':
            default:
                $styles = '
                    .swayam-ai-chatbot-toggle.swayam-ai-chatbot-position-bottom-right,
                    .swayam-ai-chatbot-floating-container.swayam-ai-chatbot-position-bottom-right {
                        right: 20px;
                        left: auto;
                    }
                ';
                break;
        }

        if (!empty($styles)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is hardcoded, not user input
            echo '<style>' . $styles . '</style>';
        }
    }
}
