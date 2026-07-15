<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Repository/CommunityCommentsRepository.php

namespace App\Repository;

use App\Entity\CommunityComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityComment>
 */
class CommunityCommentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityComment::class);
    }

    /**
     * returns comments for a specific post sorted by chronological appearance.
     *
     * @return CommunityComment[]
     */
    public function findCommentsByPostId(int $postId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.post = :postId')
            ->setParameter('postId', $postId)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * fetches all comments grouped by post_id.
     *
     * @return array<int, CommunityComment[]>
     */
    public function findCommentsGroupedByPostId(): array
    {
        // 1. query all records ordered by post context alignment
        $comments = $this->createQueryBuilder('c')
            ->orderBy('c.post', 'ASC')
            ->addOrderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // 2. dynamically map comments into an array grouped by post_id
        $grouped = [];
        foreach ($comments as $comment) {
            $postId = $comment->getPost()->getId();
            $grouped[$postId][] = $comment;
        }

        return $grouped;
    }
}
