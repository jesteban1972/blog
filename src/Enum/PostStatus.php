<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Enum/PostStatus.php

namespace App\Enum;

use App\Entity\Post;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';

    /**
     * derives the status dynamically from a Post instance.
     */
    public static function fromPost(Post $post): self
    {
        $publishedAt = $post->getPublishedAt();

        if ($publishedAt === null) {
            return self::DRAFT;
        }

        if ($publishedAt > new \DateTime()) {
            return self::SCHEDULED;
        }

        return self::PUBLISHED;
    }

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'status.draft',
            self::SCHEDULED => 'status.scheduled',
            self::PUBLISHED => 'status.published',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::DRAFT => 'badge-secondary',
            self::SCHEDULED => 'badge-warning',
            self::PUBLISHED => 'badge-success',
        };
    }
}
