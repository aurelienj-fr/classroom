<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClassSessionRepository;
use App\Repository\DisciplineRepository;
use App\Repository\StudioRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        DisciplineRepository $disciplineRepository,
        StudioRepository $studioRepository,
        ClassSessionRepository $sessionRepository,
        ClockInterface $clock,
    ): Response {
        return $this->render('home/index.html.twig', [
            'disciplines' => $disciplineRepository->findAllOrdered(),
            'cities' => $studioRepository->findPublishedCities(),
            'nextSessions' => $sessionRepository->findNextPublished($clock->now()),
        ]);
    }
}
