<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Prevents open redirects through the "_target_path" login parameter:
 * a redirection to another host after login is replaced by the home page.
 */
#[AsEventListener]
final readonly class SafeLoginRedirectListener
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response instanceof RedirectResponse) {
            return;
        }

        $target = $response->getTargetUrl();
        $host = parse_url($target, \PHP_URL_HOST);
        $isRelative = str_starts_with($target, '/') && !str_starts_with($target, '//') && !str_contains($target, '\\');

        if ($isRelative || $host === $event->getRequest()->getHost()) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_home')));
    }
}
