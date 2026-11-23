<?php

declare(strict_types=1);

namespace App\Owner;

use App\Booking\BookingManager;
use App\Entity\User;
use App\Notification\EmailNotifier;
use App\Repository\ClassSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Life cycle of the studio owner role: request, administrator review, and going back to a member.
 */
final readonly class OwnerRoleManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClassSessionRepository $sessionRepository,
        private BookingManager $bookingManager,
        private EmailNotifier $notifier,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Records a request and notifies the administrators. The user must be persisted by the caller
     * when it is new (registration).
     *
     * @return bool whether a new request was recorded
     */
    public function request(User $user): bool
    {
        if (!$user->requestOwnerRole($this->clock->now())) {
            return false;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->notifier->ownerRequestSubmitted($user);

        return true;
    }

    /**
     * Approves (grants ROLE_OWNER) or rejects a pending request and notifies the applicant.
     *
     * @throws \LogicException when the request is not pending
     */
    public function review(User $user, bool $approved): void
    {
        $user->reviewOwnerRequest($approved, $this->clock->now());
        $this->entityManager->flush();
        $this->notifier->ownerRequestReviewed($user);
    }

    /**
     * The owner goes back to a simple member: their studios are unpublished (kept as drafts)
     * and their upcoming sessions are cancelled, which cancels and notifies the bookings.
     */
    public function relinquish(User $owner): RelinquishResult
    {
        $cancelledSessions = 0;
        foreach ($this->sessionRepository->findUpcomingForOwner($owner, $this->clock->now()) as $session) {
            $this->bookingManager->cancelSession($session);
            ++$cancelledSessions;
        }

        $unpublishedStudios = 0;
        foreach ($owner->getStudios() as $studio) {
            if ($studio->isPublished()) {
                $studio->setPublished(false);
                ++$unpublishedStudios;
            }
        }

        $owner->relinquishOwnerRole();
        $this->entityManager->flush();

        $result = new RelinquishResult($unpublishedStudios, $cancelledSessions);
        $this->notifier->ownerRoleRelinquished($owner, $result->unpublishedStudios, $result->cancelledSessions);

        return $result;
    }
}
