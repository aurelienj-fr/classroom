<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of the studio search (?ville=…&discipline=…).
 */
final readonly class StudioSearch
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $ville = null,
        #[Assert\Length(max: 100)]
        public ?string $discipline = null,
    ) {
    }
}
