<?php

declare(strict_types=1);

namespace SwayamAiChatbot\Embedding;

defined('ABSPATH') || exit;

class ContentExtractor
{
    public function extractFromPost(\WP_Post $post): string
    {
        $parts = [];

        // Add title
        if (!empty($post->post_title)) {
            $parts[] = $post->post_title;
        }

        // Add excerpt if available
        if (!empty($post->post_excerpt)) {
            $parts[] = $this->cleanText($post->post_excerpt);
        }

        // Process content
        $content = $this->processContent($post->post_content);
        if (!empty($content)) {
            $parts[] = $content;
        }

        return implode("\n\n", $parts);
    }

    private function processContent(string $content): string
    {
        // Parse Gutenberg blocks if present
        if (has_blocks($content)) {
            $content = $this->extractFromBlocks($content);
        }

        // Strip shortcodes
        $content = strip_shortcodes($content);

        // Clean HTML and text
        return $this->cleanText($content);
    }

    private function extractFromBlocks(string $content): string
    {
        $blocks = parse_blocks($content);
        $text = [];

        foreach ($blocks as $block) {
            $blockText = $this->extractTextFromBlock($block);
            if (!empty($blockText)) {
                $text[] = $blockText;
            }
        }

        return implode("\n\n", $text);
    }

    private function extractTextFromBlock(array $block): string
    {
        $text = [];

        // Skip empty blocks
        if (empty($block['blockName']) && empty(trim($block['innerHTML'] ?? ''))) {
            return '';
        }

        // Handle specific block types
        switch ($block['blockName']) {
            case 'core/paragraph':
            case 'core/heading':
            case 'core/list':
            case 'core/list-item':
            case 'core/quote':
            case 'core/pullquote':
            case 'core/verse':
            case 'core/preformatted':
            case 'core/code':
            case 'core/table':
                $text[] = $this->cleanText($block['innerHTML'] ?? '');
                break;

            case 'core/columns':
            case 'core/column':
            case 'core/group':
            case 'core/cover':
            case 'core/media-text':
                // Process inner blocks
                if (!empty($block['innerBlocks'])) {
                    foreach ($block['innerBlocks'] as $innerBlock) {
                        $innerText = $this->extractTextFromBlock($innerBlock);
                        if (!empty($innerText)) {
                            $text[] = $innerText;
                        }
                    }
                }
                break;

            case 'core/image':
            case 'core/gallery':
                // Extract alt text and captions
                if (!empty($block['attrs']['alt'])) {
                    $text[] = '[Image: ' . $block['attrs']['alt'] . ']';
                }
                $caption = $this->cleanText($block['innerHTML'] ?? '');
                if (!empty($caption)) {
                    $text[] = $caption;
                }
                break;

            case 'core/embed':
            case 'core/video':
            case 'core/audio':
                // Extract caption if available
                $caption = $this->cleanText($block['innerHTML'] ?? '');
                if (!empty($caption)) {
                    $text[] = $caption;
                }
                break;

            default:
                // For unknown blocks, try to extract any text content
                if (!empty($block['innerHTML'])) {
                    $cleaned = $this->cleanText($block['innerHTML']);
                    if (!empty($cleaned)) {
                        $text[] = $cleaned;
                    }
                }
                // Also process inner blocks
                if (!empty($block['innerBlocks'])) {
                    foreach ($block['innerBlocks'] as $innerBlock) {
                        $innerText = $this->extractTextFromBlock($innerBlock);
                        if (!empty($innerText)) {
                            $text[] = $innerText;
                        }
                    }
                }
                break;
        }

        return implode("\n", array_filter($text));
    }

    private function cleanText(string $text): string
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove HTML tags but preserve line breaks
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $text);
        $text = wp_strip_all_tags($text);

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    public function splitIntoChunks(string $text, int $chunkSize = 800, int $overlap = 100): array
    {
        if (empty($text) || strlen($text) <= $chunkSize) {
            return !empty($text) ? [$text] : [];
        }

        $chunks = [];
        $sentences = $this->splitIntoSentences($text);
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            // If adding this sentence would exceed chunk size
            if (strlen($currentChunk) + strlen($sentence) > $chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);

                // Start new chunk with overlap
                if ($overlap > 0) {
                    // Get last portion of current chunk for overlap
                    $overlapText = substr($currentChunk, -$overlap);
                    $currentChunk = $overlapText . ' ' . $sentence;
                } else {
                    $currentChunk = $sentence;
                }
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
            }
        }

        // Add remaining chunk
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    private function splitIntoSentences(string $text): array
    {
        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $sentences ?: [$text];
    }
}
