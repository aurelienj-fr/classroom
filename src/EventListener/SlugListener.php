<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Discipline;
use App\Entity\Studio;
use App\Repository\StudioRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Generates URL slugs on creation. Slugs are kept stable afterwards so that public URLs don't break.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersistStudio', entity: Studio::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersistDiscipline', entity: Discipline::class)]
final readonly class SlugListener
{
    public function __construct(
        private SluggerInterface $slugger,
        private StudioRepository $studioRepository,
    ) {
    }

    public function prePersistStudio(Studio $studio): void
    {
        if ('' !== $studio->getSlug()) {
            return;
        }

        $base = $this->slugify($studio->getName().' '.$studio->getCity());
        $slug = $base;
        $suffix = 2;
        while ($this->studioRepository->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        $studio->setSlug($slug);
    }

    public function prePersistDiscipline(Discipline $discipline): void
    {
        if ('' === $discipline->getSlug()) {
            $discipline->setSlug($this->slugify($discipline->getName()));
        }
    }

    private function slugify(string $value): string
    {
        return mb_substr($this->slugger->slug($value)->lower()->toString(), 0, 120);
    }
}
