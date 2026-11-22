<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function countConfirmedForSession(ClassSession $session): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.session = :session')
            ->andWhere('b.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', BookingStatus::Confirmed)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForMemberAndSession(User $member, ClassSession $session): ?Booking
    {
        return $this->findOneBy(['member' => $member, 'session' => $session]);
    }

    /**
     * @return list<Booking>
     */
    public function findUpcomingForMember(User $member, \DateTimeImmutable $now): array
    {
        /** @var list<Booking> */
        return $this->createMemberQueryBuilder($member)
            ->andWhere('cs.startsAt >= :now')
            ->setParameter('now', $now)
            ->orderBy('cs.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Booking>
     */
    public function findPastForMember(User $member, \DateTimeImmutable $now, int $limit = 30): array
    {
        /** @var list<Booking> */
        return $this->createMemberQueryBuilder($member)
            ->andWhere('cs.startsAt < :now')
            ->setParameter('now', $now)
            ->orderBy('cs.startsAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Ids of the given sessions the member holds a confirmed booking for.
     *
     * @param list<ClassSession> $sessions
     *
     * @return list<int>
     */
    public function findConfirmedSessionIds(User $member, array $sessions): array
    {
        if ([] === $sessions) {
            return [];
        }

        /** @var list<int|string> $ids */
        $ids = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.session)')
            ->andWhere('b.member = :member')
            ->andWhere('b.status = :status')
            ->andWhere('b.session IN (:sessions)')
            ->setParameter('member', $member)
            ->setParameter('status', BookingStatus::Confirmed)
            ->setParameter('sessions', $sessions)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $ids);
    }

    private function createMemberQueryBuilder(User $member): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('b')
            ->addSelect('cs', 'a', 's')
            ->innerJoin('b.session', 'cs')
            ->innerJoin('cs.activity', 'a')
            ->innerJoin('a.studio', 's')
            ->andWhere('b.member = :member')
            ->setParameter('member', $member);
    }
}
