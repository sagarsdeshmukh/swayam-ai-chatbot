<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Admin;

defined('ABSPATH') || exit;

use Elastic\Elasticsearch\ClientBuilder;

class SettingsPage
{
    private const OPTION_NAME = 'swayam_ai_chatbot_settings';
    private const OPTION_GROUP = 'swayam_ai_chatbot_options';
    private const PAGE_SLUG = 'swayam-ai-chatbot-settings';

    public function addMenuPage(): void
    {
        add_options_page(
            __('Swayam AI Chatbot Settings', 'swayam-ai-chatbot'),
            __('Swayam AI Chatbot', 'swayam-ai-chatbot'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->getDefaults(),
            ]
        );

        // Ollama Section
        add_settings_section(
            'swayam_ai_chatbot_ollama',
            __('Ollama Configuration', 'swayam-ai-chatbot'),
            [$this, 'renderOllamaSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'ollama_url',
            __('Ollama URL', 'swayam-ai-chatbot'),
            [$this, 'renderTextField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_ollama',
            ['field' => 'ollama_url', 'placeholder' => 'http://localhost:11434']
        );

        add_settings_field(
            'ollama_model',
            __('Ollama Model', 'swayam-ai-chatbot'),
            [$this, 'renderTextField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_ollama',
            ['field' => 'ollama_model', 'placeholder' => 'llama3.2:3b']
        );

        // Elasticsearch Section
        add_settings_section(
            'swayam_ai_chatbot_elasticsearch',
            __('Elasticsearch Configuration', 'swayam-ai-chatbot'),
            [$this, 'renderElasticsearchSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'elasticsearch_url',
            __('Elasticsearch URL', 'swayam-ai-chatbot'),
            [$this, 'renderTextField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_elasticsearch',
            ['field' => 'elasticsearch_url', 'placeholder' => 'http://localhost:9200']
        );

        add_settings_field(
            'elasticsearch_api_key',
            __('Elasticsearch API Key', 'swayam-ai-chatbot'),
            [$this, 'renderPasswordField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_elasticsearch',
            ['field' => 'elasticsearch_api_key']
        );

        add_settings_field(
            'index_name',
            __('Index Name', 'swayam-ai-chatbot'),
            [$this, 'renderTextField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_elasticsearch',
            ['field' => 'index_name', 'placeholder' => 'wp_rag_content']
        );

        // Content Section
        add_settings_section(
            'swayam_ai_chatbot_content',
            __('Content Indexing', 'swayam-ai-chatbot'),
            [$this, 'renderContentSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'post_types',
            __('Post Types to Index', 'swayam-ai-chatbot'),
            [$this, 'renderPostTypesField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_content'
        );

        // Widget Section
        add_settings_section(
            'swayam_ai_chatbot_widget',
            __('Floating Widget', 'swayam-ai-chatbot'),
            [$this, 'renderWidgetSection'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'widget_enabled',
            __('Enable Floating Widget', 'swayam-ai-chatbot'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_widget',
            ['field' => 'widget_enabled']
        );

        add_settings_field(
            'widget_position',
            __('Widget Position', 'swayam-ai-chatbot'),
            [$this, 'renderSelectField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_widget',
            [
                'field' => 'widget_position',
                'options' => [
                    'bottom-right' => __('Bottom Right', 'swayam-ai-chatbot'),
                    'bottom-left' => __('Bottom Left', 'swayam-ai-chatbot'),
                ],
            ]
        );

        add_settings_field(
            'chat_title',
            __('Chat Window Title', 'swayam-ai-chatbot'),
            [$this, 'renderTextField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_widget',
            ['field' => 'chat_title', 'placeholder' => 'Ask me anything']
        );

        add_settings_field(
            'chat_placeholder',
            __('Input Placeholder Text', 'swayam-ai-chatbot'),
            [$this, 'renderTextField'],
            self::PAGE_SLUG,
            'swayam_ai_chatbot_widget',
            ['field' => 'chat_placeholder', 'placeholder' => 'Type your question...']
        );
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'swayam-ai-chatbot-admin',
            SWAYAM_AI_CHATBOT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SWAYAM_AI_CHATBOT_VERSION
        );

        wp_enqueue_script(
            'swayam-ai-chatbot-admin',
            SWAYAM_AI_CHATBOT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            SWAYAM_AI_CHATBOT_VERSION,
            true
        );

        wp_localize_script('swayam-ai-chatbot-admin', 'swayamAiChatbotAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('swayam_ai_chatbot_admin'),
            'strings' => [
                'syncing' => __('Syncing...', 'swayam-ai-chatbot'),
                'syncComplete' => __('Sync complete!', 'swayam-ai-chatbot'),
                'syncError' => __('Sync failed. Check console for details.', 'swayam-ai-chatbot'),
                'testing' => __('Testing...', 'swayam-ai-chatbot'),
                'testSuccess' => __('Connection successful!', 'swayam-ai-chatbot'),
                'testError' => __('Connection failed.', 'swayam-ai-chatbot'),
            ],
        ]);
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Save Settings', 'swayam-ai-chatbot'));
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Content Sync', 'swayam-ai-chatbot'); ?></h2>
            <div class="swayam-ai-chatbot-sync-section">
                <p>
                    <strong><?php esc_html_e('Last Sync:', 'swayam-ai-chatbot'); ?></strong>
                    <span id="swayam-ai-chatbot-last-sync">
                        <?php echo esc_html($settings['last_sync'] ?: __('Never', 'swayam-ai-chatbot')); ?>
                    </span>
                </p>
                <p>
                    <strong><?php esc_html_e('Indexed Documents:', 'swayam-ai-chatbot'); ?></strong>
                    <span id="swayam-ai-chatbot-indexed-count"><?php echo esc_html((string) $settings['indexed_count']); ?></span>
                </p>
                <p>
                    <button type="button" id="swayam-ai-chatbot-sync-btn" class="button button-primary">
                        <?php esc_html_e('Sync All Content', 'swayam-ai-chatbot'); ?>
                    </button>
                    <button type="button" id="swayam-ai-chatbot-test-btn" class="button">
                        <?php esc_html_e('Test Connections', 'swayam-ai-chatbot'); ?>
                    </button>
                    <span id="swayam-ai-chatbot-sync-status"></span>
                </p>
            </div>

            <hr>

            <h2><?php esc_html_e('Usage', 'swayam-ai-chatbot'); ?></h2>
            <p><?php esc_html_e('Use the shortcode to embed the chatbot on any page or post:', 'swayam-ai-chatbot'); ?></p>
            <code>[swayam_ai_chatbot]</code>
            <p><?php esc_html_e('Optional attributes:', 'swayam-ai-chatbot'); ?></p>
            <code>[swayam_ai_chatbot title="Ask me anything" placeholder="Type your question..."]</code>
        </div>
        <?php
    }

    public function renderOllamaSection(): void
    {
        echo '<p>' . esc_html__('Configure the connection to your Ollama instance running Llama 3.2.', 'swayam-ai-chatbot') . '</p>';
    }

    public function renderElasticsearchSection(): void
    {
        echo '<p>' . esc_html__('Configure the connection to your Elasticsearch instance for vector storage.', 'swayam-ai-chatbot') . '</p>';
    }

    public function renderContentSection(): void
    {
        echo '<p>' . esc_html__('Select which post types should be indexed for the RAG system.', 'swayam-ai-chatbot') . '</p>';
    }

    public function renderWidgetSection(): void
    {
        echo '<p>' . esc_html__('Configure the optional floating chat widget that appears on all pages.', 'swayam-ai-chatbot') . '</p>';
    }

    public function renderTextField(array $args): void
    {
        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        $field = $args['field'];
        $value = $settings[$field] ?? '';
        $placeholder = $args['placeholder'] ?? '';
        ?>
        <input
            type="text"
            id="swayam_ai_chatbot_<?php echo esc_attr($field); ?>"
            name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field . ']'); ?>"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            class="regular-text"
        >
        <?php
    }

    public function renderPasswordField(array $args): void
    {
        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        $field = $args['field'];
        $value = $settings[$field] ?? '';
        ?>
        <input
            type="password"
            id="swayam_ai_chatbot_<?php echo esc_attr($field); ?>"
            name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field . ']'); ?>"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
        >
        <?php
    }

    public function renderCheckboxField(array $args): void
    {
        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        $field = $args['field'];
        $checked = !empty($settings[$field]);
        ?>
        <input
            type="checkbox"
            id="swayam_ai_chatbot_<?php echo esc_attr($field); ?>"
            name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field . ']'); ?>"
            value="1"
            <?php checked($checked); ?>
        >
        <?php
    }

    public function renderSelectField(array $args): void
    {
        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        $field = $args['field'];
        $value = $settings[$field] ?? '';
        $options = $args['options'] ?? [];
        ?>
        <select
            id="swayam_ai_chatbot_<?php echo esc_attr($field); ?>"
            name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field . ']'); ?>"
        >
            <?php foreach ($options as $optValue => $label) : ?>
                <option value="<?php echo esc_attr($optValue); ?>" <?php selected($value, $optValue); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function renderPostTypesField(): void
    {
        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        $selectedTypes = $settings['post_types'] ?? [];
        $postTypes = get_post_types(['public' => true], 'objects');

        foreach ($postTypes as $postType) {
            if ($postType->name === 'attachment') {
                continue;
            }
            $checked = in_array($postType->name, $selectedTypes, true);
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(self::OPTION_NAME . '[post_types][]'); ?>"
                    value="<?php echo esc_attr($postType->name); ?>"
                    <?php checked($checked); ?>
                >
                <?php echo esc_html($postType->labels->name . ' (' . $postType->name . ')'); ?>
            </label>
            <?php
        }
    }

    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];

        $sanitized['ollama_url'] = esc_url_raw($input['ollama_url'] ?? '');
        $sanitized['ollama_model'] = sanitize_text_field($input['ollama_model'] ?? '');
        $sanitized['elasticsearch_url'] = esc_url_raw($input['elasticsearch_url'] ?? '');
        $sanitized['elasticsearch_api_key'] = sanitize_text_field($input['elasticsearch_api_key'] ?? '');
        $sanitized['index_name'] = sanitize_key($input['index_name'] ?? 'wp_rag_content');
        $sanitized['post_types'] = array_map('sanitize_key', $input['post_types'] ?? []);
        $sanitized['widget_enabled'] = !empty($input['widget_enabled']);
        $sanitized['widget_position'] = in_array($input['widget_position'] ?? '', ['bottom-right', 'bottom-left'], true)
            ? $input['widget_position']
            : 'bottom-right';
        $sanitized['chat_title'] = sanitize_text_field($input['chat_title'] ?? '');
        $sanitized['chat_placeholder'] = sanitize_text_field($input['chat_placeholder'] ?? '');

        // Preserve sync stats
        $existing = get_option(self::OPTION_NAME, []);
        $sanitized['last_sync'] = $existing['last_sync'] ?? '';
        $sanitized['indexed_count'] = $existing['indexed_count'] ?? 0;

        return $sanitized;
    }

    public function ajaxTestConnection(): void
    {
        check_ajax_referer('swayam_ai_chatbot_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'swayam-ai-chatbot')]);
        }

        $settings = get_option(self::OPTION_NAME, $this->getDefaults());
        $results = ['ollama' => false, 'elasticsearch' => false];
        $errors = [];

        // Test Ollama
        try {
            $response = wp_remote_get($settings['ollama_url'] . '/api/tags', ['timeout' => 10]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $results['ollama'] = true;
            } else {
                $errors['ollama'] = is_wp_error($response) ? $response->get_error_message() : 'Invalid response';
            }
        } catch (\Exception $e) {
            $errors['ollama'] = $e->getMessage();
        }

        // Test Elasticsearch
        try {
            $es = (new ClientBuilder())::create()
                ->setHosts([$settings['elasticsearch_url']])
                ->setApiKey($settings['elasticsearch_api_key'])
                ->build();

            $info = $es->info();
            if (!empty($info['cluster_name'])) {
                $results['elasticsearch'] = true;
            }
        } catch (\Exception $e) {
            $errors['elasticsearch'] = $e->getMessage();
        }

        wp_send_json_success([
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    private function getDefaults(): array
    {
        return [
            'ollama_url' => 'http://localhost:11434',
            'ollama_model' => 'llama3.2:3b',
            'elasticsearch_url' => 'http://localhost:9200',
            'elasticsearch_api_key' => '',
            'index_name' => 'wp_rag_content',
            'post_types' => ['post', 'page'],
            'widget_enabled' => false,
            'widget_position' => 'bottom-right',
            'chat_title' => 'Ask me anything',
            'chat_placeholder' => 'Type your question...',
            'last_sync' => '',
            'indexed_count' => 0,
        ];
    }
}
