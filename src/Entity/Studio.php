<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StudioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: StudioRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_studio_slug', fields: ['slug'])]
#[ORM\Index(name: 'idx_studio_city', fields: ['city'])]
class Studio implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 140)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $address = '';

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'studio.postal_code.invalid')]
    private string $postalCode = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $city = '';

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * Members can cancel a booking up to this many hours before the session starts.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 168)]
    private int $cancellationDeadlineHours = 2;

    #[ORM\Column]
    private bool $published = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'studios')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    /**
     * @var Collection<int, Discipline>
     */
    #[ORM\ManyToMany(targetEntity: Discipline::class)]
    #[ORM\OrderBy(['name' => \SortDirection::Ascending])]
    #[Assert\Count(min: 1, minMessage: 'studio.disciplines.required')]
    private Collection $disciplines;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'studio', orphanRemoval: true)]
    #[ORM\OrderBy(['name' => \SortDirection::Ascending])]
    private Collection $activities;

    public function __construct(User $owner)
    {
        $this->owner = $owner;
        // Global clock: honours the mocked time in tests (ClockSensitiveTrait).
        $this->createdAt = Clock::get()->now();
        $this->disciplines = new ArrayCollection();
        $this->activities = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = trim($address);

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = trim($postalCode);

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = trim($city);

        return $this;
    }

    public function getFullAddress(): string
    {
        return \sprintf('%s, %s %s', $this->address, $this->postalCode, $this->city);
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getCancellationDeadlineHours(): int
    {
        return $this->cancellationDeadlineHours;
    }

    public function setCancellationDeadlineHours(int $cancellationDeadlineHours): static
    {
        $this->cancellationDeadlineHours = $cancellationDeadlineHours;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner === $user;
    }

    /**
     * @return Collection<int, Discipline>
     */
    public function getDisciplines(): Collection
    {
        return $this->disciplines;
    }

    public function addDiscipline(Discipline $discipline): static
    {
        if (!$this->disciplines->contains($discipline)) {
            $this->disciplines->add($discipline);
        }

        return $this;
    }

    public function removeDiscipline(Discipline $discipline): static
    {
        $this->disciplines->removeElement($discipline);

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    /**
     * A discipline still used by one of the studio's activities cannot be removed.
     */
    #[Assert\Callback]
    public function validateDisciplinesInUse(ExecutionContextInterface $context): void
    {
        foreach ($this->activities as $activity) {
            $discipline = $activity->getDiscipline();
            if (null !== $discipline && !$this->disciplines->contains($discipline)) {
                $context->buildViolation('studio.disciplines.in_use')
                    ->setParameter('{{ discipline }}', $discipline->getName())
                    ->setParameter('{{ activity }}', $activity->getName())
                    ->atPath('disciplines')
                    ->addViolation();
            }
        }
    }
}
