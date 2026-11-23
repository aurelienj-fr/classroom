<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Discipline;
use App\Entity\Studio;
use App\Repository\DisciplineRepository;
use App\Repository\StudioRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Generates unique URL slugs on creation. Slugs are kept stable afterwards so that public URLs don't break.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersistStudio', entity: Studio::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersistDiscipline', entity: Discipline::class)]
final class SlugListener
{
    private const int MAX_LENGTH = 100;

    /**
     * Slugs handed out but possibly not flushed yet (several entities persisted in one flush).
     *
     * @var array<string, true>
     */
    private array $reserved = [];

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly StudioRepository $studioRepository,
        private readonly DisciplineRepository $disciplineRepository,
    ) {
    }

    public function prePersistStudio(Studio $studio): void
    {
        if ('' === $studio->getSlug()) {
            $studio->setSlug($this->uniqueSlug(
                $studio->getName().' '.$studio->getCity(),
                'studio',
                $this->studioRepository->slugExists(...),
            ));
        }
    }

    public function prePersistDiscipline(Discipline $discipline): void
    {
        if ('' === $discipline->getSlug()) {
            $discipline->setSlug($this->uniqueSlug(
                $discipline->getName(),
                'discipline',
                $this->disciplineRepository->slugExists(...),
            ));
        }
    }

    /**
     * @param \Closure(string): bool $exists
     */
    private function uniqueSlug(string $value, string $fallback, \Closure $exists): string
    {
        $base = mb_substr($this->slugger->slug($value)->lower()->toString(), 0, self::MAX_LENGTH - 4);
        // A name made only of symbols (e.g. "★★") yields an empty slug: use a generic one.
        $base = '' === trim($base, '-') ? $fallback : trim($base, '-');

        $slug = $base;
        $suffix = 2;
        while (isset($this->reserved[$fallback.':'.$slug]) || $exists($slug)) {
            $slug = $base.'-'.$suffix++;
        }
        $this->reserved[$fallback.':'.$slug] = true;

        return $slug;
    }
}
