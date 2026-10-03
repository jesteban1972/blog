<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Repository/CategoriesRepository.php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 *
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function add(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * fetches categories with pagination, order, and optional language filtering.
     */
    public function getCategoriesPaginated(
        int $currentPage = 1,
        int $resultsPerPage = 25,
        string $sortOrder = 'ASC',
        ?string $language = null
    ): array {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.copulationes', 'cop')
            ->addSelect('cop');

        if (strtoupper($sortOrder) === 'DESC') {
            $queryBuilder->orderBy('c.name', 'DESC');
        } else {
            $queryBuilder->orderBy('c.name', 'ASC');
        }

        if ($language !== null) {
            $queryBuilder->andWhere('c.language = :language')
                ->setParameter('language', $language);
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
     * fetch categories sorted alphabetically.
     *
     * @return Category[]
     */
    public function findAllSortedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * fetches only categories that have at least one active post
     * in the specified locale, preventing empty filters.
     *
     * @return Category[]
     */
    public function findActiveCategoriesByLocale(string $locale): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.posts', 'p')
            ->andWhere('p.locale = :locale')
            ->setParameter('locale', $locale)
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
