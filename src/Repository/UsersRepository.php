<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Repository/UsersRepository.php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsersRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Sourced from UserLoaderInterface.
     * In the Shadow User model, the 'identifier' passed here is the
     * Global Integer ID extracted from the JWT.
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->find((int) $identifier);
    }

//    #[Route('/', name: 'app_users_index', methods: ['GET'])]
//    public function index(): Response
//    {
//        $users = $this->usersRepository->findAll();
//        dump($users); // Add this to see the content
//        die(); // Stop execution for debugging
//
//        return $this->render('templates/users/index.html.twig', [
//            'users' => $users,
//        ]);
//    }

    public function add(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getAllUsersPaginated(int $currentPage = 1, int $resultsPerPage = 10): array
    {
        // query to get all users ordered by id:
        $queryBuilder = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC');

        $query = $queryBuilder->getQuery();

        // paginate the query results:
        $paginator = $this->paginate($query, $currentPage, $resultsPerPage);

        return [
            'paginator' => $paginator,
            'query' => $query,
        ];
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function paginate($dql, $page = 1, $limit = 3): Paginator
    {
        $paginator = new Paginator($dql);

        $paginator->getQuery()
            ->setFirstResult($limit * ($page - 1)) // offset
            ->setMaxResults($limit); // limit

        return $paginator;
    }
}
