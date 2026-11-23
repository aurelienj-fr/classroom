<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
    ) {
    }

    /**
     * Sends visitors of "/" to the home page in their preferred language (French by default).
     */
    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function root(Request $request): RedirectResponse
    {
        $locale = $request->getPreferredLanguage($this->enabledLocales) ?? $this->enabledLocales[0];

        $response = $this->redirectToRoute('app_home', ['_locale' => $locale]);
        $response->setVary('Accept-Language');

        return $response;
    }
}
