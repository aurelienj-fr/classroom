<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Required level for an activity.
 */
enum Level: string implements TranslatableInterface
{
    case All = 'all';
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('level.'.$this->value, locale: $locale);
    }
}
