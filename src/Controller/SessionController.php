<?php

declare(strict_types=1);

namespace App\Controller;

use App\Booking\BookingException;
use App\Booking\BookingManager;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Repository\BookingRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SessionController extends AbstractController
{
    #[Route('/seances/{id}', name: 'app_session_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        ClassSession $session,
        BookingRepository $bookingRepository,
        BookingManager $bookingManager,
        ClockInterface $clock,
    ): Response {
        if (!$session->getStudio()->isPublished()) {
            throw $this->createNotFoundException('Séance introuvable.');
        }

        $user = $this->getUser();
        $booking = $user instanceof User ? $bookingRepository->findOneForMemberAndSession($user, $session) : null;
        $now = $clock->now();

        return $this->render('session/show.html.twig', [
            'session' => $session,
            'bookedCount' => $bookingRepository->countConfirmedForSession($session),
            'booking' => $booking,
            'canCancel' => null !== $booking && $bookingManager->canStillCancel($booking, $now),
            'now' => $now,
        ]);
    }

    #[Route('/seances/{id}/reserver', name: 'app_session_book', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsCsrfTokenValid(new Expression('"book-session-" ~ args["session"].getId()'))]
    public function book(ClassSession $session, BookingManager $bookingManager, #[CurrentUser] User $user): Response
    {
        try {
            $bookingManager->book($user, $session);
            $this->addFlash('success', 'Votre place est réservée. À bientôt sur le tapis !');
        } catch (BookingException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
    }
}
