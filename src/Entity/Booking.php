<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A member's seat in a class session. A member owns at most one booking per session:
 * cancelling then booking again reactivates the same row.
 */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_booking_member_session', fields: ['member', 'session'])]
#[ORM\Index(name: 'idx_booking_session_status', fields: ['session', 'status'])]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $member;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClassSession $session;

    #[ORM\Column(length: 20, enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::Confirmed;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    public function __construct(User $member, ClassSession $session, \DateTimeImmutable $createdAt)
    {
        $this->member = $member;
        $this->session = $session;
        $this->createdAt = $createdAt;
        $session->getBookings()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): User
    {
        return $this->member;
    }

    public function getSession(): ClassSession
    {
        return $this->session;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function isConfirmed(): bool
    {
        return BookingStatus::Confirmed === $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function cancel(\DateTimeImmutable $at): void
    {
        $this->status = BookingStatus::Cancelled;
        $this->cancelledAt = $at;
        $this->checkedInAt = null;
    }

    /**
     * The studio cancelled the session (see BookingManager::cancelSession()).
     */
    public function cancelByStudio(\DateTimeImmutable $at): void
    {
        $this->status = BookingStatus::CancelledByStudio;
        $this->cancelledAt = $at;
        $this->checkedInAt = null;
    }

    /**
     * Books again after a cancellation. "createdAt" becomes the new booking time on purpose:
     * it orders attendee lists first-come, first-served.
     */
    public function reactivate(\DateTimeImmutable $at): void
    {
        $this->status = BookingStatus::Confirmed;
        $this->createdAt = $at;
        $this->cancelledAt = null;
    }

    public function toggleCheckIn(\DateTimeImmutable $at): void
    {
        $this->checkedInAt = null === $this->checkedInAt ? $at : null;
    }
}
