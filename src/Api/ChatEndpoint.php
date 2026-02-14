<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Api;

defined('ABSPATH') || exit;

use SwayamAiChatbot\Chat\QuestionAnswering;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ChatEndpoint
{
    private const NAMESPACE = 'swayam-ai-chatbot/v1';

    public function registerRoutes(): void
    {
        // Public chat endpoint - intentionally accessible without authentication
        // to allow all site visitors to use the chatbot. Rate limiting is applied.
        register_rest_route(self::NAMESPACE, '/chat', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleChatRequest'],
            'permission_callback' => '__return_true',
            'args' => [
                'question' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return !empty(trim($value));
                    },
                ],
                'session_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Public health check endpoint - intentionally accessible without authentication.
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handleHealthCheck'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handleChatRequest(WP_REST_Request $request): WP_REST_Response
    {
        $question = $request->get_param('question');
        $sessionId = $request->get_param('session_id');

        // Rate limiting (simple implementation)
        $rateLimitKey = 'swayam_ai_chatbot_rate_' . $this->getClientIdentifier();
        $rateLimit = get_transient($rateLimitKey);

        if ($rateLimit !== false && $rateLimit >= 10) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Too many requests. Please wait a moment before trying again.', 'swayam-ai-chatbot'),
            ], 429);
        }

        // Increment rate limit
        set_transient($rateLimitKey, ($rateLimit ?: 0) + 1, 60);

        // Process the question
        $qa = new QuestionAnswering();
        $result = $qa->answerQuestion($question);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'answer' => $result['answer'],
                'sources' => $result['sources'],
                'session_id' => $sessionId ?: wp_generate_uuid4(),
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'error' => $result['error'],
        ], 500);
    }

    public function handleHealthCheck(): WP_REST_Response
    {
        $settings = get_option('swayam_ai_chatbot_settings', []);

        return new WP_REST_Response([
            'status' => 'ok',
            'plugin' => 'swayam-ai-chatbot',
            'version' => SWAYAM_AI_CHATBOT_VERSION,
            'indexed_count' => $settings['indexed_count'] ?? 0,
            'last_sync' => $settings['last_sync'] ?? null,
        ], 200);
    }

    private function getClientIdentifier(): string
    {
        // Use IP address or logged-in user ID for rate limiting
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? filter_var(wp_unslash($_SERVER['REMOTE_ADDR']), FILTER_VALIDATE_IP) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $remote_addr = $remote_addr ? $remote_addr : 'unknown';
        return 'ip_' . md5($remote_addr);
    }
}
