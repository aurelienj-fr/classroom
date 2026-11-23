<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DisciplineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[ORM\Entity(repositoryClass: DisciplineRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_discipline_slug', fields: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_discipline_name', fields: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'discipline.name.already_exists')]
/**
 * "name" is the French (default) name; other languages live in "nameTranslations".
 * Implements TranslatableInterface so that `{{ discipline|trans }}` and form choice labels
 * display the name in the current locale.
 */
class Discipline implements \Stringable, TranslatableInterface
{
    public const string DEFAULT_LOCALE = 'fr';

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
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'discipline.icon.invalid')]
    #[Assert\Length(max: 50)]
    private string $icon = 'dumbbell';

    /**
     * Names in other languages, indexed by locale (e.g. ["en" => "Dance", "es" => "Danza"]).
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Length(max: 80)])]
    private array $nameTranslations = [];

    public function __toString(): string
    {
        return $this->name;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->getLocalizedName($locale ?? $translator->getLocale());
    }

    /**
     * Name in the given locale, falling back to the default (French) name.
     */
    public function getLocalizedName(string $locale): string
    {
        return $this->nameTranslations[$locale] ?? $this->name;
    }

    public function getNameTranslation(string $locale): ?string
    {
        return $this->nameTranslations[$locale] ?? null;
    }

    public function setNameTranslation(string $locale, ?string $name): static
    {
        $name = null === $name ? '' : trim($name);
        if ('' === $name || self::DEFAULT_LOCALE === $locale) {
            unset($this->nameTranslations[$locale]);
        } else {
            $this->nameTranslations[$locale] = $name;
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getNameTranslations(): array
    {
        return $this->nameTranslations;
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
