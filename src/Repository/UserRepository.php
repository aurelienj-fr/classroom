<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\OwnerRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * Pending owner requests first (oldest first), then the most recently reviewed ones.
     *
     * @return list<User>
     */
    public function findOwnerRequests(int $limit = 100): array
    {
        /** @var list<User> */
        return $this->createQueryBuilder('u')
            ->addSelect('CASE WHEN u.ownerRequestStatus = :pending THEN 0 ELSE 1 END AS HIDDEN pendingFirst')
            ->andWhere('u.ownerRequestStatus IS NOT NULL')
            ->setParameter('pending', OwnerRequestStatus::Pending)
            ->orderBy('pendingFirst', 'ASC')
            ->addOrderBy('u.ownerReviewedAt', 'DESC')
            ->addOrderBy('u.ownerRequestedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Platform administrators (recipients of owner request notifications).
     *
     * @return list<User>
     */
    public function findAdministrators(): array
    {
        /** @var list<User> */
        return $this->createQueryBuilder('u')
            // roles is a JSON array column: match the quoted role name.
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"'.User::ROLE_ADMIN.'"%')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingOwnerRequests(): int
    {
        return $this->count(['ownerRequestStatus' => OwnerRequestStatus::Pending]);
    }
}
