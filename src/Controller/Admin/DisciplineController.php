<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Discipline;
use App\Entity\User;
use App\Form\DisciplineType;
use App\Repository\DisciplineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/disciplines')]
#[IsGranted(User::ROLE_ADMIN)]
final class DisciplineController extends AbstractController
{
    #[Route('', name: 'app_admin_discipline_index', methods: ['GET'])]
    public function index(DisciplineRepository $disciplineRepository): Response
    {
        return $this->render('admin/discipline/index.html.twig', [
            'disciplines' => $disciplineRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nouvelle', name: 'app_admin_discipline_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleForm(new Discipline(), $request, $entityManager, 'Discipline créée.');
    }

    #[Route('/{id}/modifier', name: 'app_admin_discipline_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Discipline $discipline, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->handleForm($discipline, $request, $entityManager, 'Discipline mise à jour.');
    }

    private function handleForm(Discipline $discipline, Request $request, EntityManagerInterface $entityManager, string $successMessage): Response
    {
        $form = $this->createForm(DisciplineType::class, $discipline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($discipline);
            $entityManager->flush();
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_discipline_index');
        }

        return $this->render('admin/discipline/form.html.twig', [
            'form' => $form,
            'discipline' => $discipline,
        ]);
    }
}
