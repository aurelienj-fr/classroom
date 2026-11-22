<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Booking\BookingException;
use App\Booking\BookingManager;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use App\Tests\Support\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
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

    private function bookingRepository(): BookingRepository
    {
        return static::getContainer()->get(BookingRepository::class);
    }
}
