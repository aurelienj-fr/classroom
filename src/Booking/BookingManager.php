<?php

declare(strict_types=1);

namespace App\Booking;

use App\Booking\Event\BookingCancelledEvent;
use App\Booking\Event\BookingConfirmedEvent;
use App\Booking\Event\SessionCancelledEvent;
use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ClassSessionRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Books and cancels seats while enforcing capacity, timing and cancellation rules.
 *
 * Every operation that depends on the number of confirmed bookings runs inside a transaction
 * holding a write lock on the session row (SELECT … FOR UPDATE), so that they are serialized.
 */
final readonly class BookingManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClassSessionRepository $sessionRepository,
        private BookingRepository $bookingRepository,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Books a seat for the member.
     *
     * @throws BookingException when a booking rule is violated
     */
    public function book(User $member, ClassSession $session): Booking
    {
        $sessionId = $session->getId() ?? throw BookingException::sessionNotFound();

        $booking = $this->transactional(function () use ($member, $sessionId): Booking {
            // find() with a lock re-reads the row even when the entity is already in the identity map.
            $session = $this->sessionRepository->findForUpdate($sessionId)
                ?? throw BookingException::sessionNotFound();

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

            return $booking;
        });

        $this->eventDispatcher->dispatch(new BookingConfirmedEvent($booking));

        return $booking;
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

        $this->eventDispatcher->dispatch(new BookingCancelledEvent($booking));
    }

    public function canStillCancel(Booking $booking, ?\DateTimeImmutable $now = null): bool
    {
        $session = $booking->getSession();
        $deadline = $session->getStartsAt()->modify(
            \sprintf('-%d hours', $session->getStudio()->getCancellationDeadlineHours()),
        );

        return $booking->isConfirmed() && ($now ?? $this->clock->now()) <= $deadline;
    }

    /**
     * Saves changes made to a session (typically bound by a form), refusing a capacity
     * lower than the number of confirmed bookings.
     *
     * The pending changes are kept: the row is locked without being reloaded.
     *
     * @throws BookingException when the new capacity is below the confirmed bookings
     */
    public function saveSession(ClassSession $session): void
    {
        $this->transactional(function () use ($session): void {
            $this->entityManager->lock($session, LockMode::PESSIMISTIC_WRITE);

            $booked = $this->bookingRepository->countConfirmedForSession($session);
            if ($session->getCapacity() < $booked) {
                throw BookingException::capacityBelowBookings($booked);
            }
        });
    }

    /**
     * Cancels a session on behalf of the studio: every confirmed booking is cancelled
     * (status CancelledByStudio), then SessionCancelledEvent is dispatched so that members
     * can be recredited and notified.
     *
     * @return list<Booking> the bookings that were cancelled
     *
     * @throws BookingException when the session already started or is already cancelled
     */
    public function cancelSession(ClassSession $session): array
    {
        $sessionId = $session->getId() ?? throw BookingException::sessionNotFound();

        $cancelled = $this->transactional(function () use ($sessionId): array {
            $session = $this->sessionRepository->findForUpdate($sessionId)
                ?? throw BookingException::sessionNotFound();

            $now = $this->clock->now();
            if ($session->isCancelled()) {
                throw BookingException::sessionCancelled();
            }
            if ($session->hasStarted($now)) {
                throw BookingException::sessionStarted();
            }

            $session->setCancelled(true);
            $bookings = $this->bookingRepository->findConfirmedForSession($session);
            foreach ($bookings as $booking) {
                $booking->cancelByStudio($now);
            }

            return $bookings;
        });

        $this->eventDispatcher->dispatch(new SessionCancelledEvent($session, $cancelled));

        return $cancelled;
    }

    /**
     * Reopens a cancelled session for booking. Bookings cancelled by the studio stay cancelled
     * (members were recredited): they have to book again.
     *
     * @throws BookingException when the session already started
     */
    public function restoreSession(ClassSession $session): void
    {
        if ($session->hasStarted($this->clock->now())) {
            throw BookingException::sessionStarted();
        }

        $session->setCancelled(false);
        $this->entityManager->flush();
    }

    /**
     * Runs $operation in a transaction and flushes before committing.
     *
     * Explicit transaction rather than wrapInTransaction(), which closes the EntityManager on any
     * exception, including the expected business-rule ones.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function transactional(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
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
