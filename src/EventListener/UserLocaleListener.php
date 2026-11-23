<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Remembers the language a member uses on the site, so that e-mails are sent in that language.
 */
#[AsEventListener]
final readonly class UserLocaleListener
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $locale = $event->getRequest()->getLocale();

        if ($user instanceof User && $user->getLocale() !== $locale) {
            $user->setLocale($locale);
            $this->entityManager->flush();
        }
    }
}
