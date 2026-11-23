<?php

declare(strict_types=1);

namespace App\Controller\Manage;

use App\Booking\BookingException;
use App\Booking\BookingManager;
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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(['fr' => '/gestion', 'en' => '/manage', 'es' => '/gestion'])]
#[IsGranted(User::ROLE_OWNER)]
final class SessionController extends AbstractController
{
    #[Route(['fr' => '/cours/{id}/seances/nouvelle', 'en' => '/activities/{id}/sessions/new', 'es' => '/actividades/{id}/sesiones/nueva'], name: 'app_manage_session_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["activity"].getStudio()'))]
    public function new(
        Activity $activity,
        Request $request,
        SessionScheduler $scheduler,
        ClockInterface $clock,
        TranslatorInterface $translator,
    ): Response {
        $session = new ClassSession($activity, $clock->now()->modify('+1 day')->setTime(18, 0));
        $form = $this->createForm(ClassSessionType::class, $session, ['allow_repetition' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->assertStartsInFuture($form, $session, $clock, $translator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var int $repetitions */
            $repetitions = $form->get('weeklyRepetitions')->getData();
            $created = $scheduler->schedule($session, $repetitions);
            $this->addFlash('success', new TranslatableMessage('flash.session.scheduled', ['%count%' => \count($created)]));

            return $this->redirectToRoute('app_manage_studio_show', ['id' => $activity->getStudio()->getId()]);
        }

        return $this->render('manage/session/form.html.twig', [
            'form' => $form,
            'activity' => $activity,
            'session' => null,
        ]);
    }

    #[Route(['fr' => '/seances/{id}', 'en' => '/sessions/{id}', 'es' => '/sesiones/{id}'], name: 'app_manage_session_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["session"].getStudio()'))]
    public function show(ClassSession $session, BookingRepository $bookingRepository, ClockInterface $clock): Response
    {
        return $this->render('manage/session/show.html.twig', [
            'session' => $session,
            'attendees' => $bookingRepository->findConfirmedForSession($session),
            'started' => $session->hasStarted($clock->now()),
        ]);
    }

    #[Route(['fr' => '/seances/{id}/modifier', 'en' => '/sessions/{id}/edit', 'es' => '/sesiones/{id}/editar'], name: 'app_manage_session_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["session"].getStudio()'))]
    public function edit(
        ClassSession $session,
        Request $request,
        BookingManager $bookingManager,
        ClockInterface $clock,
        TranslatorInterface $translator,
    ): Response {
        // Past and cancelled sessions are read-only: members' history must not change.
        if ($session->isCancelled() || $session->hasStarted($clock->now())) {
            $this->addFlash('warning', new TranslatableMessage('flash.session.not_editable'));

            return $this->redirectToRoute('app_manage_session_show', ['id' => $session->getId()]);
        }

        $form = $this->createForm(ClassSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->assertStartsInFuture($form, $session, $clock, $translator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Checks the capacity against confirmed bookings under the session lock.
                $bookingManager->saveSession($session);
                $this->addFlash('success', new TranslatableMessage('flash.session.updated'));

                return $this->redirectToRoute('app_manage_session_show', ['id' => $session->getId()]);
            } catch (BookingException $exception) {
                $form->get('capacity')->addError(new FormError($exception->getTranslatable()->trans($translator)));
            }
        }

        return $this->render('manage/session/form.html.twig', [
            'form' => $form,
            'activity' => $session->getActivity(),
            'session' => $session,
        ]);
    }

    #[Route(['fr' => '/seances/{id}/annulation', 'en' => '/sessions/{id}/cancellation', 'es' => '/sesiones/{id}/anulacion'], name: 'app_manage_session_toggle_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["session"].getStudio()'))]
    #[IsCsrfTokenValid(new Expression('"toggle-session-" ~ args["session"].getId()'))]
    public function toggleCancel(ClassSession $session, BookingManager $bookingManager): Response
    {
        try {
            if ($session->isCancelled()) {
                $bookingManager->restoreSession($session);
                $this->addFlash('success', new TranslatableMessage('flash.session.restored'));
            } else {
                $cancelled = $bookingManager->cancelSession($session);
                $this->addFlash('success', new TranslatableMessage('flash.session.cancelled', ['%count%' => \count($cancelled)]));
            }
        } catch (BookingException $exception) {
            $this->addFlash('danger', $exception->getTranslatable());
        }

        return $this->redirectToRoute('app_manage_session_show', ['id' => $session->getId()]);
    }

    #[Route(['fr' => '/reservations/{id}/presence', 'en' => '/bookings/{id}/attendance', 'es' => '/reservas/{id}/asistencia'], name: 'app_manage_booking_check_in', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["booking"].getSession().getStudio()'))]
    #[IsCsrfTokenValid(new Expression('"check-in-" ~ args["booking"].getId()'))]
    public function toggleCheckIn(Booking $booking, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        if ($booking->isConfirmed() && !$booking->getSession()->isCancelled()) {
            $booking->toggleCheckIn($clock->now());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_manage_session_show', ['id' => $booking->getSession()->getId()]);
    }

    /**
     * @param FormInterface<ClassSession> $form
     */
    private function assertStartsInFuture(FormInterface $form, ClassSession $session, ClockInterface $clock, TranslatorInterface $translator): void
    {
        if ($session->hasStarted($clock->now())) {
            $form->get('startsAt')->addError(new FormError($translator->trans('session.must_start_in_future', domain: 'validators')));
        }
    }
}
