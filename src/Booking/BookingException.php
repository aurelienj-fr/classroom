<?php

declare(strict_types=1);

namespace App\Booking;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * A booking rule was violated.
 *
 * The exception message is a translation key (useful in logs); getTranslatable() returns
 * the message to display to the member in their language.
 */
final class BookingException extends \DomainException
{
    /**
     * @param array<string, int|string> $parameters
     */
    private function __construct(string $key, private readonly array $parameters = [])
    {
        parent::__construct($key);
    }

    public function getTranslatable(): TranslatableMessage
    {
        return new TranslatableMessage($this->getMessage(), $this->parameters);
    }

    public static function sessionCancelled(): self
    {
        return new self('booking.error.session_cancelled');
    }

    public static function sessionStarted(): self
    {
        return new self('booking.error.session_started');
    }

    public static function studioNotPublished(): self
    {
        return new self('booking.error.studio_not_published');
    }

    public static function alreadyBooked(): self
    {
        return new self('booking.error.already_booked');
    }

    public static function sessionFull(): self
    {
        return new self('booking.error.session_full');
    }

    public static function notConfirmed(): self
    {
        return new self('booking.error.not_confirmed');
    }

    public static function cancellationDeadlinePassed(int $hours): self
    {
        return new self('booking.error.cancellation_deadline_passed', ['%hours%' => $hours]);
    }

    public static function capacityBelowBookings(int $confirmedBookings): self
    {
        return new self('booking.error.capacity_below_bookings', ['%count%' => $confirmedBookings]);
    }

    public static function sessionNotFound(): self
    {
        return new self('booking.error.session_not_found');
    }
}
