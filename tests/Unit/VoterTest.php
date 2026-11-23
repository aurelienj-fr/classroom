<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Activity;
use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\Studio;
use App\Entity\User;
use App\Security\Voter\BookingVoter;
use App\Security\Voter\StudioVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VoterTest extends TestCase
{
    public function testStudioVoter(): void
    {
        $owner = (new User())->setEmail('owner@example.test')->setRoles([User::ROLE_OWNER]);
        $otherOwner = (new User())->setEmail('other@example.test')->setRoles([User::ROLE_OWNER]);
        $admin = (new User())->setEmail('admin@example.test')->setRoles([User::ROLE_ADMIN]);
        $studio = new Studio($owner);

        $decisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $decisionManager->method('decide')->willReturnCallback(
            static fn ($token, array $attributes): bool => \in_array(User::ROLE_ADMIN, $token->getRoleNames(), true),
        );
        $voter = new StudioVoter($decisionManager);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($owner), $studio, [StudioVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($otherOwner), $studio, [StudioVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($admin), $studio, [StudioVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), $studio, [StudioVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->token($owner), new \stdClass(), [StudioVoter::MANAGE]));
    }

    public function testBookingVoter(): void
    {
        $member = (new User())->setEmail('member@example.test');
        $intruder = (new User())->setEmail('intruder@example.test');
        $owner = (new User())->setEmail('owner@example.test')->setRoles([User::ROLE_OWNER]);
        $session = new ClassSession(new Activity(new Studio($owner)), new \DateTimeImmutable('2026-11-23 18:00'));
        $booking = new Booking($member, $session, new \DateTimeImmutable());
        $voter = new BookingVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($member), $booking, [BookingVoter::CANCEL]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($intruder), $booking, [BookingVoter::CANCEL]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($owner), $booking, [BookingVoter::CANCEL]), "Owners cannot cancel members' bookings.");
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
