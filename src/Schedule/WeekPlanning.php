<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Entity\ClassSession;

/**
 * Sessions of a rolling 7-day window (starting today, or N weeks later) grouped by day.
 */
final readonly class WeekPlanning
{
    /**
     * @param array<string, list<ClassSession>> $days sessions indexed by date (Y-m-d), all 7 days present
     */
    public function __construct(
        public \DateTimeImmutable $start,
        public int $offset,
        public array $days,
    ) {
    }

    public function getEnd(): \DateTimeImmutable
    {
        return $this->start->modify('+6 days');
    }

    public function isEmpty(): bool
    {
        foreach ($this->days as $sessions) {
            if ([] !== $sessions) {
                return false;
            }
        }

        return true;
    }

    /**
     * Builds the planning of the 7-day window starting $offset weeks after today.
     *
     * @param list<ClassSession> $sessions sessions starting within that week
     */
    public static function build(\DateTimeImmutable $now, int $offset, array $sessions): self
    {
        $start = self::weekStart($now, $offset);
        $days = [];
        for ($i = 0; $i < 7; ++$i) {
            $days[$start->modify(\sprintf('+%d days', $i))->format('Y-m-d')] = [];
        }

        foreach ($sessions as $session) {
            $key = $session->getStartsAt()->format('Y-m-d');
            if (\array_key_exists($key, $days)) {
                $days[$key][] = $session;
            }
        }

        return new self($start, $offset, $days);
    }

    public static function weekStart(\DateTimeImmutable $now, int $offset): \DateTimeImmutable
    {
        return $now->setTime(0, 0)->modify(\sprintf('%+d weeks', $offset));
    }
}
