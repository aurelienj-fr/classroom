<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route(['fr' => '/compte', 'en' => '/account', 'es' => '/cuenta'])]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route(['fr' => '/profil', 'en' => '/profile', 'es' => '/perfil'], name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        $profileForm = $this->createForm(ProfileType::class, $user);
        $passwordForm = $this->createForm(ChangePasswordType::class);

        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $entityManager->flush();
            // The login (e-mail) may have changed: refresh the security token instead of logging out.
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', new TranslatableMessage('flash.profile.updated'));

            return $this->redirectToRoute('app_account_profile');
        }

        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            /** @var string $newPassword */
            $newPassword = $passwordForm->get('newPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $entityManager->flush();
            // A new password invalidates the session: log the member in again with it.
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', new TranslatableMessage('flash.profile.password_changed'));

            return $this->redirectToRoute('app_account_profile');
        }

        if ($profileForm->isSubmitted() && !$profileForm->isValid()) {
            // Do not keep invalid values on the authenticated user for the rest of the request.
            $entityManager->refresh($user);
        }

        $status = ($profileForm->isSubmitted() && !$profileForm->isValid()) || ($passwordForm->isSubmitted() && !$passwordForm->isValid())
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;

        return $this->render('account/profile.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
        ], new Response(status: $status));
    }
}
