<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Embedding;

defined('ABSPATH') || exit;

use Elastic\Elasticsearch\ClientBuilder;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Elasticsearch\ElasticsearchVectorStore;
use LLPhant\OllamaConfig;

class PostEmbedder
{
    private array $settings;
    private ContentExtractor $extractor;
    private ?OllamaEmbeddingGenerator $embeddingGenerator = null;
    private ?ElasticsearchVectorStore $vectorStore = null;

    public function __construct()
    {
        $this->settings = get_option('swayam_ai_chatbot_settings', []);
        $this->extractor = new ContentExtractor();
    }

    public function embedPost(\WP_Post $post): int
    {
        $content = $this->extractor->extractFromPost($post);

        if (empty($content)) {
            return 0;
        }

        // Split content into chunks
        $chunks = $this->extractor->splitIntoChunks($content, 800, 100);

        if (empty($chunks)) {
            return 0;
        }

        // Create documents for each chunk
        $documents = [];
        foreach ($chunks as $index => $chunk) {
            $doc = new Document();
            $doc->content = $chunk;
            $doc->sourceType = $post->post_type;
            $doc->hash = md5($post->ID . '_' . $index . '_' . $chunk);
            $doc->chunkNumber = $index;

            // Store metadata as JSON in sourceName for retrieval later
            $doc->sourceName = json_encode([
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_date' => $post->post_date,
                'permalink' => get_permalink($post),
                'chunk_index' => $index,
                'total_chunks' => count($chunks),
            ]);

            // formattedContent is used by LLPhant for embeddings - add title context
            $doc->formattedContent = $post->post_title . "\n\n" . $chunk;

            $documents[] = $doc;
        }

        // Generate embeddings
        $embeddingGenerator = $this->getEmbeddingGenerator();
        $embeddedDocuments = $embeddingGenerator->embedDocuments($documents);

        // Store in Elasticsearch
        $vectorStore = $this->getVectorStore();

        // Delete existing documents for this post first
        $this->deletePostDocuments($post->ID);

        // Add new documents
        $vectorStore->addDocuments($embeddedDocuments);

        return count($embeddedDocuments);
    }

    public function deletePostDocuments(int $postId): void
    {
        try {
            $es = $this->getElasticsearchClient();
            $indexName = $this->settings['index_name'] ?? 'wp_rag_content';

            // Check if index exists
            if (!$es->indices()->exists(['index' => $indexName])->asBool()) {
                return;
            }

            // Delete documents with matching post_id in formattedContent
            $es->deleteByQuery([
                'index' => $indexName,
                'body' => [
                    'query' => [
                        'match' => [
                            'formattedContent' => '"post_id":' . $postId,
                        ],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Swayam AI Chatbot: Failed to delete documents for post ' . $postId . ': ' . $e->getMessage());
            }
        }
    }

    public function getDocumentCount(): int
    {
        try {
            $es = $this->getElasticsearchClient();
            $indexName = $this->settings['index_name'] ?? 'wp_rag_content';

            if (!$es->indices()->exists(['index' => $indexName])->asBool()) {
                return 0;
            }

            $response = $es->count(['index' => $indexName]);
            return $response['count'] ?? 0;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Swayam AI Chatbot: Failed to get document count: ' . $e->getMessage());
            }
            return 0;
        }
    }

    private function getOllamaConfig(): OllamaConfig
    {
        $config = new OllamaConfig();

        // Ollama URL needs /api/ suffix for LLPhant compatibility
        $baseUrl = rtrim($this->settings['ollama_url'] ?? 'http://localhost:11434', '/');
        if (!str_ends_with($baseUrl, '/api')) {
            $baseUrl .= '/api/';
        }

        $config->url = $baseUrl;
        $config->model = $this->settings['ollama_model'] ?? 'llama3.2:3b';

        return $config;
    }

    private function getEmbeddingGenerator(): OllamaEmbeddingGenerator
    {
        if ($this->embeddingGenerator === null) {
            $this->embeddingGenerator = new OllamaEmbeddingGenerator($this->getOllamaConfig());
        }

        return $this->embeddingGenerator;
    }

    private function getElasticsearchClient()
    {
        return (new ClientBuilder())::create()
            ->setHosts([$this->settings['elasticsearch_url'] ?? 'http://localhost:9200'])
            ->setApiKey($this->settings['elasticsearch_api_key'] ?? '')
            ->build();
    }

    private function getVectorStore(): ElasticsearchVectorStore
    {
        if ($this->vectorStore === null) {
            $es = $this->getElasticsearchClient();
            $indexName = $this->settings['index_name'] ?? 'wp_rag_content';
            $this->vectorStore = new ElasticsearchVectorStore($es, $indexName);
        }

        return $this->vectorStore;
    }
}
