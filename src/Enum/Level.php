<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Required level for an activity.
 */
enum Level: string
{
    case All = 'all';
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tous niveaux',
            self::Beginner => 'Débutant',
            self::Intermediate => 'Intermédiaire',
            self::Advanced => 'Avancé',
        };
    }
}
