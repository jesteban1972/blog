<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Service/KalimaService.php

namespace App\Service;

use App\Entity\Post;
use Symfony\Component\String\Slugger\AsciiSlugger;

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
     * generates a clean, URL-safe ASCII slug handling special scripts (Arabic, Greek, etc.).
     */
    public function slugificate(string $text): string
    {
        $language = $this->detectLanguageOrScript($text);

        // handle specific polytonic / script transliterations
        $text = $this->transliterateText($text, $language);

        // leverage symfony's ASCII slugger for general unicode cleanup
        $slugger = new AsciiSlugger();

        return $slugger->slug($text)->lower()->toString();
    }


    ////////////////////////////////////////////////////////////////////////////////
    /// helpers

    private function detectLanguageOrScript(string $text): ?string
    {
        if (preg_match('/\p{Arabic}/u', $text)) {
            return 'ar';
        }

        if (preg_match('/\p{Greek}/u', $text)) {
            return 'el';
        }

        return null;
    }

    private function transliterateText(string $text, ?string $language = null): string
    {
        if ($language === 'ar') {
            $text = str_replace('اَ', 'ا', $text);
        }

        $roughBreathingMap = [
            'Ἡ' => 'i', 'Ἁ' => 'a', 'Ἑ' => 'e', 'Ἱ' => 'i', 'Ὁ' => 'o', 'Ὑ' => 'y', 'Ὡ' => 'o',
            'ἡ' => 'i', 'ἁ' => 'a', 'ἑ' => 'e', 'ἱ' => 'i', 'ὁ' => 'o', 'ὑ' => 'y', 'ὡ' => 'o',
        ];
        $text = strtr($text, $roughBreathingMap);

        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
        }

        return $text;
    }
}
