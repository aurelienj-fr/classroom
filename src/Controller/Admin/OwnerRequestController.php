<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\OwnerRequestStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Platform administrators approve or reject requests to become a studio owner.
 */
#[Route(['fr' => '/admin/gerants', 'en' => '/admin/owners', 'es' => '/admin/gestores'])]
#[IsGranted(User::ROLE_ADMIN)]
final class OwnerRequestController extends AbstractController
{
    #[Route('', name: 'app_admin_owner_request_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/owner_request/index.html.twig', [
            'requests' => $userRepository->findOwnerRequests(),
        ]);
    }

    #[Route('/{id}/{decision}', name: 'app_admin_owner_request_review', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"review-owner-" ~ args["user"].getId()'))]
    public function review(User $user, string $decision, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        if (OwnerRequestStatus::Pending !== $user->getOwnerRequestStatus()) {
            $this->addFlash('warning', new TranslatableMessage('flash.owner_request.not_pending'));

            return $this->redirectToRoute('app_admin_owner_request_index');
        }

        $approved = 'approve' === $decision;
        $user->reviewOwnerRequest($approved, $clock->now());
        $entityManager->flush();

        $this->addFlash('success', new TranslatableMessage(
            $approved ? 'flash.owner_request.approved' : 'flash.owner_request.rejected',
            ['%name%' => $user->getFullName()],
        ));

        return $this->redirectToRoute('app_admin_owner_request_index');
    }
}
