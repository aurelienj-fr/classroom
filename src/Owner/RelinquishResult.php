<?php

declare(strict_types=1);

namespace App\Owner;

final readonly class RelinquishResult
{
    public function __construct(
        public int $unpublishedStudios,
        public int $cancelledSessions,
    ) {
    }
}
