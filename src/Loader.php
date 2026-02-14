<?php

declare(strict_types=1);

namespace SwayamAiChatbot;

defined('ABSPATH') || exit;

use SwayamAiChatbot\Admin\SettingsPage;

class Loader
{
    private ?SettingsPage $settingsPage = null;

    public function init(): void
    {
        $this->initComponents();
        $this->registerHooks();
    }

    private function initComponents(): void
    {
        // Admin components
        if (is_admin()) {
            $this->settingsPage = new SettingsPage();
        }
    }

    private function registerHooks(): void
    {
        // Admin hooks
        if ($this->settingsPage) {
            add_action('admin_menu', [$this->settingsPage, 'addMenuPage']);
            add_action('admin_init', [$this->settingsPage, 'registerSettings']);
            add_action('admin_enqueue_scripts', [$this->settingsPage, 'enqueueAdminAssets']);
        }

        // Sync hooks
        add_action('save_post', [$this->syncManager, 'onSavePost'], 10, 3);
        add_action('delete_post', [$this->syncManager, 'onDeletePost']);
        add_action('transition_post_status', [$this->syncManager, 'onStatusChange'], 10, 3);

        // AJAX handlers for admin
        add_action('wp_ajax_swayam_ai_chatbot_sync_all', [$this->syncManager, 'ajaxSyncAll']);
        add_action('wp_ajax_swayam_ai_chatbot_test_connection', [$this->settingsPage, 'ajaxTestConnection']);

        // REST API
        add_action('rest_api_init', [$this->chatEndpoint, 'registerRoutes']);

        // Frontend hooks
        add_shortcode('swayam_ai_chatbot', [$this->shortcode, 'render']);
        add_action('wp_enqueue_scripts', [$this->shortcode, 'enqueueAssets']);

        // Floating widget
        $settings = get_option('swayam_ai_chatbot_settings', []);
        if (!empty($settings['widget_enabled'])) {
            add_action('wp_footer', [$this->floatingWidget, 'render']);
        }
    }

    public function getSettings(): array
    {
        return get_option('swayam_ai_chatbot_settings', []);
    }
}
