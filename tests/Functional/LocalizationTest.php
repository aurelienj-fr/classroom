<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

final class LocalizationTest extends WebTestCaseHelper
{
    /**
     * Request::create() always sends "Accept-Language: en-us,en;q=0.5" by default, so a
     * request without the header cannot be simulated here.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function acceptLanguages(): iterable
    {
        yield 'spanish browser' => ['es-ES,es;q=0.9,en;q=0.8', '/es'];
        yield 'english browser' => ['en-GB,en;q=0.9', '/en'];
        yield 'unsupported language falls back to french' => ['de-DE,de;q=0.9', '/fr'];
    }

    #[DataProvider('acceptLanguages')]
    public function testRootRedirectsToThePreferredLanguage(string $acceptLanguage, string $expected): void
    {
        $this->client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]);

        self::assertResponseRedirects($expected);
        self::assertResponseHeaderSame('Vary', 'Accept-Language');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function homePages(): iterable
    {
        yield 'french' => ['/fr', 'fr', 'Votre prochain cours'];
        yield 'english' => ['/en', 'en', 'Your next class'];
        yield 'spanish' => ['/es', 'es', 'Tu próxima clase'];
    }

    #[DataProvider('homePages')]
    public function testHomePageIsTranslated(string $url, string $locale, string $title): void
    {
        $crawler = $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSame($locale, $crawler->filter('html')->attr('lang'));
        self::assertSelectorTextContains('h1', $title);
        self::assertCount(4, $crawler->filter('link[rel="alternate"][hreflang]'), 'fr, en, es and x-default alternates');
    }

    public function testPathsAndDisciplineNamesAreLocalized(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $studio = $this->data->studio($owner, 'Atelier Soleil', 'Nantes');
        $discipline = $studio->getDisciplines()->first();
        self::assertNotFalse($discipline);
        $discipline->setNameTranslation('es', 'Yoga ES');
        $this->entityManager()->flush();
        $this->data->session($studio, new \DateTimeImmutable('2026-11-24 12:30'), name: 'Pilates');

        $crawler = $this->client->request('GET', '/es/estudios/'.$studio->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ms-day-title', 'martes 24 de noviembre');
        self::assertAnySelectorTextContains('aside .badge', 'Yoga ES');

        // The language switcher keeps the current page.
        $english = $crawler->filter('a.dropdown-item[hreflang="en"]')->attr('href');
        self::assertSame('/en/studios/'.$studio->getSlug(), $english);
    }

    public function testBookingFlashMessageIsTranslated(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));
        $this->client->loginUser($this->data->user('member@example.test'));

        $this->client->request('GET', '/en/classes/'.$session->getId());
        $this->client->submitForm('Book my spot');

        self::assertResponseRedirects('/en/classes/'.$session->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your spot is booked');
    }

    public function testBookingErrorIsTranslated(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));
        $this->client->loginUser($this->data->user('member@example.test'));

        $crawler = $this->client->request('GET', '/es/clases/'.$session->getId());
        $form = $crawler->selectButton('Reservar mi plaza')->form();
        $this->client->submit($form);
        // Second submission of the same form: the member already holds a seat.
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Ya has reservado esta clase.');
    }

    public function testValidationMessagesAreTranslated(): void
    {
        $this->client->request('GET', '/es/registro');
        $this->client->submitForm('Crear mi cuenta', [
            'registration_form[firstName]' => 'Zoé',
            'registration_form[lastName]' => 'Lambert',
            'registration_form[email]' => 'zoe@example.test',
            'registration_form[plainPassword][first]' => 'corta',
            'registration_form[plainPassword][second]' => 'corta',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'al menos 12 caracteres');
    }

    public function testLocalizedBackOfficeIsProtected(): void
    {
        $this->client->loginUser($this->data->user('member@example.test'));

        $this->client->request('GET', '/en/manage');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/es/gestion');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousVisitorIsSentToTheLocalizedLoginPage(): void
    {
        $this->client->request('GET', '/es/cuenta/reservas');

        self::assertResponseRedirects('/es/acceso');
    }
}
