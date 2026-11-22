<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DisciplineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DisciplineRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_discipline_slug', fields: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_discipline_name', fields: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'Cette discipline existe déjà.')]
class Discipline implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    private string $slug = '';

    /**
     * Font Awesome icon name without the "fa-" prefix (e.g. "dumbbell").
     */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Nom d\'icône Font Awesome invalide (ex. : dumbbell).')]
    #[Assert\Length(max: 50)]
    private string $icon = 'dumbbell';

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

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }
}
