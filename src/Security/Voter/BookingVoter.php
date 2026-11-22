<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Only the member who made a booking may cancel it.
 *
 * @extends Voter<string, Booking>
 */
final class BookingVoter extends Voter
{
    public const string CANCEL = 'BOOKING_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CANCEL === $attribute && $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getMember() === $user;
    }
}
