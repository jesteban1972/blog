<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Repository/CopulationesRepository.php

namespace App\Repository;

use App\Entity\Copulatio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Copulatio>
 *
 * @method Copulatio|null find($id, $lockMode = null, $lockVersion = null)
 * @method Copulatio|null findOneBy(array $criteria, array $orderBy = null)
 * @method Copulatio[]    findAll()
 * @method Copulatio[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CopulationesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Copulatio::class);
    }

    public function add(Copulatio $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Copulatio $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
