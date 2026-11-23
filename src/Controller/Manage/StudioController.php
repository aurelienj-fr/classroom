<?php

declare(strict_types=1);

namespace App\Controller\Manage;

use App\Entity\Studio;
use App\Entity\User;
use App\Form\StudioType;
use App\Repository\ClassSessionRepository;
use App\Security\Voter\StudioVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route(['fr' => '/gestion/studios', 'en' => '/manage/studios', 'es' => '/gestion/estudios'])]
#[IsGranted(User::ROLE_OWNER)]
final class StudioController extends AbstractController
{
    #[Route(['fr' => '/nouveau', 'en' => '/new', 'es' => '/nuevo'], name: 'app_manage_studio_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, #[CurrentUser] User $user): Response
    {
        $studio = new Studio($user);
        $form = $this->createForm(StudioType::class, $studio);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($studio);
            $entityManager->flush();
            $this->addFlash('success', new TranslatableMessage('flash.studio.created'));

            return $this->redirectToRoute('app_manage_studio_show', ['id' => $studio->getId()]);
        }

        return $this->render('manage/studio/form.html.twig', [
            'form' => $form,
            'studio' => null,
        ]);
    }

    #[Route('/{id}', name: 'app_manage_studio_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(StudioVoter::MANAGE, subject: 'studio')]
    public function show(Studio $studio, ClassSessionRepository $sessionRepository, ClockInterface $clock): Response
    {
        $sessions = $sessionRepository->findUpcomingForStudio($studio, $clock->now(), 50);

        return $this->render('manage/studio/show.html.twig', [
            'studio' => $studio,
            'sessions' => $sessions,
            'bookedCounts' => $sessionRepository->countConfirmedBookings($sessions),
        ]);
    }

    #[Route(['fr' => '/{id}/modifier', 'en' => '/{id}/edit', 'es' => '/{id}/editar'], name: 'app_manage_studio_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: 'studio')]
    public function edit(Studio $studio, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StudioType::class, $studio);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', new TranslatableMessage('flash.studio.updated'));

            return $this->redirectToRoute('app_manage_studio_show', ['id' => $studio->getId()]);
        }

        return $this->render('manage/studio/form.html.twig', [
            'form' => $form,
            'studio' => $studio,
        ]);
    }
}
