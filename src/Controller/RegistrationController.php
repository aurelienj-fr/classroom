<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route(['fr' => '/inscription', 'en' => '/sign-up', 'es' => '/registro'], name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
        #[Target('registration')]
        RateLimiterFactoryInterface $registrationLimiter,
        TranslatorInterface $translator,
        ClockInterface $clock,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            $form->addError(new FormError($translator->trans('registration.too_many_attempts')));

            return $this->render('registration/register.html.twig', ['form' => $form], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            // Owners are validated by a platform administrator: the account starts as a member.
            $ownerRequested = true === $form->get('studioOwner')->getData()
                && $user->requestOwnerRole($clock->now());

            $entityManager->persist($user);
            $entityManager->flush();

            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', new TranslatableMessage('flash.registration.welcome', ['%name%' => $user->getFirstName()]));
            if ($ownerRequested) {
                $this->addFlash('info', new TranslatableMessage('flash.owner_request.sent'));
            }

            return $this->redirectToRoute($ownerRequested ? 'app_account_bookings' : 'app_studio_index');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
