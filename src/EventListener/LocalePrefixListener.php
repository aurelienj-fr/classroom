<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the request locale from the URL prefix (/fr, /en, /es) before routing.
 *
 * Matched routes set "_locale" themselves; this matters when no route matches,
 * so that the 404 page of "/en/unknown" is rendered in English.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 40)]
final readonly class LocalePrefixListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $enabledLocales,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $prefix = explode('/', ltrim($request->getPathInfo(), '/'), 2)[0];

        if (\in_array($prefix, $this->enabledLocales, true)) {
            $request->setLocale($prefix);
        }
    }
}
