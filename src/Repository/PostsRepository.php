<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Repository/PostsRepository.php

namespace App\Repository;

use App\Entity\Category;
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
     * includes copulationes and category joins to prevent n+1 queries.
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
            ->leftJoin('p.copulationes', 'cop')
            ->leftJoin('cop.category', 'c')
            ->addSelect('cop', 'c');

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
            $queryBuilder->andWhere('c.id = :categoryId')
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
     * fetches a single post by its slug along with its copulationes and category metadata.
     */
    public function findOneBySlugWithCategory(string $slug): ?Post
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.copulationes', 'cop')
            ->leftJoin('cop.category', 'c')
            ->addSelect('cop', 'c')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * counts total posts assigned to a given category via copulationes.
     */
    public function countByCategory(Category $category): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin('p.copulationes', 'cop')
            ->where('cop.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * fetches paginated posts assigned to a given category along with copulationes.
     */
    public function findByCategoryPaginated(Category $category, int $page = 1, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.copulationes', 'cop')
            ->leftJoin('p.copulationes', 'all_cop')
            ->leftJoin('all_cop.category', 'c')
            ->addSelect('all_cop', 'c')
            ->where('cop.category = :category')
            ->setParameter('category', $category)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
