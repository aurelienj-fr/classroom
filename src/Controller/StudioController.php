<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\StudioSearch;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ClassSessionRepository;
use App\Repository\DisciplineRepository;
use App\Repository\StudioRepository;
use App\Schedule\WeekPlanning;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class StudioController extends AbstractController
{
    /** How many weeks ahead the public planning can be browsed. */
    private const int MAX_WEEK_OFFSET = 12;

    #[Route('/studios', name: 'app_studio_index', methods: ['GET'])]
    public function index(
        StudioRepository $studioRepository,
        DisciplineRepository $disciplineRepository,
        #[MapQueryString] StudioSearch $search = new StudioSearch(),
    ): Response {
        $discipline = null;
        if (null !== $search->discipline && '' !== $search->discipline) {
            $discipline = $disciplineRepository->findOneBy(['slug' => $search->discipline]);
        }

        return $this->render('studio/index.html.twig', [
            'studios' => $studioRepository->searchPublished($search->ville, $discipline),
            'search' => $search,
            'disciplines' => $disciplineRepository->findAllOrdered(),
            'cities' => $studioRepository->findPublishedCities(),
        ]);
    }

    #[Route('/studios/{slug}', name: 'app_studio_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(
        string $slug,
        StudioRepository $studioRepository,
        ClassSessionRepository $sessionRepository,
        BookingRepository $bookingRepository,
        ClockInterface $clock,
        #[MapQueryParameter] int $semaine = 0,
    ): Response {
        $studio = $studioRepository->findOnePublishedBySlug($slug)
            ?? throw $this->createNotFoundException('Studio introuvable.');

        $now = $clock->now();
        $offset = max(0, min(self::MAX_WEEK_OFFSET, $semaine));
        $weekStart = WeekPlanning::weekStart($now, $offset);
        $sessions = $sessionRepository->findForStudioBetween($studio, $weekStart, $weekStart->modify('+7 days'));

        $user = $this->getUser();

        return $this->render('studio/show.html.twig', [
            'studio' => $studio,
            'planning' => WeekPlanning::build($now, $offset, $sessions),
            'bookedCounts' => $sessionRepository->countConfirmedBookings($sessions),
            'myBookedSessionIds' => $user instanceof User ? $bookingRepository->findConfirmedSessionIds($user, $sessions) : [],
            'maxWeekOffset' => self::MAX_WEEK_OFFSET,
            'now' => $now,
        ]);
    }
}
