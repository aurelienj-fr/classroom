<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ClassSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Books and cancels seats while enforcing capacity, timing and cancellation rules.
 */
final readonly class BookingManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClassSessionRepository $sessionRepository,
        private BookingRepository $bookingRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Books a seat for the member. The session row is locked for the duration of the
     * transaction so that two concurrent requests cannot overbook the last seat.
     *
     * @throws BookingException when a booking rule is violated
     */
    public function book(User $member, ClassSession $session): Booking
    {
        $sessionId = $session->getId() ?? throw BookingException::sessionNotFound();

        // Explicit transaction rather than wrapInTransaction(), which closes the EntityManager
        // on any exception, including the expected business-rule ones.
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $session = $this->sessionRepository->findForUpdate($sessionId)
                ?? throw BookingException::sessionNotFound();
            // The identity map may hold a stale copy: make sure we read the locked row.
            $this->entityManager->refresh($session);

            $now = $this->clock->now();
            $this->assertBookable($session, $now);

            $booking = $this->bookingRepository->findOneForMemberAndSession($member, $session);
            if (null !== $booking && $booking->isConfirmed()) {
                throw BookingException::alreadyBooked();
            }

            if ($this->bookingRepository->countConfirmedForSession($session) >= $session->getCapacity()) {
                throw BookingException::sessionFull();
            }

            if (null === $booking) {
                $booking = new Booking($member, $session, $now);
                $this->entityManager->persist($booking);
            } else {
                $booking->reactivate($now);
            }

            $this->entityManager->flush();
            $connection->commit();

            return $booking;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Cancels a member's booking, provided the studio's cancellation deadline has not passed.
     *
     * @throws BookingException when the booking cannot be cancelled anymore
     */
    public function cancel(Booking $booking): void
    {
        if (!$booking->isConfirmed()) {
            throw BookingException::notConfirmed();
        }

        $now = $this->clock->now();
        if (!$this->canStillCancel($booking, $now)) {
            throw BookingException::cancellationDeadlinePassed($booking->getSession()->getStudio()->getCancellationDeadlineHours());
        }

        $booking->cancel($now);
        $this->entityManager->flush();
    }

    public function canStillCancel(Booking $booking, ?\DateTimeImmutable $now = null): bool
    {
        $session = $booking->getSession();
        $deadline = $session->getStartsAt()->modify(
            \sprintf('-%d hours', $session->getStudio()->getCancellationDeadlineHours()),
        );

        return $booking->isConfirmed() && ($now ?? $this->clock->now()) <= $deadline;
    }

    private function assertBookable(ClassSession $session, \DateTimeImmutable $now): void
    {
        if ($session->isCancelled()) {
            throw BookingException::sessionCancelled();
        }

        if ($session->hasStarted($now)) {
            throw BookingException::sessionStarted();
        }

        if (!$session->getStudio()->isPublished()) {
            throw BookingException::studioNotPublished();
        }
    }
}
