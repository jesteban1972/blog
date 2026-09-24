<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Service/KalimaService.php

namespace App\Service;

use App\Entity\Post;

class KalimaService
{
    /**
     * generates a clean plain-text excerpt for the given post.
     */
    public function fetchExcerpt(Post $post, int $maxLength = 300): string
    {
        $content = $post->getContent() ?? '';

        if (trim($content) === '') {
            return '';
        }

        // 1. decode twice in case of double-escaped entities (&amp;lt;p&amp;gt;)
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. strip all HTML tags cleanly using strip_tags
        $cleanText = strip_tags($content);

        // 3. strip markdown image and link syntax
        $cleanText = (string) preg_replace('/!\[.*?\]\(.*?\)/', '', $cleanText);
        $cleanText = (string) preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $cleanText);

        // 4. strip markdown structural formatting
        $cleanText = (string) preg_replace('/^\s*#{1,6}\s+/m', '', $cleanText);
        $cleanText = (string) preg_replace('/^\s*>\s+/m', '', $cleanText);
        $cleanText = (string) preg_replace('/[*_~`]/', '', $cleanText);

        // 5. collapse whitespace and newlines
        $cleanText = trim((string) preg_replace('/\s+/', ' ', $cleanText));

        if (mb_strlen($cleanText) <= $maxLength) {
            return $cleanText;
        }

        return mb_substr($cleanText, 0, $maxLength) . ' [...]';
    }

    /**
     * extracts embedded image URLs from post content for thumbnail rendering.
     *
     * @return string[] list of image src paths found in post content
     */
    public function extractThumbnails(Post $post): array
    {
        $content = $post->getContent() ?? '';

        if (trim($content) === '') {
            return [];
        }

        // decode entities
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $images = [];

        // 1. match markdown images: ![alt](url)
        if (preg_match_all('/!\[.*?\]\(([^\s\)]+)\)/i', $content, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        // 2. match HTML <img ... src="..." > tags without DOMDocument
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
            $images = array_merge($images, $matches[1]);
        }

        // filter empty paths
        $images = array_filter($images, static fn($url) => !empty(trim($url)));

        return array_values(array_unique($images));
    }
}
