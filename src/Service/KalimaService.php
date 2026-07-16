<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Service/KalimaService.php

namespace App\Service;

use App\Entity\Post;

class KalimaService
{
    /**
     * generates a clean excerpt for the given post.
     */
    public function fetchExcerpt(Post $post, int $maxLength = 300): string
    {
        $content = $post->getContent();

        // if the text is short enough, return it directly
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        // truncate cleanly on characters and append the trailing brackets
        return mb_substr($content, 0, $maxLength) . ' [...]';
    }
}
