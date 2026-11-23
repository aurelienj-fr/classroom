<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

/**
 * Date formatting in French, English and Spanish that does not depend on the intl extension.
 *
 * Times use the 24-hour clock in every language.
 */
final readonly class LocalizedDateExtension
{
    private const array DAYS = [
        'fr' => [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
        'en' => [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'es' => [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
    ];

    private const array SHORT_DAYS = [
        'fr' => [1 => 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'],
        'en' => [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'es' => [1 => 'lun.', 'mar.', 'mié.', 'jue.', 'vie.', 'sáb.', 'dom.'],
    ];

    private const array MONTHS = [
        'fr' => [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'en' => [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'es' => [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    ];

    private const array SHORT_MONTHS = [
        'fr' => [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
        'en' => [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'es' => [1 => 'ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sept.', 'oct.', 'nov.', 'dic.'],
    ];

    /**
     * Placeholders: {D} day name, {d} short day name, {j} day of month, {M} month, {m} short month, {Y} year, {H} time.
     */
    private const array PATTERNS = [
        'fr' => [
            'day' => '{D} {j} {M}',
            'day_short' => '{d} {j} {m}',
            'date' => '{j} {M} {Y}',
            'full' => '{D} {j} {M} {Y} à {H}',
        ],
        'en' => [
            'day' => '{D}, {M} {j}',
            'day_short' => '{d}, {m} {j}',
            'date' => '{M} {j}, {Y}',
            'full' => '{D}, {M} {j}, {Y} at {H}',
        ],
        'es' => [
            'day' => '{D} {j} de {M}',
            'day_short' => '{d} {j} {m}',
            'date' => '{j} de {M} de {Y}',
            'full' => '{D} {j} de {M} de {Y} a las {H}',
        ],
    ];

    private const string FALLBACK_LOCALE = 'fr';

    public function __construct(private LocaleSwitcher $localeSwitcher)
    {
    }

    /**
     * Formats a date in the current (or given) locale.
     *
     * Formats: "day" (lundi 23 novembre / Monday, November 23), "day_short", "date", "full" and "time" (18:30).
     */
    #[AsTwigFilter('local_date')]
    public function format(\DateTimeInterface $date, string $format = 'full', ?string $locale = null): string
    {
        $locale = $this->resolveLocale($locale ?? $this->localeSwitcher->getLocale());
        $time = $date->format('H:i');

        if ('time' === $format) {
            return $time;
        }

        $pattern = self::PATTERNS[$locale][$format]
            ?? throw new \InvalidArgumentException(\sprintf('Unknown date format "%s".', $format));
        $weekday = (int) $date->format('N');
        $month = (int) $date->format('n');

        return strtr($pattern, [
            '{D}' => self::DAYS[$locale][$weekday],
            '{d}' => self::SHORT_DAYS[$locale][$weekday],
            '{j}' => $date->format('j'),
            '{M}' => self::MONTHS[$locale][$month],
            '{m}' => self::SHORT_MONTHS[$locale][$month],
            '{Y}' => $date->format('Y'),
            '{H}' => $time,
        ]);
    }

    private function resolveLocale(string $locale): string
    {
        // "en_GB" → "en"
        $language = strtolower(substr($locale, 0, 2));

        return \array_key_exists($language, self::PATTERNS) ? $language : self::FALLBACK_LOCALE;
    }
}
