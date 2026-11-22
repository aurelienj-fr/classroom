<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Entity\ClassSession;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a session and, optionally, its weekly repetitions.
 */
final readonly class SessionScheduler
{
    public const int MAX_WEEKLY_REPETITIONS = 26;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Persists the given session plus $weeklyRepetitions copies, one per following week.
     *
     * @return list<ClassSession> all created sessions, chronologically
     */
    public function schedule(ClassSession $session, int $weeklyRepetitions = 0): array
    {
        if ($weeklyRepetitions < 0 || $weeklyRepetitions > self::MAX_WEEKLY_REPETITIONS) {
            throw new \InvalidArgumentException(\sprintf('Weekly repetitions must be between 0 and %d.', self::MAX_WEEKLY_REPETITIONS));
        }

        $sessions = [$session];
        for ($week = 1; $week <= $weeklyRepetitions; ++$week) {
            $copy = new ClassSession($session->getActivity(), $session->getStartsAt()->modify(\sprintf('+%d weeks', $week)));
            $copy->setCapacity($session->getCapacity())
                ->setInstructorName($session->getInstructorName());
            $sessions[] = $copy;
        }

        foreach ($sessions as $item) {
            $this->entityManager->persist($item);
        }
        $this->entityManager->flush();

        return $sessions;
    }
}
