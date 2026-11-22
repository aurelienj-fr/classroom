<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $isOwner = true === $form->get('studioOwner')->getData();
            if ($isOwner) {
                $user->addRole(User::ROLE_OWNER);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', \sprintf('Bienvenue sur monStudio, %s !', $user->getFirstName()));

            return $this->redirectToRoute($isOwner ? 'app_manage_dashboard' : 'app_studio_index');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
