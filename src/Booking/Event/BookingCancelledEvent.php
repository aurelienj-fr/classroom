<?php

declare(strict_types=1);

namespace App\Booking\Event;

use App\Entity\Booking;

/**
 * Dispatched after a member cancelled their own booking.
 */
final readonly class BookingCancelledEvent
{
    public function __construct(public Booking $booking)
    {
    }
}
