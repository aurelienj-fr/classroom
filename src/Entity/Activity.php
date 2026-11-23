<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Level;
use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A class template offered by a studio (e.g. "Vinyasa yoga – 60 min"), scheduled through sessions.
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    private Studio $studio;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'activity.discipline.required')]
    // Nullable in PHP so that the form can bind an empty choice; validation guarantees a value before flush.
    private ?Discipline $discipline = null; // @phpstan-ignore doctrine.associationType

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 15, max: 480)]
    private int $durationMinutes = 60;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 500)]
    private int $defaultCapacity = 15;

    #[ORM\Column(length: 20, enumType: Level::class)]
    private Level $level = Level::All;

    /**
     * @var Collection<int, ClassSession>
     */
    #[ORM\OneToMany(targetEntity: ClassSession::class, mappedBy: 'activity', orphanRemoval: true)]
    #[ORM\OrderBy(['startsAt' => \SortDirection::Ascending])]
    private Collection $sessions;

    public function __construct(Studio $studio)
    {
        $this->studio = $studio;
        $this->sessions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudio(): Studio
    {
        return $this->studio;
    }

    public function getDiscipline(): ?Discipline
    {
        return $this->discipline;
    }

    public function setDiscipline(?Discipline $discipline): static
    {
        $this->discipline = $discipline;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getDefaultCapacity(): int
    {
        return $this->defaultCapacity;
    }

    public function setDefaultCapacity(int $defaultCapacity): static
    {
        $this->defaultCapacity = $defaultCapacity;

        return $this;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function setLevel(Level $level): static
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @return Collection<int, ClassSession>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }
}
