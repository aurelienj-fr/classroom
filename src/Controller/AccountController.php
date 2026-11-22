<?php

declare(strict_types=1);

namespace App\Controller;

use App\Booking\BookingException;
use App\Booking\BookingManager;
use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Security\Voter\BookingVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/reservations', name: 'app_account_bookings', methods: ['GET'])]
    public function bookings(
        BookingRepository $bookingRepository,
        BookingManager $bookingManager,
        ClockInterface $clock,
        #[CurrentUser] User $user,
    ): Response {
        $now = $clock->now();
        $upcoming = $bookingRepository->findUpcomingForMember($user, $now);

        $cancellable = [];
        foreach ($upcoming as $booking) {
            $cancellable[(int) $booking->getId()] = $bookingManager->canStillCancel($booking, $now);
        }

        return $this->render('account/bookings.html.twig', [
            'upcoming' => $upcoming,
            'past' => $bookingRepository->findPastForMember($user, $now),
            'cancellable' => $cancellable,
        ]);
    }

    #[Route('/reservations/{id}/annuler', name: 'app_account_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(BookingVoter::CANCEL, subject: 'booking')]
    #[IsCsrfTokenValid(new Expression('"cancel-booking-" ~ args["booking"].getId()'))]
    public function cancel(Booking $booking, BookingManager $bookingManager, Request $request): Response
    {
        try {
            $bookingManager->cancel($booking);
            $this->addFlash('success', 'Votre réservation a été annulée.');
        } catch (BookingException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return 'session' === $request->getPayload()->getString('from')
            ? $this->redirectToRoute('app_session_show', ['id' => $booking->getSession()->getId()])
            : $this->redirectToRoute('app_account_bookings');
    }
}
