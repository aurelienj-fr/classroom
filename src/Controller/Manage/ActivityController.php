<?php

declare(strict_types=1);

namespace App\Controller\Manage;

use App\Entity\Activity;
use App\Entity\Studio;
use App\Entity\User;
use App\Form\ActivityType;
use App\Security\Voter\StudioVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route(['fr' => '/gestion', 'en' => '/manage', 'es' => '/gestion'])]
#[IsGranted(User::ROLE_OWNER)]
final class ActivityController extends AbstractController
{
    #[Route(['fr' => '/studios/{id}/cours/nouveau', 'en' => '/studios/{id}/activities/new', 'es' => '/estudios/{id}/actividades/nueva'], name: 'app_manage_activity_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: 'studio')]
    public function new(Studio $studio, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($studio->getDisciplines()->isEmpty()) {
            $this->addFlash('warning', new TranslatableMessage('flash.activity.discipline_required'));

            return $this->redirectToRoute('app_manage_studio_edit', ['id' => $studio->getId()]);
        }

        $activity = new Activity($studio);
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($activity);
            $entityManager->flush();
            $this->addFlash('success', new TranslatableMessage('flash.activity.created'));

            return $this->redirectToRoute('app_manage_session_new', ['id' => $activity->getId()]);
        }

        return $this->render('manage/activity/form.html.twig', [
            'form' => $form,
            'studio' => $studio,
            'activity' => null,
        ]);
    }

    #[Route(['fr' => '/cours/{id}/modifier', 'en' => '/activities/{id}/edit', 'es' => '/actividades/{id}/editar'], name: 'app_manage_activity_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(StudioVoter::MANAGE, subject: new Expression('args["activity"].getStudio()'))]
    public function edit(Activity $activity, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', new TranslatableMessage('flash.activity.updated'));

            return $this->redirectToRoute('app_manage_studio_show', ['id' => $activity->getStudio()->getId()]);
        }

        return $this->render('manage/activity/form.html.twig', [
            'form' => $form,
            'studio' => $activity->getStudio(),
            'activity' => $activity,
        ]);
    }
}
