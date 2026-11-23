<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ClassSession;
use App\Entity\Studio;
use App\Entity\User;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassSession>
 */
class ClassSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassSession::class);
    }

    /**
     * Sessions of a studio starting in [$from, $to[, ordered chronologically.
     *
     * @return list<ClassSession>
     */
    public function findForStudioBetween(Studio $studio, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<ClassSession> */
        return $this->createStudioQueryBuilder($studio)
            ->andWhere('cs.startsAt >= :from')
            ->andWhere('cs.startsAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ClassSession>
     */
    public function findUpcomingForStudio(Studio $studio, \DateTimeImmutable $now, int $limit = 100): array
    {
        /** @var list<ClassSession> */
        return $this->createStudioQueryBuilder($studio)
            ->andWhere('cs.startsAt >= :now')
            ->setParameter('now', $now)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Upcoming, not cancelled sessions of all the studios owned by a user.
     *
     * @return list<ClassSession>
     */
    public function findUpcomingForOwner(User $owner, \DateTimeImmutable $now): array
    {
        /** @var list<ClassSession> */
        return $this->createQueryBuilder('cs')
            ->innerJoin('cs.activity', 'a')
            ->innerJoin('a.studio', 's')
            ->andWhere('s.owner = :owner')
            ->andWhere('cs.cancelled = false')
            ->andWhere('cs.startsAt > :now')
            ->setParameter('owner', $owner)
            ->setParameter('now', $now)
            ->orderBy('cs.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Next bookable sessions across all published studios (home page).
     *
     * @return list<ClassSession>
     */
    public function findNextPublished(\DateTimeImmutable $now, int $limit = 6): array
    {
        /** @var list<ClassSession> */
        return $this->createQueryBuilder('cs')
            ->addSelect('a', 's', 'd')
            ->innerJoin('cs.activity', 'a')
            ->innerJoin('a.studio', 's')
            ->innerJoin('a.discipline', 'd')
            ->andWhere('s.published = true')
            ->andWhere('cs.cancelled = false')
            ->andWhere('cs.startsAt > :now')
            ->setParameter('now', $now)
            ->orderBy('cs.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Reloads a session with a row-level write lock (SELECT ... FOR UPDATE).
     * Must be called inside a transaction.
     */
    public function findForUpdate(int $id): ?ClassSession
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Number of confirmed bookings, indexed by session id.
     *
     * @param list<ClassSession> $sessions
     *
     * @return array<int, int>
     */
    public function countConfirmedBookings(array $sessions): array
    {
        if ([] === $sessions) {
            return [];
        }

        /** @var list<array{id: int, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('cs')
            ->select('cs.id AS id', 'COUNT(b.id) AS total')
            ->innerJoin('cs.bookings', 'b', 'WITH', 'b.status = :confirmed')
            ->andWhere('cs IN (:sessions)')
            ->setParameter('confirmed', BookingStatus::Confirmed)
            ->setParameter('sessions', $sessions)
            ->groupBy('cs.id')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }

    private function createStudioQueryBuilder(Studio $studio): QueryBuilder
    {
        return $this->createQueryBuilder('cs')
            ->addSelect('a', 'd')
            ->innerJoin('cs.activity', 'a')
            ->innerJoin('a.discipline', 'd')
            ->andWhere('a.studio = :studio')
            ->setParameter('studio', $studio)
            ->orderBy('cs.startsAt', 'ASC');
    }
}
