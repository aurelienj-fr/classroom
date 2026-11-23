<?php

declare(strict_types=1);

namespace App\Notification;

use App\Booking\Event\BookingCancelledEvent;
use App\Booking\Event\BookingConfirmedEvent;
use App\Booking\Event\SessionCancelledEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * E-mails members about their bookings.
 */
final readonly class BookingEmailListener
{
    public function __construct(private EmailNotifier $notifier)
    {
    }

    #[AsEventListener]
    public function onBookingConfirmed(BookingConfirmedEvent $event): void
    {
        $this->notifier->bookingConfirmed($event->booking);
    }

    #[AsEventListener]
    public function onBookingCancelled(BookingCancelledEvent $event): void
    {
        $this->notifier->bookingCancelled($event->booking);
    }

    #[AsEventListener]
    public function onSessionCancelled(SessionCancelledEvent $event): void
    {
        foreach ($event->cancelledBookings as $booking) {
            $this->notifier->sessionCancelledByStudio($booking);
        }
    }
}
