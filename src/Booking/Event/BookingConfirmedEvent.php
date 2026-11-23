<?php

declare(strict_types=1);

namespace App\Booking\Event;

use App\Entity\Booking;

/**
 * Dispatched after a member booked (or booked again) a seat; the transaction is committed.
 */
final readonly class BookingConfirmedEvent
{
    public function __construct(public Booking $booking)
    {
    }
}
