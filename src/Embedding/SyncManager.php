<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Embedding;

defined('ABSPATH') || exit;

class SyncManager
{
    private const OPTION_NAME = 'swayam_ai_chatbot_settings';

    private ?PostEmbedder $embedder = null;

    public function getEmbedder(): PostEmbedder
    {
        if ($this->embedder === null) {
            $this->embedder = new PostEmbedder();
        }

        return $this->embedder;
    }

    public function syncAll(): array
    {
        $settings = get_option(self::OPTION_NAME, []);
        $postTypes = $settings['post_types'] ?? ['post', 'page'];

        if (empty($postTypes)) {
            return [
                'success' => false,
                'message' => __('No post types selected for indexing.', 'swayam-ai-chatbot'),
            ];
        }

        $posts = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        if (empty($posts)) {
            return [
                'success' => true,
                'indexed_count' => 0,
                'message' => __('No published posts found to index.', 'swayam-ai-chatbot'),
            ];
        }

        $totalDocuments = 0;
        $errors = [];
        $embedder = $this->getEmbedder();

        foreach ($posts as $post) {
            try {
                $count = $embedder->embedPost($post);
                $totalDocuments += $count;
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    /* translators: %1$d: post ID, %2$s: post title, %3$s: error message */
                    __('Failed to index post #%1$d (%2$s): %3$s', 'swayam-ai-chatbot'),
                    $post->ID,
                    $post->post_title,
                    $e->getMessage()
                );
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Swayam AI Chatbot: ' . end($errors));
                }
            }
        }

        // Update settings with sync info
        $settings['last_sync'] = current_time('mysql');
        $settings['indexed_count'] = $embedder->getDocumentCount();
        update_option(self::OPTION_NAME, $settings);

        return [
            'success' => empty($errors),
            'indexed_count' => $settings['indexed_count'],
            'total_posts' => count($posts),
            'total_documents' => $totalDocuments,
            'errors' => $errors,
            'last_sync' => $settings['last_sync'],
        ];
    }

    public function syncPost(int $postId): bool
    {
        $post = get_post($postId);

        if (!$post || $post->post_status !== 'publish') {
            return false;
        }

        $settings = get_option(self::OPTION_NAME, []);
        $postTypes = $settings['post_types'] ?? ['post', 'page'];

        if (!in_array($post->post_type, $postTypes, true)) {
            return false;
        }

        try {
            $embedder = $this->getEmbedder();
            $embedder->embedPost($post);

            // Update indexed count
            $settings['indexed_count'] = $embedder->getDocumentCount();
            update_option(self::OPTION_NAME, $settings);

            return true;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Swayam AI Chatbot: Failed to sync post #' . $postId . ': ' . $e->getMessage());
            }
            return false;
        }
    }

    public function deletePost(int $postId): bool
    {
        try {
            $embedder = $this->getEmbedder();
            $embedder->deletePostDocuments($postId);

            // Update indexed count
            $settings = get_option(self::OPTION_NAME, []);
            $settings['indexed_count'] = $embedder->getDocumentCount();
            update_option(self::OPTION_NAME, $settings);

            return true;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Swayam AI Chatbot: Failed to delete post #' . $postId . ' from index: ' . $e->getMessage());
            }
            return false;
        }
    }

    // WordPress Hooks

    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        // Only sync published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        $settings = get_option(self::OPTION_NAME, []);
        $postTypes = $settings['post_types'] ?? ['post', 'page'];

        if (!in_array($post->post_type, $postTypes, true)) {
            return;
        }

        // Sync in background to avoid blocking the save
        wp_schedule_single_event(time(), 'swayam_ai_chatbot_sync_single_post', [$postId]);
    }

    public function onDeletePost(int $postId): void
    {
        $post = get_post($postId);

        if (!$post) {
            return;
        }

        $settings = get_option(self::OPTION_NAME, []);
        $postTypes = $settings['post_types'] ?? ['post', 'page'];

        if (!in_array($post->post_type, $postTypes, true)) {
            return;
        }

        $this->deletePost($postId);
    }

    public function onStatusChange(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        $settings = get_option(self::OPTION_NAME, []);
        $postTypes = $settings['post_types'] ?? ['post', 'page'];

        if (!in_array($post->post_type, $postTypes, true)) {
            return;
        }

        // Published -> Unpublished: remove from index
        if ($oldStatus === 'publish' && $newStatus !== 'publish') {
            $this->deletePost($post->ID);
        }
        // Unpublished -> Published: add to index
        elseif ($oldStatus !== 'publish' && $newStatus === 'publish') {
            wp_schedule_single_event(time(), 'swayam_ai_chatbot_sync_single_post', [$post->ID]);
        }
    }

    // AJAX Handlers

    public function ajaxSyncAll(): void
    {
        check_ajax_referer('swayam_ai_chatbot_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'swayam-ai-chatbot')]);
        }

        // Increase execution time for large sites (if allowed by server)
        if (function_exists('set_time_limit')) {
            set_time_limit(300); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Necessary for large sync operations
        }

        $result = $this->syncAll();

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %1$d: number of documents indexed, %2$d: number of posts processed */
                    __('Successfully indexed %1$d documents from %2$d posts.', 'swayam-ai-chatbot'),
                    $result['total_documents'],
                    $result['total_posts']
                ),
                'indexed_count' => $result['indexed_count'],
                'last_sync' => $result['last_sync'],
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Sync completed with errors. Check the error log for details.', 'swayam-ai-chatbot'),
                'errors' => $result['errors'],
                'indexed_count' => $result['indexed_count'],
                'last_sync' => $result['last_sync'],
            ]);
        }
    }
}

// Register WP Cron action for background syncing
add_action('swayam_ai_chatbot_sync_single_post', function (int $postId) {
    $syncManager = new SyncManager();
    $syncManager->syncPost($postId);
});
