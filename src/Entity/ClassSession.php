<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Repository\ClassSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A scheduled occurrence of an activity, which members can book.
 */
#[ORM\Entity(repositoryClass: ClassSessionRepository::class)]
#[ORM\Index(name: 'idx_session_starts_at', fields: ['startsAt'])]
class ClassSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private Activity $activity;

    #[ORM\Column]
    #[Assert\NotNull]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 500)]
    private int $capacity;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $instructorName = null;

    #[ORM\Column]
    private bool $cancelled = false;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'session', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => \SortDirection::Ascending])]
    private Collection $bookings;

    public function __construct(Activity $activity, \DateTimeImmutable $startsAt)
    {
        $this->activity = $activity;
        $this->capacity = $activity->getDefaultCapacity();
        $this->bookings = new ArrayCollection();
        $this->startsAt = $startsAt;
        $this->endsAt = $startsAt->modify(\sprintf('+%d minutes', $activity->getDurationMinutes()));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getStudio(): Studio
    {
        return $this->activity->getStudio();
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    /**
     * Moves the session; the end time follows the activity duration.
     */
    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $startsAt->modify(\sprintf('+%d minutes', $this->activity->getDurationMinutes()));

        return $this;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getInstructorName(): ?string
    {
        return $this->instructorName;
    }

    public function setInstructorName(?string $instructorName): static
    {
        $this->instructorName = $instructorName;

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): static
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    public function hasStarted(\DateTimeImmutable $now): bool
    {
        return $this->startsAt <= $now;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    /**
     * @return list<Booking>
     */
    public function getConfirmedBookings(): array
    {
        return array_values($this->bookings->filter(
            static fn (Booking $booking): bool => BookingStatus::Confirmed === $booking->getStatus(),
        )->toArray());
    }
}
