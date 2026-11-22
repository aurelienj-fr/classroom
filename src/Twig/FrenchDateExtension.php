<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * French date formatting that does not depend on the intl extension.
 */
final class FrenchDateExtension
{
    private const array DAYS = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    private const array MONTHS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const array SHORT_MONTHS = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /**
     * Formats a date in French.
     *
     * Formats: "day" (lundi 24 novembre), "day_short" (lun. 24 nov.), "date" (24 novembre 2026),
     * "full" (lundi 24 novembre 2026 à 18:30), "time" (18:30).
     */
    #[AsTwigFilter('fr_date')]
    public function format(\DateTimeInterface $date, string $format = 'full'): string
    {
        $day = self::DAYS[(int) $date->format('N')];
        $month = self::MONTHS[(int) $date->format('n')];
        $dayOfMonth = $date->format('j');
        $time = $date->format('H:i');

        return match ($format) {
            'day' => \sprintf('%s %s %s', $day, $dayOfMonth, $month),
            'day_short' => \sprintf('%s. %s %s', mb_substr($day, 0, 3), $dayOfMonth, self::SHORT_MONTHS[(int) $date->format('n')]),
            'date' => \sprintf('%s %s %s', $dayOfMonth, $month, $date->format('Y')),
            'time' => $time,
            'full' => \sprintf('%s %s %s %s à %s', $day, $dayOfMonth, $month, $date->format('Y'), $time),
            default => throw new \InvalidArgumentException(\sprintf('Unknown date format "%s".', $format)),
        };
    }
}
