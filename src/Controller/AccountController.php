<?php

declare(strict_types=1);

namespace App\Controller;

use App\Booking\BookingException;
use App\Booking\BookingManager;
use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Security\Voter\BookingVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route(['fr' => '/compte', 'en' => '/account', 'es' => '/cuenta'])]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route(['fr' => '/reservations', 'en' => '/bookings', 'es' => '/reservas'], name: 'app_account_bookings', methods: ['GET'])]
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

    #[Route(['fr' => '/devenir-gerant', 'en' => '/become-owner', 'es' => '/ser-gestor'], name: 'app_account_owner_request', methods: ['POST'])]
    #[IsCsrfTokenValid('owner-request')]
    public function requestOwnerRole(
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
        ClockInterface $clock,
    ): Response {
        if ($user->requestOwnerRole($clock->now())) {
            $entityManager->flush();
            $this->addFlash('info', new TranslatableMessage('flash.owner_request.sent'));
        }

        return $this->redirectToRoute('app_account_bookings');
    }

    #[Route(['fr' => '/reservations/{id}/annuler', 'en' => '/bookings/{id}/cancel', 'es' => '/reservas/{id}/cancelar'], name: 'app_account_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(BookingVoter::CANCEL, subject: 'booking')]
    #[IsCsrfTokenValid(new Expression('"cancel-booking-" ~ args["booking"].getId()'))]
    public function cancel(Booking $booking, BookingManager $bookingManager, Request $request): Response
    {
        try {
            $bookingManager->cancel($booking);
            $this->addFlash('success', new TranslatableMessage('flash.booking.cancelled'));
        } catch (BookingException $exception) {
            $this->addFlash('danger', $exception->getTranslatable());
        }

        return 'session' === $request->getPayload()->getString('from')
            ? $this->redirectToRoute('app_session_show', ['id' => $booking->getSession()->getId()])
            : $this->redirectToRoute('app_account_bookings');
    }
}
