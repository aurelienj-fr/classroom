<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Discipline;
use App\Entity\Studio;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Studio>
 */
class StudioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Studio::class);
    }

    /**
     * Published studios matching an optional city (prefix, case-insensitive) and discipline.
     *
     * The disciplines are fetch-joined, so the limit is applied through a Paginator: a plain
     * LIMIT would count SQL rows (one per studio and discipline) instead of studios.
     *
     * @return list<Studio>
     */
    public function searchPublished(?string $city, ?Discipline $discipline, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('d')
            ->leftJoin('s.disciplines', 'd')
            ->andWhere('s.published = true')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $city && '' !== trim($city)) {
            $term = addcslashes(trim($city), '%_\\');
            $qb->andWhere('s.city LIKE :city OR s.postalCode LIKE :city')
                ->setParameter('city', $term.'%');
        }

        if (null !== $discipline) {
            $sub = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(Studio::class, 's2')
                ->innerJoin('s2.disciplines', 'd2')
                ->where('s2 = s')
                ->andWhere('d2 = :discipline');
            $qb->andWhere($qb->expr()->exists($sub->getDQL()))
                ->setParameter('discipline', $discipline);
        }

        /** @var Paginator<Studio> $paginator */
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return array_values(iterator_to_array($paginator));
    }

    public function findOnePublishedBySlug(string $slug): ?Studio
    {
        /** @var Studio|null */
        return $this->createQueryBuilder('s')
            ->addSelect('d')
            ->leftJoin('s.disciplines', 'd')
            ->andWhere('s.slug = :slug')
            ->andWhere('s.published = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Studio>
     */
    public function findManagedBy(User $user): array
    {
        $qb = $this->createQueryBuilder('s')->orderBy('s.name', 'ASC');

        if (!\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            $qb->andWhere('s.owner = :owner')->setParameter('owner', $user);
        }

        /** @var list<Studio> */
        return $qb->getQuery()->getResult();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.slug = :slug')
            ->setParameter('slug', $slug);

        if (null !== $excludeId) {
            $qb->andWhere('s.id <> :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Distinct cities of published studios, for search suggestions.
     *
     * @return list<string>
     */
    public function findPublishedCities(): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('s')
            ->select('DISTINCT s.city')
            ->andWhere('s.published = true')
            ->orderBy('s.city', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
