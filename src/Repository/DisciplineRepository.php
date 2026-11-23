<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Discipline;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Discipline>
 */
class DisciplineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Discipline::class);
    }

    /**
     * @return list<Discipline>
     */
    public function findAllOrdered(): array
    {
        /** @var list<Discipline> */
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function slugExists(string $slug): bool
    {
        return $this->count(['slug' => $slug]) > 0;
    }
}
