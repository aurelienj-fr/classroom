<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use App\Enum\OwnerRequestStatus;
use PHPUnit\Framework\TestCase;

final class UserOwnerRequestTest extends TestCase
{
    public function testRequestThenApproval(): void
    {
        $user = new User();

        self::assertTrue($user->requestOwnerRole(new \DateTimeImmutable('2026-11-20')));
        self::assertFalse($user->requestOwnerRole(new \DateTimeImmutable('2026-11-21')), 'Already pending.');
        self::assertNotContains(User::ROLE_OWNER, $user->getRoles());

        $user->reviewOwnerRequest(true, new \DateTimeImmutable('2026-11-22'));

        self::assertSame(OwnerRequestStatus::Approved, $user->getOwnerRequestStatus());
        self::assertContains(User::ROLE_OWNER, $user->getRoles());
        self::assertFalse($user->requestOwnerRole(new \DateTimeImmutable('2026-11-23')), 'Already an owner.');
    }

    public function testRejectedMemberCanAskAgain(): void
    {
        $user = new User();
        $user->requestOwnerRole(new \DateTimeImmutable('2026-11-20'));
        $user->reviewOwnerRequest(false, new \DateTimeImmutable('2026-11-21'));

        self::assertSame(OwnerRequestStatus::Rejected, $user->getOwnerRequestStatus());
        self::assertNotContains(User::ROLE_OWNER, $user->getRoles());
        self::assertTrue($user->requestOwnerRole(new \DateTimeImmutable('2026-11-22')));
        self::assertNull($user->getOwnerReviewedAt());
    }

    public function testOnlyPendingRequestsCanBeReviewed(): void
    {
        $this->expectException(\LogicException::class);

        (new User())->reviewOwnerRequest(true, new \DateTimeImmutable());
    }
}
