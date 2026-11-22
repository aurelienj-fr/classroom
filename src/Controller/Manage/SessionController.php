<?php

declare(strict_types=1);

namespace App\Controller\Manage;

use App\Entity\Activity;
use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Form\ClassSessionType;
use App\Repository\BookingRepository;
use App\Schedule\SessionScheduler;
use App\Security\Voter\StudioVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestion')]
#[IsGranted(User::ROLE_OWNER)]
final class SessionController extends AbstractController
{
    #[Route('/cours/{id}/seances/nouvelle', name: 'app_manage_session_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["activity"].getStudio()'))]
    public function new(Activity $activity, Request $request, SessionScheduler $scheduler, ClockInterface $clock): Response
    {
        $session = new ClassSession($activity, $clock->now()->modify('+1 day')->setTime(18, 0));
        $form = $this->createForm(ClassSessionType::class, $session, ['allow_repetition' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $session->hasStarted($clock->now())) {
            $form->get('startsAt')->addError(new FormError('La séance doit commencer dans le futur.'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var int $repetitions */
            $repetitions = $form->get('weeklyRepetitions')->getData();
            $created = $scheduler->schedule($session, $repetitions);
            $this->addFlash('success', \sprintf('%d séance(s) planifiée(s).', \count($created)));

            return $this->redirectToRoute('app_manage_studio_show', ['id' => $activity->getStudio()->getId()]);
        }

        return $this->render('manage/session/form.html.twig', [
            'form' => $form,
            'activity' => $activity,
            'session' => null,
        ]);
    }

    #[Route('/seances/{id}', name: 'app_manage_session_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["session"].getStudio()'))]
    public function show(ClassSession $session): Response
    {
        return $this->render('manage/session/show.html.twig', [
            'session' => $session,
            'attendees' => $session->getConfirmedBookings(),
        ]);
    }

    #[Route('/seances/{id}/modifier', name: 'app_manage_session_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["session"].getStudio()'))]
    public function edit(
        ClassSession $session,
        Request $request,
        EntityManagerInterface $entityManager,
        BookingRepository $bookingRepository,
    ): Response {
        $form = $this->createForm(ClassSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $booked = $bookingRepository->countConfirmedForSession($session);
            if ($session->getCapacity() < $booked) {
                $form->get('capacity')->addError(new FormError(\sprintf('%d membre(s) ont déjà réservé : la capacité ne peut pas être inférieure.', $booked)));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Séance mise à jour.');

            return $this->redirectToRoute('app_manage_session_show', ['id' => $session->getId()]);
        }

        return $this->render('manage/session/form.html.twig', [
            'form' => $form,
            'activity' => $session->getActivity(),
            'session' => $session,
        ]);
    }

    #[Route('/seances/{id}/annulation', name: 'app_manage_session_toggle_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["session"].getStudio()'))]
    #[IsCsrfTokenValid(new Expression('"toggle-session-" ~ args["session"].getId()'))]
    public function toggleCancel(ClassSession $session, EntityManagerInterface $entityManager): Response
    {
        $session->setCancelled(!$session->isCancelled());
        $entityManager->flush();
        $this->addFlash('success', $session->isCancelled() ? 'Séance annulée.' : 'Séance rétablie.');

        return $this->redirectToRoute('app_manage_session_show', ['id' => $session->getId()]);
    }

    #[Route('/reservations/{id}/presence', name: 'app_manage_booking_check_in', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["booking"].getSession().getStudio()'))]
    #[IsCsrfTokenValid(new Expression('"check-in-" ~ args["booking"].getId()'))]
    public function toggleCheckIn(Booking $booking, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        if ($booking->isConfirmed()) {
            $booking->toggleCheckIn($clock->now());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_manage_session_show', ['id' => $booking->getSession()->getId()]);
    }
}
