<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Service/EikonService.php

namespace App\Service;

use App\Entity\Post;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EikonService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/images')]
        private readonly string $uploadsDirectory = ''
    ) {}

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
