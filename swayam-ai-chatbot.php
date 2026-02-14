<?php
/**
 * Plugin Name: Swayam AI Chatbot
 * Description: AI-powered chatbot using RAG (Retrieval-Augmented Generation) with LLPhant, Llama 3.2, and Elasticsearch
 * Version: 1.0.0
 * Author: Sagar Deshmukh
 * Author URI: https://github.com/sagarsdeshmukh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: swayam-ai-chatbot
 * Requires at least: 6.0
 * Requires PHP: 8.2
 */

defined('ABSPATH') || exit;

define('SWAYAM_AI_CHATBOT_VERSION', '1.0.0');
define('SWAYAM_AI_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SWAYAM_AI_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SWAYAM_AI_CHATBOT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader
$swayam_ai_chatbot_autoloader = SWAYAM_AI_CHATBOT_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($swayam_ai_chatbot_autoloader)) {
    require_once $swayam_ai_chatbot_autoloader;
}

// Initialize plugin
add_action('plugins_loaded', function () {
    if (!class_exists('SwayamAiChatbot\Loader')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Swayam AI Chatbot: Please run "composer install" in the plugin directory.', 'swayam-ai-chatbot');
            echo '</p></div>';
        });
        return;
    }

    $loader = new SwayamAiChatbot\Loader();
    $loader->init();
});

// Activation hook
register_activation_hook(__FILE__, function () {
    // Set default options
    $defaults = [
        'ollama_url' => 'http://localhost:11434',
        'ollama_model' => 'llama3.2:3b',
        'elasticsearch_url' => 'http://localhost:9200',
        'elasticsearch_api_key' => '',
        'index_name' => 'wp_rag_content',
        'post_types' => ['post', 'page'],
        'widget_enabled' => false,
        'widget_position' => 'bottom-right',
        'last_sync' => '',
        'indexed_count' => 0,
    ];

    if (!get_option('swayam_ai_chatbot_settings')) {
        add_option('swayam_ai_chatbot_settings', $defaults);
    }

    // Flush rewrite rules for REST API
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
