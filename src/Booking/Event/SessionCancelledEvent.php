<?php

declare(strict_types=1);

namespace App\Booking\Event;

use App\Entity\Booking;
use App\Entity\ClassSession;

/**
 * Dispatched after a studio cancelled a session and its confirmed bookings (transaction committed).
 *
 * Extension point for side effects: recrediting members once credits or passes exist,
 * notifying them by e-mail, etc.
 */
final readonly class SessionCancelledEvent
{
    /**
     * @param list<Booking> $cancelledBookings bookings that were confirmed and are now CancelledByStudio
     */
    public function __construct(
        public ClassSession $session,
        public array $cancelledBookings,
    ) {
    }
}
