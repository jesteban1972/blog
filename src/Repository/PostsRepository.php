<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Repository/PostsRepository.php

namespace App\Repository;

use App\Entity\Post;
use App\Enum\PostDiffusio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function add(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * fetches posts with pagination, order, language, and diffusio level filtering.
     * includes category and user joins to prevent n+1 queries.
     */
    public function getPostsPaginated(
        int $currentPage = 1,
        int $resultsPerPage = 25,
        string $sortOrder = 'DESC',
        ?string $language = null,
        ?PostDiffusio $diffusio = null,
        ?string $categoryId = null
    ): array {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.user', 'u')
            ->addSelect('c', 'u');

        if (strtoupper($sortOrder) === 'ASC') {
            $queryBuilder->orderBy('p.createdAt', 'ASC');
        } else {
            $queryBuilder->orderBy('p.createdAt', 'DESC');
        }

        if ($language !== null) {
            $queryBuilder->andWhere('p.language = :language')
                ->setParameter('language', $language);
        }

        if ($diffusio !== null) {
            $queryBuilder->andWhere('p.diffusio = :diffusio')
                ->setParameter('diffusio', $diffusio);
        }

        if ($categoryId !== null) {
            $queryBuilder->andWhere('p.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        $query = $queryBuilder->getQuery();
        $paginator = $this->paginate($query, $currentPage, $resultsPerPage);

        return [
            'paginator' => $paginator,
            'query' => $query,
        ];
    }

    public function paginate($dql, int $page = 1, int $limit = 25): Paginator
    {
        $paginator = new Paginator($dql);

        $paginator->getQuery()
            ->setFirstResult($limit * ($page - 1)) // offset
            ->setMaxResults($limit); // limit

        return $paginator;
    }

    /**
     * fetches a single post by its slug along with its category metadata.
     */
    public function findOneBySlugWithCategory(string $slug): ?Post
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
