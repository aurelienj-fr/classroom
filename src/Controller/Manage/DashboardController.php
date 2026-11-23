<?php

declare(strict_types=1);

namespace App\Controller\Manage;

use App\Entity\User;
use App\Repository\StudioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(User::ROLE_OWNER)]
final class DashboardController extends AbstractController
{
    #[Route(['fr' => '/gestion', 'en' => '/manage', 'es' => '/gestion'], name: 'app_manage_dashboard', methods: ['GET'])]
    public function index(StudioRepository $studioRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('manage/dashboard.html.twig', [
            'studios' => $studioRepository->findManagedBy($user),
        ]);
    }
}
