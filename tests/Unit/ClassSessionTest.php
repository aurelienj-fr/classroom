<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Activity;
use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\Studio;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class ClassSessionTest extends TestCase
{
    public function testEndTimeFollowsActivityDuration(): void
    {
        $activity = (new Activity(new Studio(new User())))->setDurationMinutes(75)->setDefaultCapacity(8);
        $session = new ClassSession($activity, new \DateTimeImmutable('2026-11-23 18:30'));

        self::assertSame(8, $session->getCapacity());
        self::assertEquals(new \DateTimeImmutable('2026-11-23 19:45'), $session->getEndsAt());

        $session->setStartsAt(new \DateTimeImmutable('2026-11-24 07:00'));
        self::assertEquals(new \DateTimeImmutable('2026-11-24 08:15'), $session->getEndsAt());
    }

    public function testConfirmedBookingsExcludeCancelledOnes(): void
    {
        $session = new ClassSession(new Activity(new Studio(new User())), new \DateTimeImmutable('2026-11-23 18:30'));
        $kept = new Booking(new User(), $session, new \DateTimeImmutable());
        $cancelled = new Booking(new User(), $session, new \DateTimeImmutable());
        $cancelled->cancel(new \DateTimeImmutable());

        self::assertSame([$kept], $session->getConfirmedBookings());
    }
}
