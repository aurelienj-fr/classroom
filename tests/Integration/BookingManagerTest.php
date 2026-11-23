<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Booking\BookingException;
use App\Booking\BookingManager;
use App\Booking\Event\SessionCancelledEvent;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use App\Tests\Support\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BookingManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private TestData $data;
    private BookingManager $manager;
    private User $owner;
    private User $member;

    protected function setUp(): void
    {
        self::bootKernel();
        static::mockTime('2026-11-22 10:00:00');

        $container = static::getContainer();
        $this->data = new TestData(
            $container->get(EntityManagerInterface::class),
            $container->get(UserPasswordHasherInterface::class),
        );
        $this->manager = $container->get(BookingManager::class);
        $this->owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $this->member = $this->data->user('member@example.test');
    }

    public function testBookConfirmsASeat(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'));

        $booking = $this->manager->book($this->member, $session);

        self::assertNotNull($booking->getId());
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus());
        self::assertSame(1, $this->bookingRepository()->countConfirmedForSession($session));
    }

    public function testCannotBookTwice(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'));
        $this->manager->book($this->member, $session);

        $this->expectExceptionObject(BookingException::alreadyBooked());
        $this->manager->book($this->member, $session);
    }

    public function testCannotBookAFullSession(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'), capacity: 1);
        $this->manager->book($this->data->user('first@example.test'), $session);

        $this->expectExceptionObject(BookingException::sessionFull());
        $this->manager->book($this->member, $session);
    }

    public function testCannotBookAStartedSession(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-22 09:30'));

        $this->expectExceptionObject(BookingException::sessionStarted());
        $this->manager->book($this->member, $session);
    }

    public function testCannotBookACancelledSession(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'));
        $session->setCancelled(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->expectExceptionObject(BookingException::sessionCancelled());
        $this->manager->book($this->member, $session);
    }

    public function testCannotBookInAnUnpublishedStudio(): void
    {
        $session = $this->data->session($this->data->studio($this->owner, published: false), new \DateTimeImmutable('2026-11-23 18:00'));

        $this->expectExceptionObject(BookingException::studioNotPublished());
        $this->manager->book($this->member, $session);
    }

    public function testCancelBeforeDeadlineFreesTheSeatAndRebookingReusesTheBooking(): void
    {
        $session = $this->data->session($this->data->studio($this->owner, cancellationDeadlineHours: 2), new \DateTimeImmutable('2026-11-22 12:00'), capacity: 1);
        $booking = $this->manager->book($this->member, $session);

        $this->manager->cancel($booking);

        self::assertSame(BookingStatus::Cancelled, $booking->getStatus());
        self::assertSame(0, $this->bookingRepository()->countConfirmedForSession($session));

        $rebooked = $this->manager->book($this->member, $session);
        self::assertSame($booking->getId(), $rebooked->getId());
        self::assertTrue($rebooked->isConfirmed());
    }

    public function testCannotCancelAfterDeadline(): void
    {
        // Starts in 1h30, the studio requires 2h notice.
        $session = $this->data->session($this->data->studio($this->owner, cancellationDeadlineHours: 2), new \DateTimeImmutable('2026-11-22 11:30'));
        $booking = $this->manager->book($this->member, $session);

        self::assertFalse($this->manager->canStillCancel($booking));
        $this->expectExceptionObject(BookingException::cancellationDeadlinePassed(2));
        $this->manager->cancel($booking);
    }

    public function testAFailedBookingKeepsTheEntityManagerUsable(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'), capacity: 1);
        $this->manager->book($this->data->user('first@example.test'), $session);

        try {
            $this->manager->book($this->member, $session);
            self::fail('Expected the session to be full.');
        } catch (BookingException) {
        }

        self::assertTrue(static::getContainer()->get(EntityManagerInterface::class)->isOpen());
    }

    public function testCanCancelExactlyAtTheDeadline(): void
    {
        // Now is 10:00 and the studio requires 2h notice: a 12:00 session is still cancellable.
        $session = $this->data->session($this->data->studio($this->owner, cancellationDeadlineHours: 2), new \DateTimeImmutable('2026-11-22 12:00'));
        $booking = $this->manager->book($this->member, $session);

        self::assertTrue($this->manager->canStillCancel($booking));
        self::assertFalse($this->manager->canStillCancel($booking, new \DateTimeImmutable('2026-11-22 10:00:01')));
    }

    public function testSaveSessionRefusesACapacityBelowConfirmedBookings(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'), capacity: 5);
        $this->manager->book($this->member, $session);
        $this->manager->book($this->data->user('other@example.test'), $session);

        $session->setCapacity(1);

        $this->expectExceptionObject(BookingException::capacityBelowBookings(2));
        $this->manager->saveSession($session);
    }

    public function testSaveSessionPersistsValidChanges(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'), capacity: 5);
        $this->manager->book($this->member, $session);

        $session->setCapacity(1)->setInstructorName('Julie');
        $this->manager->saveSession($session);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(ClassSession::class, $session->getId());
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->getCapacity());
        self::assertSame('Julie', $reloaded->getInstructorName());
    }

    public function testCancelSessionCancelsBookingsAndDispatchesAnEvent(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'));
        $first = $this->manager->book($this->member, $session);
        $second = $this->manager->book($this->data->user('other@example.test'), $session);
        $alreadyCancelled = $this->manager->book($this->data->user('third@example.test'), $session);
        $this->manager->cancel($alreadyCancelled);

        $events = [];
        static::getContainer()->get(EventDispatcherInterface::class)
            ->addListener(SessionCancelledEvent::class, static function (SessionCancelledEvent $event) use (&$events): void {
                $events[] = $event;
            });

        $cancelled = $this->manager->cancelSession($session);

        self::assertTrue($session->isCancelled());
        self::assertSame([$first, $second], $cancelled);
        self::assertSame(BookingStatus::CancelledByStudio, $first->getStatus());
        self::assertSame(BookingStatus::CancelledByStudio, $second->getStatus());
        self::assertSame(BookingStatus::Cancelled, $alreadyCancelled->getStatus(), 'Member cancellations are kept as is.');
        self::assertSame(0, $this->bookingRepository()->countConfirmedForSession($session));
        self::assertCount(1, $events);
        self::assertSame([$first, $second], $events[0]->cancelledBookings);
    }

    public function testCannotCancelAStartedSession(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-22 09:00'));

        $this->expectExceptionObject(BookingException::sessionStarted());
        $this->manager->cancelSession($session);
    }

    public function testRestoredSessionCanBeBookedAgainButOldBookingsStayCancelled(): void
    {
        $session = $this->data->session($this->data->studio($this->owner), new \DateTimeImmutable('2026-11-23 18:00'));
        $booking = $this->manager->book($this->member, $session);
        $this->manager->cancelSession($session);

        $this->manager->restoreSession($session);

        self::assertFalse($session->isCancelled());
        self::assertSame(BookingStatus::CancelledByStudio, $booking->getStatus());

        $rebooked = $this->manager->book($this->member, $session);
        self::assertSame($booking->getId(), $rebooked->getId());
        self::assertTrue($rebooked->isConfirmed());
    }

    private function bookingRepository(): BookingRepository
    {
        return static::getContainer()->get(BookingRepository::class);
    }
}
