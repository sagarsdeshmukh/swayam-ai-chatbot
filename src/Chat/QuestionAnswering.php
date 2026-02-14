<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Chat;

defined('ABSPATH') || exit;

use Elastic\Elasticsearch\ClientBuilder;
use LLPhant\Chat\OllamaChat;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Elasticsearch\ElasticsearchVectorStore;
use LLPhant\OllamaConfig;
use LLPhant\Query\SemanticSearch\QuestionAnswering as LLPhantQA;

class QuestionAnswering
{
    private array $settings;
    private ?OllamaChat $chat = null;
    private ?OllamaEmbeddingGenerator $embeddingGenerator = null;
    private ?ElasticsearchVectorStore $vectorStore = null;
    private ?LLPhantQA $qa = null;
    private array $retrievedDocuments = [];

    public function __construct()
    {
        $this->settings = get_option('swayam_ai_chatbot_settings', []);
    }

    public function answerQuestion(string $question): array
    {
        if (empty(trim($question))) {
            return [
                'success' => false,
                'error' => __('Please provide a question.', 'swayam-ai-chatbot'),
            ];
        }

        try {
            $qa = $this->getQA();
            $answer = $qa->answerQuestion($question);
            $this->retrievedDocuments = $qa->getRetrievedDocuments();

            // Format sources from retrieved documents
            $sources = $this->formatSources($this->retrievedDocuments);

            return [
                'success' => true,
                'answer' => $answer,
                'sources' => $sources,
            ];
        } catch (\Exception $e) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Swayam AI Chatbot: Error answering question: ' . $e->getMessage());
            }

            return [
                'success' => false,
                'error' => __('An error occurred while processing your question. Please try again.', 'swayam-ai-chatbot'),
                'debug' => WP_DEBUG ? $e->getMessage() : null,
            ];
        }
    }

    private function formatSources(array $documents): array
    {
        $sources = [];
        $seenUrls = [];

        foreach ($documents as $doc) {
            // Parse metadata from sourceName (stored as JSON)
            $metadata = [];
            if (!empty($doc->sourceName)) {
                $decoded = json_decode($doc->sourceName, true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $url = $metadata['permalink'] ?? '';

            // Skip duplicates
            if (empty($url) || in_array($url, $seenUrls, true)) {
                continue;
            }
            $seenUrls[] = $url;

            $sources[] = [
                'title' => $metadata['post_title'] ?? '',
                'url' => $url,
                'excerpt' => $this->truncateText($doc->content, 150),
                'post_type' => $metadata['post_type'] ?? '',
            ];
        }

        return $sources;
    }

    private function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        $truncated = substr($text, 0, $length);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    public function getRetrievedDocuments(): array
    {
        return $this->retrievedDocuments;
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
        $config->modelOptions = [
            'options' => [
                'temperature' => 0,
            ],
        ];

        return $config;
    }

    private function getChat(): OllamaChat
    {
        if ($this->chat === null) {
            $this->chat = new OllamaChat($this->getOllamaConfig());
        }

        return $this->chat;
    }

    private function getEmbeddingGenerator(): OllamaEmbeddingGenerator
    {
        if ($this->embeddingGenerator === null) {
            $this->embeddingGenerator = new OllamaEmbeddingGenerator($this->getOllamaConfig());
        }

        return $this->embeddingGenerator;
    }

    private function getVectorStore(): ElasticsearchVectorStore
    {
        if ($this->vectorStore === null) {
            $es = (new ClientBuilder())::create()
                ->setHosts([$this->settings['elasticsearch_url'] ?? 'http://localhost:9200'])
                ->setApiKey($this->settings['elasticsearch_api_key'] ?? '')
                ->build();

            $indexName = $this->settings['index_name'] ?? 'wp_rag_content';
            $this->vectorStore = new ElasticsearchVectorStore($es, $indexName);
        }

        return $this->vectorStore;
    }

    private function getQA(): LLPhantQA
    {
        if ($this->qa === null) {
            $this->qa = new LLPhantQA(
                $this->getVectorStore(),
                $this->getEmbeddingGenerator(),
                $this->getChat()
            );
        }

        return $this->qa;
    }
}
